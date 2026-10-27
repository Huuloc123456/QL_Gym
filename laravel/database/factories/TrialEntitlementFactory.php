<?php

namespace Database\Factories;

use App\Models\TrialEntitlement;
use App\Models\TrialRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrialEntitlement>
 */
class TrialEntitlementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $customer = User::factory()->create();
        $trialRequest = TrialRequest::factory()->create([
            'user_id' => $customer->id,
            'phone' => $customer->phone,
            'status' => 'approved',
        ]);
        $startsAt = now()->startOfDay();

        return [
            'trial_request_id' => $trialRequest->id,
            'user_id' => $customer->id,
            'verified_phone' => $customer->phone,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addDay(),
            'approved_by' => User::factory()->employee(),
        ];
    }
}
