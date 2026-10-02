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

class WorkerOutreachDraftApiTest extends TestCase
{
    use RefreshDatabase;

    private string $token = 'test-worker-draft-token';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('unlucky13.worker_token', $this->token);
    }

    public function test_queue_returns_qualified_contacted_research_lead_ready_for_drafting(): void
    {
        $user = User::factory()->create();
        $campaign = Campaign::factory()->for($user)->create([
            'status' => CampaignStatus::Active,
        ]);
        $lead = Lead::factory()->for($campaign)->create([
            'status' => LeadStatus::Qualified,
            'qualified_at' => now(),
            'contact_discovered_at' => now(),
        ]);
        $lead->contacts()->create([
            'type' => 'email',
            'value' => 'hello@business.test',
            'source_url' => 'https://business.test/contact',
            'discovered_at' => now(),
        ]);

        $this->withToken($this->token)
            ->getJson('/api/worker/outreach-drafts/queue')
            ->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('leads.0.uuid', $lead->uuid)
            ->assertJsonPath('leads.0.contacts.0.type', 'email');
    }

    public function test_worker_can_create_review_first_outreach_draft(): void
    {
        $user = User::factory()->create();
        $campaign = Campaign::factory()->for($user)->create([
            'status' => CampaignStatus::Active,
            'review_required' => true,
            'auto_send' => false,
        ]);
        $lead = Lead::factory()->for($campaign)->create([
            'status' => LeadStatus::Qualified,
            'qualified_at' => now(),
            'contact_discovered_at' => now(),
        ]);
        $contact = $lead->contacts()->create([
            'type' => 'email',
            'value' => 'hello@business.test',
            'source_url' => 'https://business.test/contact',
            'discovered_at' => now(),
        ]);

        $this->withToken($this->token)
            ->postJson('/api/worker/leads/'.$lead->uuid.'/outreach-drafts', [
                'contact_id' => $contact->id,
                'channel' => 'email',
                'recipient' => 'hello@business.test',
                'subject' => 'Quick website idea',
                'body' => 'A factual draft based on the public website.',
                'personalization_notes' => [
                    'Observed website signal used: sparse homepage text.',
                ],
            ])
            ->assertOk()
            ->assertJsonPath('status', 'ready_for_review')
            ->assertJsonPath('draft.status', OutreachDraft::STATUS_PENDING_REVIEW)
            ->assertJsonPath('lead.status', LeadStatus::ReadyForReview->value);

        $this->assertDatabaseHas('outreach_drafts', [
            'lead_id' => $lead->id,
            'contact_id' => $contact->id,
            'status' => OutreachDraft::STATUS_PENDING_REVIEW,
        ]);
        $this->assertSame(LeadStatus::ReadyForReview, $lead->fresh()->status);
    }

    public function test_draft_recipient_must_match_selected_public_contact(): void
    {
        $user = User::factory()->create();
        $campaign = Campaign::factory()->for($user)->create([
            'status' => CampaignStatus::Active,
        ]);
        $lead = Lead::factory()->for($campaign)->create([
            'status' => LeadStatus::Qualified,
            'contact_discovered_at' => now(),
        ]);
        $contact = $lead->contacts()->create([
            'type' => 'email',
            'value' => 'public@business.test',
            'discovered_at' => now(),
        ]);

        $this->withToken($this->token)
            ->postJson('/api/worker/leads/'.$lead->uuid.'/outreach-drafts', [
                'contact_id' => $contact->id,
                'channel' => 'email',
                'recipient' => 'guessed@business.test',
                'subject' => 'Hello',
                'body' => 'Draft',
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'recipient_mismatch');

        $this->assertDatabaseCount('outreach_drafts', 0);
    }
}
