<?php

namespace Database\Factories;

use App\Models\GymPackage;
use App\Models\MembershipOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MembershipOrder>
 */
class MembershipOrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'gym_package_id' => GymPackage::factory(),
            'amount' => 399000,
            'start_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'status' => 'pending',
            'paid_at' => null,
            'confirmed_by_user_id' => null,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'paid',
            'paid_at' => now(),
        ]);
    }
}
