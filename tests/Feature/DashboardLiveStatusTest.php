<?php

namespace Tests\Feature;

use App\Enums\CampaignStatus;
use App\Enums\LeadStatus;
use App\Models\Campaign;
use App\Models\DeliveryAutomationSetting;
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
        $telemetryPath = storage_path('app/worker-runtime/last-discovery.json');
        @mkdir(dirname($telemetryPath), 0775, true);
        file_put_contents($telemetryPath, json_encode([
            'attempted_at' => now()->subSeconds(20)->toISOString(),
            'campaign_uuid' => null,
            'candidates_scanned' => 25,
            'fresh_created' => 4,
            'api_duplicates' => 1,
            'known_duplicates_skipped' => 12,
            'duplicates_skipped' => 13,
            'failed' => 0,
        ]));

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

        $approvedLead = Lead::factory()->for($campaign)->create([
            'status' => LeadStatus::Approved,
            'qualified_at' => now()->subMinutes(3),
            'created_at' => now()->subMinutes(3),
        ]);

        OutreachDraft::query()->create([
            'lead_id' => $approvedLead->id,
            'channel' => 'email',
            'recipient' => 'approved@example.com',
            'subject' => 'Approved idea',
            'body' => 'Human-approved outreach.',
            'status' => OutreachDraft::STATUS_APPROVED,
            'generated_at' => now(),
            'reviewed_at' => now(),
        ]);

        DeliveryAutomationSetting::query()->create([
            'user_id' => $user->id,
            'enabled' => true,
            'global_daily_limit' => 20,
            'interval_minutes' => 15,
            'session_hours' => 5,
            'session_started_at' => now(),
            'session_ends_at' => now()->addHours(5),
            'last_status' => 'active',
            'last_message' => 'Automatic delivery session started.',
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
            ->assertJsonPath('activity.campaigns', 1)
            ->assertJsonPath('activity.active_campaigns', 1)
            ->assertJsonPath('activity.draft_campaigns', 0)
            ->assertJsonPath('activity.businesses_found', 2)
            ->assertJsonPath('activity.qualified', 2)
            ->assertJsonPath('activity.qualification_rate', 100)
            ->assertJsonPath('activity.review_queue', 1)
            ->assertJsonPath('activity.interested', 0)
            ->assertJsonPath('activity.new_leads_today', 2)
            ->assertJsonPath('activity.analyzed_today', 1)
            ->assertJsonPath('activity.drafts_today', 2)
            ->assertJsonPath('activity.delivery_state', 'queued')
            ->assertJsonPath('activity.delivery_queue', 1)
            ->assertJsonPath('activity.delivery_sending', 0)
            ->assertJsonPath('activity.delivery_sent_today', 0)
            ->assertJsonPath('activity.delivery_global_daily_limit', 20)
            ->assertJsonPath('activity.discovery_fresh', 4)
            ->assertJsonPath('activity.discovery_duplicates_skipped', 13)
            ->assertJsonPath('activity.discovery_failed', 0)
            ->assertJsonPath('activity.discovery_candidates_scanned', 25);

        @unlink($telemetryPath);
    }

    public function test_dashboard_live_status_requires_authentication(): void
    {
        $this->getJson('/dashboard/live-status')->assertUnauthorized();
    }
}
