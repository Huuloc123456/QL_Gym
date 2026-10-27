<?php

namespace Tests\Feature;

use App\Models\MembershipOrder;
use App\Models\OrderPayment;
use App\Models\User;
use App\Services\ManagerReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ManualRefundWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_can_record_a_refund_of_a_partial_receipt_and_staff_can_cancel_the_order(): void
    {
        $customer = User::factory()->create();
        $staff = User::factory()->employee()->create();
        $manager = User::factory()->manager()->create();
        DB::table('SANPHAM')->insert(['MASP' => 9001, 'TENSP' => 'Hàng kiểm thử', 'MALOAISP' => 3, 'DONGIA' => 2000, 'SOLUONGTON' => 5]);
        $this->actingAs($customer)->post(route('gym.cart.add'), ['kind' => 'product', 'item' => 9001, 'quantity' => 1])->assertSessionHasNoErrors();
        $this->post(route('gym.checkout'), ['token' => (string) Str::uuid(), 'mode' => 'pickup', 'payment_method' => 'cash'])->assertSessionHasNoErrors();
        $invoice = (int) DB::table('HOADON')->where('MAKH', $customer->sql_customer_id)->max('MAHD');
        $this->actingAs($staff)->post(route('gym.invoices.receive', $invoice), ['amount' => 500, 'method' => 'cash', 'token' => (string) Str::uuid()])->assertSessionHasNoErrors();
        $receipt = DB::table('gym_receipts')->where('invoice_id', $invoice)->value('id');
        $input = ['source_type' => 'receipt', 'source_id' => $receipt, 'amount' => 500, 'reference' => 'QA-REFUND-1', 'reason' => 'Khách yêu cầu hủy', 'token' => (string) Str::uuid()];

        $this->post('/quan-ly/hoan-tien', $input)->assertForbidden();
        $this->actingAs($manager)->get(route('gym.invoices.show', $invoice))->assertOk()->assertSee('Ghi nhận hoàn tiền');
        $this->actingAs($manager)->post('/quan-ly/hoan-tien', $input)->assertRedirect()->assertSessionHasNoErrors();
        $this->post('/quan-ly/hoan-tien', $input)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('gym_refunds', 1);
        $this->assertDatabaseHas('gym_refunds', ['source_type' => 'receipt', 'source_id' => $receipt, 'amount' => 500]);
        $this->assertDatabaseHas('gym_receipts', ['id' => $receipt, 'amount' => 500]);
        $report = app(ManagerReportService::class)->report(today()->toDateString(), today()->toDateString());
        $this->assertSame(500, $report['refunds_total']);
        $this->assertSame($report['receipts_total'] - 500, $report['receipts_net']);
        $invoiceRow = $report['invoice_orders']->firstWhere('id', $invoice);
        $this->assertSame(500, (int) $invoiceRow->total_received);
        $this->assertSame(500, (int) $invoiceRow->refunded_total);
        $this->assertSame(0, (int) $invoiceRow->net_received);
        $this->assertSame(2000, (int) $invoiceRow->remaining_amount);
        $this->post('/quan-ly/hoan-tien', array_merge($input, ['token' => (string) Str::uuid()]))->assertSessionHasErrors('reference');
        $this->post('/quan-ly/hoan-tien', array_merge($input, ['token' => (string) Str::uuid(), 'reference' => 'QA-REFUND-2', 'amount' => 1]))->assertSessionHasErrors('amount');
        $this->actingAs($staff)->post(route('gym.invoices.cancel', $invoice))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('HOADON', ['MAHD' => $invoice, 'TRANGTHAI' => 'Hủy']);
        $cancelledReport = app(ManagerReportService::class)->report(today()->toDateString(), today()->toDateString());
        $this->assertSame(0, (int) $cancelledReport['invoice_orders']->firstWhere('id', $invoice)->remaining_amount);
        $this->assertSame(5, (int) DB::table('SANPHAM')->where('MASP', 9001)->value('SOLUONGTON'));
        $this->actingAs($customer)->get(route('gym.invoices.show', $invoice))->assertOk()->assertViewHas('remainingAmount', 0)->assertSee('Lịch sử hoàn tiền');
    }

    public function test_manager_can_record_a_refund_for_a_legacy_pending_package_order(): void
    {
        $manager = User::factory()->manager()->create();
        $staff = User::factory()->employee()->create();
        $order = MembershipOrder::factory()->create(['amount' => 1000, 'status' => 'pending']);
        $payment = OrderPayment::factory()->for($order, 'order')->create(['amount' => 500, 'received_by' => $staff->id]);
        $this->actingAs($manager)->get(route('staff.orders.show', $order))->assertOk()->assertSee('Ghi nhận hoàn tiền');
        $this->post(route('manager.refunds.store'), ['source_type' => 'order_payment', 'source_id' => $payment->id,
            'amount' => 500, 'reference' => 'QA-LEGACY-1', 'reason' => 'Khách yêu cầu hủy', 'token' => (string) Str::uuid()])->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($staff)->post(route('staff.orders.cancel', $order))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('membership_orders', ['id' => $order->id, 'status' => 'cancelled']);
        $this->assertDatabaseHas('order_payments', ['id' => $payment->id, 'amount' => 500]);
    }

    public function test_manager_cannot_record_two_refunds_against_the_same_bank_reference(): void
    {
        $customer = User::factory()->create();
        $staff = User::factory()->employee()->create();
        $manager = User::factory()->manager()->create();
        DB::table('SANPHAM')->insert(['MASP' => 9002, 'TENSP' => 'Hàng hoàn một phần', 'MALOAISP' => 3, 'DONGIA' => 1000, 'SOLUONGTON' => 5]);
        $this->actingAs($customer)->post(route('gym.cart.add'), ['kind' => 'product', 'item' => 9002, 'quantity' => 1]);
        $this->post(route('gym.checkout'), ['token' => (string) Str::uuid(), 'mode' => 'pickup', 'payment_method' => 'cash']);
        $invoice = (int) DB::table('HOADON')->where('MAKH', $customer->sql_customer_id)->max('MAHD');
        $this->actingAs($staff)->post(route('gym.invoices.receive', $invoice), ['amount' => 500, 'method' => 'bank_transfer', 'token' => (string) Str::uuid()]);
        $receipt = DB::table('gym_receipts')->where('invoice_id', $invoice)->value('id');
        $input = ['source_type' => 'receipt', 'source_id' => $receipt, 'amount' => 200, 'reference' => 'BANK-REF-UNIQUE',
            'reason' => 'Hoàn theo yêu cầu khách', 'token' => (string) Str::uuid()];

        $this->actingAs($manager)->post(route('manager.refunds.store'), $input)->assertSessionHasNoErrors();
        $this->post(route('manager.refunds.store'), array_merge($input, ['token' => (string) Str::uuid()]))
            ->assertSessionHasErrors('reference');

        $this->assertDatabaseCount('gym_refunds', 1);
    }
}
