<?php

namespace App\Http\Controllers;

use App\Enums\CampaignStatus;
use App\Models\OutreachDraft;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReviewQueueController extends Controller
{
    public function __invoke(Request $request): View
    {
        $userId = $request->user()->id;

        $ownedActionable = OutreachDraft::query()
            ->whereIn('status', [
                OutreachDraft::STATUS_PENDING_REVIEW,
                OutreachDraft::STATUS_APPROVED,
            ])
            ->whereHas(
                'lead.campaign',
                fn ($query) => $query->where('user_id', $userId),
            );

        $queueQuery = clone $ownedActionable;

        if ($request->filled('campaign')) {
            $campaignUuid = (string) $request->string('campaign');

            $queueQuery->whereHas(
                'lead.campaign',
                fn ($query) => $query
                    ->where('user_id', $userId)
                    ->where('uuid', $campaignUuid),
            );
        }

        if ($request->filled('channel')) {
            $channel = (string) $request->string('channel');

            if (in_array($channel, ['email', 'contact_form'], true)) {
                $queueQuery->where('channel', $channel);
            }
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->string('search'));

            $queueQuery->where(function ($query) use ($search): void {
                $query
                    ->where('recipient', 'like', '%'.$search.'%')
                    ->orWhere('subject', 'like', '%'.$search.'%')
                    ->orWhereHas('lead', function ($leadQuery) use ($search): void {
                        $leadQuery
                            ->where('business_name', 'like', '%'.$search.'%')
                            ->orWhere('domain', 'like', '%'.$search.'%');
                    });
            });
        }

        $drafts = $queueQuery
            ->with([
                'lead.campaign:id,uuid,user_id,name,status,daily_outreach_limit',
                'message',
            ])
            ->latest('generated_at')
            ->latest('id')
            ->paginate(12)
            ->withQueryString();

        $campaigns = $request->user()
            ->campaigns()
            ->where('status', '!=', CampaignStatus::Archived->value)
            ->orderBy('name')
            ->get(['id', 'uuid', 'name', 'status']);

        $stats = [
            'pending' => (clone $ownedActionable)
                ->where('status', OutreachDraft::STATUS_PENDING_REVIEW)
                ->count(),
            'approved' => (clone $ownedActionable)
                ->where('status', OutreachDraft::STATUS_APPROVED)
                ->count(),
            'email_ready' => (clone $ownedActionable)
                ->where('status', OutreachDraft::STATUS_APPROVED)
                ->where('channel', 'email')
                ->count(),
            'total' => (clone $ownedActionable)->count(),
        ];

        return view('review-queue.index', [
            'drafts' => $drafts,
            'campaigns' => $campaigns,
            'stats' => $stats,
        ]);
    }
}
