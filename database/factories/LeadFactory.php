<?php

namespace Database\Factories;

use App\Enums\LeadStatus;
use App\Models\Campaign;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Lead>
 */
class LeadFactory extends Factory
{
    public function definition(): array
    {
        $domain = fake()->unique()->domainName();

        return [
            'campaign_id' => Campaign::factory(),
            'business_name' => fake()->company(),
            'website_url' => 'https://'.$domain,
            'domain' => $domain,
            'industry' => fake()->randomElement(['restaurant', 'real_estate', 'recruitment', 'salon']),
            'country' => fake()->countryCode(),
            'city' => fake()->city(),
            'source' => 'manual',
            'status' => LeadStatus::Discovered,
            'observed_signals' => [],
        ];
    }
}
