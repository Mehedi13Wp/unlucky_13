<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Worker\LocalWorkerManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class WorkerRuntimeControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_shows_worker_start_and_stop_controls(): void
    {
        $user = User::factory()->create();

        $manager = Mockery::mock(LocalWorkerManager::class);
        $manager->shouldReceive('status')->once()->andReturn([
            'supported' => true,
            'running' => false,
            'state' => 'stopped',
            'pid' => null,
            'message' => 'Worker is stopped.',
        ]);

        $this->app->instance(LocalWorkerManager::class, $manager);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Automation worker')
            ->assertSee('STOPPED')
            ->assertSee('Start Worker')
            ->assertSee('Stop Worker');
    }

    public function test_authenticated_user_can_start_worker_from_dashboard(): void
    {
        $user = User::factory()->create();

        $manager = Mockery::mock(LocalWorkerManager::class);
        $manager->shouldReceive('start')->once()->andReturn([
            'supported' => true,
            'running' => true,
            'state' => 'running',
            'pid' => 1234,
            'message' => 'Worker started.',
        ]);

        $this->app->instance(LocalWorkerManager::class, $manager);

        $this->actingAs($user)
            ->from('/dashboard')
            ->post('/worker/start')
            ->assertRedirect('/dashboard')
            ->assertSessionHas('success');
    }

    public function test_authenticated_user_can_stop_worker_from_dashboard(): void
    {
        $user = User::factory()->create();

        $manager = Mockery::mock(LocalWorkerManager::class);
        $manager->shouldReceive('stop')->once()->andReturn([
            'supported' => true,
            'running' => false,
            'state' => 'stopped',
            'pid' => null,
            'message' => 'Worker stopped.',
        ]);

        $this->app->instance(LocalWorkerManager::class, $manager);

        $this->actingAs($user)
            ->from('/dashboard')
            ->post('/worker/stop')
            ->assertRedirect('/dashboard')
            ->assertSessionHas('success');
    }

    public function test_worker_controls_require_authentication(): void
    {
        $this->post('/worker/start')->assertRedirect('/login');
        $this->post('/worker/stop')->assertRedirect('/login');
    }
}
