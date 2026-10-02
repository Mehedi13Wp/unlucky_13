<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login_from_dashboard(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_login_screen_can_be_rendered(): void
    {
        $this->get('/login')->assertOk()->assertSee('Unlucky 13');
    }

    public function test_user_can_authenticate(): void
    {
        $user = User::factory()->create([
            'password' => 'correct-password-123',
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'correct-password-123',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('dashboard'));
    }

    public function test_user_cannot_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create([
            'password' => 'correct-password-123',
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_authenticated_user_can_log_out(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect(route('login'));
    }
}
