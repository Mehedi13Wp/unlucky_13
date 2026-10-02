<?php

namespace Tests\Feature;

use App\Enums\CampaignStatus;
use App\Enums\LeadStatus;
use App\Models\Campaign;
use App\Models\Lead;
use App\Models\OutreachDraft;
use App\Models\OutreachMessage;
use App\Models\Suppression;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class WorkerDeliveryApiTest extends TestCase
{
    use RefreshDatabase;

    private string $token = 'test-worker-delivery-token';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('unlucky13.worker_token', $this->token);
    }

    public function test_queue_exposes_only_approved_email_drafts_from_active_campaigns(): void
    {
        $user = User::factory()->create();
        $campaign = Campaign::factory()->for($user)->create([
            'status' => CampaignStatus::Active,
            'daily_outreach_limit' => 10,
        ]);
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

        $lead->outreachDrafts()->create([
            'channel' => 'contact_form',
            'recipient' => 'https://business.test/contact',
            'body' => 'Manual form body',
            'status' => OutreachDraft::STATUS_APPROVED,
            'reviewed_at' => now(),
        ]);

        $this->withToken($this->token)
            ->getJson('/api/worker/delivery/queue')
            ->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('drafts.0.draft_id', $draft->id)
            ->assertJsonPath('drafts.0.eligible', true);
    }

    public function test_approved_email_can_be_sent_once_and_moves_lead_to_contacted(): void
    {
        Mail::fake();
        config()->set('mail.default', 'smtp');

        $user = User::factory()->create();
        $campaign = Campaign::factory()->for($user)->create([
            'status' => CampaignStatus::Active,
            'daily_outreach_limit' => 10,
        ]);
        $lead = Lead::factory()->for($campaign)->create([
            'status' => LeadStatus::Approved,
        ]);
        $draft = $lead->outreachDrafts()->create([
            'channel' => 'email',
            'recipient' => 'hello@business.test',
            'subject' => 'Quick website idea',
            'body' => 'Approved body',
            'status' => OutreachDraft::STATUS_APPROVED,
            'reviewed_at' => now(),
        ]);

        $this->withToken($this->token)
            ->postJson('/api/worker/outreach-drafts/'.$draft->id.'/send')
            ->assertOk()
            ->assertJsonPath('status', 'sent')
            ->assertJsonPath('lead_status', LeadStatus::Contacted->value);

        $this->assertSame(OutreachDraft::STATUS_SENT, $draft->fresh()->status);
        $this->assertSame(LeadStatus::Contacted, $lead->fresh()->status);
        $this->assertNotNull($lead->fresh()->last_contacted_at);
        $this->assertDatabaseHas('outreach_messages', [
            'outreach_draft_id' => $draft->id,
            'status' => OutreachMessage::STATUS_SENT,
        ]);
    }

    public function test_suppressed_recipient_cannot_be_sent(): void
    {
        config()->set('mail.default', 'smtp');

        $user = User::factory()->create();
        $campaign = Campaign::factory()->for($user)->create([
            'status' => CampaignStatus::Active,
            'daily_outreach_limit' => 10,
        ]);
        $lead = Lead::factory()->for($campaign)->create([
            'status' => LeadStatus::Approved,
        ]);
        $draft = $lead->outreachDrafts()->create([
            'channel' => 'email',
            'recipient' => 'blocked@business.test',
            'subject' => 'Hello',
            'body' => 'Approved body',
            'status' => OutreachDraft::STATUS_APPROVED,
            'reviewed_at' => now(),
        ]);

        Suppression::query()->create([
            'type' => 'email',
            'value' => 'blocked@business.test',
            'reason' => 'unsubscribe',
            'source' => 'test',
            'suppressed_at' => now(),
        ]);

        $this->withToken($this->token)
            ->postJson('/api/worker/outreach-drafts/'.$draft->id.'/send')
            ->assertStatus(409)
            ->assertJsonPath('code', 'recipient_suppressed');

        $this->assertDatabaseCount('outreach_messages', 0);
    }

    public function test_log_mailer_is_not_treated_as_real_delivery(): void
    {
        config()->set('mail.default', 'log');

        $user = User::factory()->create();
        $campaign = Campaign::factory()->for($user)->create([
            'status' => CampaignStatus::Active,
            'daily_outreach_limit' => 10,
        ]);
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

        $this->withToken($this->token)
            ->postJson('/api/worker/outreach-drafts/'.$draft->id.'/send')
            ->assertStatus(409)
            ->assertJsonPath('code', 'mailer_not_delivery_capable');

        $this->assertSame(OutreachDraft::STATUS_APPROVED, $draft->fresh()->status);
    }
}
