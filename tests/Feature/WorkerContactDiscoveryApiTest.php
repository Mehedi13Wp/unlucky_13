<?php

namespace Tests\Feature;

use App\Enums\CampaignStatus;
use App\Enums\LeadStatus;
use App\Models\Campaign;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkerContactDiscoveryApiTest extends TestCase
{
    use RefreshDatabase;

    private string $token = 'test-worker-contact-token';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('unlucky13.worker_token', $this->token);
    }

    public function test_contact_queue_returns_only_unprocessed_qualified_leads_from_active_campaigns(): void
    {
        $user = User::factory()->create();

        $active = Campaign::factory()->for($user)->create([
            'status' => CampaignStatus::Active,
        ]);
        $paused = Campaign::factory()->for($user)->create([
            'status' => CampaignStatus::Paused,
        ]);

        $qualified = Lead::factory()->for($active)->create([
            'business_name' => 'Qualified Business',
            'status' => LeadStatus::Qualified,
            'qualified_at' => now(),
        ]);

        Lead::factory()->for($active)->create([
            'business_name' => 'Rejected Business',
            'status' => LeadStatus::Rejected,
        ]);

        Lead::factory()->for($paused)->create([
            'business_name' => 'Paused Qualified Business',
            'status' => LeadStatus::Qualified,
            'qualified_at' => now(),
        ]);

        Lead::factory()->for($active)->create([
            'business_name' => 'Already Checked Business',
            'status' => LeadStatus::Qualified,
            'qualified_at' => now(),
            'contact_discovered_at' => now(),
            'contact_discovery_version' => 2,
        ]);

        $this->withToken($this->token)
            ->getJson('/api/worker/contact-discovery/queue')
            ->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('leads.0.uuid', $qualified->uuid)
            ->assertJsonPath('leads.0.business_name', 'Qualified Business');
    }

    public function test_legacy_review_ready_lead_is_queued_once_for_smart_contact_upgrade(): void
    {
        $user = User::factory()->create();
        $campaign = Campaign::factory()->for($user)->create([
            'status' => CampaignStatus::Active,
        ]);
        $lead = Lead::factory()->for($campaign)->create([
            'status' => LeadStatus::ReadyForReview,
            'qualified_at' => now(),
            'contact_discovered_at' => now()->subHour(),
            'contact_discovery_version' => 0,
        ]);

        $this->withToken($this->token)
            ->getJson('/api/worker/contact-discovery/queue')
            ->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('leads.0.uuid', $lead->uuid)
            ->assertJsonPath('leads.0.contact_discovery_version', 0);
    }

    public function test_worker_can_store_public_contacts_for_qualified_lead(): void
    {
        $user = User::factory()->create();
        $campaign = Campaign::factory()->for($user)->create([
            'status' => CampaignStatus::Active,
        ]);
        $lead = Lead::factory()->for($campaign)->create([
            'status' => LeadStatus::Qualified,
            'qualified_at' => now(),
        ]);

        $response = $this->withToken($this->token)
            ->postJson('/api/worker/leads/'.$lead->uuid.'/contacts/complete', [
                'contacts' => [
                    [
                        'type' => 'email',
                        'value' => 'hello@example-business.test',
                        'label' => 'Public email',
                        'source_url' => 'https://example-business.test/contact',
                    ],
                    [
                        'type' => 'contact_page',
                        'value' => 'https://example-business.test/contact',
                        'label' => 'Public contact page',
                        'source_url' => 'https://example-business.test',
                    ],
                    [
                        'type' => 'facebook',
                        'value' => 'https://www.facebook.com/examplebusiness',
                        'label' => 'Official Facebook',
                        'source_url' => 'https://example-business.test',
                    ],
                    [
                        'type' => 'instagram',
                        'value' => 'https://www.instagram.com/examplebusiness',
                        'label' => 'Official Instagram',
                        'source_url' => 'https://example-business.test',
                    ],
                    [
                        'type' => 'linkedin',
                        'value' => 'https://www.linkedin.com/company/examplebusiness',
                        'label' => 'Official Linkedin',
                        'source_url' => 'https://example-business.test',
                    ],
                ],
                'notes' => [
                    'Inspected 2 public pages on the lead website.',
                ],
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('status', 'completed')
            ->assertJsonPath('contact_count', 5)
            ->assertJsonPath('lead.status', 'qualified');

        $this->assertDatabaseHas('contacts', [
            'lead_id' => $lead->id,
            'type' => 'email',
            'value' => 'hello@example-business.test',
        ]);

        $this->assertDatabaseHas('contacts', [
            'lead_id' => $lead->id,
            'type' => 'facebook',
            'value' => 'https://www.facebook.com/examplebusiness',
        ]);

        $this->assertDatabaseHas('contacts', [
            'lead_id' => $lead->id,
            'type' => 'instagram',
            'value' => 'https://www.instagram.com/examplebusiness',
        ]);

        $this->assertDatabaseHas('contacts', [
            'lead_id' => $lead->id,
            'type' => 'linkedin',
            'value' => 'https://www.linkedin.com/company/examplebusiness',
        ]);

        $this->assertNotNull($lead->fresh()->contact_discovered_at);
        $this->assertSame(2, $lead->fresh()->contact_discovery_version);
        $this->assertSame(LeadStatus::Qualified, $lead->fresh()->status);
    }

    public function test_qualified_lead_can_complete_contact_discovery_with_no_public_contacts(): void
    {
        $user = User::factory()->create();
        $campaign = Campaign::factory()->for($user)->create([
            'status' => CampaignStatus::Active,
        ]);
        $lead = Lead::factory()->for($campaign)->create([
            'status' => LeadStatus::Qualified,
            'qualified_at' => now(),
        ]);

        $this->withToken($this->token)
            ->postJson('/api/worker/leads/'.$lead->uuid.'/contacts/complete', [
                'contacts' => [],
                'notes' => [
                    'No public contact path found on inspected pages.',
                ],
            ])
            ->assertOk()
            ->assertJsonPath('status', 'completed')
            ->assertJsonPath('contact_count', 0);

        $this->assertNotNull($lead->fresh()->contact_discovered_at);
    }

    public function test_non_qualified_lead_cannot_receive_worker_contacts(): void
    {
        $user = User::factory()->create();
        $campaign = Campaign::factory()->for($user)->create([
            'status' => CampaignStatus::Active,
        ]);
        $lead = Lead::factory()->for($campaign)->create([
            'status' => LeadStatus::Rejected,
        ]);

        $this->withToken($this->token)
            ->postJson('/api/worker/leads/'.$lead->uuid.'/contacts/complete', [
                'contacts' => [],
            ])
            ->assertStatus(409)
            ->assertJsonPath('code', 'lead_not_qualified');
    }
}
