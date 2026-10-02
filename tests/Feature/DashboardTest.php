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

    public function test_dashboard_renders_operational_counts(): void
    {
        $user = User::factory()->create();

        $campaign = Campaign::factory()->for($user)->create([
            'name' => 'Restaurant Booking Campaign',
            'status' => CampaignStatus::Active,
        ]);

        Lead::factory()->for($campaign)->create([
            'business_name' => 'Example Restaurant',
            'status' => LeadStatus::Qualified,
        ]);

        Lead::factory()->for($campaign)->create([
            'business_name' => 'Interested Restaurant',
            'status' => LeadStatus::Interested,
        ]);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Command Center')
            ->assertSee('Restaurant Booking Campaign')
            ->assertSee('Example Restaurant')
            ->assertSee('Interested Restaurant');
    }
}
