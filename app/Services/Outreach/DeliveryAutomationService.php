<?php

namespace App\Services\Outreach;

use App\Enums\CampaignStatus;
use App\Models\DeliveryAutomationSetting;
use App\Models\OutreachDraft;
use App\Models\OutreachMessage;
use App\Models\SenderAccount;
use Illuminate\Support\Facades\DB;

class DeliveryAutomationService
{
    public function __construct(
        private readonly ApprovedEmailDeliveryService $deliveryService,
    ) {
    }

    public function run(): array
    {
        $settingIds = DeliveryAutomationSetting::query()
            ->where('enabled', true)
            ->orderBy('id')
            ->pluck('id');

        if ($settingIds->isEmpty()) {
            return [
                'status' => 'disabled',
                'message' => 'Automatic delivery is not enabled.',
                'users_checked' => 0,
                'sent' => 0,
            ];
        }

        $results = [];

        foreach ($settingIds as $settingId) {
            $results[] = $this->runSetting((int) $settingId);
        }

        return [
            'status' => collect($results)->contains(
                fn (array $result): bool => $result['status'] === 'sent',
            ) ? 'sent' : 'idle',
            'users_checked' => count($results),
            'sent' => collect($results)->where('status', 'sent')->count(),
            'results' => $results,
        ];
    }

    private function runSetting(int $settingId): array
    {
        $gate = DB::transaction(function () use ($settingId): array {
            $setting = DeliveryAutomationSetting::query()
                ->whereKey($settingId)
                ->lockForUpdate()
                ->first();

            if ($setting === null || ! $setting->enabled) {
                return ['status' => 'disabled'];
            }

            if (
                $setting->last_status === 'checking_queue'
                && $setting->last_attempt_at !== null
                && now()->lessThan($setting->last_attempt_at->copy()->addMinutes(2))
            ) {
                return [
                    'status' => 'busy',
                    'user_id' => $setting->user_id,
                    'message' => 'Another automatic delivery check is already in progress.',
                ];
            }

            if ($setting->session_ends_at === null || now()->greaterThanOrEqualTo($setting->session_ends_at)) {
                $setting->update([
                    'enabled' => false,
                    'last_status' => 'session_expired',
                    'last_message' => 'Automatic delivery session finished. Start a new session to continue.',
                ]);

                return [
                    'status' => 'session_expired',
                    'user_id' => $setting->user_id,
                ];
            }

            $sentToday = OutreachMessage::query()
                ->whereHas(
                    'campaign',
                    fn ($query) => $query->where('user_id', $setting->user_id),
                )
                ->where('status', OutreachMessage::STATUS_SENT)
                ->whereBetween('sent_at', [now()->startOfDay(), now()->endOfDay()])
                ->count();

            if ($sentToday >= max(1, (int) $setting->global_daily_limit)) {
                $setting->update([
                    'enabled' => false,
                    'last_status' => 'global_daily_limit_reached',
                    'last_message' => 'Global daily delivery limit reached. Start a new session on a later day to continue.',
                ]);

                return [
                    'status' => 'global_daily_limit_reached',
                    'user_id' => $setting->user_id,
                    'sent_today' => $sentToday,
                ];
            }

            if (
                $setting->last_sent_at !== null
                && now()->lessThan(
                    $setting->last_sent_at->copy()->addMinutes(
                        max(5, (int) $setting->interval_minutes),
                    ),
                )
            ) {
                return [
                    'status' => 'waiting_interval',
                    'user_id' => $setting->user_id,
                    'next_at' => $setting->last_sent_at
                        ->copy()
                        ->addMinutes(max(5, (int) $setting->interval_minutes))
                        ->toISOString(),
                ];
            }

            $setting->update([
                'last_attempt_at' => now(),
                'last_status' => 'checking_queue',
                'last_message' => 'Checking the approved delivery queue.',
            ]);

            return [
                'status' => 'ready',
                'user_id' => $setting->user_id,
                'setting_id' => $setting->id,
            ];
        });

        if (($gate['status'] ?? null) !== 'ready') {
            return $gate;
        }

        $userId = (int) $gate['user_id'];
        $settingId = (int) $gate['setting_id'];

        if (! SenderAccount::query()->where('user_id', $userId)->exists()) {
            return $this->pause(
                $settingId,
                'no_sender_available',
                'You have no sender emails configured. Add sender accounts and start a new automatic delivery session.',
            );
        }

        $drafts = OutreachDraft::query()
            ->where('status', OutreachDraft::STATUS_APPROVED)
            ->where('channel', 'email')
            ->whereDoesntHave(
                'message',
                fn ($query) => $query->where('status', OutreachMessage::STATUS_SENT),
            )
            ->whereHas(
                'lead.campaign',
                fn ($query) => $query
                    ->where('user_id', $userId)
                    ->where('status', CampaignStatus::Active->value),
            )
            ->with(['lead.campaign', 'message'])
            ->oldest('reviewed_at')
            ->limit(25)
            ->get();

        if ($drafts->isEmpty()) {
            $this->mark(
                $settingId,
                'waiting_for_approved_queue',
                'No approved email drafts are waiting. Discovery and drafting can continue normally.',
            );

            return [
                'status' => 'waiting_for_approved_queue',
                'user_id' => $userId,
            ];
        }

        foreach ($drafts as $draft) {
            $result = $this->deliveryService->send($draft);
            $status = (string) ($result['status'] ?? 'unknown');

            if (in_array($status, ['sent', 'already_sent'], true)) {
                DeliveryAutomationSetting::query()
                    ->whereKey($settingId)
                    ->update([
                        'last_sent_at' => now(),
                        'last_status' => 'sent',
                        'last_message' => 'One human-approved email was delivered automatically.',
                    ]);

                return [
                    'status' => 'sent',
                    'user_id' => $userId,
                    'draft_id' => $draft->id,
                    'recipient' => $draft->recipient,
                ];
            }

            if (in_array($status, [
                'recipient_suppressed',
                'campaign_not_active',
                'lead_not_approved',
                'daily_limit_reached',
                'already_sent',
            ], true)) {
                continue;
            }

            if ($status === 'no_sender_available') {
                return $this->pause(
                    $settingId,
                    'no_sender_available',
                    'You have no available sender emails. Add/update sender accounts, then start a new automatic delivery session.',
                );
            }

            if ($status === 'failed') {
                return $this->pause(
                    $settingId,
                    'delivery_failed',
                    'Automatic delivery paused after an SMTP/delivery failure. Review the sender account before restarting.',
                );
            }

            return $this->pause(
                $settingId,
                'delivery_blocked',
                (string) ($result['message'] ?? 'Automatic delivery was blocked and has been paused for review.'),
            );
        }

        $this->mark(
            $settingId,
            'waiting_for_eligible_draft',
            'Approved drafts exist, but none are currently eligible under campaign or suppression controls.',
        );

        return [
            'status' => 'waiting_for_eligible_draft',
            'user_id' => $userId,
        ];
    }

    private function pause(int $settingId, string $status, string $message): array
    {
        $setting = DeliveryAutomationSetting::query()->find($settingId);

        if ($setting !== null) {
            $setting->update([
                'enabled' => false,
                'last_status' => $status,
                'last_message' => $message,
            ]);
        }

        return [
            'status' => $status,
            'user_id' => $setting?->user_id,
            'message' => $message,
        ];
    }

    private function mark(int $settingId, string $status, string $message): void
    {
        DeliveryAutomationSetting::query()
            ->whereKey($settingId)
            ->update([
                'last_status' => $status,
                'last_message' => $message,
            ]);
    }
}
