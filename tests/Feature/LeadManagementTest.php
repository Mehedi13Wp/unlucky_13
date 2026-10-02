<?php

namespace Tests\Feature;

use App\Enums\CampaignStatus;
use App\Enums\LeadStatus;
use App\Models\Campaign;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_leads(): void
    {
        $this->get('/leads')->assertRedirect('/login');
    }

    public function test_user_only_sees_leads_from_their_campaigns(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $ownCampaign = Campaign::factory()->for($user)->create();
        $otherCampaign = Campaign::factory()->for($otherUser)->create();

        Lead::factory()->for($ownCampaign)->create(['business_name' => 'Visible Business']);
        Lead::factory()->for($otherCampaign)->create(['business_name' => 'Hidden Business']);

        $this->actingAs($user)
            ->get(route('leads.index'))
            ->assertOk()
            ->assertSee('Visible Business')
            ->assertDontSee('Hidden Business');
    }

    public function test_user_can_create_manual_lead_with_normalized_domain(): void
    {
        $user = User::factory()->create();
        $campaign = Campaign::factory()->for($user)->create([
            'status' => CampaignStatus::Active,
        ]);

        $response = $this->actingAs($user)->post(route('leads.store'), [
            'campaign_uuid' => $campaign->uuid,
            'business_name' => 'Example Restaurant',
            'website_url' => 'WWW.Example.com/menu',
            'industry' => 'Restaurants',
            'country' => 'United States',
            'city' => 'Boston',
            'source' => 'manual',
        ]);

        $lead = Lead::where('business_name', 'Example Restaurant')->firstOrFail();

        $response->assertRedirect(route('leads.show', $lead));
        $this->assertSame('example.com', $lead->domain);
        $this->assertSame('https://WWW.Example.com/menu', $lead->website_url);
        $this->assertSame(LeadStatus::Discovered, $lead->status);
    }

    public function test_duplicate_domain_is_rejected_inside_same_campaign(): void
    {
        $user = User::factory()->create();
        $campaign = Campaign::factory()->for($user)->create();

        Lead::factory()->for($campaign)->create([
            'domain' => 'example.com',
            'website_url' => 'https://example.com',
        ]);

        $this->actingAs($user)
            ->from(route('leads.create'))
            ->post(route('leads.store'), [
                'campaign_uuid' => $campaign->uuid,
                'business_name' => 'Duplicate Business',
                'website_url' => 'www.example.com/contact',
            ])
            ->assertRedirect(route('leads.create'))
            ->assertSessionHasErrors('website_url');

        $this->assertSame(1, $campaign->leads()->count());
    }

    public function test_same_domain_can_exist_in_different_campaigns(): void
    {
        $user = User::factory()->create();
        $firstCampaign = Campaign::factory()->for($user)->create();
        $secondCampaign = Campaign::factory()->for($user)->create();

        Lead::factory()->for($firstCampaign)->create([
            'domain' => 'example.com',
            'website_url' => 'https://example.com',
        ]);

        $this->actingAs($user)
            ->post(route('leads.store'), [
                'campaign_uuid' => $secondCampaign->uuid,
                'business_name' => 'Second Campaign Business',
                'website_url' => 'https://example.com/about',
            ])
            ->assertRedirect();

        $this->assertSame(1, $secondCampaign->leads()->where('domain', 'example.com')->count());
    }

    public function test_user_cannot_open_another_users_lead(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $otherCampaign = Campaign::factory()->for($otherUser)->create();
        $lead = Lead::factory()->for($otherCampaign)->create();

        $this->actingAs($user)
            ->get(route('leads.show', $lead))
            ->assertNotFound();
    }

    public function test_archived_campaign_cannot_receive_new_manual_leads(): void
    {
        $user = User::factory()->create();
        $campaign = Campaign::factory()->for($user)->create([
            'status' => CampaignStatus::Archived,
        ]);

        $this->actingAs($user)
            ->post(route('leads.store'), [
                'campaign_uuid' => $campaign->uuid,
                'business_name' => 'Blocked Business',
                'website_url' => 'blocked.example',
            ])
            ->assertNotFound();

        $this->assertDatabaseMissing('leads', ['business_name' => 'Blocked Business']);
    }
}
