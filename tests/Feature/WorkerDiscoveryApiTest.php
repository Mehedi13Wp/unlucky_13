<?php

namespace Tests\Feature;

use App\Enums\CampaignStatus;
use App\Enums\LeadStatus;
use App\Models\Campaign;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkerDiscoveryApiTest extends TestCase
{
    use RefreshDatabase;

    private string $token = 'test-worker-token-with-enough-entropy';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('unlucky13.worker_token', $this->token);
    }

    public function test_worker_health_requires_valid_bearer_token(): void
    {
        $this->getJson('/api/worker/health')
            ->assertUnauthorized()
            ->assertJson([
                'message' => 'Invalid worker API token.',
            ]);

        $this->withToken($this->token)
            ->getJson('/api/worker/health')
            ->assertOk()
            ->assertJson([
                'status' => 'ok',
                'service' => 'unlucky13-worker-api',
            ]);
    }

    public function test_worker_can_list_only_active_campaigns(): void
    {
        $user = User::factory()->create();

        $active = Campaign::factory()->for($user)->create([
            'name' => 'Active Restaurant Campaign',
            'status' => CampaignStatus::Active,
            'target_industry' => 'Restaurants',
        ]);

        Campaign::factory()->for($user)->create([
            'name' => 'Paused Campaign',
            'status' => CampaignStatus::Paused,
        ]);

        $this->withToken($this->token)
            ->getJson('/api/worker/campaigns')
            ->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('campaigns.0.uuid', $active->uuid)
            ->assertJsonPath('campaigns.0.name', 'Active Restaurant Campaign')
            ->assertJsonPath('campaigns.0.status', 'active')
            ->assertJsonMissing([
                'name' => 'Paused Campaign',
            ]);
    }

    public function test_worker_can_record_successful_run_for_active_campaign(): void
    {
        $user = User::factory()->create();
        $campaign = Campaign::factory()->for($user)->create([
            'status' => CampaignStatus::Active,
            'last_run_at' => null,
        ]);

        $this->withToken($this->token)
            ->postJson('/api/worker/campaigns/'.$campaign->uuid.'/run-complete')
            ->assertOk()
            ->assertJsonPath('status', 'recorded')
            ->assertJsonPath('campaign_uuid', $campaign->uuid);

        $this->assertNotNull($campaign->fresh()->last_run_at);
    }

    public function test_paused_campaign_cannot_record_worker_run(): void
    {
        $user = User::factory()->create();
        $campaign = Campaign::factory()->for($user)->create([
            'status' => CampaignStatus::Paused,
            'last_run_at' => null,
        ]);

        $this->withToken($this->token)
            ->postJson('/api/worker/campaigns/'.$campaign->uuid.'/run-complete')
            ->assertStatus(409)
            ->assertJsonPath('code', 'campaign_not_active');

        $this->assertNull($campaign->fresh()->last_run_at);
    }

    public function test_worker_can_ingest_lead_into_active_campaign(): void
    {
        $user = User::factory()->create();
        $campaign = Campaign::factory()->for($user)->create([
            'status' => CampaignStatus::Active,
            'target_industry' => 'Restaurants',
        ]);

        $response = $this->withToken($this->token)
            ->postJson('/api/worker/campaigns/'.$campaign->uuid.'/leads', [
                'business_name' => 'Example Restaurant',
                'website_url' => 'WWW.Example.com/menu',
                'country' => 'United States',
                'city' => 'Boston',
                'source' => 'worker',
                'observed_signals' => [
                    'No direct booking flow',
                    'Public contact page',
                ],
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('status', 'created')
            ->assertJsonPath('lead.domain', 'example.com')
            ->assertJsonPath('lead.campaign_uuid', $campaign->uuid);

        $lead = Lead::where('domain', 'example.com')->firstOrFail();

        $this->assertSame(LeadStatus::Discovered, $lead->status);
        $this->assertSame('Restaurants', $lead->industry);
        $this->assertSame([
            'No direct booking flow',
            'Public contact page',
        ], $lead->observed_signals);
    }

    public function test_worker_ingestion_is_idempotent_by_domain_inside_campaign(): void
    {
        $user = User::factory()->create();
        $campaign = Campaign::factory()->for($user)->create([
            'status' => CampaignStatus::Active,
        ]);

        $existing = Lead::factory()->for($campaign)->create([
            'domain' => 'example.com',
            'website_url' => 'https://example.com',
        ]);

        $this->withToken($this->token)
            ->postJson('/api/worker/campaigns/'.$campaign->uuid.'/leads', [
                'business_name' => 'Duplicate Example',
                'website_url' => 'https://www.example.com/contact',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'duplicate')
            ->assertJsonPath('lead.uuid', $existing->uuid);

        $this->assertSame(1, $campaign->leads()->where('domain', 'example.com')->count());
    }

    public function test_worker_ingestion_is_globally_idempotent_by_domain_across_campaigns(): void
    {
        $user = User::factory()->create();

        $firstCampaign = Campaign::factory()->for($user)->create([
            'status' => CampaignStatus::Active,
        ]);

        $secondCampaign = Campaign::factory()->for($user)->create([
            'status' => CampaignStatus::Active,
        ]);

        $existing = Lead::factory()->for($firstCampaign)->create([
            'domain' => 'shared-example.com',
            'website_url' => 'https://shared-example.com',
        ]);

        $this->withToken($this->token)
            ->postJson('/api/worker/campaigns/'.$secondCampaign->uuid.'/leads', [
                'business_name' => 'Same Website From Another Campaign',
                'website_url' => 'https://www.shared-example.com/contact',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'duplicate')
            ->assertJsonPath('duplicate_scope', 'global_domain')
            ->assertJsonPath('lead.uuid', $existing->uuid)
            ->assertJsonPath('lead.campaign_uuid', $firstCampaign->uuid);

        $this->assertSame(0, $secondCampaign->leads()->where('domain', 'shared-example.com')->count());

        $this->assertDatabaseHas('discovery_domains', [
            'domain' => 'shared-example.com',
            'first_lead_id' => $existing->id,
            'first_campaign_id' => $firstCampaign->id,
        ]);
    }

    public function test_worker_can_check_known_domains_before_ingestion(): void
    {
        $user = User::factory()->create();
        $campaign = Campaign::factory()->for($user)->create();

        Lead::factory()->for($campaign)->create([
            'domain' => 'known-example.com',
            'website_url' => 'https://known-example.com',
        ]);

        $this->withToken($this->token)
            ->postJson('/api/worker/discovery/known-domains', [
                'domains' => [
                    'www.known-example.com',
                    'new-example.com',
                ],
            ])
            ->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('domains.0', 'known-example.com');
    }

    public function test_non_active_campaign_rejects_worker_discovery(): void
    {
        $user = User::factory()->create();
        $campaign = Campaign::factory()->for($user)->create([
            'status' => CampaignStatus::Paused,
        ]);

        $this->withToken($this->token)
            ->postJson('/api/worker/campaigns/'.$campaign->uuid.'/leads', [
                'business_name' => 'Blocked Business',
                'website_url' => 'blocked.example',
            ])
            ->assertStatus(409)
            ->assertJsonPath('code', 'campaign_not_active');

        $this->assertDatabaseMissing('leads', [
            'business_name' => 'Blocked Business',
        ]);
    }

    public function test_invalid_domain_is_rejected(): void
    {
        $user = User::factory()->create();
        $campaign = Campaign::factory()->for($user)->create([
            'status' => CampaignStatus::Active,
        ]);

        $this->withToken($this->token)
            ->postJson('/api/worker/campaigns/'.$campaign->uuid.'/leads', [
                'business_name' => 'Invalid Domain Business',
                'website_url' => 'not a valid website',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'invalid_domain');
    }
}
