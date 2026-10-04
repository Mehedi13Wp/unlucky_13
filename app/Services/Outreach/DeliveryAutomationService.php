<?php

namespace App\Services\Outreach;

use App\Enums\CampaignStatus;
use App\Models\DeliveryAutomationSetting;
use App\Models\OutreachDraft;
use App\Models\OutreachMessage;
use App\Models\SenderAccount;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DeliveryAutomationService
{
    private const MAX_AUTOMATIC_ATTEMPTS = 3;

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
                in_array($setting->last_status, ['checking_queue', 'sending'], true)
                && $setting->last_attempt_at !== null
                && now()->lessThan($setting->last_attempt_at->copy()->addMinutes(5))
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
                    'next_attempt_at' => null,
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
                    'next_attempt_at' => null,
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
                $setting->next_attempt_at !== null
                && now()->lessThan($setting->next_attempt_at)
            ) {
                return [
                    'status' => $setting->last_status === 'retry_wait'
                        ? 'retry_wait'
                        : 'waiting_interval',
                    'user_id' => $setting->user_id,
                    'next_at' => $setting->next_attempt_at->toISOString(),
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
                'interval_minutes' => max(5, (int) $setting->interval_minutes),
            ];
        });

        if (($gate['status'] ?? null) !== 'ready') {
            return $gate;
        }

        $userId = (int) $gate['user_id'];
        $settingId = (int) $gate['setting_id'];
        $intervalMinutes = (int) $gate['interval_minutes'];

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
            ->oldest('id')
            ->limit(100)
            ->get();

        if ($drafts->isEmpty()) {
            $this->mark(
                $settingId,
                'waiting_for_approved_queue',
                'No approved email drafts are waiting. Discovery and drafting can continue normally.',
                ['next_attempt_at' => null],
            );

            return [
                'status' => 'waiting_for_approved_queue',
                'user_id' => $userId,
            ];
        }

        $earliestRetryAt = null;

        foreach ($drafts as $draft) {
            $this->mark(
                $settingId,
                'sending',
                'Sending one human-approved email from the controlled queue.',
            );

            $result = $this->deliveryService->send($draft);
            $status = (string) ($result['status'] ?? 'unknown');

            if ($status === 'sent') {
                $nextAttemptAt = now()->addMinutes($intervalMinutes);

                DeliveryAutomationSetting::query()
                    ->whereKey($settingId)
                    ->update([
                        'last_sent_at' => now(),
                        'next_attempt_at' => $nextAttemptAt,
                        'last_status' => 'sent',
                        'last_message' => 'One human-approved email was delivered automatically. The queue is waiting for the next interval.',
                    ]);

                return [
                    'status' => 'sent',
                    'user_id' => $userId,
                    'draft_id' => $draft->id,
                    'recipient' => $draft->recipient,
                    'next_at' => $nextAttemptAt->toISOString(),
                ];
            }

            if ($status === 'already_sent') {
                continue;
            }

            if ($status === 'retry_wait') {
                $retryAt = $this->parseRetryAt($result['retry_at'] ?? null);

                if (
                    $retryAt !== null
                    && ($earliestRetryAt === null || $retryAt->lessThan($earliestRetryAt))
                ) {
                    $earliestRetryAt = $retryAt;
                }

                continue;
            }

            if (in_array($status, [
                'recipient_suppressed',
                'campaign_not_active',
                'lead_not_approved',
                'daily_limit_reached',
                'draft_state_changed',
                'draft_not_approved',
                'unsupported_channel',
                'delivery_in_progress',
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
                $attemptCount = max(1, (int) ($result['attempt_count'] ?? 1));

                if ($attemptCount >= self::MAX_AUTOMATIC_ATTEMPTS) {
                    return $this->pause(
                        $settingId,
                        'delivery_failed',
                        'Automatic delivery paused after repeated delivery failures. Review the sender account before restarting.',
                    );
                }

                $retryAt = $this->parseRetryAt($result['retry_at'] ?? null)
                    ?? now()->addMinutes($intervalMinutes);
                $intervalAt = now()->addMinutes($intervalMinutes);
                $nextAttemptAt = $retryAt->greaterThan($intervalAt)
                    ? $retryAt
                    : $intervalAt;

                $this->mark(
                    $settingId,
                    'retry_wait',
                    "Delivery failed safely. Retry {$attemptCount} is scheduled after the controlled wait.",
                    ['next_attempt_at' => $nextAttemptAt],
                );

                return [
                    'status' => 'retry_wait',
                    'user_id' => $userId,
                    'draft_id' => $draft->id,
                    'attempt_count' => $attemptCount,
                    'next_at' => $nextAttemptAt->toISOString(),
                ];
            }

            return $this->pause(
                $settingId,
                'delivery_blocked',
                (string) ($result['message'] ?? 'Automatic delivery was blocked and has been paused for review.'),
            );
        }

        if ($earliestRetryAt !== null) {
            $nextAttemptAt = $earliestRetryAt->greaterThan(now())
                ? $earliestRetryAt
                : now()->addMinute();

            $this->mark(
                $settingId,
                'retry_wait',
                'A failed draft is waiting for its controlled retry window.',
                ['next_attempt_at' => $nextAttemptAt],
            );

            return [
                'status' => 'retry_wait',
                'user_id' => $userId,
                'next_at' => $nextAttemptAt->toISOString(),
            ];
        }

        $this->mark(
            $settingId,
            'waiting_for_eligible_draft',
            'Approved drafts exist, but none are currently eligible under campaign or suppression controls.',
            ['next_attempt_at' => null],
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
                'next_attempt_at' => null,
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

    private function mark(
        int $settingId,
        string $status,
        string $message,
        array $extra = [],
    ): void {
        DeliveryAutomationSetting::query()
            ->whereKey($settingId)
            ->update([
                'last_status' => $status,
                'last_message' => $message,
                ...$extra,
            ]);
    }

    private function parseRetryAt(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
