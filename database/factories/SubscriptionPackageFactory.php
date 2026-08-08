<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class SubscriptionPackageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true).' Plan',
            'description' => fake()->optional()->sentence(),
            'monthly_price' => fake()->randomFloat(2, 200, 1000),
            'clothes_allowance' => 20,
            'collections_per_month' => 1,
            'max_clothes_per_cycle' => 80,
            'is_active' => true,
        ];
    }
}
