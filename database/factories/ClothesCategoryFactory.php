<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ClothesCategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'description' => fake()->optional()->sentence(),
        ];
    }
}
