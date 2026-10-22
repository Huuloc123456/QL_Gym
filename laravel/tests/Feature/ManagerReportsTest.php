<?php

namespace Tests\Feature;

use App\Models\GymPackage;
use App\Models\MembershipOrder;
use App\Models\OrderPayment;
use App\Models\User;
use App\Services\ManagerReportService;
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
            ->assertSee('500.000')
            ->assertSee('350.000')
            ->assertViewHas('report', function (array $report) use ($confirmedThisMonth, $pending): bool {
                $this->assertSame(500000, $report['confirmed_revenue']);
                $this->assertSame(350000, $report['receipts_total']);
                $this->assertSame(100000, $report['receipts_by_method']['cash']);
                $this->assertSame(250000, $report['receipts_by_method']['bank_transfer']);
                $this->assertSame(1, $report['order_counts']['pending']);
                $this->assertTrue($report['orders']->contains('id', $confirmedThisMonth->id));
                $this->assertTrue($report['orders']->contains('id', $pending->id));

                return true;
            });
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
        $this->assertSame(639000, $report['receipts_total']);
        $this->assertSame(639000, $report['receipts_by_method']['vnpay']);
        $this->assertCount(2, $report['online_payments']);

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
}
