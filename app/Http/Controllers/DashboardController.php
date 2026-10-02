<?php

namespace App\Http\Controllers;

use App\Enums\CampaignStatus;
use App\Enums\LeadStatus;
use App\Models\Campaign;
use App\Models\Lead;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $stats = [
            'campaigns' => Campaign::count(),
            'active_campaigns' => Campaign::where('status', CampaignStatus::Active)->count(),
            'leads' => Lead::count(),
            'qualified_leads' => Lead::where('status', LeadStatus::Qualified)->count(),
            'review_queue' => Lead::where('status', LeadStatus::ReadyForReview)->count(),
            'interested_leads' => Lead::where('status', LeadStatus::Interested)->count(),
        ];

        $recentCampaigns = Campaign::query()
            ->withCount('leads')
            ->latest()
            ->limit(5)
            ->get();

        $recentLeads = Lead::query()
            ->with('campaign:id,name')
            ->latest()
            ->limit(6)
            ->get();

        return view('dashboard', compact('stats', 'recentCampaigns', 'recentLeads'));
    }
}
