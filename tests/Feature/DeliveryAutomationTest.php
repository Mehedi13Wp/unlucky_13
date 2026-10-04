<?php

namespace Tests\Feature;

use App\Enums\CampaignStatus;
use App\Enums\LeadStatus;
use App\Models\Campaign;
use App\Models\DeliveryAutomationSetting;
use App\Models\Lead;
use App\Models\OutreachDraft;
use App\Models\OutreachMessage;
use App\Models\SenderAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class DeliveryAutomationTest extends TestCase
{
    use RefreshDatabase;

    private string $token = 'delivery-automation-test-token';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('unlucky13.worker_token', $this->token);
    }

    public function test_user_can_start_a_controlled_five_hour_session(): void
    {
        $user = User::factory()->create();
        $this->sender($user);

        $this->actingAs($user)
            ->post(route('sender-accounts.automation.start'), [
                'global_daily_limit' => 20,
                'interval_minutes' => 15,
                'session_hours' => 5,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $setting = DeliveryAutomationSetting::query()->firstOrFail();

        $this->assertTrue($setting->enabled);
        $this->assertSame(20, $setting->global_daily_limit);
        $this->assertSame(15, $setting->interval_minutes);
        $this->assertSame(5, $setting->session_hours);
        $this->assertNotNull($setting->session_ends_at);
    }

    public function test_worker_sends_one_approved_email_then_waits_for_interval(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $this->sender($user);

        DeliveryAutomationSetting::query()->create([
            'user_id' => $user->id,
            'enabled' => true,
            'global_daily_limit' => 20,
            'interval_minutes' => 15,
            'session_hours' => 5,
            'session_started_at' => now(),
            'session_ends_at' => now()->addHours(5),
        ]);

        $campaign = Campaign::factory()->for($user)->create([
            'status' => CampaignStatus::Active,
            'daily_outreach_limit' => 20,
        ]);

        $first = $this->approvedDraft($campaign, 'one@business.test');

        $this->withToken($this->token)
            ->postJson('/api/worker/delivery/automation/run')
            ->assertOk()
            ->assertJsonPath('status', 'sent')
            ->assertJsonPath('sent', 1);

        $this->assertSame(OutreachDraft::STATUS_SENT, $first->fresh()->status);

        $second = $this->approvedDraft($campaign, 'two@business.test');

        $this->withToken($this->token)
            ->postJson('/api/worker/delivery/automation/run')
            ->assertOk()
            ->assertJsonPath('status', 'idle')
            ->assertJsonPath('sent', 0)
            ->assertJsonPath('results.0.status', 'waiting_interval');

        $this->assertSame(OutreachDraft::STATUS_APPROVED, $second->fresh()->status);
    }

    public function test_worker_pauses_when_sender_pool_has_no_available_sender(): void
    {
        $user = User::factory()->create();
        $sender = $this->sender($user);
        $sender->update(['enabled' => false]);

        $setting = DeliveryAutomationSetting::query()->create([
            'user_id' => $user->id,
            'enabled' => true,
            'global_daily_limit' => 20,
            'interval_minutes' => 15,
            'session_hours' => 5,
            'session_started_at' => now(),
            'session_ends_at' => now()->addHours(5),
        ]);

        $campaign = Campaign::factory()->for($user)->create([
            'status' => CampaignStatus::Active,
            'daily_outreach_limit' => 20,
        ]);
        $this->approvedDraft($campaign, 'waiting@business.test');

        $this->withToken($this->token)
            ->postJson('/api/worker/delivery/automation/run')
            ->assertOk()
            ->assertJsonPath('status', 'idle')
            ->assertJsonPath('results.0.status', 'no_sender_available');

        $this->assertFalse($setting->fresh()->enabled);
        $this->assertSame('no_sender_available', $setting->fresh()->last_status);
    }

    private function sender(User $user): SenderAccount
    {
        return SenderAccount::query()->create([
            'user_id' => $user->id,
            'email' => 'sender@example.com',
            'from_name' => 'Mehedi',
            'smtp_host' => 'smtp.gmail.com',
            'smtp_port' => 587,
            'smtp_scheme' => 'smtp',
            'smtp_username' => 'sender@example.com',
            'smtp_password' => 'secret',
            'daily_limit' => 20,
            'enabled' => true,
        ]);
    }

    private function approvedDraft(Campaign $campaign, string $recipient): OutreachDraft
    {
        $lead = Lead::factory()->for($campaign)->create([
            'status' => LeadStatus::Approved,
        ]);

        return $lead->outreachDrafts()->create([
            'channel' => 'email',
            'recipient' => $recipient,
            'subject' => 'Quick website idea',
            'body' => 'Approved body',
            'status' => OutreachDraft::STATUS_APPROVED,
            'reviewed_at' => now(),
        ]);
    }
}
