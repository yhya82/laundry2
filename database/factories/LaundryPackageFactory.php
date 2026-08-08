<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class LaundryPackageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true).' Package',
            'description' => fake()->optional()->sentence(),
            'base_price' => fake()->randomFloat(2, 50, 500),
            'priority' => 'normal',
            'clothes_allowed' => 10,
            'is_active' => true,
        ];
    }
}
