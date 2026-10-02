<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppShellTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_app_shell_contains_responsive_navigation_controls(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Open navigation')
            ->assertSee('Close navigation')
            ->assertSee('System online')
            ->assertSee('js/unlucky13.js');
    }

    public function test_login_screen_uses_private_workspace_auth_shell(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('From research to qualified conversations.')
            ->assertSee('Public registration is disabled.');
    }
}
