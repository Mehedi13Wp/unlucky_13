<?php

namespace App\Http\Controllers\Api;

use App\Enums\CampaignStatus;
use App\Enums\LeadStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\IngestDiscoveredLeadRequest;
use App\Models\Campaign;
use App\Models\Lead;
use App\Services\Leads\DomainNormalizer;
use Illuminate\Http\JsonResponse;

class WorkerDiscoveryController extends Controller
{
    public function health(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'service' => 'unlucky13-worker-api',
        ]);
    }

    public function store(
        IngestDiscoveredLeadRequest $request,
        Campaign $campaign,
        DomainNormalizer $domainNormalizer,
    ): JsonResponse {
        if ($campaign->status !== CampaignStatus::Active) {
            return response()->json([
                'message' => 'Only active campaigns can receive discovered leads.',
                'code' => 'campaign_not_active',
            ], 409);
        }

        $validated = $request->validated();
        $website = $domainNormalizer->normalizeWebsite($validated['website_url']);

        if ($website['domain'] === null) {
            return response()->json([
                'message' => 'A valid website domain is required for worker discovery.',
                'code' => 'invalid_domain',
                'errors' => [
                    'website_url' => ['The website URL must contain a valid domain.'],
                ],
            ], 422);
        }

        $existingLead = $campaign->leads()
            ->where('domain', $website['domain'])
            ->first();

        if ($existingLead) {
            return response()->json([
                'status' => 'duplicate',
                'lead' => $this->leadPayload($existingLead),
            ]);
        }

        $lead = $campaign->leads()->create([
            'business_name' => $validated['business_name'],
            'website_url' => $website['website_url'],
            'domain' => $website['domain'],
            'industry' => $validated['industry'] ?? $campaign->target_industry,
            'country' => $validated['country'] ?? null,
            'city' => $validated['city'] ?? null,
            'source' => $validated['source'] ?? 'worker',
            'source_url' => $validated['source_url'] ?? null,
            'status' => LeadStatus::Discovered,
            'observed_signals' => $validated['observed_signals'] ?? [],
        ]);

        return response()->json([
            'status' => 'created',
            'lead' => $this->leadPayload($lead),
        ], 201);
    }

    private function leadPayload(Lead $lead): array
    {
        return [
            'uuid' => $lead->uuid,
            'business_name' => $lead->business_name,
            'domain' => $lead->domain,
            'status' => $lead->status->value,
            'campaign_uuid' => $lead->campaign()->value('uuid'),
        ];
    }
}
