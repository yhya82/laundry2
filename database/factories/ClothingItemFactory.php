<?php

namespace Database\Factories;

use App\Models\ClothesCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClothingItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'clothes_category_id' => ClothesCategory::factory(),
            'name' => fake()->unique()->word(),
        ];
    }
}
