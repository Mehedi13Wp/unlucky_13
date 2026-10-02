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
            'draft_campaigns' => (clone $campaignQuery)->where('status', CampaignStatus::Draft)->count(),
            'paused_campaigns' => (clone $campaignQuery)->where('status', CampaignStatus::Paused)->count(),
            'leads' => (clone $leadQuery)->count(),
            'qualified_leads' => (clone $leadQuery)->where('status', LeadStatus::Qualified)->count(),
            'review_queue' => (clone $leadQuery)->where('status', LeadStatus::ReadyForReview)->count(),
            'interested_leads' => (clone $leadQuery)->where('status', LeadStatus::Interested)->count(),
        ];

        $pipeline = [
            'researching' => (clone $leadQuery)
                ->whereIn('status', [
                    LeadStatus::Discovered->value,
                    LeadStatus::Analyzing->value,
                ])
                ->count(),
            'qualified' => $stats['qualified_leads'],
            'review' => $stats['review_queue'],
            'contacted' => (clone $leadQuery)
                ->whereIn('status', [
                    LeadStatus::Contacted->value,
                    LeadStatus::Replied->value,
                    LeadStatus::Interested->value,
                    LeadStatus::NotInterested->value,
                    LeadStatus::Unsubscribed->value,
                    LeadStatus::Bounced->value,
                ])
                ->count(),
            'interested' => $stats['interested_leads'],
        ];

        $rates = [
            'qualification' => $stats['leads'] > 0
                ? (int) round(($stats['qualified_leads'] / $stats['leads']) * 100)
                : 0,
            'interest' => $pipeline['contacted'] > 0
                ? (int) round(($stats['interested_leads'] / $pipeline['contacted']) * 100)
                : 0,
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

        return view('dashboard', compact(
            'stats',
            'pipeline',
            'rates',
            'recentCampaigns',
            'recentLeads',
        ));
    }
}
