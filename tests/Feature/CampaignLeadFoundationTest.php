<?php

namespace Tests\Feature;

use App\Enums\CampaignStatus;
use App\Enums\LeadStatus;
use App\Models\Campaign;
use App\Models\Lead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignLeadFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_campaign_defaults_are_safe_for_review_first_outreach(): void
    {
        $campaign = Campaign::factory()->create([
            'status' => CampaignStatus::Draft,
            'review_required' => true,
            'auto_send' => false,
        ])->refresh();

        $this->assertNotEmpty($campaign->uuid);
        $this->assertSame(CampaignStatus::Draft, $campaign->status);
        $this->assertTrue($campaign->review_required);
        $this->assertFalse($campaign->auto_send);
        $this->assertSame(20, $campaign->daily_outreach_limit);
    }

    public function test_lead_belongs_to_campaign_and_casts_analysis_data(): void
    {
        $campaign = Campaign::factory()->create();

        $lead = Lead::factory()->for($campaign)->create([
            'status' => LeadStatus::Qualified,
            'qualification_score' => 82,
            'observed_signals' => [
                'no_online_booking' => true,
                'mobile_issue' => false,
            ],
        ])->refresh();

        $this->assertTrue($lead->campaign->is($campaign));
        $this->assertSame(LeadStatus::Qualified, $lead->status);
        $this->assertSame(82, $lead->qualification_score);
        $this->assertTrue($lead->observed_signals['no_online_booking']);
    }

    public function test_same_domain_cannot_be_added_twice_to_one_campaign(): void
    {
        $campaign = Campaign::factory()->create();

        Lead::factory()->for($campaign)->create([
            'domain' => 'example.com',
            'website_url' => 'https://example.com',
        ]);

        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);

        Lead::factory()->for($campaign)->create([
            'domain' => 'example.com',
            'website_url' => 'https://example.com/contact',
        ]);
    }
}
