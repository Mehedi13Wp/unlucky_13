<?php

namespace Tests\Feature;

use App\Enums\CampaignStatus;
use App\Enums\LeadStatus;
use App\Models\Campaign;
use App\Models\Lead;
use App\Models\OutreachDraft;
use App\Models\User;
use App\Services\Worker\LocalWorkerManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class DashboardLiveStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_dashboard_live_status_reports_real_activity_counts(): void
    {
        $user = User::factory()->create();

        $campaign = Campaign::factory()->for($user)->create([
            'status' => CampaignStatus::Active,
            'last_run_at' => now()->subMinutes(5),
        ]);

        $lead = Lead::factory()->for($campaign)->create([
            'status' => LeadStatus::ReadyForReview,
            'analyzed_at' => now()->subMinute(),
            'qualified_at' => now()->subMinute(),
            'created_at' => now()->subMinutes(2),
        ]);

        OutreachDraft::query()->create([
            'lead_id' => $lead->id,
            'channel' => 'email',
            'recipient' => 'public@example.com',
            'subject' => 'Website idea',
            'body' => 'Public website improvement idea.',
            'status' => OutreachDraft::STATUS_PENDING_REVIEW,
            'generated_at' => now(),
        ]);

        $manager = Mockery::mock(LocalWorkerManager::class);
        $manager->shouldReceive('status')->once()->andReturn([
            'supported' => true,
            'running' => true,
            'state' => 'running',
            'pid' => 1234,
            'message' => 'Worker is running.',
            'heartbeat_at' => now()->subSeconds(3)->toISOString(),
            'heartbeat_age_seconds' => 3,
            'last_cycle_at' => now()->subMinute()->toISOString(),
            'last_cycle' => [
                'active_campaigns' => 1,
                'discovery_due' => 0,
                'discovery_completed' => 0,
                'discovery_failed' => 0,
                'pipeline_failed' => 0,
            ],
        ]);

        $this->app->instance(LocalWorkerManager::class, $manager);

        $this->actingAs($user)
            ->getJson('/dashboard/live-status')
            ->assertOk()
            ->assertJsonPath('worker.state', 'running')
            ->assertJsonPath('activity.active_campaigns', 1)
            ->assertJsonPath('activity.campaigns', 1)
            ->assertJsonPath('activity.businesses_found', 1)
            ->assertJsonPath('activity.qualified', 1)
            ->assertJsonPath('activity.review_queue', 1)
            ->assertJsonPath('activity.interested', 0)
            ->assertJsonPath('activity.new_leads_today', 1)
            ->assertJsonPath('activity.analyzed_today', 1)
            ->assertJsonPath('activity.drafts_today', 1);
    }

    public function test_dashboard_live_status_requires_authentication(): void
    {
        $this->getJson('/dashboard/live-status')->assertUnauthorized();
    }
}
