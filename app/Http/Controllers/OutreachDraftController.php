<?php

namespace App\Http\Controllers;

use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\OutreachDraft;
use App\Services\Outreach\ApprovedEmailDeliveryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OutreachDraftController extends Controller
{
    public function approve(
        Request $request,
        Lead $lead,
        OutreachDraft $outreachDraft,
    ): RedirectResponse {
        $this->ensureOwnerAndDraft($request, $lead, $outreachDraft);

        abort_unless(
            $outreachDraft->status === OutreachDraft::STATUS_PENDING_REVIEW
                && $lead->status === LeadStatus::ReadyForReview,
            409,
        );

        $outreachDraft->update([
            'status' => OutreachDraft::STATUS_APPROVED,
            'reviewed_at' => now(),
        ]);

        $lead->update([
            'status' => LeadStatus::Approved,
        ]);

        return back()->with(
            'success',
            'Outreach draft approved. Nothing has been sent.',
        );
    }

    public function send(
        Request $request,
        Lead $lead,
        OutreachDraft $outreachDraft,
        ApprovedEmailDeliveryService $deliveryService,
    ): RedirectResponse {
        $this->ensureOwnerAndDraft($request, $lead, $outreachDraft);

        $result = $deliveryService->send($outreachDraft);

        if ($result['status'] === 'sent') {
            return back()->with(
                'success',
                'Email sent to '.$outreachDraft->recipient.'.',
            );
        }

        if ($result['status'] === 'already_sent') {
            return back()->with('success', 'This email has already been sent.');
        }

        return back()->with(
            'error',
            (string) ($result['message'] ?? 'Email could not be sent.'),
        );
    }

    public function reject(
        Request $request,
        Lead $lead,
        OutreachDraft $outreachDraft,
    ): RedirectResponse {
        $this->ensureOwnerAndDraft($request, $lead, $outreachDraft);

        abort_unless(
            $outreachDraft->status === OutreachDraft::STATUS_PENDING_REVIEW
                && $lead->status === LeadStatus::ReadyForReview,
            409,
        );

        $outreachDraft->update([
            'status' => OutreachDraft::STATUS_REJECTED,
            'reviewed_at' => now(),
        ]);

        $lead->update([
            'status' => LeadStatus::Qualified,
        ]);

        return back()->with(
            'success',
            'Outreach draft rejected. The lead was returned to qualified status.',
        );
    }

    private function ensureOwnerAndDraft(
        Request $request,
        Lead $lead,
        OutreachDraft $outreachDraft,
    ): void {
        $lead->loadMissing('campaign');

        abort_unless($lead->campaign->user_id === $request->user()->id, 404);
        abort_unless($outreachDraft->lead_id === $lead->id, 404);
    }
}
