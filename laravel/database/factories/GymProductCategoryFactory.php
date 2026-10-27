<?php

namespace Database\Factories;

use App\Models\GymProductCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GymProductCategory>
 */
class GymProductCategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'MALOAISP' => fake()->unique()->numberBetween(100000, 999999),
            'TENLOAISP' => fake()->unique()->words(2, true),
        ];
    }
}
