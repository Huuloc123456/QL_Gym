<?php

namespace Database\Factories;

use App\Models\GymPackage;
use App\Models\Membership;
use App\Models\MembershipOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Membership>
 */
class MembershipFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'membership_order_id' => MembershipOrder::factory()->paid(),
            'user_id' => User::factory(),
            'gym_package_id' => GymPackage::factory(),
            'starts_on' => now()->toDateString(),
            'expires_on' => now()->addMonthNoOverflow()->toDateString(),
        ];
    }
}
