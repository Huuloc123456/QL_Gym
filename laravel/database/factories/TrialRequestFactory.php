<?php

namespace Database\Factories;

use App\Models\TrialRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrialRequest>
 */
class TrialRequestFactory extends Factory
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
            'phone' => fake()->numerify('09########'),
            'email' => fake()->safeEmail(),
            'status' => 'received',
        ];
    }
}
