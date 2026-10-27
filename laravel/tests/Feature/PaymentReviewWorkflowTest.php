<?php

namespace Tests\Feature;

use App\Models\GymPackage;
use App\Models\MembershipOrder;
use App\Models\User;
use App\Services\GymPortalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaymentReviewWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('vnpay.tmn_code', 'TESTGYM01');
        Config::set('vnpay.hash_secret', 'test-signing-secret');
        Config::set('vnpay.url', 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html');
        Config::set('vnpay.return_url', 'http://localhost/thanh-toan/vnpay/ket-qua');
    }

    public function test_manager_can_confirm_a_successful_payment_held_for_manual_review(): void
    {
        $manager = User::factory()->manager()->create();
        $customer = User::factory()->create();
        $package = GymPackage::factory()->create(['price' => 250000]);
        $order = MembershipOrder::factory()->for($customer)->for($package, 'gymPackage')->create(['amount' => 250000]);

        $this->actingAs($customer)->post(route('my.orders.vnpay', $order))->assertRedirect();
        $payment = DB::table('gym_online_payments')->first();
        $order->update(['cancellation_requested_at' => now()]);
        $this->get(route('vnpay.ipn', $this->signedIpn($payment->txn_ref, 25000000)))->assertJsonPath('RspCode', '00');

        $this->actingAs($manager)->get(route('manager.payment-reviews.index'))
            ->assertOk()->assertSee($payment->txn_ref)->assertSee('Khách đã gửi yêu cầu hủy đơn.');
        $this->post(route('manager.payment-reviews.confirm', $payment->id), ['note' => 'Đã đối chiếu giao dịch với ngân hàng.'])
            ->assertRedirect(route('manager.payment-reviews.index'));

        $this->assertDatabaseHas('gym_online_payments', [
            'id' => $payment->id,
            'status' => 'succeeded',
            'review_resolution' => 'confirmed',
            'resolution_note' => 'Đã đối chiếu giao dịch với ngân hàng.',
            'reviewed_by_user_id' => $manager->id,
        ]);
        $this->assertSame(MembershipOrder::STATUS_PAID, $order->fresh()->status);
        $this->assertDatabaseCount('memberships', 1);
    }

    public function test_manager_can_choose_a_new_start_date_for_a_late_membership_payment(): void
    {
        $manager = User::factory()->manager()->create();
        $customer = User::factory()->create();
        $package = GymPackage::factory()->create();
        $order = MembershipOrder::factory()->for($customer)->for($package, 'gymPackage')->create([
            'amount' => 250000, 'start_date' => today()->subDay(),
        ]);
        $paymentId = $this->reviewPayment($customer, 'membership_order', $order->id, 250000);
        $newDate = today()->addDay()->toDateString();

        $this->actingAs($manager)->post(route('manager.payment-reviews.confirm', $paymentId), [
            'note' => 'Đã đối chiếu và thống nhất ngày mới.', 'start_date' => $newDate,
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('membership_orders', ['id' => $order->id, 'status' => 'paid', 'start_date' => $newDate]);
        $this->assertDatabaseHas('memberships', ['membership_order_id' => $order->id, 'starts_on' => $newDate]);
    }

    public function test_manager_can_record_an_external_refund_and_payment_no_longer_blocks_retry(): void
    {
        $manager = User::factory()->manager()->create();
        $customer = User::factory()->create();
        $package = GymPackage::factory()->create(['price' => 250000]);
        $order = MembershipOrder::factory()->for($customer)->for($package, 'gymPackage')->create(['amount' => 250000]);
        $paymentId = $this->reviewPayment($customer, 'membership_order', $order->id, 250000);

        $this->actingAs($customer)->post(route('my.orders.vnpay', $order))->assertSessionHasErrors('payment');
        $this->actingAs($manager)->post(route('manager.payment-reviews.refund', $paymentId), [
            'reference' => 'REFUND-12345',
            'note' => 'Đã hoàn khoản tiền qua ngân hàng.',
        ])->assertRedirect(route('manager.payment-reviews.index'));

        $this->assertDatabaseHas('gym_online_payments', [
            'id' => $paymentId,
            'status' => 'refunded',
            'review_resolution' => 'refunded',
            'resolution_reference' => 'REFUND-12345',
            'resolution_note' => 'Đã hoàn khoản tiền qua ngân hàng.',
        ]);
        $this->assertDatabaseHas('gym_refunds', [
            'source_type' => 'online_payment', 'source_id' => $paymentId,
            'target_type' => 'membership_order', 'target_id' => $order->id,
            'amount' => 250000, 'reference' => 'REFUND-12345',
        ]);
        $this->assertSame(MembershipOrder::STATUS_PENDING, $order->fresh()->status);
        $this->assertDatabaseCount('memberships', 0);
        $this->actingAs($customer)->post(route('my.orders.vnpay', $order))->assertRedirect();
        $this->assertDatabaseHas('gym_online_payments', ['target_type' => 'membership_order', 'target_id' => $order->id, 'status' => 'pending']);
        $this->get(route('vnpay.ipn', $this->signedIpn(DB::table('gym_online_payments')->where('id', $paymentId)->value('txn_ref'), 25000000)))
            ->assertOk()->assertJsonPath('RspCode', '00');
        $this->assertDatabaseHas('gym_online_payments', ['id' => $paymentId, 'status' => 'refunded', 'review_resolution' => 'refunded']);
    }

    public function test_only_managers_can_resolve_review_and_reviewed_invoice_is_not_expired_automatically(): void
    {
        $employee = User::factory()->employee()->create();
        $customer = User::factory()->create();
        DB::table('SANPHAM')->insert(['MASP' => 9901, 'TENSP' => 'Sản phẩm đối soát', 'MALOAISP' => 3, 'DONGIA' => 1000, 'SOLUONGTON' => 5]);
        $this->actingAs($customer)->post(route('gym.cart.add'), ['kind' => 'product', 'item' => 9901, 'quantity' => 1]);
        $this->post(route('gym.checkout'), ['token' => (string) Str::uuid(), 'mode' => 'pickup', 'payment_method' => 'vnpay'])->assertSessionHasNoErrors();
        $invoice = (int) DB::table('HOADON')->where('MAKH', $customer->sql_customer_id)->max('MAHD');
        $paymentId = $this->reviewPayment($customer, 'invoice', $invoice, 1000);
        DB::table('gym_fulfillments')->where('invoice_id', $invoice)->update(['expires_at' => now()->subMinute()]);

        $this->actingAs($employee)->get(route('manager.payment-reviews.index'))->assertForbidden();
        $this->post(route('manager.payment-reviews.confirm', $paymentId), ['note' => 'Không được phép'])->assertForbidden();
        $this->actingAs($customer)->post(route('gym.invoices.vnpay', $invoice))->assertSessionHasErrors('payment');
        $this->assertSame(0, app(GymPortalService::class)->expireOrders());
        $this->assertDatabaseHas('HOADON', ['MAHD' => $invoice, 'TRANGTHAI' => 'Chưa thanh toán']);
    }

    public function test_staff_cannot_collect_more_money_while_vnpay_payment_is_under_review(): void
    {
        $employee = User::factory()->employee()->create();
        $customer = User::factory()->create();
        DB::table('SANPHAM')->insert(['MASP' => 9911, 'TENSP' => 'Hàng chờ đối soát', 'MALOAISP' => 3, 'DONGIA' => 1000, 'SOLUONGTON' => 5]);
        $this->actingAs($customer)->post(route('gym.cart.add'), ['kind' => 'product', 'item' => 9911, 'quantity' => 1]);
        $this->post(route('gym.checkout'), ['token' => (string) Str::uuid(), 'mode' => 'pickup', 'payment_method' => 'vnpay'])->assertSessionHasNoErrors();
        $invoice = (int) DB::table('HOADON')->where('MAKH', $customer->sql_customer_id)->max('MAHD');
        $this->reviewPayment($customer, 'invoice', $invoice, 1000);

        $this->actingAs($employee)->get(route('gym.invoices.show', $invoice))->assertOk()
            ->assertSee('đang chờ quản lý đối soát')->assertDontSee('Ghi nhận khoản thu');
        $this->actingAs($employee)->post(route('gym.invoices.receive', $invoice), [
            'amount' => 1000, 'method' => 'cash', 'token' => (string) Str::uuid(),
        ])->assertSessionHasErrors('payment');
        $this->assertSame(0, DB::table('gym_receipts')->where('invoice_id', $invoice)->count());

        $order = MembershipOrder::factory()->for($customer)->create(['amount' => 1000]);
        $this->reviewPayment($customer, 'membership_order', $order->id, 1000);
        $this->post(route('staff.orders.payments.store', $order), ['amount' => 1000, 'payment_method' => 'cash'])
            ->assertSessionHasErrors('payment');
        $this->assertDatabaseMissing('order_payments', ['membership_order_id' => $order->id]);
    }

    public function test_staff_must_explicitly_resume_a_package_order_after_customer_requests_cancellation(): void
    {
        $customer = User::factory()->create();
        $employee = User::factory()->employee()->create();
        $order = MembershipOrder::factory()->for($customer)->create(['amount' => 1000]);

        $this->actingAs($customer)->post(route('my.orders.cancel-request', $order))->assertSessionHasNoErrors();
        $this->actingAs($employee)->post(route('staff.orders.payments.store', $order), [
            'amount' => 1000, 'payment_method' => 'cash',
        ])->assertSessionHasErrors('order');
        $this->get(route('staff.orders.show', $order))
            ->assertSee('Xác nhận tiếp tục đơn')
            ->assertDontSee('Ghi nhận tiền đã nhận');
        $this->post(route('staff.orders.confirm', $order), [
            'start_date' => now()->toDateString(),
        ])->assertSessionHasErrors('order');
        $this->assertDatabaseMissing('order_payments', ['membership_order_id' => $order->id]);

        $this->post('/staff/don-hang/'.$order->id.'/tiep-tuc')->assertSessionHasNoErrors();
        $this->assertDatabaseHas('membership_orders', ['id' => $order->id, 'cancellation_requested_at' => null]);
        $this->post(route('staff.orders.payments.store', $order), [
            'amount' => 1000, 'payment_method' => 'cash',
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('order_payments', ['membership_order_id' => $order->id, 'amount' => 1000]);
    }

    public function test_manager_can_confirm_a_late_invoice_payment_and_fulfill_its_reserved_product(): void
    {
        $manager = User::factory()->manager()->create();
        $customer = User::factory()->create();
        DB::table('SANPHAM')->insert(['MASP' => 9902, 'TENSP' => 'Sản phẩm thanh toán trễ', 'MALOAISP' => 3, 'DONGIA' => 1000, 'SOLUONGTON' => 2]);
        $this->actingAs($customer)->post(route('gym.cart.add'), ['kind' => 'product', 'item' => 9902, 'quantity' => 1]);
        $this->post(route('gym.checkout'), ['token' => (string) Str::uuid(), 'mode' => 'pickup', 'payment_method' => 'vnpay'])->assertSessionHasNoErrors();
        $invoice = (int) DB::table('HOADON')->where('MAKH', $customer->sql_customer_id)->max('MAHD');
        $paymentId = $this->reviewPayment($customer, 'invoice', $invoice, 1000);
        DB::table('gym_fulfillments')->where('invoice_id', $invoice)->update(['expires_at' => now()->subMinute()]);

        $this->actingAs($manager)->post(route('manager.payment-reviews.confirm', $paymentId), [
            'reference' => 'BANK-VERIFY-9902',
            'note' => 'Đã xác minh khoản tiền vào tài khoản.',
        ])->assertRedirect(route('manager.payment-reviews.index'));

        $this->assertDatabaseHas('gym_online_payments', ['id' => $paymentId, 'status' => 'succeeded', 'review_resolution' => 'confirmed']);
        $this->assertDatabaseHas('HOADON', ['MAHD' => $invoice, 'TRANGTHAI' => 'Đã thanh toán']);
        $this->assertDatabaseHas('SANPHAM', ['MASP' => 9902, 'SOLUONGTON' => 1]);
    }

    private function reviewPayment(User $customer, string $targetType, int $targetId, int $amount): int
    {
        return (int) DB::table('gym_online_payments')->insertGetId([
            'target_type' => $targetType,
            'target_id' => $targetId,
            'user_id' => $customer->id,
            'txn_ref' => 'REVIEW'.Str::upper(Str::random(12)),
            'amount' => $amount,
            'status' => 'review',
            'review_reason' => 'Cần đối chiếu thủ công.',
            'expires_at' => now()->addMinutes(15),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @return array<string, string|int> */
    private function signedIpn(string $reference, int $amountInCents): array
    {
        $parameters = [
            'vnp_Amount' => $amountInCents,
            'vnp_BankCode' => 'NCB',
            'vnp_OrderInfo' => 'Thanh toan The Gym',
            'vnp_ResponseCode' => '00',
            'vnp_TmnCode' => 'TESTGYM01',
            'vnp_TransactionNo' => '123456789',
            'vnp_TransactionStatus' => '00',
            'vnp_TxnRef' => $reference,
        ];
        ksort($parameters);
        $parameters['vnp_SecureHash'] = hash_hmac('sha512', http_build_query($parameters, '', '&', PHP_QUERY_RFC1738), 'test-signing-secret');

        return $parameters;
    }
}
