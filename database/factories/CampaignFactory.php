<?php

namespace Database\Factories;

use App\Enums\CampaignStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Campaign>
 */
class CampaignFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->words(3, true),
            'service_offer' => fake()->sentence(),
            'target_industry' => fake()->randomElement(['restaurant', 'real_estate', 'recruitment', 'salon']),
            'target_countries' => ['US'],
            'target_cities' => [fake()->city()],
            'qualification_rules' => [
                'requires_website' => true,
                'minimum_score' => 60,
            ],
            'daily_outreach_limit' => 20,
            'status' => CampaignStatus::Draft,
            'review_required' => true,
            'auto_send' => false,
        ];
    }
}
