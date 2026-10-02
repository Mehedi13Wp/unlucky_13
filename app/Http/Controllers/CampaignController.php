<?php

namespace App\Http\Controllers;

use App\Enums\CampaignStatus;
use App\Http\Requests\SaveCampaignRequest;
use App\Http\Requests\UpdateCampaignStatusRequest;
use App\Models\Campaign;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CampaignController extends Controller
{
    public function index(Request $request): View
    {
        $campaigns = $request->user()
            ->campaigns()
            ->withCount('leads')
            ->latest()
            ->get();

        return view('campaigns.index', compact('campaigns'));
    }

    public function create(): View
    {
        return view('campaigns.create');
    }

    public function store(SaveCampaignRequest $request): RedirectResponse
    {
        $campaign = $request->user()->campaigns()->create(
            $this->payload($request) + [
                'status' => CampaignStatus::Draft,
                'auto_send' => false,
            ],
        );

        return redirect()
            ->route('campaigns.show', $campaign)
            ->with('success', 'Campaign created as a draft.');
    }

    public function show(Request $request, Campaign $campaign): View
    {
        $this->ensureOwner($request, $campaign);

        $campaign->loadCount([
            'leads',
            'leads as qualified_leads_count' => fn ($query) => $query->where('status', 'qualified'),
            'leads as interested_leads_count' => fn ($query) => $query->where('status', 'interested'),
        ]);

        return view('campaigns.show', compact('campaign'));
    }

    public function edit(Request $request, Campaign $campaign): View
    {
        $this->ensureOwner($request, $campaign);

        return view('campaigns.edit', compact('campaign'));
    }

    public function update(SaveCampaignRequest $request, Campaign $campaign): RedirectResponse
    {
        $this->ensureOwner($request, $campaign);

        $campaign->update($this->payload($request));

        return redirect()
            ->route('campaigns.show', $campaign)
            ->with('success', 'Campaign settings updated.');
    }

    public function updateStatus(
        UpdateCampaignStatusRequest $request,
        Campaign $campaign,
    ): RedirectResponse {
        $this->ensureOwner($request, $campaign);

        $campaign->update([
            'status' => CampaignStatus::from($request->validated('status')),
        ]);

        return back()->with('success', 'Campaign status updated.');
    }

    public function archive(Request $request, Campaign $campaign): RedirectResponse
    {
        $this->ensureOwner($request, $campaign);

        $campaign->update([
            'status' => CampaignStatus::Archived,
        ]);

        return redirect()
            ->route('campaigns.show', $campaign)
            ->with('success', 'Campaign archived. Its lead history was preserved.');
    }

    public function removePermanently(Request $request, Campaign $campaign): RedirectResponse
    {
        $this->ensureOwner($request, $campaign);

        abort_unless(
            $campaign->status === CampaignStatus::Archived,
            409,
            'Archive the campaign before permanently removing it.',
        );

        $request->validate([
            'delete_confirmation' => ['required', 'in:DELETE'],
        ]);

        $campaignName = $campaign->name;
        $campaign->delete();

        return redirect()
            ->route('campaigns.index')
            ->with('success', $campaignName.' was permanently deleted.');
    }

    private function ensureOwner(Request $request, Campaign $campaign): void
    {
        abort_unless($campaign->user_id === $request->user()->id, 404);
    }

    private function payload(SaveCampaignRequest $request): array
    {
        $validated = $request->validated();

        return [
            'name' => $validated['name'],
            'service_offer' => $validated['service_offer'],
            'target_industry' => $validated['target_industry'] ?: null,
            'target_countries' => $this->splitList($validated['target_countries'] ?? null),
            'target_cities' => $this->splitList($validated['target_cities'] ?? null),
            'qualification_rules' => [
                'minimum_score' => (int) $validated['minimum_score'],
                'requires_website' => (bool) $validated['requires_website'],
                'requires_public_contact' => (bool) $validated['requires_public_contact'],
            ],
            'daily_outreach_limit' => (int) $validated['daily_outreach_limit'],
            'review_required' => (bool) $validated['review_required'],
        ];
    }

    private function splitList(?string $value): array
    {
        if ($value === null || trim($value) === '') {
            return [];
        }

        $items = preg_split('/[\r\n,]+/', $value) ?: [];

        return array_values(array_unique(array_filter(
            array_map(static fn (string $item): string => trim($item), $items),
        )));
    }
}
