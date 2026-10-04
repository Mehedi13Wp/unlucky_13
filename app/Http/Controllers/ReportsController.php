<?php

namespace App\Http\Controllers;

use App\Enums\CampaignStatus;
use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\OutreachDraft;
use App\Models\OutreachMessage;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportsController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $today = now()->startOfDay();
        $week = now()->subDays(6)->startOfDay();

        $leadQuery = Lead::query()->whereHas(
            'campaign',
            fn ($query) => $query->where('user_id', $user->id),
        );

        $draftQuery = OutreachDraft::query()->whereHas(
            'lead.campaign',
            fn ($query) => $query->where('user_id', $user->id),
        );

        $messageQuery = OutreachMessage::query()->whereHas(
            'campaign',
            fn ($query) => $query->where('user_id', $user->id),
        );

        $summary = [
            'leads' => (clone $leadQuery)->count(),
            'qualified' => (clone $leadQuery)->whereNotNull('qualified_at')->count(),
            'review' => (clone $leadQuery)->where('status', LeadStatus::ReadyForReview->value)->count(),
            'contacted' => (clone $leadQuery)->whereNotNull('last_contacted_at')->count(),
            'interested' => (clone $leadQuery)->where('status', LeadStatus::Interested->value)->count(),
            'emails_sent' => (clone $messageQuery)->where('status', OutreachMessage::STATUS_SENT)->count(),
            'emails_failed' => (clone $messageQuery)->where('status', OutreachMessage::STATUS_FAILED)->count(),
        ];

        $summary['qualification_rate'] = $summary['leads'] > 0
            ? (int) round(($summary['qualified'] / $summary['leads']) * 100)
            : 0;

        $summary['send_rate'] = ($summary['emails_sent'] + $summary['emails_failed']) > 0
            ? (int) round(
                ($summary['emails_sent'] / ($summary['emails_sent'] + $summary['emails_failed'])) * 100
            )
            : 0;

        $activity = [
            'leads_today' => (clone $leadQuery)->where('created_at', '>=', $today)->count(),
            'analyzed_today' => (clone $leadQuery)
                ->whereNotNull('analyzed_at')
                ->where('analyzed_at', '>=', $today)
                ->count(),
            'drafts_today' => (clone $draftQuery)
                ->whereNotNull('generated_at')
                ->where('generated_at', '>=', $today)
                ->count(),
            'sent_today' => (clone $messageQuery)
                ->where('status', OutreachMessage::STATUS_SENT)
                ->where('sent_at', '>=', $today)
                ->count(),
            'leads_7d' => (clone $leadQuery)->where('created_at', '>=', $week)->count(),
            'qualified_7d' => (clone $leadQuery)
                ->whereNotNull('qualified_at')
                ->where('qualified_at', '>=', $week)
                ->count(),
            'drafts_7d' => (clone $draftQuery)
                ->whereNotNull('generated_at')
                ->where('generated_at', '>=', $week)
                ->count(),
            'sent_7d' => (clone $messageQuery)
                ->where('status', OutreachMessage::STATUS_SENT)
                ->where('sent_at', '>=', $week)
                ->count(),
        ];

        $campaignRows = $user->campaigns()
            ->where('status', '!=', CampaignStatus::Archived->value)
            ->latest()
            ->get()
            ->map(function ($campaign) {
                $leads = Lead::query()->where('campaign_id', $campaign->id);
                $total = (clone $leads)->count();
                $qualified = (clone $leads)->whereNotNull('qualified_at')->count();
                $review = (clone $leads)->where('status', LeadStatus::ReadyForReview->value)->count();
                $interested = (clone $leads)->where('status', LeadStatus::Interested->value)->count();
                $sent = OutreachMessage::query()
                    ->where('campaign_id', $campaign->id)
                    ->where('status', OutreachMessage::STATUS_SENT)
                    ->count();

                return [
                    'campaign' => $campaign,
                    'leads' => $total,
                    'qualified' => $qualified,
                    'qualification_rate' => $total > 0
                        ? (int) round(($qualified / $total) * 100)
                        : 0,
                    'review' => $review,
                    'emails_sent' => $sent,
                    'interested' => $interested,
                ];
            });

        return view('reports.index', [
            'summary' => $summary,
            'activity' => $activity,
            'campaignRows' => $campaignRows,
        ]);
    }
}
