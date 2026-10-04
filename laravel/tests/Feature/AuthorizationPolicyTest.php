<?php

namespace Tests\Feature;

use App\Models\CheckIn;
use App\Models\GymPackage;
use App\Models\Membership;
use App\Models\MembershipOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class AuthorizationPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_only_view_and_request_cancellation_for_their_own_pending_order(): void
    {
        $owner = User::factory()->create(['role' => 'customer']);
        $otherCustomer = User::factory()->create(['role' => 'customer']);
        $employee = User::factory()->create(['role' => 'employee']);
        $package = GymPackage::factory()->create();
        $order = MembershipOrder::factory()->for($owner)->for($package, 'gymPackage')->create();

        $this->assertTrue(Gate::forUser($owner)->allows('view', $order));
        $this->assertFalse(Gate::forUser($otherCustomer)->allows('view', $order));
        $this->assertFalse(Gate::forUser($employee)->allows('view', $order));
        $this->assertTrue(Gate::forUser($owner)->allows('requestCancellation', $order));
        $this->assertFalse(Gate::forUser($otherCustomer)->allows('requestCancellation', $order));
        $this->assertFalse(Gate::forUser($employee)->allows('requestCancellation', $order));
    }

    public function test_only_staff_can_view_the_order_queue_and_record_or_confirm_payments(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $employee = User::factory()->create(['role' => 'employee']);
        $manager = User::factory()->create(['role' => 'manager']);
        $package = GymPackage::factory()->create();
        $order = MembershipOrder::factory()->for($customer)->for($package, 'gymPackage')->create();

        foreach ([$employee, $manager] as $staff) {
            $this->assertTrue(Gate::forUser($staff)->allows('viewAny', MembershipOrder::class));
            $this->assertTrue(Gate::forUser($staff)->allows('recordPayment', $order));
            $this->assertTrue(Gate::forUser($staff)->allows('confirm', $order));
            $this->assertTrue(Gate::forUser($staff)->allows('cancel', $order));
        }

        $this->assertFalse(Gate::forUser($customer)->allows('viewAny', MembershipOrder::class));
        $this->assertFalse(Gate::forUser($customer)->allows('recordPayment', $order));
        $this->assertFalse(Gate::forUser($customer)->allows('confirm', $order));
        $this->assertFalse(Gate::forUser($customer)->allows('cancel', $order));
    }

    public function test_only_staff_can_check_in_and_close_visits(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $employee = User::factory()->create(['role' => 'employee']);
        $manager = User::factory()->create(['role' => 'manager']);
        $package = GymPackage::factory()->create();
        $order = MembershipOrder::factory()->for($customer)->for($package, 'gymPackage')->paid()->create();
        $membership = Membership::factory()->for($customer)->for($package, 'gymPackage')->for($order, 'order')->create();
        $openVisit = CheckIn::factory()->for($customer)->for($membership)->create(['checked_out_at' => null]);
        $closedVisit = CheckIn::factory()->for($customer)->for($membership)->create(['checked_out_at' => now()]);

        foreach ([$employee, $manager] as $staff) {
            $this->assertTrue(Gate::forUser($staff)->allows('checkIn', CheckIn::class));
            $this->assertTrue(Gate::forUser($staff)->allows('checkOut', $openVisit));
            $this->assertTrue(Gate::forUser($staff)->allows('manualClose', $openVisit));
            $this->assertFalse(Gate::forUser($staff)->allows('checkOut', $closedVisit));
            $this->assertFalse(Gate::forUser($staff)->allows('manualClose', $closedVisit));
        }

        $this->assertFalse(Gate::forUser($customer)->allows('checkIn', CheckIn::class));
        $this->assertFalse(Gate::forUser($customer)->allows('checkOut', $openVisit));
        $this->assertFalse(Gate::forUser($customer)->allows('manualClose', $openVisit));
    }
}
