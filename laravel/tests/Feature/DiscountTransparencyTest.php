<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\GymPortalService;
use App\Services\ManagerReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class DiscountTransparencyTest extends TestCase
{
    use RefreshDatabase;

    private function addProduct(int $id = 9901): void
    {
        DB::table('SANPHAM')->insert([
            'MASP' => $id,
            'TENSP' => 'Sản phẩm minh bạch giá',
            'MALOAISP' => 3,
            'DONGIA' => 1200,
            'GIAKHUYENMAI' => 1000,
            'SOLUONGTON' => 8,
        ]);
    }

    private function checkoutToken(User $customer): string
    {
        return (string) $this->actingAs($customer)->get(route('gym.cart'))->assertOk()->viewData('checkoutToken');
    }

    public function test_student_discount_requires_manager_verification_and_is_snapshotted_on_the_invoice(): void
    {
        $this->addProduct();
        $customer = User::factory()->create();
        $tier = DB::table('LOAIKHACHHANG')->where('TENLOAI', 'Học sinh, Sinh viên')->first();
        $this->assertNotNull($tier);
        DB::table('KHACHHANG')->where('MAKH', $customer->sql_customer_id)->update(['MALOAIKH' => $tier->MALOAIKH]);
        $this->actingAs($customer)->post(route('gym.cart.add'), ['kind' => 'product', 'item' => 9901, 'quantity' => 2]);

        $quoteToken = $this->checkoutToken($customer);
        $this->get(route('gym.cart'))->assertSee('chưa được áp dụng')->assertSee('0đ');

        $manager = User::factory()->manager()->create();
        $this->actingAs($manager)->post(route('gym.admin.customers.verify-tier', $customer->sql_customer_id), [
            'verified' => 1,
            'reason' => 'Đã kiểm tra thẻ sinh viên tại quầy',
        ])->assertSessionHasNoErrors();
        $quoteToken = $this->checkoutToken($customer);

        $checkoutResponse = $this->actingAs($customer)->post(route('gym.checkout'), [
            'token' => $quoteToken,
            'mode' => 'pickup',
            'payment_method' => 'cash',
        ])->assertSessionHasNoErrors();
        $this->assertSame(302, $checkoutResponse->getStatusCode());
        $this->assertStringContainsString('/hoa-don/', (string) $checkoutResponse->headers->get('Location'));

        $invoice = (int) DB::table('gym_fulfillments')->where('checkout_token', $quoteToken)->value('invoice_id');
        $this->assertGreaterThan(0, $invoice);
        $this->assertDatabaseHas('HOADON', ['MAHD' => $invoice, 'TONGTIEN' => 2000, 'GIAMGIA' => 100, 'THANHTIEN' => 1900]);
        $this->assertDatabaseHas('gym_invoice_lines', ['invoice_id' => $invoice, 'unit_price' => 1000, 'original_unit_price' => 1200]);
        $this->assertDatabaseHas('gym_invoice_discounts', [
            'invoice_id' => $invoice,
            'source_type' => 'customer_tier',
            'tier_id' => $tier->MALOAIKH,
            'tier_name' => 'Học sinh, Sinh viên',
            'rate_percent' => $tier->MUCGIAM,
            'base_amount' => 2000,
            'discount_amount' => 100,
        ]);
        $this->assertDatabaseHas('gym_fulfillments', ['invoice_id' => $invoice, 'pricing_snapshot_version' => 1]);

        $this->get(route('gym.invoices.show', $invoice))->assertOk()
            ->assertSee('Học sinh, Sinh viên')
            ->assertSee('Giá khuyến mãi')
            ->assertSee('1.200đ');
    }

    public function test_checkout_requires_review_when_the_displayed_product_price_changes(): void
    {
        $this->addProduct();
        $customer = User::factory()->create();
        $this->actingAs($customer)->post(route('gym.cart.add'), ['kind' => 'product', 'item' => 9901, 'quantity' => 1]);
        $quoteToken = $this->checkoutToken($customer);
        DB::table('SANPHAM')->where('MASP', 9901)->update(['GIAKHUYENMAI' => 800]);

        $this->from(route('gym.cart'))->actingAs($customer)->post(route('gym.checkout'), [
            'token' => $quoteToken,
            'mode' => 'pickup',
            'payment_method' => 'cash',
        ])->assertSessionHasErrors('pricing_changed');

        $this->assertDatabaseMissing('HOADON', ['MAKH' => $customer->sql_customer_id]);
        $this->assertDatabaseHas('CHITIETGIOHANG', ['MAKH' => $customer->sql_customer_id, 'MASP' => 9901]);
    }

    public function test_checkout_requires_review_when_cart_quantity_changes_after_quote(): void
    {
        $this->addProduct();
        $customer = User::factory()->create();
        $this->actingAs($customer)->post(route('gym.cart.add'), ['kind' => 'product', 'item' => 9901, 'quantity' => 1]);
        $quoteToken = $this->checkoutToken($customer);
        $lineId = (int) DB::table('CHITIETGIOHANG')->where('MAKH', $customer->sql_customer_id)->value('MACHITIETGH');
        $this->actingAs($customer)->post(route('gym.cart.change', $lineId), ['quantity' => 2]);

        $this->from(route('gym.cart'))->actingAs($customer)->post(route('gym.checkout'), [
            'token' => $quoteToken,
            'mode' => 'pickup',
            'payment_method' => 'cash',
        ])->assertSessionHasErrors('pricing_changed');

        $this->assertDatabaseMissing('HOADON', ['MAKH' => $customer->sql_customer_id]);
    }

    public function test_checkout_requires_review_when_delivery_fee_changes_after_quote(): void
    {
        $this->addProduct();
        $customer = User::factory()->create();
        $this->actingAs($customer)->post(route('gym.cart.add'), ['kind' => 'product', 'item' => 9901, 'quantity' => 1]);
        $addressId = (int) DB::table('DIACHI')->insertGetId([
            'MAKH' => $customer->sql_customer_id,
            'TINHTHANHPHO' => 'TP.HCM',
            'QUANHUYEN' => 'Quận 1',
            'PHUONGXA' => 'Phường Bến Nghé',
            'DIACHICUTHE' => '1 Đường QA',
            'LADIACHIMACDINH' => 1,
            'NGAYTHEM' => now(),
        ]);
        DB::table('gym_settings')->where('id', 1)->update(['shipping_fee' => 25000]);
        $quoteToken = $this->checkoutToken($customer);
        DB::table('gym_settings')->where('id', 1)->update(['shipping_fee' => 30000]);

        $this->from(route('gym.cart'))->actingAs($customer)->post(route('gym.checkout'), [
            'token' => $quoteToken,
            'mode' => 'delivery',
            'address_id' => $addressId,
            'recipient' => $customer->name,
            'phone' => $customer->phone,
            'payment_method' => 'cash',
        ])->assertSessionHasErrors('pricing_changed');

        $this->assertDatabaseMissing('HOADON', ['MAKH' => $customer->sql_customer_id]);
    }

    public function test_new_customer_registration_does_not_auto_assign_student_tier_from_birth_date(): void
    {
        $customer = User::factory()->create(['birth_date' => today()->subYears(18)]);
        $tierId = DB::table('KHACHHANG')->where('MAKH', $customer->sql_customer_id)->value('MALOAIKH');
        $defaultTierId = DB::table('LOAIKHACHHANG')->where('TENLOAI', 'Gym Null')->value('MALOAIKH');

        $this->assertSame((string) $defaultTierId, (string) $tierId);
    }

    public function test_legacy_discount_without_a_snapshot_is_explained_as_unknown(): void
    {
        $this->addProduct();
        $customer = User::factory()->create();
        $this->actingAs($customer)->post(route('gym.cart.add'), ['kind' => 'product', 'item' => 9901, 'quantity' => 1]);
        $this->actingAs($customer)->post(route('gym.checkout'), [
            'token' => (string) Str::uuid(), 'mode' => 'pickup', 'payment_method' => 'cash',
        ])->assertSessionHasNoErrors();
        $customerId = (int) DB::table('users')->where('id', $customer->id)->value('sql_customer_id');
        $invoice = (int) DB::table('HOADON')->where('MAKH', $customerId)->max('MAHD');
        DB::table('gym_fulfillments')->where('invoice_id', $invoice)->update(['pricing_snapshot_version' => null]);
        DB::table('HOADON')->where('MAHD', $invoice)->update(['GIAMGIA' => 50, 'THANHTIEN' => 950]);

        $this->get(route('gym.invoices.show', $invoice))->assertOk()->assertSee('chưa xác định nguồn');
    }

    public function test_invalid_zero_product_promotion_is_not_shown_or_included_in_sale_filter(): void
    {
        DB::table('SANPHAM')->insert([
            ['MASP' => 9902, 'TENSP' => 'Legacy zero sale product', 'MALOAISP' => 3, 'DONGIA' => 1200, 'GIAKHUYENMAI' => 0, 'SOLUONGTON' => 8],
            ['MASP' => 9903, 'TENSP' => 'Valid sale product', 'MALOAISP' => 3, 'DONGIA' => 1500, 'GIAKHUYENMAI' => 900, 'SOLUONGTON' => 8],
        ]);

        $response = $this->get(route('gym.products.index', ['q' => 'Legacy zero sale product']))->assertOk()
            ->assertSee('Legacy zero sale product')
            ->assertSee('<strong class="text-gym-blue">1.200đ</strong>', false)
            ->assertDontSee('<strong class="text-gym-blue">0đ</strong>', false);

        $this->assertSame(0, $this->get(route('gym.products.index', ['q' => 'Legacy zero sale product', 'sale' => 1]))->assertOk()->viewData('products')->total());
        $this->assertSame(1, $this->get(route('gym.products.index', ['q' => 'Valid sale product', 'sale' => 1]))->assertOk()->viewData('products')->total());
    }

    public function test_inconsistent_discount_snapshot_is_flagged_for_reconciliation_and_report_keeps_source(): void
    {
        $this->addProduct();
        $customer = User::factory()->create();
        $tierId = (int) DB::table('LOAIKHACHHANG')->max('MALOAIKH') + 1;
        DB::table('LOAIKHACHHANG')->insert(['MALOAIKH' => $tierId, 'TENLOAI' => 'Hạng QA 10%', 'MUCGIAM' => 10, 'REQUIRES_VERIFICATION' => false]);
        $tier = DB::table('LOAIKHACHHANG')->where('MALOAIKH', $tierId)->first();
        DB::table('KHACHHANG')->where('MAKH', $customer->sql_customer_id)->update(['MALOAIKH' => $tier->MALOAIKH]);
        $this->actingAs($customer)->post(route('gym.cart.add'), ['kind' => 'product', 'item' => 9901, 'quantity' => 2]);
        $token = $this->checkoutToken($customer);
        $this->actingAs($customer)->post(route('gym.checkout'), ['token' => $token, 'mode' => 'pickup', 'payment_method' => 'cash'])->assertSessionHasNoErrors();
        $invoice = (int) DB::table('gym_fulfillments')->where('checkout_token', $token)->value('invoice_id');

        $this->assertFalse(app(GymPortalService::class)->unreconciledInvoices()->where('h.MAHD', $invoice)->exists());
        $reportRow = app(ManagerReportService::class)->report(today()->toDateString(), today()->toDateString())['invoice_orders']->firstWhere('id', $invoice);
        $this->assertSame('Hạng QA 10% (10%)', $reportRow->discount_source);
        $this->assertSame(200, (int) $reportRow->discount_amount);

        $manager = User::factory()->manager()->create();
        $export = $this->actingAs($manager)->get(route('manager.reports.export', ['from_date' => today()->toDateString(), 'to_date' => today()->toDateString()]))->assertOk();
        $path = tempnam(sys_get_temp_dir(), 'gym-discount-report-');
        file_put_contents($path, $export->streamedContent());
        $spreadsheet = IOFactory::load($path);
        unlink($path);
        $ordersSheet = $spreadsheet->getSheetByName('Đơn hàng');
        $this->assertSame('Tiền giảm (VNĐ)', $ordersSheet->getCell('N1')->getValue());
        $this->assertSame('Nguồn giảm', $ordersSheet->getCell('O1')->getValue());
        $exportRow = null;
        foreach (range(2, $ordersSheet->getHighestRow()) as $rowNumber) {
            if ($ordersSheet->getCell('A'.$rowNumber)->getValue() === 'HD-'.$invoice) {
                $exportRow = $rowNumber;
                break;
            }
        }
        $this->assertNotNull($exportRow);
        $this->assertSame(200, (int) $ordersSheet->getCell('N'.$exportRow)->getValue());
        $this->assertSame('Hạng QA 10% (10%)', $ordersSheet->getCell('O'.$exportRow)->getValue());

        DB::table('gym_invoice_discounts')->where('invoice_id', $invoice)->update(['discount_amount' => 199]);
        $this->assertTrue(app(GymPortalService::class)->unreconciledInvoices()->where('h.MAHD', $invoice)->exists());
        $this->get(route('gym.invoices.show', $invoice))->assertOk()
            ->assertSee('Hóa đơn đang cần đối chiếu nội dung và số tiền')
            ->assertDontSee('Ưu đãi hạng Hạng QA 10%');
        DB::table('gym_invoice_discounts')->where('invoice_id', $invoice)->update(['discount_amount' => 200]);
        DB::table('gym_invoice_lines')->where('invoice_id', $invoice)->update(['original_unit_price' => null]);
        $this->assertTrue(app(GymPortalService::class)->unreconciledInvoices()->where('h.MAHD', $invoice)->exists());
    }

    public function test_only_manager_can_verify_or_revoke_tier_and_each_action_is_audited(): void
    {
        $customer = User::factory()->create();
        $tier = DB::table('LOAIKHACHHANG')->where('TENLOAI', 'Học sinh, Sinh viên')->first();
        DB::table('KHACHHANG')->where('MAKH', $customer->sql_customer_id)->update(['MALOAIKH' => $tier->MALOAIKH]);
        $route = route('gym.admin.customers.verify-tier', $customer->sql_customer_id);

        $this->actingAs($customer)->post($route, ['verified' => 1, 'reason' => 'Không đủ quyền kiểm thử'])->assertForbidden();
        $this->assertDatabaseMissing('gym_customer_tier_verifications', ['customer_id' => $customer->sql_customer_id]);

        $manager = User::factory()->manager()->create();
        $this->actingAs($manager)->post($route, ['verified' => 1, 'reason' => 'Đã đối chiếu thẻ tại quầy'])->assertSessionHasNoErrors();
        $verification = DB::table('gym_customer_tier_verifications')->where('customer_id', $customer->sql_customer_id)->first();
        $this->assertSame($manager->id, (int) $verification->verified_by);
        $this->assertDatabaseHas('gym_action_logs', ['user_id' => $manager->id, 'action' => 'Xác minh ưu đãi hạng khách']);

        $this->actingAs($manager)->post($route, ['verified' => 0, 'reason' => 'Thẻ đã hết hạn theo hồ sơ'])->assertSessionHasNoErrors();
        $this->assertNotNull(DB::table('gym_customer_tier_verifications')->where('id', $verification->id)->value('revoked_at'));
        $this->assertSame(2, DB::table('gym_action_logs')->where('user_id', $manager->id)->whereIn('action', ['Xác minh ưu đãi hạng khách', 'Thu hồi xác minh ưu đãi hạng khách'])->count());
    }
}
