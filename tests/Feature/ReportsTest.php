<?php

namespace Tests\Feature;

use App\Enums\CampaignStatus;
use App\Enums\LeadStatus;
use App\Models\Campaign;
use App\Models\Lead;
use App\Models\OutreachDraft;
use App\Models\OutreachMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_show_owned_campaign_performance(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $campaign = Campaign::factory()->for($user)->create([
            'name' => 'NY Real Estate',
            'status' => CampaignStatus::Active,
        ]);

        $lead = Lead::factory()->for($campaign)->create([
            'business_name' => 'Example Realty',
            'status' => LeadStatus::Contacted,
            'qualified_at' => now(),
            'analyzed_at' => now(),
            'last_contacted_at' => now(),
        ]);

        $draft = OutreachDraft::query()->create([
            'lead_id' => $lead->id,
            'channel' => 'email',
            'recipient' => 'hello@realty.test',
            'subject' => 'Website idea',
            'body' => 'Approved body',
            'status' => OutreachDraft::STATUS_SENT,
            'generated_at' => now(),
            'reviewed_at' => now(),
        ]);

        OutreachMessage::query()->create([
            'outreach_draft_id' => $draft->id,
            'lead_id' => $lead->id,
            'campaign_id' => $campaign->id,
            'channel' => 'email',
            'recipient' => 'hello@realty.test',
            'subject' => 'Website idea',
            'body' => 'Approved body',
            'status' => OutreachMessage::STATUS_SENT,
            'attempted_at' => now(),
            'sent_at' => now(),
        ]);

        $otherCampaign = Campaign::factory()->for($other)->create([
            'name' => 'Other User Campaign',
            'status' => CampaignStatus::Active,
        ]);
        Lead::factory()->for($otherCampaign)->create();

        $this->actingAs($user)
            ->get(route('reports.index'))
            ->assertOk()
            ->assertSee('Know what your campaigns are actually producing.')
            ->assertSee('NY Real Estate')
            ->assertDontSee('Other User Campaign')
            ->assertSee('Emails sent')
            ->assertSee('100%');
    }

    public function test_reports_require_authentication(): void
    {
        $this->get(route('reports.index'))
            ->assertRedirect(route('login'));
    }
}
