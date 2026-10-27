<?php

namespace Database\Factories;

use App\Models\GymProduct;
use App\Models\GymProductCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GymProduct>
 */
class GymProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'MASP' => fake()->unique()->numberBetween(100000, 999999),
            'TENSP' => fake()->unique()->words(3, true),
            'MALOAISP' => GymProductCategory::factory(),
            'DONGIA' => fake()->numberBetween(10000, 1000000),
            'SOLUONGTON' => fake()->numberBetween(0, 100),
            'GIAKHUYENMAI' => null,
            'HANG' => fake()->company(),
            'XUATXU' => fake()->country(),
            'BAOHANH' => null,
            'MOTACHUNG' => fake()->sentence(),
            'MOTACHITIET' => fake()->paragraph(),
        ];
    }
}
