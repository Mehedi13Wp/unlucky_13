<?php

namespace App\Http\Controllers;

use App\Enums\CampaignStatus;
use App\Enums\LeadStatus;
use App\Http\Requests\StoreLeadRequest;
use App\Models\Campaign;
use App\Models\Lead;
use App\Services\Leads\DomainNormalizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LeadController extends Controller
{
    public function index(Request $request): View
    {
        $leadQuery = Lead::query()
            ->whereHas('campaign', fn ($query) => $query->where('user_id', $request->user()->id));

        if ($request->filled('campaign')) {
            $campaignUuid = (string) $request->string('campaign');

            $leadQuery->whereHas(
                'campaign',
                fn ($query) => $query
                    ->where('user_id', $request->user()->id)
                    ->where('uuid', $campaignUuid),
            );
        }

        if ($request->filled('status')) {
            $status = LeadStatus::tryFrom((string) $request->string('status'));

            if ($status) {
                $leadQuery->where('status', $status->value);
            }
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->string('search'));

            $leadQuery->where(function ($query) use ($search): void {
                $query
                    ->where('business_name', 'like', '%'.$search.'%')
                    ->orWhere('domain', 'like', '%'.$search.'%')
                    ->orWhere('city', 'like', '%'.$search.'%')
                    ->orWhere('country', 'like', '%'.$search.'%');
            });
        }

        $leads = $leadQuery
            ->with('campaign:id,uuid,name,user_id')
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $campaigns = $request->user()
            ->campaigns()
            ->where('status', '!=', CampaignStatus::Archived->value)
            ->orderBy('name')
            ->get(['id', 'uuid', 'name']);

        $statsQuery = Lead::query()
            ->whereHas('campaign', fn ($query) => $query->where('user_id', $request->user()->id));

        $stats = [
            'total' => (clone $statsQuery)->count(),
            'qualified' => (clone $statsQuery)->where('status', LeadStatus::Qualified->value)->count(),
            'review' => (clone $statsQuery)->where('status', LeadStatus::ReadyForReview->value)->count(),
            'interested' => (clone $statsQuery)->where('status', LeadStatus::Interested->value)->count(),
        ];

        return view('leads.index', [
            'leads' => $leads,
            'campaigns' => $campaigns,
            'statuses' => LeadStatus::cases(),
            'stats' => $stats,
        ]);
    }

    public function create(Request $request): View
    {
        $campaigns = $request->user()
            ->campaigns()
            ->where('status', '!=', CampaignStatus::Archived->value)
            ->orderBy('name')
            ->get(['id', 'uuid', 'name']);

        return view('leads.create', [
            'campaigns' => $campaigns,
            'selectedCampaign' => $request->string('campaign')->toString(),
        ]);
    }

    public function store(
        StoreLeadRequest $request,
        DomainNormalizer $domainNormalizer,
    ): RedirectResponse {
        $campaign = $request->user()
            ->campaigns()
            ->where('uuid', $request->validated('campaign_uuid'))
            ->where('status', '!=', CampaignStatus::Archived->value)
            ->firstOrFail();

        $website = $domainNormalizer->normalizeWebsite($request->validated('website_url'));

        if ($website['domain'] !== null) {
            $exists = $campaign->leads()
                ->where('domain', $website['domain'])
                ->exists();

            if ($exists) {
                throw ValidationException::withMessages([
                    'website_url' => 'This website already exists in the selected campaign.',
                ]);
            }
        }

        $lead = $campaign->leads()->create([
            'business_name' => $request->validated('business_name'),
            'website_url' => $website['website_url'],
            'domain' => $website['domain'],
            'industry' => $request->validated('industry'),
            'country' => $request->validated('country'),
            'city' => $request->validated('city'),
            'source' => $request->validated('source') ?: 'manual',
            'source_url' => $request->validated('source_url'),
            'status' => LeadStatus::Discovered,
            'observed_signals' => [],
        ]);

        return redirect()
            ->route('leads.show', $lead)
            ->with('success', 'Lead added to the discovery pipeline.');
    }

    public function show(Request $request, Lead $lead): View
    {
        $lead->load([
            'campaign',
            'contacts',
            'outreachDrafts' => fn ($query) => $query->with('contact')->latest(),
        ]);

        $this->ensureOwner($request, $lead);

        return view('leads.show', compact('lead'));
    }

    private function ensureOwner(Request $request, Lead $lead): void
    {
        abort_unless($lead->campaign->user_id === $request->user()->id, 404);
    }
}
