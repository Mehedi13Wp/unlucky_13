<?php

namespace Tests\Feature;

use App\Models\SenderAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
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
        $this->assertNull($account->smtp_verified_at);
    }

    public function test_sender_accounts_page_requires_authentication(): void
    {
        $this->get(route('sender-accounts.index'))
            ->assertRedirect(route('login'));
    }

    public function test_user_cannot_toggle_another_users_sender(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $sender = $this->sender($other, 'other@example.com');

        $this->actingAs($user)
            ->patch(route('sender-accounts.toggle', $sender))
            ->assertNotFound();
    }

    public function test_user_cannot_verify_another_users_sender(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $other = User::factory()->create();

        $sender = $this->sender($other, 'other@example.com');

        $this->actingAs($user)
            ->post(route('sender-accounts.verify', $sender))
            ->assertNotFound();

        $this->assertNull($sender->fresh()->smtp_verified_at);
    }

    public function test_sender_can_be_verified_by_sending_a_self_test_message(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $sender = $this->sender($user, 'sender@example.com');

        $this->actingAs($user)
            ->post(route('sender-accounts.verify', $sender))
            ->assertRedirect()
            ->assertSessionHas('success');

        $sender->refresh();

        $this->assertNotNull($sender->smtp_verified_at);
        $this->assertNotNull($sender->smtp_verification_attempted_at);
        $this->assertNull($sender->smtp_verification_error);
    }

    public function test_updating_sender_credentials_clears_previous_verification(): void
    {
        $user = User::factory()->create();
        $sender = $this->sender($user, 'sender@example.com', true);

        $this->assertNotNull($sender->smtp_verified_at);

        $this->actingAs($user)
            ->post(route('sender-accounts.store'), [
                'email' => 'sender@example.com',
                'from_name' => 'Updated Sender',
                'smtp_host' => 'smtp.gmail.com',
                'smtp_port' => 587,
                'smtp_scheme' => 'smtp',
                'smtp_username' => 'sender@example.com',
                'smtp_password' => 'new-app-password',
                'daily_limit' => 10,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $sender->refresh();

        $this->assertSame('new-app-password', $sender->smtp_password);
        $this->assertNull($sender->smtp_verified_at);
        $this->assertNull($sender->smtp_verification_error);
    }

    public function test_csv_import_reports_invalid_rows_and_never_verifies_imported_credentials(): void
    {
        $user = User::factory()->create();

        $csv = implode("\n", [
            'email,from_name,smtp_host,smtp_port,smtp_scheme,smtp_username,smtp_password,daily_limit,enabled',
            'good@example.com,Good Sender,smtp.gmail.com,587,smtp,good@example.com,secret,5,1',
            'not-an-email,Bad Sender,smtp.gmail.com,587,smtp,bad@example.com,secret,5,1',
        ]);

        $file = UploadedFile::fake()->createWithContent('senders.csv', $csv);

        $this->actingAs($user)
            ->post(route('sender-accounts.import'), ['csv' => $file])
            ->assertRedirect()
            ->assertSessionHas('success')
            ->assertSessionHas('import_errors', function (array $errors): bool {
                return count($errors) === 1
                    && $errors[0]['row'] === 3
                    && $errors[0]['email'] === 'not-an-email';
            });

        $this->assertDatabaseCount('sender_accounts', 1);
        $this->assertNull(SenderAccount::query()->firstOrFail()->smtp_verified_at);
    }

    private function sender(
        User $user,
        string $email,
        bool $verified = false,
    ): SenderAccount {
        return SenderAccount::query()->create([
            'user_id' => $user->id,
            'email' => $email,
            'from_name' => 'Mehedi',
            'smtp_host' => 'smtp.gmail.com',
            'smtp_port' => 587,
            'smtp_scheme' => 'smtp',
            'smtp_username' => $email,
            'smtp_password' => 'secret',
            'daily_limit' => 20,
            'enabled' => true,
            'smtp_verified_at' => $verified ? now() : null,
        ]);
    }
}
