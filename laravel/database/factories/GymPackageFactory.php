<?php

namespace Database\Factories;

use App\Models\GymPackage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GymPackage>
 */
class GymPackageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'description' => fake()->sentence(),
            'price' => 399000,
            'duration_months' => 1,
            'is_active' => true,
        ];
    }
}
