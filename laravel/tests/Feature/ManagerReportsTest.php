<?php

namespace Tests\Feature;

use App\Models\GymPackage;
use App\Models\MembershipOrder;
use App\Models\OrderPayment;
use App\Models\User;
use App\Services\GymCommerceService;
use App\Services\ManagerReportService;
use App\Services\MembershipOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ManagerReportsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function manager_report_summary_matches_detail_totals_without_loading_detail_collections(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-15 12:00:00', 'Asia/Ho_Chi_Minh'));
        $manager = User::factory()->manager()->create();
        $employee = User::factory()->employee()->create();
        $customer = User::factory()->create();
        $package = GymPackage::factory()->create();
        $paidOrder = MembershipOrder::factory()->paid()->for($customer)->for($package, 'gymPackage')->create([
            'amount' => 300000,
            'created_at' => Carbon::parse('2026-10-10 09:00:00', 'Asia/Ho_Chi_Minh'),
            'paid_at' => Carbon::parse('2026-10-11 09:00:00', 'Asia/Ho_Chi_Minh'),
        ]);
        $payment = OrderPayment::factory()->for($paidOrder, 'order')->create([
            'received_by' => $employee->id,
            'amount' => 100000,
            'payment_method' => 'cash',
            'received_at' => Carbon::parse('2026-10-12 09:00:00', 'Asia/Ho_Chi_Minh'),
        ]);
        MembershipOrder::factory()->for($customer)->for($package, 'gymPackage')->create([
            'amount' => 200000,
            'created_at' => Carbon::parse('2026-10-13 09:00:00', 'Asia/Ho_Chi_Minh'),
        ]);
        DB::table('gym_refunds')->insert([
            'source_type' => 'order_payment',
            'source_id' => $payment->id,
            'target_type' => 'membership_order',
            'target_id' => $paidOrder->id,
            'amount' => 25000,
            'method' => 'cash',
            'reference' => 'SUMMARY-REFUND-1',
            'reason' => 'Test tổng hợp báo cáo.',
            'recorded_by' => $manager->id,
            'token' => (string) Str::uuid(),
            'refunded_at' => Carbon::parse('2026-10-14 09:00:00', 'Asia/Ho_Chi_Minh'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $details = app(ManagerReportService::class)->report('2026-10-01', '2026-10-31');
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = strtolower($query->sql);
        });

        $summary = app(ManagerReportService::class)->summary('2026-10-01', '2026-10-31');

        foreach (['from_date', 'to_date', 'order_counts', 'confirmed_revenue', 'receipts_total', 'refunds_total', 'receipts_net', 'payment_review_total', 'receipts_by_method'] as $key) {
            $this->assertSame($details[$key], $summary[$key]);
        }
        $this->assertArrayNotHasKey('orders', $summary);
        $this->assertArrayNotHasKey('invoice_orders', $summary);
        $this->assertArrayNotHasKey('payments', $summary);
        $this->assertArrayNotHasKey('invoice_receipts', $summary);
        $this->assertArrayNotHasKey('online_payments', $summary);
        $this->assertArrayNotHasKey('refunds', $summary);
        $this->assertNotContains(true, array_map(fn (string $query): bool => str_contains($query, '`membership_orders`.*'), $queries));
        $this->assertNotContains(true, array_map(fn (string $query): bool => str_contains($query, '`order_payments`.*'), $queries));
        $this->assertNotContains(true, array_map(fn (string $query): bool => str_contains($query, '`gym_online_payments`.*'), $queries));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    #[Test]
    public function manager_sees_orders_confirmed_and_receipts_in_the_selected_local_period(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-21 09:00:00', 'Asia/Ho_Chi_Minh'));

        $manager = User::factory()->manager()->create();
        $employee = User::factory()->employee()->create();
        $package = GymPackage::factory()->create();
        $customer = User::factory()->create(['name' => 'Report Member']);
        $baseline = app(ManagerReportService::class)->report('2026-10-01', '2026-10-31');
        $confirmedThisMonth = MembershipOrder::factory()->paid()->for($customer)->for($package, 'gymPackage')->create([
            'amount' => 500000,
            'created_at' => Carbon::parse('2026-09-30 23:00:00', 'Asia/Ho_Chi_Minh'),
            'paid_at' => Carbon::parse('2026-10-01 00:00:00', 'Asia/Ho_Chi_Minh'),
        ]);
        MembershipOrder::factory()->paid()->for($customer)->for($package, 'gymPackage')->create([
            'amount' => 300000,
            'paid_at' => Carbon::parse('2026-09-30 23:59:59', 'Asia/Ho_Chi_Minh'),
        ]);
        $pending = MembershipOrder::factory()->for($customer)->for($package, 'gymPackage')->create([
            'amount' => 200000,
            'created_at' => Carbon::parse('2026-10-31 23:59:59', 'Asia/Ho_Chi_Minh'),
        ]);

        OrderPayment::factory()->for($confirmedThisMonth, 'order')->create([
            'received_by' => $employee->id,
            'amount' => 100000,
            'payment_method' => 'cash',
            'received_at' => Carbon::parse('2026-10-01 00:00:00', 'Asia/Ho_Chi_Minh'),
        ]);
        OrderPayment::factory()->for($confirmedThisMonth, 'order')->create([
            'received_by' => $employee->id,
            'amount' => 250000,
            'payment_method' => 'bank_transfer',
            'received_at' => Carbon::parse('2026-10-31 23:59:59', 'Asia/Ho_Chi_Minh'),
        ]);
        OrderPayment::factory()->for($confirmedThisMonth, 'order')->create([
            'received_by' => $employee->id,
            'amount' => 150000,
            'payment_method' => 'cash',
            'received_at' => Carbon::parse('2026-11-01 00:00:00', 'Asia/Ho_Chi_Minh'),
        ]);

        $this->actingAs($manager)
            ->get(route('manager.reports.index', ['from_date' => '2026-10-01', 'to_date' => '2026-10-31']))
            ->assertOk()
            ->assertViewHas('report', function (array $report) use ($baseline): bool {
                $this->assertSame($baseline['confirmed_revenue'] + 500000, $report['confirmed_revenue']);
                $this->assertSame($baseline['receipts_total'] + 350000, $report['receipts_total']);
                $this->assertSame($baseline['receipts_by_method']['cash'] + 100000, $report['receipts_by_method']['cash']);
                $this->assertSame($baseline['receipts_by_method']['bank_transfer'] + 250000, $report['receipts_by_method']['bank_transfer']);
                $this->assertSame($baseline['order_counts']['pending'] + 1, $report['order_counts']['pending']);
                $this->assertArrayNotHasKey('orders', $report);
                $this->assertArrayNotHasKey('invoice_orders', $report);
                $this->assertSame($baseline['order_counts']['pending'] + 1, $report['order_counts']['pending']);

                return true;
            });
    }

    #[Test]
    public function linking_a_paid_legacy_order_keeps_its_receipt_and_confirmation_date_in_reports(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-01 09:00:00', 'Asia/Ho_Chi_Minh'));

        $manager = User::factory()->manager()->create();
        $employee = User::factory()->employee()->create();
        $customer = User::factory()->create();
        $package = GymPackage::factory()->create();
        $baselineOctober = app(ManagerReportService::class)->report('2026-10-01', '2026-10-01');
        $baselineSeptember = app(ManagerReportService::class)->report('2026-09-01', '2026-09-30');
        $order = MembershipOrder::factory()->for($customer)->for($package, 'gymPackage')->create([
            'amount' => 1000,
            'start_date' => '2026-10-01',
            'created_at' => Carbon::parse('2026-09-30 18:00:00', 'Asia/Ho_Chi_Minh'),
            'updated_at' => Carbon::parse('2026-09-30 18:00:00', 'Asia/Ho_Chi_Minh'),
        ]);
        OrderPayment::factory()->for($order, 'order')->create([
            'received_by' => $employee->id,
            'amount' => 1000,
            'received_at' => Carbon::parse('2026-10-01 08:50:00', 'Asia/Ho_Chi_Minh'),
        ]);

        app(MembershipOrderService::class)->confirm($order, $employee, '2026-10-01');

        $invoiceId = (int) $order->fresh()->sql_invoice_id;
        $october = app(ManagerReportService::class)->report('2026-10-01', '2026-10-01');
        $september = app(ManagerReportService::class)->report('2026-09-01', '2026-09-30');

        $this->assertSame($baselineOctober['receipts_total'] + 1000, $october['receipts_total']);
        $this->assertSame($baselineOctober['confirmed_revenue'] + 1000, $october['confirmed_revenue']);
        $this->assertTrue($october['invoice_orders']->contains('id', $invoiceId));
        $this->assertSame($baselineSeptember['receipts_total'], $september['receipts_total']);
        $this->assertSame($baselineSeptember['confirmed_revenue'], $september['confirmed_revenue']);
        $this->assertSame(1000, (int) $october['invoice_orders']->firstWhere('id', $invoiceId)->total_received);
        $this->assertSame(1000, app(GymCommerceService::class)->received($invoiceId));
        $this->actingAs($customer)->get(route('gym.invoices.show', $invoiceId))
            ->assertOk()
            ->assertViewHas('received', 1000)
            ->assertViewHas('remainingAmount', 0);

        $this->actingAs($manager)->get(route('manager.reports.index', ['from_date' => '2026-10-01', 'to_date' => '2026-10-01']))
            ->assertOk()
            ->assertViewHas('report', fn (array $report): bool => $report['receipts_total'] === $baselineOctober['receipts_total'] + 1000);

        $export = $this->get(route('manager.reports.export', ['from_date' => '2026-10-01', 'to_date' => '2026-10-01']))
            ->assertOk();
        $path = tempnam(sys_get_temp_dir(), 'gym-linked-package-');
        file_put_contents($path, $export->streamedContent());
        $spreadsheet = IOFactory::load($path);
        unlink($path);
        $orderSheet = $spreadsheet->getSheet(1);
        $this->assertSame('HD-'.$invoiceId, $orderSheet->getCell('A2')->getValue());
        $this->assertSame(1000, $orderSheet->getCell('J2')->getValue());
        $this->assertSame(1000, $orderSheet->getCell('L2')->getValue());
        $this->assertSame(0, $orderSheet->getCell('M2')->getValue());
        $this->assertSame(1000, $spreadsheet->getSheet(2)->getCell('E2')->getValue());

        $csv = $this->get(route('gym.reports.export', ['from' => '2026-10-01', 'to' => '2026-10-01']))->assertOk();
        $csvRows = array_map(
            fn (string $row): array => str_getcsv($row, ',', '"', '\\'),
            array_filter(explode("\n", trim($csv->streamedContent())))
        );
        $csvInvoice = collect($csvRows)->first(fn (array $row): bool => ($row[0] ?? null) === 'HD-'.$invoiceId);
        $this->assertSame('1000', $csvInvoice[9]);
        $this->assertSame('1000', $csvInvoice[11]);
        $this->assertSame('0', $csvInvoice[12]);
    }

    #[Test]
    public function payment_waiting_for_reconciliation_is_not_treated_as_available_for_an_order_balance(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-01 09:00:00', 'Asia/Ho_Chi_Minh'));

        $customer = User::factory()->create();
        $package = GymPackage::factory()->create();
        $order = MembershipOrder::factory()->for($customer)->for($package, 'gymPackage')->create(['amount' => 1000]);
        DB::table('gym_online_payments')->insert([
            'target_type' => 'membership_order',
            'target_id' => $order->id,
            'user_id' => $customer->id,
            'txn_ref' => 'REVIEW-BALANCE-001',
            'amount' => 1000,
            'status' => 'review',
            'expires_at' => now()->addMinutes(15),
            'paid_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $report = app(ManagerReportService::class)->report('2026-10-01', '2026-10-01');
        $summary = app(ManagerReportService::class)->summary('2026-10-01', '2026-10-01');
        $reportedOrder = $report['orders']->firstWhere('id', $order->id);

        $this->assertSame(1000, (int) $reportedOrder->remaining_amount);
        $this->assertSame(0, (int) $reportedOrder->net_received);
        $this->assertSame(1000, $report['payment_review_total']);
        $this->assertSame($report['payment_review_total'], $summary['payment_review_total']);
        $this->assertSame($report['receipts_total'], $summary['receipts_total']);
    }

    #[Test]
    public function only_managers_can_view_or_export_reports(): void
    {
        $customer = User::factory()->create();
        $employee = User::factory()->employee()->create();

        $this->actingAs($customer)->get(route('manager.reports.index'))->assertForbidden();
        $this->actingAs($employee)->get(route('manager.reports.index'))->assertForbidden();
        $this->actingAs($employee)->get(route('manager.reports.export'))->assertForbidden();
    }

    #[Test]
    public function manager_can_download_xlsx_with_summary_orders_and_receipts(): void
    {
        $manager = User::factory()->manager()->create();
        $employee = User::factory()->employee()->create();
        $package = GymPackage::factory()->create();
        $customer = User::factory()->create(['name' => '=HYPERLINK("https://bad.example")']);
        $order = MembershipOrder::factory()->paid()->for($customer)->for($package, 'gymPackage')->create([
            'amount' => 399000,
            'paid_at' => now(),
        ]);
        OrderPayment::factory()->for($order, 'order')->create([
            'received_by' => $employee->id,
            'amount' => 399000,
            'received_at' => now(),
        ]);

        $response = $this->actingAs($manager)->get(route('manager.reports.export'));

        $response->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $path = tempnam(sys_get_temp_dir(), 'gym-report-');
        file_put_contents($path, $response->streamedContent());
        $spreadsheet = IOFactory::load($path);
        unlink($path);

        $this->assertSame('Tổng quan', $spreadsheet->getSheet(0)->getTitle());
        $this->assertSame('Đơn hàng', $spreadsheet->getSheet(1)->getTitle());
        $this->assertSame('Khoản thu', $spreadsheet->getSheet(2)->getTitle());
        $customerName = $spreadsheet->getSheet(1)->getCell('B2');
        $this->assertSame('=HYPERLINK("https://bad.example")', $customerName->getValue());
        $this->assertSame(DataType::TYPE_STRING, $customerName->getDataType());
    }

    #[Test]
    public function online_membership_and_invoice_payments_appear_in_report_totals_and_excel_history(): void
    {
        $manager = User::factory()->manager()->create();
        $customer = User::factory()->create(['name' => 'VNPay Member']);
        $package = GymPackage::factory()->create();
        $order = MembershipOrder::factory()->paid()->for($customer)->for($package, 'gymPackage')->create(['amount' => 399000]);
        DB::table('gym_online_payments')->insert([
            'target_type' => 'membership_order',
            'target_id' => $order->id,
            'user_id' => $customer->id,
            'txn_ref' => 'REPORTMEMBERSHIP001',
            'amount' => 399000,
            'status' => 'succeeded',
            'provider_transaction_no' => 'REPORT-TRANS-1',
            'expires_at' => now()->addMinutes(15),
            'paid_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('SANPHAM')->insert(['MASP' => 9922, 'TENSP' => 'Sản phẩm báo cáo VNPay', 'MALOAISP' => 3, 'DONGIA' => 120000, 'SOLUONGTON' => 4]);
        $this->actingAs($customer)->post(route('gym.cart.add'), ['kind' => 'product', 'item' => 9922, 'quantity' => 2]);
        $this->post(route('gym.checkout'), [
            'token' => (string) Str::uuid(),
            'mode' => 'pickup',
            'payment_method' => 'vnpay',
        ])->assertSessionHasNoErrors();
        $invoice = (int) DB::table('HOADON')->where('MAKH', $customer->sql_customer_id)->max('MAHD');
        DB::table('gym_online_payments')->insert([
            'target_type' => 'invoice',
            'target_id' => $invoice,
            'user_id' => $customer->id,
            'txn_ref' => 'REPORTINVOICE0001',
            'amount' => 240000,
            'status' => 'succeeded',
            'provider_transaction_no' => 'REPORT-TRANS-2',
            'expires_at' => now()->addMinutes(15),
            'paid_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $from = now(config('app.timezone'))->toDateString();
        $report = app(ManagerReportService::class)->report($from, $from);
        $summary = app(ManagerReportService::class)->summary($from, $from);
        $this->assertSame(639000, $report['receipts_total']);
        $this->assertSame(639000, $report['receipts_by_method']['vnpay']);
        $this->assertCount(2, $report['online_payments']);
        $this->assertSame($report['receipts_total'], $summary['receipts_total']);
        $this->assertSame($report['receipts_by_method'], $summary['receipts_by_method']);
        $this->assertSame($report['order_counts'], $summary['order_counts']);

        $response = $this->actingAs($manager)->get(route('manager.reports.export', ['from_date' => $from, 'to_date' => $from]));
        $response->assertOk();
        $path = tempnam(sys_get_temp_dir(), 'gym-report-vnpay-');
        file_put_contents($path, $response->streamedContent());
        $spreadsheet = IOFactory::load($path);
        unlink($path);

        $receipts = $spreadsheet->getSheet(2);
        $this->assertSame('VNPay', $receipts->getCell('D2')->getValue());
        $this->assertSame('Đơn gói #'.$order->id, $receipts->getCell('B2')->getValue());
        $this->assertSame('VNPay', $receipts->getCell('D3')->getValue());
        $this->assertSame('HD-'.$invoice, $receipts->getCell('B3')->getValue());
    }

    #[Test]
    public function report_counts_legacy_and_sql_invoice_orders_once_and_exports_both(): void
    {
        $manager = User::factory()->manager()->create();
        $customer = User::factory()->create(['name' => 'Unified Order Customer']);
        $package = GymPackage::factory()->create(['name' => 'Gói cũ']);
        $from = now(config('app.timezone'))->toDateString();
        $baseline = app(ManagerReportService::class)->report($from, $from);
        MembershipOrder::factory()->paid()->for($customer)->for($package, 'gymPackage')->create([
            'amount' => 500000,
            'paid_at' => now(),
        ]);
        MembershipOrder::factory()->for($customer)->for($package, 'gymPackage')->create([
            'amount' => 100000,
        ]);
        MembershipOrder::factory()->for($customer)->for($package, 'gymPackage')->create([
            'amount' => 100000,
            'sql_invoice_id' => 987654,
        ]);

        DB::table('SANPHAM')->insert(['MASP' => 9923, 'TENSP' => 'Hàng cho báo cáo thống nhất', 'MALOAISP' => 3, 'DONGIA' => 200000, 'SOLUONGTON' => 4]);
        $this->actingAs($customer)->post(route('gym.cart.add'), ['kind' => 'product', 'item' => 9923, 'quantity' => 1]);
        $this->post(route('gym.checkout'), ['token' => (string) Str::uuid(), 'mode' => 'pickup', 'payment_method' => 'cash'])->assertSessionHasNoErrors();
        $invoice = (int) DB::table('HOADON')->where('MAKH', $customer->sql_customer_id)->max('MAHD');
        DB::table('HOADON')->where('MAHD', $invoice)->update(['TRANGTHAI' => 'Đã thanh toán']);
        DB::table('gym_fulfillments')->where('invoice_id', $invoice)->update(['paid_at' => now()]);
        DB::table('membership_orders')->where('sql_invoice_id', 987654)->update(['sql_invoice_id' => $invoice]);

        $report = app(ManagerReportService::class)->report($from, $from);

        $this->assertSame($baseline['order_counts']['pending'] + 1, $report['order_counts']['pending']);
        $this->assertSame($baseline['order_counts']['paid'] + 2, $report['order_counts']['paid']);
        $this->assertSame($baseline['confirmed_revenue'] + 700000, $report['confirmed_revenue']);
        $this->assertCount($baseline['invoice_orders']->count() + 1, $report['invoice_orders']);
        $this->assertCount($baseline['orders']->count() + 2, $report['orders']);

        $response = $this->actingAs($manager)->get(route('manager.reports.export', ['from_date' => $from, 'to_date' => $from]))->assertOk();
        $path = tempnam(sys_get_temp_dir(), 'gym-unified-report-');
        file_put_contents($path, $response->streamedContent());
        $spreadsheet = IOFactory::load($path);
        unlink($path);
        $orders = $spreadsheet->getSheet(1);
        $orderIds = collect(range(2, $orders->getHighestRow()))->map(fn (int $row) => $orders->getCell('A'.$row)->getValue());
        $invoiceRow = collect(range(2, $orders->getHighestRow()))->first(fn (int $row) => $orders->getCell('A'.$row)->getValue() === 'HD-'.$invoice);
        $this->assertTrue($orderIds->contains('HD-'.$invoice));
        $this->assertSame('Unified Order Customer', $orders->getCell('B'.$invoiceRow)->getValue());
        $this->assertSame($report['orders']->count() + $report['invoice_orders']->count() + 1, $orders->getHighestRow());
    }
}
