<?php

namespace App\Http\Controllers\Api;

use App\Enums\CampaignStatus;
use App\Enums\LeadStatus;
use App\Http\Controllers\Controller;
use App\Models\OutreachDraft;
use App\Models\OutreachMessage;
use App\Models\SenderAccount;
use App\Models\Suppression;
use App\Services\Outreach\ApprovedEmailDeliveryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
        $legacyMailer = (string) config('mail.default', 'log');
        $senderPoolConfigured = SenderAccount::query()->exists();
        $mailer = $senderPoolConfigured ? 'sender_pool' : $legacyMailer;

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
                'lead.campaign:id,user_id,uuid,name,daily_outreach_limit,status',
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
            } elseif (
                SenderAccount::query()
                    ->where('user_id', $campaign->user_id)
                    ->exists()
                && ! $this->hasAvailableSender($campaign->user_id)
            ) {
                $blockedReason = 'no_sender_available';
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
            'delivery_capable' => $senderPoolConfigured
                || in_array($legacyMailer, self::DELIVERY_MAILERS, true),
            'drafts' => $items,
        ]);
    }

    private function hasAvailableSender(int $userId): bool
    {
        $today = [now()->startOfDay(), now()->endOfDay()];

        $accounts = SenderAccount::query()
            ->where('user_id', $userId)
            ->where('enabled', true)
            ->orderBy('id')
            ->get();

        foreach ($accounts as $account) {
            if (! $account->isConfigured()) {
                continue;
            }

            $sentToday = OutreachMessage::query()
                ->where('sender_account_id', $account->id)
                ->where('status', OutreachMessage::STATUS_SENT)
                ->whereBetween('sent_at', $today)
                ->count();

            if ($sentToday < max(1, (int) $account->daily_limit)) {
                return true;
            }
        }

        return false;
    }

    public function send(
        OutreachDraft $outreachDraft,
        ApprovedEmailDeliveryService $deliveryService,
    ): JsonResponse {
        $result = $deliveryService->send($outreachDraft);
        $httpStatus = (int) ($result['http_status'] ?? 200);
        unset($result['http_status']);

        return response()->json($result, $httpStatus);
    }
}
