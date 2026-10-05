<?php

namespace Tests\Feature;

use App\Enums\CampaignStatus;
use App\Enums\LeadStatus;
use App\Models\Campaign;
use App\Models\Lead;
use App\Models\OutreachDraft;
use App\Models\OutreachMessage;
use App\Models\SenderAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SenderRotationDeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_delivery_uses_next_sender_after_first_reaches_daily_limit(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $campaign = Campaign::factory()->for($user)->create([
            'status' => CampaignStatus::Active,
            'daily_outreach_limit' => 20,
        ]);

        $first = $this->sender($user, 'first@example.com', 1);
        $second = $this->sender($user, 'second@example.com', 20);

        $oldLead = Lead::factory()->for($campaign)->create(['status' => LeadStatus::Contacted]);
        $oldDraft = $oldLead->outreachDrafts()->create([
            'channel' => 'email',
            'recipient' => 'old@business.test',
            'subject' => 'Old',
            'body' => 'Old',
            'status' => OutreachDraft::STATUS_SENT,
        ]);
        OutreachMessage::query()->create([
            'outreach_draft_id' => $oldDraft->id,
            'lead_id' => $oldLead->id,
            'campaign_id' => $campaign->id,
            'sender_account_id' => $first->id,
            'sender_address' => $first->email,
            'channel' => 'email',
            'recipient' => 'old@business.test',
            'subject' => 'Old',
            'body' => 'Old',
            'status' => OutreachMessage::STATUS_SENT,
            'sent_at' => now(),
        ]);

        $lead = Lead::factory()->for($campaign)->create(['status' => LeadStatus::Approved]);
        $draft = $lead->outreachDrafts()->create([
            'channel' => 'email',
            'recipient' => 'hello@business.test',
            'subject' => 'Hello',
            'body' => 'Approved body',
            'status' => OutreachDraft::STATUS_APPROVED,
            'reviewed_at' => now(),
        ]);

        $this->actingAs($user)
            ->post(route('outreach-drafts.send', [$lead, $draft]))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('outreach_messages', [
            'outreach_draft_id' => $draft->id,
            'sender_account_id' => $second->id,
            'sender_address' => 'second@example.com',
            'status' => OutreachMessage::STATUS_SENT,
        ]);
    }

    public function test_live_manual_delivery_refuses_unverified_sender(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $campaign = Campaign::factory()->for($user)->create([
            'status' => CampaignStatus::Active,
            'daily_outreach_limit' => 20,
        ]);

        $sender = $this->sender($user, 'unverified@example.com', 20);
        $sender->update(['smtp_verified_at' => null]);

        $lead = Lead::factory()->for($campaign)->create([
            'status' => LeadStatus::Approved,
        ]);
        $draft = $lead->outreachDrafts()->create([
            'channel' => 'email',
            'recipient' => 'hello@business.test',
            'subject' => 'Hello',
            'body' => 'Approved body',
            'status' => OutreachDraft::STATUS_APPROVED,
            'reviewed_at' => now(),
        ]);

        $this->actingAs($user)
            ->post(route('outreach-drafts.send', [$lead, $draft]))
            ->assertRedirect()
            ->assertSessionHas('error', fn ($message) => str_contains(
                $message,
                'no delivery-ready sender emails',
            ));

        $this->assertSame(OutreachDraft::STATUS_APPROVED, $draft->fresh()->status);
        $this->assertDatabaseMissing('outreach_messages', [
            'outreach_draft_id' => $draft->id,
            'status' => OutreachMessage::STATUS_SENT,
        ]);
    }

    public function test_delivery_stops_when_sender_pool_has_no_capacity(): void
    {
        $user = User::factory()->create();
        $campaign = Campaign::factory()->for($user)->create([
            'status' => CampaignStatus::Active,
            'daily_outreach_limit' => 20,
        ]);

        $sender = $this->sender($user, 'only@example.com', 1);

        $oldLead = Lead::factory()->for($campaign)->create(['status' => LeadStatus::Contacted]);
        $oldDraft = $oldLead->outreachDrafts()->create([
            'channel' => 'email',
            'recipient' => 'old@business.test',
            'subject' => 'Old',
            'body' => 'Old',
            'status' => OutreachDraft::STATUS_SENT,
        ]);
        OutreachMessage::query()->create([
            'outreach_draft_id' => $oldDraft->id,
            'lead_id' => $oldLead->id,
            'campaign_id' => $campaign->id,
            'sender_account_id' => $sender->id,
            'sender_address' => $sender->email,
            'channel' => 'email',
            'recipient' => 'old@business.test',
            'subject' => 'Old',
            'body' => 'Old',
            'status' => OutreachMessage::STATUS_SENT,
            'sent_at' => now(),
        ]);

        $lead = Lead::factory()->for($campaign)->create(['status' => LeadStatus::Approved]);
        $draft = $lead->outreachDrafts()->create([
            'channel' => 'email',
            'recipient' => 'hello@business.test',
            'subject' => 'Hello',
            'body' => 'Approved body',
            'status' => OutreachDraft::STATUS_APPROVED,
            'reviewed_at' => now(),
        ]);

        $this->actingAs($user)
            ->post(route('outreach-drafts.send', [$lead, $draft]))
            ->assertRedirect()
            ->assertSessionHas('error', fn ($message) => str_contains(
                $message,
                'no delivery-ready sender emails',
            ));

        $this->assertSame(OutreachDraft::STATUS_APPROVED, $draft->fresh()->status);
    }

    private function sender(User $user, string $email, int $dailyLimit): SenderAccount
    {
        return SenderAccount::query()->create([
            'user_id' => $user->id,
            'email' => $email,
            'from_name' => 'Mehedi',
            'smtp_host' => 'smtp.gmail.com',
            'smtp_port' => 587,
            'smtp_scheme' => 'smtp',
            'smtp_username' => $email,
            'smtp_password' => 'secret',
            'daily_limit' => $dailyLimit,
            'enabled' => true,
            'smtp_verified_at' => now(),
        ]);
    }
}
