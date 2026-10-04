<?php

namespace Database\Factories;

use App\Models\CheckIn;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CheckIn>
 */
class CheckInFactory extends Factory
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
            'membership_id' => Membership::factory(),
            'checked_in_at' => now(),
            'checked_out_at' => null,
            'manual_close_reason' => null,
            'closed_by_user_id' => null,
        ];
    }
}
