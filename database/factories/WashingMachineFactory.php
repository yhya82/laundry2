<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class WashingMachineFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Washer '.fake()->unique()->numberBetween(1, 9999),
            'is_active' => true,
        ];
    }
}
