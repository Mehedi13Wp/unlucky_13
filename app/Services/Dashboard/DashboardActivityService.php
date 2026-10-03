<?php

namespace App\Services\Dashboard;

use App\Enums\CampaignStatus;
use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\OutreachDraft;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

class DashboardActivityService
{
    public function snapshot(User $user, array $workerStatus): array
    {
        $today = now()->startOfDay();

        $leadQuery = Lead::query()->whereHas(
            'campaign',
            fn ($query) => $query->where('user_id', $user->id),
        );

        $draftQuery = OutreachDraft::query()->whereHas(
            'lead.campaign',
            fn ($query) => $query->where('user_id', $user->id),
        );

        $lastDiscovery = $user->campaigns()
            ->whereNotNull('last_run_at')
            ->max('last_run_at');

        $campaigns = $user->campaigns()->count();
        $activeCampaigns = $user->campaigns()
            ->where('status', CampaignStatus::Active)
            ->count();
        $draftCampaigns = $user->campaigns()
            ->where('status', CampaignStatus::Draft)
            ->count();
        $businessesFound = (clone $leadQuery)->count();
        $qualified = (clone $leadQuery)
            ->whereNotNull('qualified_at')
            ->count();
        $reviewQueue = (clone $leadQuery)
            ->where('status', LeadStatus::ReadyForReview)
            ->count();
        $interested = (clone $leadQuery)
            ->where('status', LeadStatus::Interested)
            ->count();
        $contacted = (clone $leadQuery)
            ->whereIn('status', [
                LeadStatus::Contacted->value,
                LeadStatus::Replied->value,
                LeadStatus::Interested->value,
                LeadStatus::NotInterested->value,
                LeadStatus::Unsubscribed->value,
                LeadStatus::Bounced->value,
            ])
            ->count();

        return [
            'campaigns' => $campaigns,
            'active_campaigns' => $activeCampaigns,
            'draft_campaigns' => $draftCampaigns,
            'businesses_found' => $businessesFound,
            'qualified' => $qualified,
            'review_queue' => $reviewQueue,
            'interested' => $interested,
            'qualification_rate' => $businessesFound > 0
                ? (int) round(($qualified / $businessesFound) * 100)
                : 0,
            'interest_rate' => $contacted > 0
                ? (int) round(($interested / $contacted) * 100)
                : 0,
            'new_leads_today' => (clone $leadQuery)
                ->where('created_at', '>=', $today)
                ->count(),
            'analyzed_today' => (clone $leadQuery)
                ->whereNotNull('analyzed_at')
                ->where('analyzed_at', '>=', $today)
                ->count(),
            'drafts_today' => (clone $draftQuery)
                ->where('generated_at', '>=', $today)
                ->count(),
            'heartbeat_at' => $workerStatus['heartbeat_at'] ?? null,
            'heartbeat_label' => $this->relativeLabel(
                $workerStatus['heartbeat_at'] ?? null,
                $workerStatus['running'] ?? false,
                'Waiting for worker',
            ),
            'last_cycle_at' => $workerStatus['last_cycle_at'] ?? null,
            'last_cycle_label' => $this->relativeLabel(
                $workerStatus['last_cycle_at'] ?? null,
                true,
                'No cycle yet',
            ),
            'last_discovery_at' => $lastDiscovery
                ? Carbon::parse($lastDiscovery)->toISOString()
                : null,
            'last_discovery_label' => $this->relativeLabel(
                $lastDiscovery,
                true,
                'No discovery yet',
            ),
            'last_cycle' => $workerStatus['last_cycle'] ?? [],
        ];
    }

    private function relativeLabel(
        mixed $value,
        bool $enabled,
        string $fallback,
    ): string {
        if (! $enabled && empty($value)) {
            return $fallback;
        }

        if ($value instanceof CarbonInterface) {
            return $value->diffForHumans();
        }

        if (! is_string($value) || trim($value) === '') {
            return $fallback;
        }

        try {
            return Carbon::parse($value)->diffForHumans();
        } catch (\Throwable) {
            return $fallback;
        }
    }
}
