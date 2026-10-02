<?php

namespace App\Http\Controllers\Api;

use App\Enums\CampaignStatus;
use App\Enums\LeadStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CompleteLeadAnalysisRequest;
use App\Models\Lead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkerAnalysisController extends Controller
{
    public function queue(Request $request): JsonResponse
    {
        $limit = max(1, min((int) $request->integer('limit', 10), 25));

        $leads = Lead::query()
            ->whereIn('status', [
                LeadStatus::Discovered->value,
                LeadStatus::Analyzing->value,
            ])
            ->whereHas(
                'campaign',
                fn ($query) => $query->where('status', CampaignStatus::Active->value),
            )
            ->with('campaign:id,uuid,name,target_industry,target_countries,target_cities,qualification_rules,status')
            ->oldest()
            ->limit($limit)
            ->get();

        return response()->json([
            'count' => $leads->count(),
            'leads' => $leads->map(fn (Lead $lead) => $this->analysisPayload($lead))->values(),
        ]);
    }

    public function start(Lead $lead): JsonResponse
    {
        $lead->load('campaign');

        if ($lead->campaign->status !== CampaignStatus::Active) {
            return response()->json([
                'message' => 'The lead campaign is not active.',
                'code' => 'campaign_not_active',
            ], 409);
        }

        if (! in_array($lead->status, [LeadStatus::Discovered, LeadStatus::Analyzing], true)) {
            return response()->json([
                'message' => 'This lead is not waiting for analysis.',
                'code' => 'analysis_not_pending',
            ], 409);
        }

        if ($lead->status === LeadStatus::Discovered) {
            $lead->update([
                'status' => LeadStatus::Analyzing,
            ]);
        }

        return response()->json([
            'status' => 'analyzing',
            'lead' => $this->analysisPayload($lead->fresh('campaign')),
        ]);
    }

    public function complete(
        CompleteLeadAnalysisRequest $request,
        Lead $lead,
    ): JsonResponse {
        $lead->load('campaign');

        if ($lead->campaign->status !== CampaignStatus::Active) {
            return response()->json([
                'message' => 'The lead campaign is not active.',
                'code' => 'campaign_not_active',
            ], 409);
        }

        if (in_array($lead->status, [LeadStatus::Qualified, LeadStatus::Rejected], true)) {
            return response()->json([
                'status' => 'already_completed',
                'lead' => $this->resultPayload($lead),
            ]);
        }

        if ($lead->status !== LeadStatus::Analyzing) {
            return response()->json([
                'message' => 'Start analysis before completing it.',
                'code' => 'analysis_not_started',
            ], 409);
        }

        $validated = $request->validated();
        $score = (int) $validated['qualification_score'];
        $minimumScore = (int) ($lead->campaign->qualification_rules['minimum_score'] ?? 60);
        $requiresWebsite = (bool) ($lead->campaign->qualification_rules['requires_website'] ?? true);

        $qualified = $score >= $minimumScore;

        if ($requiresWebsite && empty($lead->domain)) {
            $qualified = false;
        }

        $observedSignals = array_values(array_unique(array_filter([
            ...($lead->observed_signals ?? []),
            ...($validated['observed_signals'] ?? []),
        ], static fn ($signal) => is_string($signal) && trim($signal) !== '')));

        $lead->update([
            'status' => $qualified ? LeadStatus::Qualified : LeadStatus::Rejected,
            'qualification_score' => $score,
            'qualification_reason' => $validated['qualification_reason'],
            'observed_signals' => $observedSignals,
            'analyzed_at' => now(),
            'qualified_at' => $qualified ? now() : null,
        ]);

        return response()->json([
            'status' => 'completed',
            'decision' => $qualified ? 'qualified' : 'rejected',
            'minimum_score' => $minimumScore,
            'lead' => $this->resultPayload($lead->fresh()),
        ]);
    }

    private function analysisPayload(Lead $lead): array
    {
        return [
            'uuid' => $lead->uuid,
            'business_name' => $lead->business_name,
            'website_url' => $lead->website_url,
            'domain' => $lead->domain,
            'industry' => $lead->industry,
            'country' => $lead->country,
            'city' => $lead->city,
            'source' => $lead->source,
            'source_url' => $lead->source_url,
            'status' => $lead->status->value,
            'observed_signals' => $lead->observed_signals ?? [],
            'campaign' => [
                'uuid' => $lead->campaign->uuid,
                'name' => $lead->campaign->name,
                'target_industry' => $lead->campaign->target_industry,
                'target_countries' => $lead->campaign->target_countries ?? [],
                'target_cities' => $lead->campaign->target_cities ?? [],
                'qualification_rules' => $lead->campaign->qualification_rules ?? [],
            ],
        ];
    }

    private function resultPayload(Lead $lead): array
    {
        return [
            'uuid' => $lead->uuid,
            'business_name' => $lead->business_name,
            'domain' => $lead->domain,
            'status' => $lead->status->value,
            'qualification_score' => $lead->qualification_score,
            'qualification_reason' => $lead->qualification_reason,
            'observed_signals' => $lead->observed_signals ?? [],
            'analyzed_at' => $lead->analyzed_at?->toISOString(),
            'qualified_at' => $lead->qualified_at?->toISOString(),
        ];
    }
}
