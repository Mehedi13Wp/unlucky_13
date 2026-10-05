<?php

namespace App\Services\Outreach;

use App\Enums\CampaignStatus;
use App\Enums\LeadStatus;
use App\Models\OutreachDraft;
use App\Models\OutreachMessage;
use App\Models\SenderAccount;
use App\Models\Suppression;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

class ApprovedEmailDeliveryService
{
    private const DELIVERY_MAILERS = [
        'smtp',
        'sendmail',
        'mailgun',
        'ses',
        'ses-v2',
        'postmark',
        'resend',
        'failover',
        'roundrobin',
    ];

    public function send(OutreachDraft $outreachDraft): array
    {
        $outreachDraft->load(['lead.campaign', 'message']);

        if (
            $outreachDraft->message
            && $outreachDraft->message->status === OutreachMessage::STATUS_SENT
        ) {
            return $this->result(
                'already_sent',
                'This approved email has already been sent.',
                200,
                $outreachDraft,
                $outreachDraft->message,
            );
        }

        if ($outreachDraft->channel !== 'email') {
            return $this->result(
                'unsupported_channel',
                'Only approved email drafts can use automated delivery.',
                409,
                $outreachDraft,
            );
        }

        if ($outreachDraft->status === OutreachDraft::STATUS_SENT) {
            return $this->result(
                'already_sent',
                'This approved email has already been sent.',
                200,
                $outreachDraft,
            );
        }

        if ($outreachDraft->status === OutreachDraft::STATUS_SENDING) {
            return $this->result(
                'delivery_in_progress',
                'This draft is already marked as sending and requires reconciliation before retry.',
                409,
                $outreachDraft,
            );
        }

        if ($outreachDraft->status !== OutreachDraft::STATUS_APPROVED) {
            return $this->result(
                'draft_not_approved',
                'Only approved drafts can be delivered.',
                409,
                $outreachDraft,
            );
        }

        $lead = $outreachDraft->lead;
        $campaign = $lead->campaign;

        if ($campaign->status !== CampaignStatus::Active) {
            return $this->result(
                'campaign_not_active',
                'The draft campaign is not active.',
                409,
                $outreachDraft,
            );
        }

        if ($lead->status !== LeadStatus::Approved) {
            return $this->result(
                'lead_not_approved',
                'The lead must be approved before delivery.',
                409,
                $outreachDraft,
            );
        }

        if (Suppression::isEmailSuppressed($outreachDraft->recipient)) {
            return $this->result(
                'recipient_suppressed',
                'Recipient is on the suppression list.',
                409,
                $outreachDraft,
            );
        }

        if (
            $outreachDraft->message?->status === OutreachMessage::STATUS_FAILED
            && $outreachDraft->message->retry_at !== null
            && now()->lessThan($outreachDraft->message->retry_at)
        ) {
            return [
                ...$this->result(
                    'retry_wait',
                    'Delivery retry is waiting for its controlled retry window.',
                    200,
                    $outreachDraft,
                    $outreachDraft->message,
                ),
                'attempt_count' => (int) $outreachDraft->message->attempt_count,
                'retry_at' => $outreachDraft->message->retry_at->toISOString(),
            ];
        }

        $message = null;
        $senderAccount = null;
        $mailer = null;
        $transactionResult = null;

        DB::transaction(function () use (
            $outreachDraft,
            &$message,
            &$senderAccount,
            &$mailer,
            &$transactionResult,
        ): void {
            $lockedDraft = OutreachDraft::query()
                ->whereKey($outreachDraft->id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedLead = $lockedDraft->lead()
                ->lockForUpdate()
                ->firstOrFail();

            $lockedCampaign = $lockedLead->campaign()
                ->lockForUpdate()
                ->firstOrFail();

            $lockedDraft->setRelation('lead', $lockedLead);
            $lockedLead->setRelation('campaign', $lockedCampaign);

            $existingMessage = OutreachMessage::query()
                ->where('outreach_draft_id', $lockedDraft->id)
                ->lockForUpdate()
                ->first();

            if ($existingMessage?->status === OutreachMessage::STATUS_SENT) {
                $transactionResult = $this->result(
                    'already_sent',
                    'This approved email has already been sent.',
                    200,
                    $lockedDraft,
                    $existingMessage,
                );

                return;
            }

            if ($lockedDraft->status !== OutreachDraft::STATUS_APPROVED) {
                $transactionResult = $this->result(
                    'draft_state_changed',
                    'Draft state changed before delivery could start.',
                    409,
                    $lockedDraft,
                    $existingMessage,
                );

                return;
            }

            if ($lockedDraft->channel !== 'email') {
                $transactionResult = $this->result(
                    'unsupported_channel',
                    'Only approved email drafts can use automated delivery.',
                    409,
                    $lockedDraft,
                    $existingMessage,
                );

                return;
            }

            if ($lockedCampaign->status !== CampaignStatus::Active) {
                $transactionResult = $this->result(
                    'campaign_not_active',
                    'The draft campaign is not active.',
                    409,
                    $lockedDraft,
                    $existingMessage,
                );

                return;
            }

            if ($lockedLead->status !== LeadStatus::Approved) {
                $transactionResult = $this->result(
                    'lead_not_approved',
                    'The lead must be approved before delivery.',
                    409,
                    $lockedDraft,
                    $existingMessage,
                );

                return;
            }

            if (Suppression::isEmailSuppressed($lockedDraft->recipient)) {
                $transactionResult = $this->result(
                    'recipient_suppressed',
                    'Recipient is on the suppression list.',
                    409,
                    $lockedDraft,
                    $existingMessage,
                );

                return;
            }

            if (
                $existingMessage?->status === OutreachMessage::STATUS_FAILED
                && $existingMessage->retry_at !== null
                && now()->lessThan($existingMessage->retry_at)
            ) {
                $transactionResult = [
                    ...$this->result(
                        'retry_wait',
                        'Delivery retry is waiting for its controlled retry window.',
                        200,
                        $lockedDraft,
                        $existingMessage,
                    ),
                    'attempt_count' => (int) $existingMessage->attempt_count,
                    'retry_at' => $existingMessage->retry_at->toISOString(),
                ];

                return;
            }

            $today = [now()->startOfDay(), now()->endOfDay()];
            $dailyLimit = max(0, (int) $lockedCampaign->daily_outreach_limit);
            $reservedToday = $this->reservedMessageCount(
                'campaign_id',
                $lockedCampaign->id,
                $today,
            );

            if ($reservedToday >= $dailyLimit) {
                $transactionResult = [
                    ...$this->result(
                        'daily_limit_reached',
                        'Campaign daily outreach limit has been reached.',
                        409,
                        $lockedDraft,
                        $existingMessage,
                    ),
                    'daily_limit' => $dailyLimit,
                    'sent_today' => OutreachMessage::query()
                        ->where('campaign_id', $lockedCampaign->id)
                        ->where('status', OutreachMessage::STATUS_SENT)
                        ->whereBetween('sent_at', $today)
                        ->count(),
                ];

                return;
            }

            $senderPoolExists = SenderAccount::query()
                ->where('user_id', $lockedCampaign->user_id)
                ->exists();

            if ($senderPoolExists) {
                $accounts = SenderAccount::query()
                    ->where('user_id', $lockedCampaign->user_id)
                    ->where('enabled', true)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                foreach ($accounts as $account) {
                    if (! $account->isDeliveryReady()) {
                        continue;
                    }

                    $reservedBySender = $this->reservedMessageCount(
                        'sender_account_id',
                        $account->id,
                        $today,
                    );

                    if ($reservedBySender < max(1, (int) $account->daily_limit)) {
                        $senderAccount = $account;
                        break;
                    }
                }

                if ($senderAccount === null) {
                    $transactionResult = $this->result(
                        'no_sender_available',
                        'You have no delivery-ready sender emails today. Verify/enable a sender, fix its SMTP setup, or wait for a daily limit to reset.',
                        409,
                        $lockedDraft,
                        $existingMessage,
                    );

                    return;
                }
            }

            $mailer = $senderAccount ? 'sender_pool' : (string) config('mail.default', 'log');

            if ($senderAccount === null && ! in_array($mailer, self::DELIVERY_MAILERS, true)) {
                $transactionResult = [
                    ...$this->result(
                        'mailer_not_delivery_capable',
                        'A delivery-capable mailer must be configured before sending.',
                        409,
                        $lockedDraft,
                        $existingMessage,
                    ),
                    'mailer' => $mailer,
                ];

                return;
            }

            $attemptCount = max(0, (int) ($existingMessage?->attempt_count ?? 0)) + 1;

            $lockedDraft->update([
                'status' => OutreachDraft::STATUS_SENDING,
            ]);

            $message = OutreachMessage::query()->updateOrCreate(
                ['outreach_draft_id' => $lockedDraft->id],
                [
                    'lead_id' => $lockedLead->id,
                    'campaign_id' => $lockedCampaign->id,
                    'sender_account_id' => $senderAccount?->id,
                    'sender_address' => $senderAccount?->email
                        ?: (string) config('mail.from.address'),
                    'channel' => 'email',
                    'recipient' => Suppression::normalizeEmail($lockedDraft->recipient),
                    'subject' => $lockedDraft->subject,
                    'body' => $lockedDraft->body,
                    'status' => OutreachMessage::STATUS_SENDING,
                    'error_message' => null,
                    'attempt_count' => $attemptCount,
                    'attempted_at' => now(),
                    'retry_at' => null,
                    'sent_at' => null,
                ],
            );
        });

        if ($transactionResult !== null) {
            return $transactionResult;
        }

        if ($senderAccount !== null) {
            app(SenderAccountMailer::class)->configure($senderAccount);
        }

        try {
            if ($senderAccount !== null) {
                Mail::mailer(SenderAccountMailer::NAME)->raw(
                    $outreachDraft->body,
                    function ($mailMessage) use ($outreachDraft, $senderAccount): void {
                        $mailMessage->from(
                            $senderAccount->email,
                            $senderAccount->from_name ?: null,
                        );

                        $mailMessage->to($outreachDraft->recipient)
                            ->subject((string) $outreachDraft->subject);
                    },
                );
            } else {
                Mail::raw(
                    $outreachDraft->body,
                    function ($mailMessage) use ($outreachDraft): void {
                        $mailMessage->to($outreachDraft->recipient)
                            ->subject((string) $outreachDraft->subject);
                    },
                );
            }
        } catch (Throwable $error) {
            $attemptCount = max(1, (int) ($message?->attempt_count ?? 1));
            $retryMinutes = $this->retryDelayMinutes($attemptCount);
            $retryAt = now()->addMinutes($retryMinutes);

            DB::transaction(function () use (
                $message,
                $outreachDraft,
                $error,
                $retryAt,
            ): void {
                $message?->update([
                    'status' => OutreachMessage::STATUS_FAILED,
                    'error_message' => mb_substr($error->getMessage(), 0, 2000),
                    'retry_at' => $retryAt,
                ]);

                OutreachDraft::query()
                    ->whereKey($outreachDraft->id)
                    ->update([
                        'status' => OutreachDraft::STATUS_APPROVED,
                    ]);
            });

            $outreachDraft->setAttribute(
                'status',
                OutreachDraft::STATUS_APPROVED,
            );

            return [
                ...$this->result(
                    'failed',
                    'Delivery failed. The draft remains approved and has been scheduled for a controlled retry.',
                    200,
                    $outreachDraft,
                    $message,
                ),
                'attempt_count' => $attemptCount,
                'retry_at' => $retryAt->toISOString(),
                'retry_in_minutes' => $retryMinutes,
            ];
        }

        DB::transaction(function () use ($message, $outreachDraft, $lead, $senderAccount): void {
            $message?->update([
                'status' => OutreachMessage::STATUS_SENT,
                'error_message' => null,
                'retry_at' => null,
                'sent_at' => now(),
            ]);

            $outreachDraft->update([
                'status' => OutreachDraft::STATUS_SENT,
            ]);

            $lead->update([
                'status' => LeadStatus::Contacted,
                'last_contacted_at' => now(),
            ]);

            $senderAccount?->update([
                'last_used_at' => now(),
            ]);
        });

        return [
            ...$this->result(
                'sent',
                'Approved email sent successfully.',
                200,
                $outreachDraft,
                $message,
                LeadStatus::Contacted->value,
            ),
            'attempt_count' => (int) ($message?->attempt_count ?? 1),
        ];
    }

    private function reservedMessageCount(
        string $column,
        int $id,
        array $today,
    ): int {
        return OutreachMessage::query()
            ->where($column, $id)
            ->where(function ($query) use ($today): void {
                $query
                    ->where(function ($sent) use ($today): void {
                        $sent
                            ->where('status', OutreachMessage::STATUS_SENT)
                            ->whereBetween('sent_at', $today);
                    })
                    ->orWhere(function ($sending) use ($today): void {
                        $sending
                            ->where('status', OutreachMessage::STATUS_SENDING)
                            ->whereBetween('attempted_at', $today);
                    });
            })
            ->count();
    }

    private function retryDelayMinutes(int $attemptCount): int
    {
        return match (true) {
            $attemptCount <= 1 => 5,
            $attemptCount === 2 => 15,
            default => 30,
        };
    }

    private function result(
        string $status,
        string $message,
        int $httpStatus,
        OutreachDraft $draft,
        ?OutreachMessage $outreachMessage = null,
        ?string $leadStatus = null,
    ): array {
        $result = [
            'status' => $status,
            'message' => $message,
            'http_status' => $httpStatus,
            'draft_id' => $draft->id,
            'message_id' => $outreachMessage?->id,
            'lead_uuid' => $draft->lead?->uuid,
            'lead_status' => $leadStatus,
        ];

        if ($httpStatus >= 400) {
            $result['code'] = $status;
        }

        return $result;
    }
}
