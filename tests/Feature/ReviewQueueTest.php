<?php

namespace Tests\Feature;

use App\Enums\CampaignStatus;
use App\Enums\LeadStatus;
use App\Models\Campaign;
use App\Models\Lead;
use App\Models\OutreachDraft;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_review_queue_shows_only_the_authenticated_users_actionable_drafts(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $campaign = Campaign::factory()->for($user)->create([
            'name' => 'Real Estate Campaign',
            'status' => CampaignStatus::Active,
        ]);

        $pendingLead = Lead::factory()->for($campaign)->create([
            'business_name' => 'Own Pending Business',
            'status' => LeadStatus::ReadyForReview,
            'qualification_score' => 82,
        ]);

        $pendingLead->outreachDrafts()->create([
            'channel' => 'email',
            'recipient' => 'hello@pending.test',
            'subject' => 'Website opportunity',
            'body' => 'Pending review body',
            'status' => OutreachDraft::STATUS_PENDING_REVIEW,
            'generated_at' => now(),
        ]);

        $approvedLead = Lead::factory()->for($campaign)->create([
            'business_name' => 'Own Approved Business',
            'status' => LeadStatus::Approved,
            'qualification_score' => 91,
        ]);

        $approvedLead->outreachDrafts()->create([
            'channel' => 'email',
            'recipient' => 'hello@approved.test',
            'subject' => 'Approved website idea',
            'body' => 'Approved body',
            'status' => OutreachDraft::STATUS_APPROVED,
            'reviewed_at' => now(),
            'generated_at' => now(),
        ]);

        $otherCampaign = Campaign::factory()->for($otherUser)->create([
            'status' => CampaignStatus::Active,
        ]);

        $otherLead = Lead::factory()->for($otherCampaign)->create([
            'business_name' => 'Other Users Business',
            'status' => LeadStatus::ReadyForReview,
        ]);

        $otherLead->outreachDrafts()->create([
            'channel' => 'email',
            'recipient' => 'other@example.test',
            'subject' => 'Other',
            'body' => 'Other body',
            'status' => OutreachDraft::STATUS_PENDING_REVIEW,
            'generated_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('review-queue.index'))
            ->assertOk()
            ->assertSee('Review outreach before it leaves your account')
            ->assertSee('Own Pending Business')
            ->assertSee('Own Approved Business')
            ->assertSee('Approve')
            ->assertSee('Reject')
            ->assertSee('Send Email')
            ->assertDontSee('Other Users Business')
            ->assertDontSee('other@example.test');
    }

    public function test_review_queue_can_filter_by_campaign_and_channel(): void
    {
        $user = User::factory()->create();

        $emailCampaign = Campaign::factory()->for($user)->create([
            'name' => 'Email Campaign',
            'status' => CampaignStatus::Active,
        ]);

        $formCampaign = Campaign::factory()->for($user)->create([
            'name' => 'Form Campaign',
            'status' => CampaignStatus::Active,
        ]);

        $emailLead = Lead::factory()->for($emailCampaign)->create([
            'business_name' => 'Email Business',
            'status' => LeadStatus::ReadyForReview,
        ]);

        $emailLead->outreachDrafts()->create([
            'channel' => 'email',
            'recipient' => 'email@example.test',
            'body' => 'Email body',
            'status' => OutreachDraft::STATUS_PENDING_REVIEW,
            'generated_at' => now(),
        ]);

        $formLead = Lead::factory()->for($formCampaign)->create([
            'business_name' => 'Form Business',
            'status' => LeadStatus::ReadyForReview,
        ]);

        $formLead->outreachDrafts()->create([
            'channel' => 'contact_form',
            'recipient' => 'https://form.test/contact',
            'body' => 'Form body',
            'status' => OutreachDraft::STATUS_PENDING_REVIEW,
            'generated_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('review-queue.index', [
                'campaign' => $emailCampaign->uuid,
                'channel' => 'email',
            ]))
            ->assertOk()
            ->assertSee('Email Business')
            ->assertDontSee('Form Business');
    }

    public function test_review_queue_requires_authentication(): void
    {
        $this->get(route('review-queue.index'))
            ->assertRedirect(route('login'));
    }
}
