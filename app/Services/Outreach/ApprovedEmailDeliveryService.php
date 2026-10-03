<?php

namespace App\Services\Outreach;

use App\Enums\CampaignStatus;
use App\Enums\LeadStatus;
use App\Models\OutreachDraft;
use App\Models\OutreachMessage;
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

        $dailyLimit = max(0, (int) $campaign->daily_outreach_limit);
        $sentToday = OutreachMessage::query()
            ->where('campaign_id', $campaign->id)
            ->where('status', OutreachMessage::STATUS_SENT)
            ->whereBetween('sent_at', [now()->startOfDay(), now()->endOfDay()])
            ->count();

        if ($sentToday >= $dailyLimit) {
            return [
                ...$this->result(
                    'daily_limit_reached',
                    'Campaign daily outreach limit has been reached.',
                    409,
                    $outreachDraft,
                ),
                'daily_limit' => $dailyLimit,
                'sent_today' => $sentToday,
            ];
        }

        $mailer = (string) config('mail.default', 'log');

        if (! in_array($mailer, self::DELIVERY_MAILERS, true)) {
            return [
                ...$this->result(
                    'mailer_not_delivery_capable',
                    'A delivery-capable mailer must be configured before sending.',
                    409,
                    $outreachDraft,
                ),
                'mailer' => $mailer,
            ];
        }

        $message = null;
        $transactionResult = null;

        DB::transaction(function () use (
            $outreachDraft,
            $lead,
            $campaign,
            &$message,
            &$transactionResult,
        ): void {
            $lockedDraft = OutreachDraft::query()
                ->whereKey($outreachDraft->id)
                ->lockForUpdate()
                ->firstOrFail();

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
                );

                return;
            }

            $lockedDraft->update([
                'status' => OutreachDraft::STATUS_SENDING,
            ]);

            $message = OutreachMessage::query()->updateOrCreate(
                ['outreach_draft_id' => $lockedDraft->id],
                [
                    'lead_id' => $lead->id,
                    'campaign_id' => $campaign->id,
                    'channel' => 'email',
                    'recipient' => Suppression::normalizeEmail($lockedDraft->recipient),
                    'subject' => $lockedDraft->subject,
                    'body' => $lockedDraft->body,
                    'status' => OutreachMessage::STATUS_SENDING,
                    'error_message' => null,
                    'attempted_at' => now(),
                    'sent_at' => null,
                ],
            );
        });

        if ($transactionResult !== null) {
            return $transactionResult;
        }

        try {
            Mail::raw($outreachDraft->body, function ($mail) use ($outreachDraft): void {
                $mail->to($outreachDraft->recipient)
                    ->subject((string) $outreachDraft->subject);
            });
        } catch (Throwable $error) {
            $message?->update([
                'status' => OutreachMessage::STATUS_FAILED,
                'error_message' => mb_substr($error->getMessage(), 0, 2000),
            ]);

            OutreachDraft::query()
                ->whereKey($outreachDraft->id)
                ->update([
                    'status' => OutreachDraft::STATUS_APPROVED,
                ]);

            $outreachDraft->setAttribute(
                'status',
                OutreachDraft::STATUS_APPROVED,
            );

            return $this->result(
                'failed',
                'Delivery failed. The draft remains approved for a controlled retry.',
                200,
                $outreachDraft,
                $message,
            );
        }

        DB::transaction(function () use ($message, $outreachDraft, $lead): void {
            $message?->update([
                'status' => OutreachMessage::STATUS_SENT,
                'error_message' => null,
                'sent_at' => now(),
            ]);

            $outreachDraft->update([
                'status' => OutreachDraft::STATUS_SENT,
            ]);

            $lead->update([
                'status' => LeadStatus::Contacted,
                'last_contacted_at' => now(),
            ]);
        });

        return $this->result(
            'sent',
            'Approved email sent successfully.',
            200,
            $outreachDraft,
            $message,
            LeadStatus::Contacted->value,
        );
    }

    private function result(
        string $status,
        string $message,
        int $httpStatus,
        OutreachDraft $draft,
        ?OutreachMessage $outreachMessage = null,
        ?string $leadStatus = null,
    ): array {
        return [
            'status' => $status,
            'message' => $message,
            'http_status' => $httpStatus,
            'draft_id' => $draft->id,
            'message_id' => $outreachMessage?->id,
            'lead_uuid' => $draft->lead?->uuid,
            'lead_status' => $leadStatus,
        ];
    }
}
