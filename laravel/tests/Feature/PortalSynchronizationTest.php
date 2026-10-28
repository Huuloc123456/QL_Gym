<?php

namespace Tests\Feature;

use App\Models\GymPackage;
use App\Models\MembershipOrder;
use App\Models\User;
use App\Services\GymCommerceService;
use App\Services\GymIdentityService;
use App\Services\GymPortalService;
use App\Services\ManagerReportService;
use App\Services\MembershipOrderService;
use App\Services\VnPayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class PortalSynchronizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_reserved_product_cannot_be_deleted_and_can_still_be_paid(): void
    {
        $invoice = $this->invoice($this->customer());
        $manager = User::factory()->manager()->create();
        $this->actingAs($manager)->delete(route('gym.admin.destroy', ['products', 9901]))->assertSessionHasErrors('record');
        $this->pay($invoice, $manager);
        $this->assertDatabaseHas('SANPHAM', ['MASP' => 9901, 'SOLUONGTON' => 4]);
        $this->assertDatabaseHas('HOADON', ['MAHD' => $invoice, 'TRANGTHAI' => 'Đã thanh toán']);
    }

    public function test_unavailable_package_keeps_cart_accessible_and_removable(): void
    {
        $customer = $this->customer();
        $package = GymPackage::factory()->create();
        $this->actingAs($customer)->post(route('gym.cart.add'), ['kind' => 'package', 'item' => $package->sql_package_id, 'quantity' => 1]);
        $package->update(['is_active' => false]);
        $this->get(route('gym.cart'))->assertOk()->assertSee('Không còn mở bán');
        $this->post(route('gym.checkout'), $this->checkoutData())->assertSessionHasErrors('cart');
        $line = DB::table('CHITIETGIOHANG')->where('MAKH', $customer->sql_customer_id)->value('MACHITIETGH');
        $this->post(route('gym.cart.change', $line), ['quantity' => 0])->assertSessionHasNoErrors();
        $this->get(route('gym.cart'))->assertOk()->assertSee('Giỏ hàng của bạn đang trống');
    }

    public function test_stock_filter_and_home_exclude_fully_reserved_products(): void
    {
        $this->invoice($this->customer(), 5);
        $this->get(route('gym.products.index', ['q' => 'SYNC Bottle', 'stock' => 1]))->assertOk()->assertViewHas('products', fn ($products) => $products->total() === 0);
        $this->get(route('home'))->assertOk()->assertViewHas('featuredProducts', fn ($products) => ! $products->contains('MASP', 9901));
        $this->get(route('gym.products.show', 9901))->assertOk()->assertViewHas('available', 0);
    }

    public function test_product_revenue_and_order_count_are_included_in_reports(): void
    {
        $this->travelTo('2027-02-01 10:00');
        $invoice = $this->invoice($this->customer());
        $manager = User::factory()->manager()->create();
        $this->pay($invoice, $manager);
        $report = app(ManagerReportService::class)->report('2027-02-01', '2027-02-01');
        $this->assertSame(100000, $report['confirmed_revenue']);
        $this->assertSame(100000, $report['receipts_total']);
        $this->assertSame(1, $report['order_counts']['paid']);
        $this->assertCount(1, $report['invoices']);
        $this->assertCount(0, $report['orders']);
        $csv = $this->actingAs($manager)->get(route('gym.reports.export', ['from_date' => '2027-02-01', 'to_date' => '2027-02-01']))->assertOk()->streamedContent();
        $this->assertStringContainsString('100000', $csv);
        $this->assertSame(2, count(array_filter(explode("\n", trim($csv)))));
        $xlsx = $this->get(route('manager.reports.export', ['from_date' => '2027-02-01', 'to_date' => '2027-02-01']))->assertOk()->streamedContent();
        $path = tempnam(sys_get_temp_dir(), 'sync-report-');
        try {
            file_put_contents($path, $xlsx);
            $sheet = IOFactory::load($path)->getSheet(1);
            $this->assertSame('HD-'.$invoice, $sheet->getCell('A2')->getValue());
            $this->assertEquals(100000, $sheet->getCell('I2')->getValue());
            $this->assertSame(2, $sheet->getHighestDataRow());
        } finally {
            unlink($path);
        }
    }

    public function test_mixed_discounted_invoice_is_canonical_without_double_counting(): void
    {
        $this->travelTo('2027-02-01 10:00');
        $customer = $this->customer(10);
        $package = GymPackage::factory()->create(['price' => 300000]);
        $this->product();
        $shop = app(GymCommerceService::class);
        $shop->add($customer, 'product', 9901, 1);
        $shop->add($customer, 'package', $package->sql_package_id, 1);
        $invoice = $shop->checkout($customer, $this->checkoutData());
        $this->actingAs($customer)->get(route('my.orders.index'))->assertOk()->assertSee('Hóa đơn #'.$invoice)->assertSee('360.000');
        $this->pay($invoice, User::factory()->manager()->create());
        $cache = MembershipOrder::where('sql_invoice_id', $invoice)->firstOrFail();
        $this->get(route('my.orders.show', $cache))->assertRedirect(route('gym.invoices.show', $invoice));
        $this->get(route('gym.invoices.show', $invoice))->assertOk()->assertSee('360.000')->assertSee('Lịch sử thanh toán');
        $report = app(ManagerReportService::class)->report('2027-02-01', '2027-02-01');
        $this->assertSame(360000, $report['confirmed_revenue']);
        $this->assertSame(360000, $report['receipts_total']);
        $this->assertSame(1, $report['order_counts']['paid']);
    }

    public function test_legacy_receipts_remain_visible_on_linked_invoice(): void
    {
        $this->travelTo('2027-02-01 10:00');
        $customer = $this->customer();
        $manager = User::factory()->manager()->create();
        $package = GymPackage::factory()->create(['price' => 100000]);
        $orders = app(MembershipOrderService::class);
        $order = $orders->create($customer, $package, '2027-02-01', 'cash');
        $orders->recordPayment($order, $manager, 100000, 'cash', 'LEGACY-SYNC');
        $orders->confirm($order, $manager, '2027-02-01');
        $invoice = (int) $order->fresh()->sql_invoice_id;
        $this->assertSame(100000, app(GymCommerceService::class)->received($invoice));
        $this->actingAs($customer)->get(route('gym.invoices.show', $invoice))->assertOk()->assertSee('LEGACY-SYNC');
        $report = app(ManagerReportService::class)->report('2027-02-01', '2027-02-01');
        $this->assertSame(100000, $report['confirmed_revenue']);
        $this->assertSame(100000, $report['receipts_total']);
    }

    public function test_review_is_visible_protected_and_confirmed_once_by_manager(): void
    {
        [$customer, $invoice, $payment, $callback] = $this->reviewInvoice();
        $this->actingAs($customer)->post(route('gym.invoices.vnpay', $invoice))->assertSessionHasErrors('payment');
        $this->actingAs(User::factory()->employee()->create())->get(route('gym.invoices.show', $invoice))->assertOk()->assertSee($payment->txn_ref)->assertSee('đang được đối soát');
        $this->post(route('gym.invoices.receive', $invoice), ['amount' => 100000, 'method' => 'cash', 'token' => (string) Str::uuid()])->assertSessionHasErrors('payment');
        $this->post(route('gym.invoices.cancel', $invoice))->assertSessionHasErrors('payment');
        $this->get(route('manager.payment-reviews.index'))->assertForbidden();
        DB::table('gym_fulfillments')->where('invoice_id', $invoice)->update(['expires_at' => now()->subMinute()]);
        $this->assertSame(0, app(GymPortalService::class)->expireOrders());
        $manager = User::factory()->manager()->create();
        $data = ['note' => 'Đã đối chiếu và khách đồng ý tiếp tục'];
        $this->actingAs($manager)->post(route('manager.payment-reviews.confirm', $payment->id), $data)->assertSessionHasNoErrors();
        $this->post(route('manager.payment-reviews.confirm', $payment->id), $data)->assertSessionHasErrors('payment');
        $this->get(route('vnpay.ipn', $callback))->assertJsonPath('RspCode', '00');
        $this->assertDatabaseHas('gym_online_payments', ['id' => $payment->id, 'status' => 'succeeded', 'reviewed_by_user_id' => $manager->id]);
        $this->assertDatabaseHas('SANPHAM', ['MASP' => 9901, 'SOLUONGTON' => 4]);
    }

    public function test_online_payment_restarts_with_the_remaining_amount_after_counter_payment(): void
    {
        config(['vnpay.tmn_code' => 'SYNCTEST', 'vnpay.hash_secret' => 'sync-test-no-real-gateway']);
        $customer = $this->customer();
        $invoice = $this->invoice($customer);
        $gateway = app(VnPayService::class);
        $gateway->startInvoice($invoice, $customer);
        app(GymCommerceService::class)->receive($invoice, User::factory()->employee()->create(), ['amount' => 40000, 'method' => 'cash', 'token' => (string) Str::uuid()]);
        $url = $gateway->startInvoice($invoice, $customer);
        parse_str(parse_url($url, PHP_URL_QUERY), $parameters);
        $this->assertSame('6000000', $parameters['vnp_Amount']);
        $this->assertDatabaseHas('gym_online_payments', ['txn_ref' => $parameters['vnp_TxnRef'], 'amount' => 60000]);
    }

    public function test_external_refund_resolution_requires_evidence_and_no_bank_request(): void
    {
        Http::preventStrayRequests();
        [, $invoice, $payment, $callback] = $this->reviewInvoice();
        $this->actingAs(User::factory()->manager()->create())->post(route('manager.payment-reviews.refund', $payment->id), ['note' => 'Đã hoàn tiền kiểm thử'])->assertSessionHasErrors('reference');
        $this->post(route('manager.payment-reviews.refund', $payment->id), ['note' => 'Đã đối chiếu hoàn tiền bên ngoài', 'reference' => 'REFUND-QA-01'])->assertSessionHasNoErrors();
        $this->get(route('vnpay.ipn', $callback))->assertJsonPath('RspCode', '00');
        $this->assertDatabaseHas('gym_online_payments', ['id' => $payment->id, 'status' => 'refunded']);
        $this->post(route('gym.invoices.cancel', $invoice))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('HOADON', ['MAHD' => $invoice, 'TRANGTHAI' => 'Hủy']);
        Http::assertNothingSent();
    }

    public function test_role_migration_only_updates_matching_admin_and_menus_have_workflow_links(): void
    {
        $row = DB::table('NHANVIEN')->where('TENDANGNHAP', 'admin')->first();
        $admin = app(GymIdentityService::class)->importAccount($row, 'employee');
        $admin->forceFill(['role' => 'employee'])->saveQuietly();
        (require database_path('migrations/2026_10_28_160750_synchronize_imported_admin_web_role.php'))->up();
        $this->assertSame('manager', $admin->fresh()->role);
        $employee = User::factory()->employee()->create();
        $this->actingAs($employee)->get(route('staff.orders.index'))->assertOk()->assertSee(route('staff.products.index'), false)->assertSee(route('gym.invoices.index'), false)->assertSee(route('gym.calendar'), false);
        $this->assertSame('employee', $employee->fresh()->role);
        $this->actingAs($this->customer())->get(route('packages.index'))->assertOk()->assertSee(route('gym.cart'), false)->assertSee(route('gym.invoices.index'), false);
    }

    private function customer(int $discount = 0): User
    {
        $customer = User::factory()->create();
        DB::table('KHACHHANG')->where('MAKH', $customer->sql_customer_id)->update(['MALOAIKH' => DB::table('LOAIKHACHHANG')->where('MUCGIAM', $discount)->value('MALOAIKH')]);

        return $customer;
    }

    private function product(): void
    {
        DB::table('SANPHAM')->insert(['MASP' => 9901, 'TENSP' => 'SYNC Bottle', 'MALOAISP' => 3, 'DONGIA' => 100000, 'SOLUONGTON' => 5]);
    }

    private function invoice(User $customer, int $quantity = 1): int
    {
        $this->product();
        app(GymCommerceService::class)->add($customer, 'product', 9901, $quantity);

        return app(GymCommerceService::class)->checkout($customer, $this->checkoutData());
    }

    private function checkoutData(): array
    {
        return ['token' => (string) Str::uuid(), 'mode' => 'pickup', 'payment_method' => 'cash', 'start_date' => now()->toDateString()];
    }

    private function pay(int $invoice, User $manager): void
    {
        $shop = app(GymCommerceService::class);
        $shop->receive($invoice, $manager, ['amount' => (int) $shop->invoice($invoice)->THANHTIEN, 'method' => 'cash', 'token' => (string) Str::uuid()]);
        $shop->confirm($invoice, $manager);
    }

    private function reviewInvoice(): array
    {
        config(['vnpay.tmn_code' => 'SYNCTEST', 'vnpay.hash_secret' => 'sync-test-no-real-gateway']);
        $customer = $this->customer();
        $invoice = $this->invoice($customer);
        $gateway = app(VnPayService::class);
        $gateway->startInvoice($invoice, $customer);
        $payment = DB::table('gym_online_payments')->where('target_id', $invoice)->where('target_type', 'invoice')->first();
        app(GymCommerceService::class)->cancel($invoice, $customer);
        $callback = ['vnp_TmnCode' => 'SYNCTEST', 'vnp_TxnRef' => $payment->txn_ref, 'vnp_Amount' => 10000000, 'vnp_ResponseCode' => '00', 'vnp_TransactionStatus' => '00', 'vnp_TransactionNo' => (string) random_int(100000000, 999999999)];
        $callback['vnp_SecureHash'] = $gateway->signature($callback);
        $this->get(route('vnpay.ipn', $callback))->assertJsonPath('RspCode', '00');

        return [$customer, $invoice, $payment, $callback];
    }
}
