<?php

namespace Tests\Feature;

use App\Models\GymPackage;
use App\Models\MembershipOrder;
use App\Models\User;
use App\Services\GymCommerceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class VnPayPaymentFlowTest extends TestCase
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

    public function test_valid_ipn_marks_membership_order_paid_and_duplicate_ipn_does_not_create_another_membership(): void
    {
        $customer = User::factory()->create();
        $package = GymPackage::factory()->create(['price' => 399000, 'duration_months' => 1]);
        $order = MembershipOrder::factory()->for($customer)->for($package, 'gymPackage')->create(['amount' => 399000]);

        $start = $this->actingAs($customer)->post(route('my.orders.vnpay', $order))->assertRedirect();
        $payment = DB::table('gym_online_payments')->first();
        $this->assertNotNull($payment);
        $this->assertStringStartsWith('https://sandbox.vnpayment.vn/', $start->headers->get('Location'));

        $callback = $this->signedIpn($payment->txn_ref, 39900000);
        $this->get(route('vnpay.ipn', $callback))->assertOk()->assertJsonPath('RspCode', '00');
        $this->get(route('vnpay.ipn', $callback))->assertOk()->assertJsonPath('RspCode', '00');

        $this->assertDatabaseHas('gym_online_payments', ['id' => $payment->id, 'status' => 'succeeded']);
        $this->assertSame(MembershipOrder::STATUS_PAID, $order->fresh()->status);
        $this->assertSame(1, DB::table('memberships')->where('membership_order_id', $order->id)->count());
        $this->assertSame(399000, $order->fresh()->receivedAmount());
    }

    public function test_public_package_checkout_does_not_offer_vnpay_until_gateway_credentials_are_configured(): void
    {
        $customer = User::factory()->create();
        $package = GymPackage::factory()->create(['is_active' => true]);
        $this->actingAs($customer)->post(route('gym.cart.add'), [
            'kind' => 'package',
            'item' => $package->sql_package_id,
            'quantity' => 1,
        ])->assertSessionHasNoErrors();
        Config::set('vnpay.tmn_code', '');
        Config::set('vnpay.hash_secret', '');

        $this->get(route('gym.cart'))
            ->assertOk()
            ->assertDontSee('Thanh toán online qua VNPay');

        Config::set('vnpay.tmn_code', 'TESTGYM01');
        Config::set('vnpay.hash_secret', 'test-signing-secret');

        $this->get(route('gym.cart'))
            ->assertOk()
            ->assertSee('Thanh toán online qua VNPay');
    }

    public function test_return_url_cannot_confirm_payment_and_invalid_ipn_signature_cannot_change_it(): void
    {
        $customer = User::factory()->create();
        $package = GymPackage::factory()->create(['price' => 250000]);
        $order = MembershipOrder::factory()->for($customer)->for($package, 'gymPackage')->create(['amount' => 250000]);

        $this->actingAs($customer)->post(route('my.orders.vnpay', $order))->assertRedirect();
        $payment = DB::table('gym_online_payments')->first();
        $this->get(route('vnpay.return', ['vnp_TxnRef' => $payment->txn_ref, 'vnp_ResponseCode' => '00']))->assertOk();
        $this->assertSame('pending', DB::table('gym_online_payments')->where('id', $payment->id)->value('status'));

        $invalidCallback = $this->signedIpn($payment->txn_ref, 25000000);
        $invalidCallback['vnp_SecureHash'] = str_repeat('0', 128);
        $this->get(route('vnpay.ipn', $invalidCallback))->assertOk()->assertJsonPath('RspCode', '97');
        $this->assertSame('pending', DB::table('gym_online_payments')->where('id', $payment->id)->value('status'));
        $this->assertSame(MembershipOrder::STATUS_PENDING, $order->fresh()->status);
    }

    public function test_signed_ipn_with_wrong_amount_is_rejected_and_order_stays_pending(): void
    {
        $customer = User::factory()->create();
        $package = GymPackage::factory()->create(['price' => 250000]);
        $order = MembershipOrder::factory()->for($customer)->for($package, 'gymPackage')->create(['amount' => 250000]);

        $this->actingAs($customer)->post(route('my.orders.vnpay', $order))->assertRedirect();
        $payment = DB::table('gym_online_payments')->first();
        $callback = $this->signedIpn($payment->txn_ref, 24999900);

        $this->get(route('vnpay.ipn', $callback))->assertOk()->assertJsonPath('RspCode', '04');
        $this->assertSame('pending', DB::table('gym_online_payments')->where('id', $payment->id)->value('status'));
        $this->assertSame(MembershipOrder::STATUS_PENDING, $order->fresh()->status);
    }

    public function test_paid_callback_after_customer_requests_cancellation_is_held_for_manual_review(): void
    {
        $staff = User::factory()->employee()->create();
        $customer = User::factory()->create();
        $package = GymPackage::factory()->create(['price' => 250000]);
        $order = MembershipOrder::factory()->for($customer)->for($package, 'gymPackage')->create(['amount' => 250000]);

        $this->actingAs($customer)->post(route('my.orders.vnpay', $order))->assertRedirect();
        $payment = DB::table('gym_online_payments')->first();
        $order->update(['cancellation_requested_at' => now()]);
        $callback = $this->signedIpn($payment->txn_ref, 25000000);

        $this->get(route('vnpay.ipn', $callback))->assertOk()->assertJsonPath('RspCode', '00');
        $this->assertSame('review', DB::table('gym_online_payments')->where('id', $payment->id)->value('status'));
        $this->assertSame('Khách đã gửi yêu cầu hủy đơn.', DB::table('gym_online_payments')->where('id', $payment->id)->value('review_reason'));
        $this->assertSame(MembershipOrder::STATUS_PENDING, $order->fresh()->status);
        $this->assertDatabaseCount('memberships', 0);
        $this->actingAs($staff)->get(route('staff.orders.index'))
            ->assertOk()
            ->assertSee('Khách đã gửi yêu cầu hủy đơn.');
    }

    public function test_successful_invoice_ipn_confirms_order_and_deducts_reserved_stock_once(): void
    {
        DB::table('SANPHAM')->insert([
            'MASP' => 9911,
            'TENSP' => 'Bình nước VNPay',
            'MALOAISP' => 3,
            'DONGIA' => 120000,
            'SOLUONGTON' => 5,
        ]);
        $customer = User::factory()->create();

        $this->actingAs($customer)->post(route('gym.cart.add'), ['kind' => 'product', 'item' => 9911, 'quantity' => 2]);
        $this->post(route('gym.checkout'), [
            'token' => (string) Str::uuid(),
            'mode' => 'pickup',
            'payment_method' => 'vnpay',
        ])->assertSessionHasNoErrors();
        $invoice = (int) DB::table('HOADON')->where('MAKH', $customer->sql_customer_id)->max('MAHD');

        $this->post(route('gym.invoices.vnpay', $invoice))->assertRedirect();
        $payment = DB::table('gym_online_payments')->first();
        $callback = $this->signedIpn($payment->txn_ref, 24000000);
        $this->get(route('vnpay.ipn', $callback))->assertOk()->assertJsonPath('RspCode', '00');
        $this->get(route('vnpay.ipn', $callback))->assertOk()->assertJsonPath('RspCode', '00');

        $this->assertDatabaseHas('HOADON', ['MAHD' => $invoice, 'TRANGTHAI' => 'Đã thanh toán']);
        $this->assertDatabaseHas('SANPHAM', ['MASP' => 9911, 'SOLUONGTON' => 3]);
        $this->assertSame(1, DB::table('CHITIETHOADON')->where('MAHD', $invoice)->count());
        $this->assertSame(240000, app(GymCommerceService::class)->received($invoice));
    }

    /** @return array<string, string|int> */
    private function signedIpn(string $reference, int $amountInCents): array
    {
        $parameters = [
            'vnp_Amount' => $amountInCents,
            'vnp_BankCode' => 'NCB',
            'vnp_CardType' => 'ATM',
            'vnp_OrderInfo' => 'Thanh toan The Gym',
            'vnp_PayDate' => now()->format('YmdHis'),
            'vnp_ResponseCode' => '00',
            'vnp_TmnCode' => 'TESTGYM01',
            'vnp_TransactionNo' => '123456789',
            'vnp_TransactionStatus' => '00',
            'vnp_TxnRef' => $reference,
        ];
        ksort($parameters);
        $signedData = http_build_query($parameters, '', '&', PHP_QUERY_RFC1738);
        $parameters['vnp_SecureHash'] = hash_hmac('sha512', $signedData, 'test-signing-secret');

        return $parameters;
    }
}
