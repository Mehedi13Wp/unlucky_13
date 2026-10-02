<?php

namespace Tests\Feature;

use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_campaign_management(): void
    {
        $this->get('/campaigns')->assertRedirect('/login');
    }

    public function test_user_only_sees_their_own_campaigns(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        Campaign::factory()->for($user)->create(['name' => 'My Campaign']);
        Campaign::factory()->for($otherUser)->create(['name' => 'Private Other Campaign']);

        $this->actingAs($user)
            ->get('/campaigns')
            ->assertOk()
            ->assertSee('Campaign workspace')
            ->assertSee('My Campaign')
            ->assertDontSee('Private Other Campaign');
    }

    public function test_campaign_create_screen_renders_professional_campaign_form(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/campaigns/create')
            ->assertOk()
            ->assertSee('Create a focused target')
            ->assertSee('Campaign principles')
            ->assertSee('Review before send');
    }

    public function test_user_can_create_a_safe_draft_campaign(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/campaigns', [
            'name' => 'Restaurant Booking Outreach',
            'service_offer' => 'Direct online reservation system for independent restaurants.',
            'target_industry' => 'Restaurants',
            'target_countries' => "United States\nCanada",
            'target_cities' => 'New York, Toronto',
            'minimum_score' => 65,
            'requires_website' => 1,
            'requires_public_contact' => 1,
            'daily_outreach_limit' => 15,
            'review_required' => 1,
        ]);

        $campaign = Campaign::where('name', 'Restaurant Booking Outreach')->firstOrFail();

        $response->assertRedirect(route('campaigns.show', $campaign));
        $this->assertSame($user->id, $campaign->user_id);
        $this->assertSame(CampaignStatus::Draft, $campaign->status);
        $this->assertSame(['United States', 'Canada'], $campaign->target_countries);
        $this->assertSame(['New York', 'Toronto'], $campaign->target_cities);
        $this->assertTrue($campaign->review_required);
        $this->assertFalse($campaign->auto_send);
    }

    public function test_user_can_update_campaign_targeting(): void
    {
        $user = User::factory()->create();
        $campaign = Campaign::factory()->for($user)->create();

        $this->actingAs($user)
            ->put(route('campaigns.update', $campaign), [
                'name' => 'Updated Campaign',
                'service_offer' => 'A focused custom portal offer for qualified recruitment agencies.',
                'target_industry' => 'Recruitment',
                'target_countries' => 'United Kingdom',
                'target_cities' => 'London',
                'minimum_score' => 70,
                'requires_website' => 1,
                'requires_public_contact' => 1,
                'daily_outreach_limit' => 10,
                'review_required' => 1,
            ])
            ->assertRedirect(route('campaigns.show', $campaign));

        $campaign->refresh();

        $this->assertSame('Updated Campaign', $campaign->name);
        $this->assertSame(10, $campaign->daily_outreach_limit);
        $this->assertSame(70, $campaign->qualification_rules['minimum_score']);
    }

    public function test_campaign_detail_renders_control_and_policy_information(): void
    {
        $user = User::factory()->create();
        $campaign = Campaign::factory()->for($user)->create([
            'name' => 'Professional Detail Campaign',
            'review_required' => true,
        ]);

        $this->actingAs($user)
            ->get(route('campaigns.show', $campaign))
            ->assertOk()
            ->assertSee('Professional Detail Campaign')
            ->assertSee('Lead policy')
            ->assertSee('Status actions')
            ->assertSee('Human approval required');
    }

    public function test_user_can_activate_and_pause_their_campaign(): void
    {
        $user = User::factory()->create();
        $campaign = Campaign::factory()->for($user)->create([
            'status' => CampaignStatus::Draft,
        ]);

        $this->actingAs($user)
            ->patch(route('campaigns.status', $campaign), ['status' => 'active'])
            ->assertSessionHas('success');

        $this->assertSame(CampaignStatus::Active, $campaign->fresh()->status);

        $this->actingAs($user)
            ->patch(route('campaigns.status', $campaign), ['status' => 'paused'])
            ->assertSessionHas('success');

        $this->assertSame(CampaignStatus::Paused, $campaign->fresh()->status);
    }

    public function test_user_cannot_open_another_users_campaign(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $campaign = Campaign::factory()->for($otherUser)->create();

        $this->actingAs($user)
            ->get(route('campaigns.show', $campaign))
            ->assertNotFound();
    }

    public function test_archiving_preserves_campaign_and_shows_delete_option(): void
    {
        $user = User::factory()->create();
        $campaign = Campaign::factory()->for($user)->create();

        $this->actingAs($user)
            ->patch(route('campaigns.archive', $campaign))
            ->assertRedirect(route('campaigns.show', $campaign));

        $this->assertDatabaseHas('campaigns', [
            'id' => $campaign->id,
            'status' => CampaignStatus::Archived->value,
        ]);

        $this->actingAs($user)
            ->get(route('campaigns.show', $campaign))
            ->assertOk()
            ->assertSee('Delete permanently')
            ->assertSee('Type DELETE to confirm');
    }

    public function test_non_archived_campaign_cannot_be_permanently_removed(): void
    {
        $user = User::factory()->create();
        $campaign = Campaign::factory()->for($user)->create([
            'status' => CampaignStatus::Draft,
        ]);

        $this->actingAs($user)
            ->delete(route('campaigns.remove-permanently', $campaign), [
                'delete_confirmation' => 'DELETE',
            ])
            ->assertStatus(409);

        $this->assertDatabaseHas('campaigns', ['id' => $campaign->id]);
    }

    public function test_archived_campaign_requires_delete_confirmation(): void
    {
        $user = User::factory()->create();
        $campaign = Campaign::factory()->for($user)->create([
            'status' => CampaignStatus::Archived,
        ]);

        $this->actingAs($user)
            ->from(route('campaigns.show', $campaign))
            ->delete(route('campaigns.remove-permanently', $campaign), [
                'delete_confirmation' => 'WRONG',
            ])
            ->assertRedirect(route('campaigns.show', $campaign))
            ->assertSessionHasErrors('delete_confirmation');

        $this->assertDatabaseHas('campaigns', ['id' => $campaign->id]);
    }

    public function test_user_can_permanently_remove_archived_campaign_and_its_leads(): void
    {
        $user = User::factory()->create();
        $campaign = Campaign::factory()->for($user)->create([
            'status' => CampaignStatus::Archived,
        ]);
        $lead = Lead::factory()->for($campaign)->create();

        $this->actingAs($user)
            ->delete(route('campaigns.remove-permanently', $campaign), [
                'delete_confirmation' => 'DELETE',
            ])
            ->assertRedirect(route('campaigns.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('campaigns', ['id' => $campaign->id]);
        $this->assertDatabaseMissing('leads', ['id' => $lead->id]);
    }

    public function test_user_cannot_remove_another_users_campaign(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $campaign = Campaign::factory()->for($otherUser)->create([
            'status' => CampaignStatus::Archived,
        ]);

        $this->actingAs($user)
            ->delete(route('campaigns.remove-permanently', $campaign), [
                'delete_confirmation' => 'DELETE',
            ])
            ->assertNotFound();

        $this->assertDatabaseHas('campaigns', ['id' => $campaign->id]);
    }
}
