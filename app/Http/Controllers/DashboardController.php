<?php

namespace App\Http\Controllers;

use App\Enums\CampaignStatus;
use App\Enums\LeadStatus;
use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $campaignQuery = $request->user()->campaigns();
        $leadQuery = Lead::query()->whereHas(
            'campaign',
            fn ($query) => $query->where('user_id', $request->user()->id),
        );

        $stats = [
            'campaigns' => (clone $campaignQuery)->count(),
            'active_campaigns' => (clone $campaignQuery)->where('status', CampaignStatus::Active)->count(),
            'leads' => (clone $leadQuery)->count(),
            'qualified_leads' => (clone $leadQuery)->where('status', LeadStatus::Qualified)->count(),
            'review_queue' => (clone $leadQuery)->where('status', LeadStatus::ReadyForReview)->count(),
            'interested_leads' => (clone $leadQuery)->where('status', LeadStatus::Interested)->count(),
        ];

        $recentCampaigns = $request->user()
            ->campaigns()
            ->withCount('leads')
            ->latest()
            ->limit(5)
            ->get();

        $recentLeads = (clone $leadQuery)
            ->with('campaign:id,name')
            ->latest()
            ->limit(6)
            ->get();

        return view('dashboard', compact('stats', 'recentCampaigns', 'recentLeads'));
    }
}
