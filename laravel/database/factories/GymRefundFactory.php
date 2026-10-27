<?php

namespace Database\Factories;

use App\Models\GymRefund;
use App\Models\MembershipOrder;
use App\Models\OrderPayment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<GymRefund>
 */
class GymRefundFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $order = MembershipOrder::factory()->create(['amount' => 1000, 'status' => MembershipOrder::STATUS_PENDING]);
        $staff = User::factory()->employee()->create();
        $payment = OrderPayment::factory()->for($order, 'order')->create([
            'amount' => 500,
            'received_by' => $staff->id,
        ]);

        return [
            'source_type' => 'order_payment',
            'source_id' => $payment->id,
            'target_type' => 'membership_order',
            'target_id' => $order->id,
            'amount' => 250,
            'method' => $payment->payment_method,
            'reference' => 'REFUND-'.fake()->unique()->bothify('########'),
            'reason' => 'Hoàn tiền theo yêu cầu đã được duyệt.',
            'recorded_by' => User::factory()->manager(),
            'token' => (string) Str::uuid(),
            'refunded_at' => now(),
        ];
    }
}
