<?php

namespace Tests\Feature;

use App\Enums\CampaignStatus;
use App\Enums\LeadStatus;
use App\Models\Campaign;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkerAnalysisApiTest extends TestCase
{
    use RefreshDatabase;

    private string $token = 'test-worker-analysis-token';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('unlucky13.worker_token', $this->token);
    }

    public function test_queue_returns_only_pending_leads_from_active_campaigns(): void
    {
        $user = User::factory()->create();

        $active = Campaign::factory()->for($user)->create([
            'status' => CampaignStatus::Active,
        ]);
        $paused = Campaign::factory()->for($user)->create([
            'status' => CampaignStatus::Paused,
        ]);

        $pending = Lead::factory()->for($active)->create([
            'business_name' => 'Pending Lead',
            'status' => LeadStatus::Discovered,
        ]);
        Lead::factory()->for($active)->create([
            'business_name' => 'Finished Lead',
            'status' => LeadStatus::Qualified,
        ]);
        Lead::factory()->for($paused)->create([
            'business_name' => 'Paused Campaign Lead',
            'status' => LeadStatus::Discovered,
        ]);

        $this->withToken($this->token)
            ->getJson('/api/worker/analysis/queue')
            ->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('leads.0.uuid', $pending->uuid)
            ->assertJsonPath('leads.0.business_name', 'Pending Lead');
    }

    public function test_worker_can_start_and_complete_qualified_analysis(): void
    {
        $user = User::factory()->create();
        $campaign = Campaign::factory()->for($user)->create([
            'status' => CampaignStatus::Active,
            'qualification_rules' => [
                'minimum_score' => 70,
                'requires_website' => true,
                'requires_public_contact' => true,
            ],
        ]);
        $lead = Lead::factory()->for($campaign)->create([
            'status' => LeadStatus::Discovered,
            'observed_signals' => ['Public website available'],
        ]);

        $this->withToken($this->token)
            ->postJson('/api/worker/leads/'.$lead->uuid.'/analysis/start')
            ->assertOk()
            ->assertJsonPath('status', 'analyzing');

        $this->assertSame(LeadStatus::Analyzing, $lead->fresh()->status);

        $this->withToken($this->token)
            ->postJson('/api/worker/leads/'.$lead->uuid.'/analysis/complete', [
                'qualification_score' => 82,
                'qualification_reason' => 'Target fit is supported by observed public business signals.',
                'observed_signals' => [
                    'Public website available',
                    'Service gap observed on website',
                ],
            ])
            ->assertOk()
            ->assertJsonPath('status', 'completed')
            ->assertJsonPath('decision', 'qualified')
            ->assertJsonPath('minimum_score', 70);

        $lead->refresh();

        $this->assertSame(LeadStatus::Qualified, $lead->status);
        $this->assertSame(82, $lead->qualification_score);
        $this->assertNotNull($lead->analyzed_at);
        $this->assertNotNull($lead->qualified_at);
        $this->assertSame([
            'Public website available',
            'Service gap observed on website',
        ], $lead->observed_signals);
    }

    public function test_score_below_campaign_threshold_is_rejected(): void
    {
        $user = User::factory()->create();
        $campaign = Campaign::factory()->for($user)->create([
            'status' => CampaignStatus::Active,
            'qualification_rules' => [
                'minimum_score' => 75,
                'requires_website' => true,
                'requires_public_contact' => false,
            ],
        ]);
        $lead = Lead::factory()->for($campaign)->create([
            'status' => LeadStatus::Analyzing,
        ]);

        $this->withToken($this->token)
            ->postJson('/api/worker/leads/'.$lead->uuid.'/analysis/complete', [
                'qualification_score' => 60,
                'qualification_reason' => 'Observed evidence does not meet the campaign threshold.',
                'observed_signals' => ['Public website available'],
            ])
            ->assertOk()
            ->assertJsonPath('decision', 'rejected');

        $lead->refresh();

        $this->assertSame(LeadStatus::Rejected, $lead->status);
        $this->assertNull($lead->qualified_at);
        $this->assertNotNull($lead->analyzed_at);
    }

    public function test_analysis_completion_requires_start_state(): void
    {
        $user = User::factory()->create();
        $campaign = Campaign::factory()->for($user)->create([
            'status' => CampaignStatus::Active,
        ]);
        $lead = Lead::factory()->for($campaign)->create([
            'status' => LeadStatus::Discovered,
        ]);

        $this->withToken($this->token)
            ->postJson('/api/worker/leads/'.$lead->uuid.'/analysis/complete', [
                'qualification_score' => 80,
                'qualification_reason' => 'Should not be accepted before start.',
            ])
            ->assertStatus(409)
            ->assertJsonPath('code', 'analysis_not_started');
    }
}
