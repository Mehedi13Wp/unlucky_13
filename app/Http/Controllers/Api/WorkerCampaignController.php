<?php

namespace App\Http\Controllers\Api;

use App\Enums\CampaignStatus;
use App\Http\Controllers\Controller;
use App\Models\Campaign;
use Illuminate\Http\JsonResponse;

class WorkerCampaignController extends Controller
{
    public function index(): JsonResponse
    {
        $campaigns = Campaign::query()
            ->where('status', CampaignStatus::Active->value)
            ->oldest()
            ->get([
                'id',
                'uuid',
                'name',
                'service_offer',
                'target_industry',
                'target_countries',
                'target_cities',
                'qualification_rules',
                'daily_outreach_limit',
                'status',
                'last_run_at',
            ]);

        return response()->json([
            'count' => $campaigns->count(),
            'campaigns' => $campaigns->map(fn (Campaign $campaign) => [
                'uuid' => $campaign->uuid,
                'name' => $campaign->name,
                'service_offer' => $campaign->service_offer,
                'target_industry' => $campaign->target_industry,
                'target_countries' => $campaign->target_countries ?? [],
                'target_cities' => $campaign->target_cities ?? [],
                'qualification_rules' => $campaign->qualification_rules ?? [],
                'daily_outreach_limit' => $campaign->daily_outreach_limit,
                'status' => $campaign->status->value,
                'last_run_at' => $campaign->last_run_at?->toISOString(),
            ])->values(),
        ]);
    }
}
