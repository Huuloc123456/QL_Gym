<?php

namespace Database\Factories;

use App\Models\MembershipOrder;
use App\Models\OrderPayment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderPayment>
 */
class OrderPaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'membership_order_id' => MembershipOrder::factory(),
            'received_by' => User::factory()->employee(),
            'amount' => 100000,
            'payment_method' => 'cash',
            'reference' => null,
            'received_at' => now(),
        ];
    }
}
