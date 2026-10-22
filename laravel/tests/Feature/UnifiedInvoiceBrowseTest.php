<?php

namespace Tests\Feature;

use App\Models\GymPackage;
use App\Models\MembershipOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class UnifiedInvoiceBrowseTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_sees_pending_package_orders_in_the_same_filtered_paginated_list_as_invoices(): void
    {
        $customer = User::factory()->create(['name' => 'Khách kiểm thử hóa đơn chung']);
        $package = GymPackage::factory()->create(['name' => 'Gói kiểm thử hóa đơn chung']);
        $order = MembershipOrder::factory()->for($customer)->for($package, 'gymPackage')->create();
        $staff = User::factory()->employee()->create();

        $response = $this->actingAs($staff)->get(route('gym.invoices.index', ['q' => (string) $order->id, 'status' => 'Chưa thanh toán']))->assertOk();
        $rows = $response->viewData('invoices');
        $this->assertTrue($rows->getCollection()->contains(fn ($row) => $row->source === 'package_order' && (int) $row->record_id === $order->id));
        $response->assertSee('Gói #'.$order->id)
            ->assertSee($package->name)
            ->assertSee(route('staff.orders.show', $order), false)
            ->assertSee('Chi tiết')
            ->assertDontSee(route('staff.orders.payments.store', $order), false)
            ->assertDontSee('Ghi nhận tiền đã nhận')
            ->assertDontSee(route('staff.orders.index', ['status' => 'all', 'q' => $order->id]))
            ->assertDontSee('Đăng ký gói đang chờ từ trước khi bổ sung cửa hàng');

        $this->actingAs($staff)->get(route('gym.invoices.index', ['q' => $package->name]))
            ->assertOk()->assertSee('Gói #'.$order->id);
        $this->actingAs($staff)->get(route('gym.invoices.index', ['status' => 'Đã thanh toán', 'q' => $package->name]))
            ->assertOk()->assertDontSee('Gói #'.$order->id);
        $this->actingAs($staff)->get(route('gym.invoices.index', ['source' => 'invoice', 'q' => $package->name]))
            ->assertOk()->assertDontSee('Gói #'.$order->id);
    }

    public function test_staff_opens_package_order_actions_on_a_full_width_detail_page(): void
    {
        $customer = User::factory()->create();
        $package = GymPackage::factory()->create(['name' => 'Gói chi tiết nhân viên']);
        $order = MembershipOrder::factory()->for($customer)->for($package, 'gymPackage')->create();
        $employee = User::factory()->employee()->create();

        $this->actingAs($employee)->get(route('staff.orders.show', $order))
            ->assertOk()
            ->assertSee('Chi tiết đơn hội viên')
            ->assertSee($package->name)
            ->assertSee($customer->name)
            ->assertSee(route('staff.orders.payments.store', $order), false)
            ->assertSee('Ghi nhận tiền đã nhận');
    }

    public function test_only_staff_with_order_permission_can_open_package_order_details(): void
    {
        $customer = User::factory()->create();
        $package = GymPackage::factory()->create();
        $order = MembershipOrder::factory()->for($customer)->for($package, 'gymPackage')->create();
        $employee = User::factory()->employee()->create(['can_manage_orders' => false]);

        $this->actingAs($employee)->get(route('staff.orders.show', $order))->assertForbidden();
        $this->actingAs($customer)->get(route('staff.orders.show', $order))->assertForbidden();
    }

    public function test_customer_only_sees_own_package_orders_and_paid_orders_are_not_duplicated(): void
    {
        $customer = User::factory()->create();
        $otherCustomer = User::factory()->create();
        $package = GymPackage::factory()->create();
        $own = MembershipOrder::factory()->for($customer)->for($package, 'gymPackage')->create();
        $other = MembershipOrder::factory()->for($otherCustomer)->for($package, 'gymPackage')->create();
        $linked = MembershipOrder::factory()->for($customer)->for($package, 'gymPackage')->paid()->create(['sql_invoice_id' => 182]);

        $response = $this->actingAs($customer)->get(route('gym.invoices.index'))->assertOk();
        $rows = $response->viewData('invoices')->getCollection();
        $this->assertTrue($rows->contains(fn ($row) => $row->source === 'package_order' && (int) $row->record_id === $own->id));
        $this->assertFalse($rows->contains(fn ($row) => $row->source === 'package_order' && in_array((int) $row->record_id, [$other->id, $linked->id], true)));
        $response->assertSee(route('my.orders.show', $own), false);
    }

    public function test_legacy_package_orders_share_invoice_pagination(): void
    {
        $staff = User::factory()->employee()->create();
        $customer = User::factory()->create();
        $package = GymPackage::factory()->create(['name' => 'Gói phân trang chung']);
        MembershipOrder::factory()->count(26)->for($customer)->for($package, 'gymPackage')->create();

        $first = $this->actingAs($staff)->get(route('gym.invoices.index', ['q' => $package->name]))->assertOk()->viewData('invoices');
        $second = $this->actingAs($staff)->get(route('gym.invoices.index', ['q' => $package->name, 'page' => 2]))->assertOk()->viewData('invoices');

        $this->assertSame(26, $first->total());
        $this->assertCount(25, $first->items());
        $this->assertCount(1, $second->items());
    }

    public function test_package_without_sql_link_uses_the_cart_and_creates_an_invoice_before_payment(): void
    {
        $customer = User::factory()->create();
        $package = GymPackage::factory()->create(['name' => 'Gói mới chưa liên kết']);
        $package->forceFill(['sql_package_id' => null])->saveQuietly();

        $this->actingAs($customer)->get(route('packages.index'))
            ->assertOk()
            ->assertSee('name="gym_package_id" value="'.$package->id.'"', false);
        $this->post(route('gym.cart.add'), [
            'kind' => 'package',
            'gym_package_id' => $package->id,
            'quantity' => 1,
        ])->assertRedirect(route('gym.cart'));
        $this->assertNotNull($package->fresh()->sql_package_id);

        $this->post(route('gym.checkout'), [
            'token' => (string) Str::uuid(),
            'mode' => 'pickup',
            'payment_method' => 'bank_transfer',
            'start_date' => now(config('app.timezone'))->toDateString(),
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseCount('membership_orders', 0);
        $invoice = $this->actingAs($customer)->get(route('gym.invoices.index'))->assertOk()->viewData('invoices')->getCollection()
            ->first(fn ($row) => $row->source === 'invoice' && $row->status === 'Chưa thanh toán');
        $this->assertNotNull($invoice);
        $this->assertDatabaseHas('gym_invoice_lines', ['invoice_id' => $invoice->record_id, 'kind' => 'package']);
        $this->actingAs($customer)->get(route('gym.invoices.index', ['q' => $package->name]))
            ->assertOk()
            ->assertSee('HĐ #'.$invoice->record_id);
    }

    public function test_staff_can_see_online_payment_history_on_an_authorized_invoice(): void
    {
        $staff = User::factory()->employee()->create();
        $customer = User::factory()->create();
        DB::table('gym_online_payments')->insert([
            'target_type' => 'invoice',
            'target_id' => 182,
            'user_id' => $customer->id,
            'txn_ref' => 'INVOICE-STAFF-HISTORY-182',
            'amount' => 100000,
            'status' => 'succeeded',
            'expires_at' => now()->addMinutes(15),
            'paid_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($staff)->get(route('gym.invoices.show', 182))
            ->assertOk()
            ->assertSee('INVOICE-STAFF-HISTORY-182');
    }
}
