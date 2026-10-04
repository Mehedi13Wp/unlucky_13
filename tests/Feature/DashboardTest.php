<?php

namespace Tests\Feature;

use App\Enums\CampaignStatus;
use App\Enums\LeadStatus;
use App\Models\Campaign;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_renders_operational_counts_and_pipeline(): void
    {
        $user = User::factory()->create();

        $campaign = Campaign::factory()->for($user)->create([
            'name' => 'Restaurant Booking Campaign',
            'status' => CampaignStatus::Active,
        ]);

        Lead::factory()->for($campaign)->create([
            'business_name' => 'Example Restaurant',
            'status' => LeadStatus::ReadyForReview,
            'qualified_at' => now()->subMinutes(10),
        ]);

        Lead::factory()->for($campaign)->create([
            'business_name' => 'Interested Restaurant',
            'status' => LeadStatus::Interested,
            'qualified_at' => now()->subMinutes(20),
        ]);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Command Center')
            ->assertSee('Lead pipeline')
            ->assertSee('System readiness')
            ->assertSee('Restaurant Booking Campaign')
            ->assertSee('Example Restaurant')
            ->assertSee('Interested Restaurant')
            ->assertSee('Ever qualified')
            ->assertSee('100%');
    }

    public function test_empty_dashboard_guides_user_to_create_first_campaign(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Build your first focused outreach campaign.')
            ->assertSee('Create first campaign')
            ->assertSee('Waiting for discovery');
    }
}
