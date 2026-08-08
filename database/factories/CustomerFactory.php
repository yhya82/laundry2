<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'full_name' => fake()->name(),
            'phone' => '+220'.fake()->unique()->numerify('#######'),
            'email' => fake()->optional()->safeEmail(),
            'customer_type' => 'walk_in',
            'address' => fake()->optional()->streetAddress(),
        ];
    }

    public function subscription(): static
    {
        return $this->state(fn () => ['customer_type' => 'subscription']);
    }
}
