<?php

namespace Tests\Feature;

use App\Models\SenderAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SenderAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_add_sender_account_and_password_is_encrypted_at_rest(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('sender-accounts.store'), [
                'email' => 'sender@example.com',
                'from_name' => 'Mehedi',
                'smtp_host' => 'smtp.gmail.com',
                'smtp_port' => 587,
                'smtp_scheme' => 'smtp',
                'smtp_username' => 'sender@example.com',
                'smtp_password' => 'app-password-secret',
                'daily_limit' => 20,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $account = SenderAccount::query()->firstOrFail();

        $this->assertSame('app-password-secret', $account->smtp_password);
        $this->assertNotSame('app-password-secret', $account->getRawOriginal('smtp_password'));
        $this->assertSame(20, $account->daily_limit);
        $this->assertTrue($account->enabled);
    }

    public function test_sender_pool_page_requires_authentication(): void
    {
        $this->get(route('sender-accounts.index'))
            ->assertRedirect(route('login'));
    }

    public function test_user_cannot_toggle_another_users_sender(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $sender = SenderAccount::query()->create([
            'user_id' => $other->id,
            'email' => 'other@example.com',
            'smtp_host' => 'smtp.gmail.com',
            'smtp_port' => 587,
            'smtp_scheme' => 'smtp',
            'smtp_username' => 'other@example.com',
            'smtp_password' => 'secret',
            'daily_limit' => 20,
            'enabled' => true,
        ]);

        $this->actingAs($user)
            ->patch(route('sender-accounts.toggle', $sender))
            ->assertNotFound();
    }
}
