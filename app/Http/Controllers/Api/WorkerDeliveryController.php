<?php

namespace App\Http\Controllers\Api;

use App\Enums\CampaignStatus;
use App\Enums\LeadStatus;
use App\Http\Controllers\Controller;
use App\Models\OutreachDraft;
use App\Models\OutreachMessage;
use App\Models\Suppression;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

class WorkerDeliveryController extends Controller
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

    public function queue(Request $request): JsonResponse
    {
        $limit = max(1, min((int) $request->integer('limit', 5), 25));
        $mailer = (string) config('mail.default', 'log');

        $drafts = OutreachDraft::query()
            ->where('status', OutreachDraft::STATUS_APPROVED)
            ->where('channel', 'email')
            ->whereDoesntHave(
                'message',
                fn ($query) => $query->where('status', OutreachMessage::STATUS_SENT),
            )
            ->whereHas(
                'lead.campaign',
                fn ($query) => $query->where('status', CampaignStatus::Active->value),
            )
            ->with([
                'lead.campaign:id,uuid,name,daily_outreach_limit,status',
                'message',
            ])
            ->oldest('reviewed_at')
            ->limit($limit)
            ->get();

        $items = $drafts->map(function (OutreachDraft $draft): array {
            $campaign = $draft->lead->campaign;
            $dailyLimit = max(0, (int) $campaign->daily_outreach_limit);
            $sentToday = OutreachMessage::query()
                ->where('campaign_id', $campaign->id)
                ->where('status', OutreachMessage::STATUS_SENT)
                ->whereBetween('sent_at', [now()->startOfDay(), now()->endOfDay()])
                ->count();

            $suppressed = Suppression::isEmailSuppressed($draft->recipient);
            $remaining = max(0, $dailyLimit - $sentToday);

            $blockedReason = null;

            if ($suppressed) {
                $blockedReason = 'suppressed_recipient';
            } elseif ($remaining < 1) {
                $blockedReason = 'daily_limit_reached';
            }

            return [
                'draft_id' => $draft->id,
                'lead_uuid' => $draft->lead->uuid,
                'business_name' => $draft->lead->business_name,
                'recipient' => $draft->recipient,
                'subject' => $draft->subject,
                'status' => $draft->status,
                'eligible' => $blockedReason === null,
                'blocked_reason' => $blockedReason,
                'campaign' => [
                    'uuid' => $campaign->uuid,
                    'name' => $campaign->name,
                    'daily_outreach_limit' => $dailyLimit,
                    'sent_today' => $sentToday,
                    'remaining_today' => $remaining,
                ],
            ];
        })->values();

        return response()->json([
            'count' => $items->count(),
            'mailer' => $mailer,
            'delivery_capable' => in_array($mailer, self::DELIVERY_MAILERS, true),
            'drafts' => $items,
        ]);
    }

    public function send(OutreachDraft $outreachDraft): JsonResponse
    {
        $outreachDraft->load(['lead.campaign', 'message']);

        if (
            $outreachDraft->message
            && $outreachDraft->message->status === OutreachMessage::STATUS_SENT
        ) {
            return response()->json([
                'status' => 'already_sent',
                'draft_id' => $outreachDraft->id,
                'message_id' => $outreachDraft->message->id,
            ]);
        }

        if ($outreachDraft->channel !== 'email') {
            return response()->json([
                'message' => 'Only approved email drafts can use automated delivery.',
                'code' => 'unsupported_channel',
            ], 409);
        }

        if ($outreachDraft->status === OutreachDraft::STATUS_SENT) {
            return response()->json([
                'status' => 'already_sent',
                'draft_id' => $outreachDraft->id,
            ]);
        }

        if ($outreachDraft->status === OutreachDraft::STATUS_SENDING) {
            return response()->json([
                'message' => 'This draft is already marked as sending and requires reconciliation before retry.',
                'code' => 'delivery_in_progress',
            ], 409);
        }

        if ($outreachDraft->status !== OutreachDraft::STATUS_APPROVED) {
            return response()->json([
                'message' => 'Only approved drafts can be delivered.',
                'code' => 'draft_not_approved',
            ], 409);
        }

        $lead = $outreachDraft->lead;
        $campaign = $lead->campaign;

        if ($campaign->status !== CampaignStatus::Active) {
            return response()->json([
                'message' => 'The draft campaign is not active.',
                'code' => 'campaign_not_active',
            ], 409);
        }

        if ($lead->status !== LeadStatus::Approved) {
            return response()->json([
                'message' => 'The lead must be approved before delivery.',
                'code' => 'lead_not_approved',
            ], 409);
        }

        if (Suppression::isEmailSuppressed($outreachDraft->recipient)) {
            return response()->json([
                'message' => 'Recipient is on the suppression list.',
                'code' => 'recipient_suppressed',
            ], 409);
        }

        $dailyLimit = max(0, (int) $campaign->daily_outreach_limit);
        $sentToday = OutreachMessage::query()
            ->where('campaign_id', $campaign->id)
            ->where('status', OutreachMessage::STATUS_SENT)
            ->whereBetween('sent_at', [now()->startOfDay(), now()->endOfDay()])
            ->count();

        if ($sentToday >= $dailyLimit) {
            return response()->json([
                'message' => 'Campaign daily outreach limit has been reached.',
                'code' => 'daily_limit_reached',
                'daily_limit' => $dailyLimit,
                'sent_today' => $sentToday,
            ], 409);
        }

        $mailer = (string) config('mail.default', 'log');

        if (! in_array($mailer, self::DELIVERY_MAILERS, true)) {
            return response()->json([
                'message' => 'A delivery-capable mailer must be configured before sending.',
                'code' => 'mailer_not_delivery_capable',
                'mailer' => $mailer,
            ], 409);
        }

        $message = DB::transaction(function () use ($outreachDraft, $lead, $campaign): OutreachMessage {
            $lockedDraft = OutreachDraft::query()
                ->whereKey($outreachDraft->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedDraft->status !== OutreachDraft::STATUS_APPROVED) {
                abort(409, 'Draft state changed before delivery could start.');
            }

            $existingMessage = OutreachMessage::query()
                ->where('outreach_draft_id', $lockedDraft->id)
                ->lockForUpdate()
                ->first();

            if ($existingMessage?->status === OutreachMessage::STATUS_SENT) {
                abort(409, 'This draft already has a sent delivery record.');
            }

            $lockedDraft->update([
                'status' => OutreachDraft::STATUS_SENDING,
            ]);

            return OutreachMessage::query()->updateOrCreate(
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

        try {
            Mail::raw($outreachDraft->body, function ($mail) use ($outreachDraft): void {
                $mail->to($outreachDraft->recipient)
                    ->subject((string) $outreachDraft->subject);
            });
        } catch (Throwable $error) {
            $message->update([
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

            return response()->json([
                'status' => 'failed',
                'draft_id' => $outreachDraft->id,
                'message' => 'Delivery failed. The draft remains approved for a controlled retry.',
            ]);
        }

        DB::transaction(function () use ($message, $outreachDraft, $lead): void {
            $message->update([
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

        return response()->json([
            'status' => 'sent',
            'draft_id' => $outreachDraft->id,
            'message_id' => $message->id,
            'lead_uuid' => $lead->uuid,
            'lead_status' => LeadStatus::Contacted->value,
        ]);
    }
}
