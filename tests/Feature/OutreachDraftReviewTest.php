<?php

namespace Tests\Feature;

use App\Enums\LeadStatus;
use App\Models\Campaign;
use App\Models\Lead;
use App\Models\OutreachDraft;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OutreachDraftReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_approve_pending_draft_without_sending_it(): void
    {
        $user = User::factory()->create();
        $campaign = Campaign::factory()->for($user)->create();
        $lead = Lead::factory()->for($campaign)->create([
            'status' => LeadStatus::ReadyForReview,
        ]);
        $draft = $lead->outreachDrafts()->create([
            'channel' => 'contact_form',
            'recipient' => 'https://business.test/contact',
            'body' => 'Draft body',
            'status' => OutreachDraft::STATUS_PENDING_REVIEW,
            'generated_at' => now(),
        ]);

        $this->actingAs($user)
            ->patch(route('outreach-drafts.approve', [$lead, $draft]))
            ->assertRedirect();

        $this->assertSame(OutreachDraft::STATUS_APPROVED, $draft->fresh()->status);
        $this->assertSame(LeadStatus::Approved, $lead->fresh()->status);
        $this->assertNull($lead->fresh()->last_contacted_at);
    }

    public function test_owner_can_reject_pending_draft_and_return_lead_to_qualified(): void
    {
        $user = User::factory()->create();
        $campaign = Campaign::factory()->for($user)->create();
        $lead = Lead::factory()->for($campaign)->create([
            'status' => LeadStatus::ReadyForReview,
        ]);
        $draft = $lead->outreachDrafts()->create([
            'channel' => 'contact_form',
            'recipient' => 'https://business.test/contact',
            'body' => 'Draft body',
            'status' => OutreachDraft::STATUS_PENDING_REVIEW,
            'generated_at' => now(),
        ]);

        $this->actingAs($user)
            ->patch(route('outreach-drafts.reject', [$lead, $draft]))
            ->assertRedirect();

        $this->assertSame(OutreachDraft::STATUS_REJECTED, $draft->fresh()->status);
        $this->assertSame(LeadStatus::Qualified, $lead->fresh()->status);
    }

    public function test_user_cannot_review_another_users_draft(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $campaign = Campaign::factory()->for($otherUser)->create();
        $lead = Lead::factory()->for($campaign)->create([
            'status' => LeadStatus::ReadyForReview,
        ]);
        $draft = $lead->outreachDrafts()->create([
            'channel' => 'contact_form',
            'recipient' => 'https://business.test/contact',
            'body' => 'Draft body',
            'status' => OutreachDraft::STATUS_PENDING_REVIEW,
        ]);

        $this->actingAs($user)
            ->patch(route('outreach-drafts.approve', [$lead, $draft]))
            ->assertNotFound();
    }
}
