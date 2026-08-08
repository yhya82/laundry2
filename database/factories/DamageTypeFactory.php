<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class DamageTypeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'description' => fake()->optional()->sentence(),
        ];
    }
}
