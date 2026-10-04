<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Worker\LocalWorkerManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class WorkerWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_worker_workspace_shows_runtime_and_log_sections(): void
    {
        $user = User::factory()->create();

        $manager = Mockery::mock(LocalWorkerManager::class);
        $manager->shouldReceive('status')->once()->andReturn([
            'supported' => true,
            'running' => true,
            'state' => 'running',
            'pid' => 4321,
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
            ->get(route('worker.index'))
            ->assertOk()
            ->assertSee('See exactly what the worker is doing.')
            ->assertSee('RUNNING')
            ->assertSee('Recent activity log')
            ->assertSee('Recent error log')
            ->assertSee('Start Worker')
            ->assertSee('Stop Worker');
    }

    public function test_worker_live_status_requires_authentication(): void
    {
        $this->getJson(route('worker.live-status'))
            ->assertUnauthorized();
    }
}
