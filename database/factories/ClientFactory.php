<?php

namespace Database\Factories;

use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'phone' => fake()->unique()->numerify('05########'),
            'email' => fake()->boolean(70) ? fake()->unique()->safeEmail() : null,
            'last_follow_up' => fake()->optional()->dateTimeBetween('-3 months', 'now')?->format('Y-m-d'),
        ];
    }
}
