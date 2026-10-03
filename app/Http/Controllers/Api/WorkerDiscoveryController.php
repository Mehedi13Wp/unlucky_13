<?php

namespace App\Http\Controllers\Api;

use App\Enums\CampaignStatus;
use App\Enums\LeadStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\IngestDiscoveredLeadRequest;
use App\Models\Campaign;
use App\Models\DiscoveryDomain;
use App\Models\Lead;
use App\Services\Leads\DomainNormalizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WorkerDiscoveryController extends Controller
{
    public function health(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'service' => 'unlucky13-worker-api',
        ]);
    }

    public function knownDomains(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'domains' => ['required', 'array', 'max:200'],
            'domains.*' => ['required', 'string', 'max:253'],
        ]);

        $domains = collect($validated['domains'])
            ->map(fn (string $domain): ?string => $this->normalizeDomain($domain))
            ->filter()
            ->unique()
            ->values();

        if ($domains->isEmpty()) {
            return response()->json([
                'count' => 0,
                'domains' => [],
            ]);
        }

        $known = DiscoveryDomain::query()
            ->whereIn('domain', $domains)
            ->pluck('domain')
            ->merge(
                Lead::query()
                    ->whereIn('domain', $domains)
                    ->pluck('domain'),
            )
            ->filter()
            ->unique()
            ->sort()
            ->values();

        return response()->json([
            'count' => $known->count(),
            'domains' => $known,
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

        [$status, $lead] = DB::transaction(function () use (
            $campaign,
            $validated,
            $website,
        ): array {
            $history = DiscoveryDomain::query()->firstOrCreate(
                ['domain' => $website['domain']],
                [
                    'canonical_url' => $website['website_url'],
                    'source' => $validated['source'] ?? 'worker',
                    'source_url' => $validated['source_url'] ?? null,
                    'first_seen_at' => now(),
                    'last_seen_at' => now(),
                ],
            );

            $history = DiscoveryDomain::query()
                ->whereKey($history->id)
                ->lockForUpdate()
                ->firstOrFail();

            $existingLead = null;

            if ($history->first_lead_id !== null) {
                $existingLead = Lead::query()->find($history->first_lead_id);
            }

            $existingLead ??= Lead::query()
                ->where('domain', $website['domain'])
                ->oldest('id')
                ->first();

            if ($existingLead) {
                $history->update([
                    'first_lead_id' => $history->first_lead_id ?? $existingLead->id,
                    'first_campaign_id' => $history->first_campaign_id ?? $existingLead->campaign_id,
                    'canonical_url' => $history->canonical_url ?? $existingLead->website_url,
                    'last_seen_at' => now(),
                ]);

                return ['duplicate', $existingLead];
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

            $history->update([
                'canonical_url' => $website['website_url'],
                'first_lead_id' => $lead->id,
                'first_campaign_id' => $campaign->id,
                'source' => $validated['source'] ?? 'worker',
                'source_url' => $validated['source_url'] ?? null,
                'first_seen_at' => $history->first_seen_at ?? now(),
                'last_seen_at' => now(),
            ]);

            return ['created', $lead];
        });

        return response()->json([
            'status' => $status,
            'duplicate_scope' => $status === 'duplicate' ? 'global_domain' : null,
            'lead' => $this->leadPayload($lead),
        ], $status === 'created' ? 201 : 200);
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

    private function normalizeDomain(string $domain): ?string
    {
        $domain = strtolower(trim($domain));
        $domain = rtrim($domain, '.');

        if (str_starts_with($domain, 'www.')) {
            $domain = substr($domain, 4);
        }

        if (
            $domain === ''
            || filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) === false
        ) {
            return null;
        }

        return $domain;
    }
}
