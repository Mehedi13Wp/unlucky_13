<?php

namespace App\Http\Controllers\Api;

use App\Enums\CampaignStatus;
use App\Enums\LeadStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CompleteOutreachDraftRequest;
use App\Models\Lead;
use App\Models\OutreachDraft;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkerOutreachDraftController extends Controller
{
    public function queue(Request $request): JsonResponse
    {
        $limit = max(1, min((int) $request->integer('limit', 5), 25));

        $leads = Lead::query()
            ->where('status', LeadStatus::Qualified->value)
            ->whereNotNull('contact_discovered_at')
            ->whereHas(
                'campaign',
                fn ($query) => $query->where('status', CampaignStatus::Active->value),
            )
            ->whereHas(
                'contacts',
                fn ($query) => $query->whereIn('type', ['email', 'contact_page']),
            )
            ->whereDoesntHave(
                'outreachDrafts',
                fn ($query) => $query->whereIn('status', [
                    OutreachDraft::STATUS_PENDING_REVIEW,
                    OutreachDraft::STATUS_APPROVED,
                ]),
            )
            ->with([
                'campaign:id,uuid,name,service_offer,status,review_required,auto_send',
                'contacts:id,lead_id,type,value,label,source_url',
            ])
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
                'qualification_score' => $lead->qualification_score,
                'observed_signals' => $lead->observed_signals ?? [],
                'contacts' => $lead->contacts->map(fn ($contact) => [
                    'id' => $contact->id,
                    'type' => $contact->type,
                    'value' => $contact->value,
                    'label' => $contact->label,
                    'source_url' => $contact->source_url,
                ])->values(),
                'campaign' => [
                    'uuid' => $lead->campaign->uuid,
                    'name' => $lead->campaign->name,
                    'service_offer' => $lead->campaign->service_offer,
                    'review_required' => $lead->campaign->review_required,
                    'auto_send' => $lead->campaign->auto_send,
                ],
            ])->values(),
        ]);
    }

    public function complete(
        CompleteOutreachDraftRequest $request,
        Lead $lead,
    ): JsonResponse {
        $lead->load(['campaign', 'contacts']);

        if ($lead->campaign->status !== CampaignStatus::Active) {
            return response()->json([
                'message' => 'The lead campaign is not active.',
                'code' => 'campaign_not_active',
            ], 409);
        }

        if ($lead->status !== LeadStatus::Qualified) {
            return response()->json([
                'message' => 'Only qualified leads can receive outreach drafts.',
                'code' => 'lead_not_qualified',
            ], 409);
        }

        $validated = $request->validated();
        $contact = $lead->contacts->firstWhere('id', (int) $validated['contact_id']);

        if (! $contact) {
            return response()->json([
                'message' => 'The selected contact does not belong to this lead.',
                'code' => 'invalid_contact',
            ], 422);
        }

        $expectedChannel = $contact->type === 'email'
            ? 'email'
            : ($contact->type === 'contact_page' ? 'contact_form' : null);

        if ($expectedChannel === null || $validated['channel'] !== $expectedChannel) {
            return response()->json([
                'message' => 'The selected contact does not support the requested outreach channel.',
                'code' => 'invalid_channel',
            ], 422);
        }

        if ($validated['recipient'] !== $contact->value) {
            return response()->json([
                'message' => 'Draft recipient must match the selected public contact.',
                'code' => 'recipient_mismatch',
            ], 422);
        }

        if ($validated['channel'] === 'email' && blank($validated['subject'] ?? null)) {
            return response()->json([
                'message' => 'Email drafts require a subject.',
                'code' => 'subject_required',
            ], 422);
        }

        $draft = $lead->outreachDrafts()->create([
            'contact_id' => $contact->id,
            'channel' => $validated['channel'],
            'recipient' => $validated['recipient'],
            'subject' => $validated['subject'] ?? null,
            'body' => $validated['body'],
            'personalization_notes' => $validated['personalization_notes'] ?? [],
            'status' => OutreachDraft::STATUS_PENDING_REVIEW,
            'generated_at' => now(),
        ]);

        $lead->update([
            'status' => LeadStatus::ReadyForReview,
        ]);

        return response()->json([
            'status' => 'ready_for_review',
            'draft' => [
                'id' => $draft->id,
                'channel' => $draft->channel,
                'recipient' => $draft->recipient,
                'subject' => $draft->subject,
                'body' => $draft->body,
                'personalization_notes' => $draft->personalization_notes ?? [],
                'status' => $draft->status,
            ],
            'lead' => [
                'uuid' => $lead->uuid,
                'status' => $lead->fresh()->status->value,
            ],
        ]);
    }
}
