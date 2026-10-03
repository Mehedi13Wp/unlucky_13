<?php

namespace Tests\Feature;

use App\Enums\LeadStatus;
use App\Models\Campaign;
use App\Models\Lead;
use App\Models\OutreachDraft;
use App\Models\OutreachMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
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

    public function test_owner_can_send_an_approved_email_from_the_lead_page(): void
    {
        Mail::fake();
        config()->set('mail.default', 'smtp');

        $user = User::factory()->create();
        $campaign = Campaign::factory()->for($user)->create([
            'status' => \App\Enums\CampaignStatus::Active,
            'daily_outreach_limit' => 10,
        ]);
        $lead = Lead::factory()->for($campaign)->create([
            'status' => LeadStatus::Approved,
        ]);
        $draft = $lead->outreachDrafts()->create([
            'channel' => 'email',
            'recipient' => 'hello@business.test',
            'subject' => 'Quick website idea',
            'body' => 'Approved body',
            'status' => OutreachDraft::STATUS_APPROVED,
            'reviewed_at' => now(),
        ]);

        $this->actingAs($user)
            ->post(route('outreach-drafts.send', [$lead, $draft]))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(OutreachDraft::STATUS_SENT, $draft->fresh()->status);
        $this->assertSame(LeadStatus::Contacted, $lead->fresh()->status);
        $this->assertNotNull($lead->fresh()->last_contacted_at);
        $this->assertDatabaseHas('outreach_messages', [
            'outreach_draft_id' => $draft->id,
            'status' => OutreachMessage::STATUS_SENT,
        ]);
    }

    public function test_contact_form_draft_cannot_use_send_email_button_endpoint(): void
    {
        config()->set('mail.default', 'smtp');

        $user = User::factory()->create();
        $campaign = Campaign::factory()->for($user)->create([
            'status' => \App\Enums\CampaignStatus::Active,
            'daily_outreach_limit' => 10,
        ]);
        $lead = Lead::factory()->for($campaign)->create([
            'status' => LeadStatus::Approved,
        ]);
        $draft = $lead->outreachDrafts()->create([
            'channel' => 'contact_form',
            'recipient' => 'https://business.test/contact',
            'body' => 'Approved form body',
            'status' => OutreachDraft::STATUS_APPROVED,
            'reviewed_at' => now(),
        ]);

        $this->actingAs($user)
            ->post(route('outreach-drafts.send', [$lead, $draft]))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(OutreachDraft::STATUS_APPROVED, $draft->fresh()->status);
        $this->assertSame(LeadStatus::Approved, $lead->fresh()->status);
        $this->assertDatabaseCount('outreach_messages', 0);
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
