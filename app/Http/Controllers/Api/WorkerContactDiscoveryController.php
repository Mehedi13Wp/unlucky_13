<?php

namespace App\Http\Controllers\Api;

use App\Enums\CampaignStatus;
use App\Enums\LeadStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CompleteContactDiscoveryRequest;
use App\Models\Lead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkerContactDiscoveryController extends Controller
{
    private const CONTACT_DISCOVERY_VERSION = 2;

    public function queue(Request $request): JsonResponse
    {
        $limit = max(1, min((int) $request->integer('limit', 10), 25));
        $leadUuid = trim((string) $request->query('lead', ''));

        $leads = Lead::query()
            ->when(
                $leadUuid !== '',
                fn ($query) => $query->where('uuid', $leadUuid),
            )
            ->whereIn('status', [
                LeadStatus::Qualified->value,
                LeadStatus::ReadyForReview->value,
            ])
            ->where(function ($query): void {
                $query
                    ->whereNull('contact_discovered_at')
                    ->orWhere('contact_discovery_version', '<', self::CONTACT_DISCOVERY_VERSION);
            })
            ->whereHas(
                'campaign',
                fn ($query) => $query->where('status', CampaignStatus::Active->value),
            )
            ->with('campaign:id,uuid,name,service_offer,status')
            ->oldest('qualified_at')
            ->limit($limit)
            ->get();

        return response()->json([
            'count' => $leads->count(),
            'leads' => $leads->map(fn (Lead $lead) => [
                'uuid' => $lead->uuid,
                'business_name' => $lead->business_name,
                'website_url' => $lead->website_url,
                'domain' => $lead->domain,
                'industry' => $lead->industry,
                'country' => $lead->country,
                'city' => $lead->city,
                'status' => $lead->status->value,
                'qualification_score' => $lead->qualification_score,
                'contact_discovery_version' => $lead->contact_discovery_version,
                'observed_signals' => $lead->observed_signals ?? [],
                'campaign' => [
                    'uuid' => $lead->campaign->uuid,
                    'name' => $lead->campaign->name,
                    'service_offer' => $lead->campaign->service_offer,
                ],
            ])->values(),
        ]);
    }

    public function complete(
        CompleteContactDiscoveryRequest $request,
        Lead $lead,
    ): JsonResponse {
        $lead->load('campaign');

        if ($lead->campaign->status !== CampaignStatus::Active) {
            return response()->json([
                'message' => 'The lead campaign is not active.',
                'code' => 'campaign_not_active',
            ], 409);
        }

        if (! in_array($lead->status, [
            LeadStatus::Qualified,
            LeadStatus::ReadyForReview,
        ], true)) {
            return response()->json([
                'message' => 'Only qualified or review-ready leads can receive discovered contacts.',
                'code' => 'lead_not_qualified',
            ], 409);
        }

        $validated = $request->validated();
        $stored = [];

        foreach ($validated['contacts'] as $contact) {
            $value = trim((string) $contact['value']);

            if ($value === '') {
                continue;
            }

            $record = $lead->contacts()->updateOrCreate(
                [
                    'type' => $contact['type'],
                    'value' => $value,
                ],
                [
                    'label' => $contact['label'] ?? null,
                    'source_url' => $contact['source_url'] ?? null,
                    'discovered_at' => now(),
                ],
            );

            $stored[] = [
                'id' => $record->id,
                'type' => $record->type,
                'value' => $record->value,
                'label' => $record->label,
                'source_url' => $record->source_url,
            ];
        }

        $lead->update([
            'contact_discovered_at' => now(),
            'contact_discovery_version' => self::CONTACT_DISCOVERY_VERSION,
        ]);

        return response()->json([
            'status' => 'completed',
            'contact_count' => count($stored),
            'contacts' => $stored,
            'notes' => $validated['notes'] ?? [],
            'lead' => [
                'uuid' => $lead->uuid,
                'status' => $lead->status->value,
                'contact_discovered_at' => $lead->fresh()->contact_discovered_at?->toISOString(),
                'contact_discovery_version' => $lead->fresh()->contact_discovery_version,
            ],
        ]);
    }
}
