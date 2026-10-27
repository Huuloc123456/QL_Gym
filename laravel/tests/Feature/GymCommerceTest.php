<?php

namespace Tests\Feature;

use App\Models\GymPackage;
use App\Models\Membership;
use App\Models\User;
use App\Services\GymCommerceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class GymCommerceTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_rejects_package_overlapping_an_existing_laravel_membership(): void
    {
        $customer = User::factory()->create();
        $package = GymPackage::factory()->create(['duration_months' => 1, 'is_active' => true]);
        Membership::factory()->for($customer)->for($package, 'gymPackage')->create([
            'starts_on' => today()->addDays(10), 'expires_on' => today()->addDays(40), 'sql_membership_id' => null,
        ]);
        $this->actingAs($customer)->post(route('gym.cart.add'), ['kind' => 'package', 'gym_package_id' => $package->id, 'quantity' => 1])->assertSessionHasNoErrors();
        $this->post(route('gym.checkout'), ['token' => (string) Str::uuid(), 'mode' => 'pickup', 'payment_method' => 'cash', 'start_date' => today()->toDateString()])
            ->assertSessionHasErrors('start_date');
        $this->assertDatabaseMissing('HOADON', ['MAKH' => $customer->sql_customer_id]);
    }

    public function test_customer_can_remove_a_package_that_was_stopped_after_it_was_added_to_cart(): void
    {
        $customer = User::factory()->create();
        $package = GymPackage::factory()->create(['is_active' => true]);
        $this->actingAs($customer)->post(route('gym.cart.add'), [
            'kind' => 'package', 'gym_package_id' => $package->id, 'quantity' => 1,
        ])->assertSessionHasNoErrors();
        $package->update(['is_active' => false]);

        $this->get(route('gym.cart'))->assertOk()->assertSee('Không còn mở bán');
        $this->post(route('gym.checkout'), [
            'token' => (string) Str::uuid(), 'mode' => 'pickup', 'payment_method' => 'cash',
        ])->assertSessionHasErrors('cart');
        $line = DB::table('CHITIETGIOHANG')->where('MAKH', $customer->sql_customer_id)->value('MACHITIETGH');
        $this->post(route('gym.cart.change', $line), ['quantity' => 0])->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('CHITIETGIOHANG', ['MACHITIETGH' => $line]);
    }

    private function product(int $stock = 5): void
    {
        DB::table('SANPHAM')->insert(['MASP' => 9001, 'TENSP' => 'Bình nước kiểm thử', 'MALOAISP' => 3, 'DONGIA' => 1200, 'GIAKHUYENMAI' => 1000, 'SOLUONGTON' => $stock]);
    }

    private function order(User $customer, int $quantity = 2, array $data = []): int
    {
        $this->actingAs($customer)->post(route('gym.cart.add'), ['kind' => 'product', 'item' => 9001, 'quantity' => $quantity])->assertRedirect(route('gym.cart'));
        $this->get(route('gym.cart'))->assertOk()->assertSee('Bình nước kiểm thử');
        $this->post(route('gym.checkout'), $data + ['token' => (string) Str::uuid(), 'mode' => 'pickup', 'payment_method' => 'cash', 'price' => 1])->assertSessionHasNoErrors();

        return (int) DB::table('HOADON')->where('MAKH', $customer->sql_customer_id)->max('MAHD');
    }

    private function pay(User $staff, int $invoice, int $amount = 2000, ?string $token = null): void
    {
        $this->actingAs($staff)->post(route('gym.invoices.receive', $invoice), ['amount' => $amount, 'method' => 'cash', 'token' => $token ?? (string) Str::uuid()])->assertSessionHasNoErrors();
    }

    public function test_checkout_uses_current_sql_promotion_and_tier_and_keeps_stock_until_confirmation(): void
    {
        $this->product();
        $customer = User::factory()->create();
        DB::table('KHACHHANG')->where('MAKH', $customer->sql_customer_id)->update(['MALOAIKH' => 3]);

        $invoice = $this->order($customer);

        $this->assertDatabaseHas('HOADON', ['MAHD' => $invoice, 'TONGTIEN' => 2000, 'GIAMGIA' => 200, 'THANHTIEN' => 1800, 'TRANGTHAI' => 'Chưa thanh toán']);
        $this->assertDatabaseHas('SANPHAM', ['MASP' => 9001, 'SOLUONGTON' => 5]);
        $this->assertSame(0, DB::table('CHITIETHOADON')->where('MAHD', $invoice)->count());
        $this->assertSame(0, DB::table('CHITIETGIOHANG')->where('MAKH', $customer->sql_customer_id)->count());
    }

    public function test_homepage_shows_available_products_from_the_gym_store(): void
    {
        DB::table('SANPHAM')->insert(['MASP' => 9002, 'TENSP' => 'Sản phẩm hết hàng', 'MALOAISP' => 3, 'DONGIA' => 1200, 'SOLUONGTON' => 0]);

        $response = $this->get(route('home'))
            ->assertOk()
            ->assertSee('Sản phẩm tại The Gym');
        $featuredProducts = $response->viewData('featuredProducts');

        $this->assertGreaterThan(0, $featuredProducts->count());
        $this->assertLessThanOrEqual(10, $featuredProducts->count());
        $this->assertTrue($featuredProducts->every(fn ($featuredProduct): bool => (int) $featuredProduct->SOLUONGTON > 0));
        $this->assertFalse($featuredProducts->contains('MASP', 9002));
        $response->assertSee($featuredProducts->first()->TENSP)
            ->assertSee(route('gym.products.show', $featuredProducts->first()->MASP), false);
    }

    public function test_public_stock_filter_and_cards_use_quantity_left_after_pending_orders(): void
    {
        $this->product(stock: 1);
        $customer = User::factory()->create();
        $invoice = $this->order($customer, 1);

        $catalogue = $this->get(route('gym.products.index', ['q' => 'Bình nước kiểm thử']))->assertOk();
        $product = $catalogue->viewData('products')->firstWhere('MASP', 9001);
        $this->assertNotNull($product);
        $this->assertSame(0, (int) $product->available_quantity);
        $catalogue->assertSee('Bình nước kiểm thử')->assertSee('Hết hàng');

        $stockFiltered = $this->get(route('gym.products.index', ['q' => 'Bình nước kiểm thử', 'stock' => 1]))
            ->assertOk()
            ->viewData('products');
        $this->assertSame(0, $stockFiltered->total());

        $this->get(route('gym.products.show', 9001))
            ->assertOk()
            ->assertSee('Có thể đặt')
            ->assertSee('0 sản phẩm')
            ->assertSee('Sản phẩm hiện hết hàng.')
            ->assertDontSee('Thêm vào giỏ hàng');

        $home = $this->get(route('home'))->assertOk();
        $featuredProducts = $home->viewData('featuredProducts');
        $this->assertFalse($featuredProducts->contains('MASP', 9001));
        $this->assertDatabaseHas('HOADON', ['MAHD' => $invoice, 'TRANGTHAI' => 'Chưa thanh toán']);
    }

    public function test_product_catalogue_paginates_in_complete_rows_of_three_cards(): void
    {
        foreach (range(1, 16) as $productId) {
            DB::table('SANPHAM')->insert([
                'MASP' => 9100 + $productId,
                'TENSP' => 'Sản phẩm phân trang '.$productId,
                'MALOAISP' => 3,
                'DONGIA' => 100000,
                'SOLUONGTON' => 10,
            ]);
        }

        $firstPage = $this->get(route('gym.products.index'))->assertOk()->viewData('products');
        $secondPage = $this->get(route('gym.products.index', ['page' => 2]))->assertOk()->viewData('products');

        $this->assertSame(15, $firstPage->count());
        $this->assertSame(15, $firstPage->perPage());
        $this->assertSame(15, $secondPage->count());
    }

    public function test_double_payment_submission_and_double_confirmation_do_not_duplicate_receipts_or_deduct_stock_twice(): void
    {
        $this->product();
        $customer = User::factory()->create();
        $staff = User::factory()->employee()->create();
        $invoice = $this->order($customer);
        $token = (string) Str::uuid();
        $this->pay($staff, $invoice, 2000, $token);
        $this->pay($staff, $invoice, 2000, $token);

        $this->post(route('gym.invoices.confirm', $invoice))->assertSessionHasNoErrors();
        $this->post(route('gym.invoices.confirm', $invoice))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('SANPHAM', ['MASP' => 9001, 'SOLUONGTON' => 3]);
        $this->assertSame(1, DB::table('CHITIETHOADON')->where('MAHD', $invoice)->count());
        $this->assertSame(1, DB::table('gym_receipts')->where('invoice_id', $invoice)->count());
        $this->assertDatabaseHas('HOADON', ['MAHD' => $invoice, 'TRANGTHAI' => 'Đã thanh toán']);
    }

    public function test_partial_payment_cannot_activate_order_or_be_cancelled_with_money_received(): void
    {
        $this->product();
        $staff = User::factory()->employee()->create();
        $invoice = $this->order(User::factory()->create());
        $this->pay($staff, $invoice, 500);

        $this->post(route('gym.invoices.confirm', $invoice))->assertSessionHasErrors('payment');
        $this->post(route('gym.invoices.cancel', $invoice))->assertSessionHasErrors('invoice');

        $this->assertDatabaseHas('HOADON', ['MAHD' => $invoice, 'TRANGTHAI' => 'Chưa thanh toán']);
        $this->assertDatabaseHas('SANPHAM', ['MASP' => 9001, 'SOLUONGTON' => 5]);
        $this->assertSame(500, (int) DB::table('gym_receipts')->where('invoice_id', $invoice)->sum('amount'));
    }

    public function test_staff_must_resolve_an_invoice_cancellation_request_before_receiving_money(): void
    {
        $this->product();
        $customer = User::factory()->create();
        $staff = User::factory()->employee()->create();
        $invoice = $this->order($customer, 1);

        $this->actingAs($customer)->post(route('gym.invoices.cancel', $invoice))->assertSessionHasNoErrors();
        $this->actingAs($staff)->post(route('gym.invoices.receive', $invoice), [
            'amount' => 1000, 'method' => 'cash', 'token' => (string) Str::uuid(),
        ])->assertSessionHasErrors('payment');
        $this->get(route('gym.invoices.show', $invoice))
            ->assertSee('Xác nhận tiếp tục đơn')
            ->assertDontSee('Ghi nhận khoản thu');
        $this->post(route('gym.invoices.confirm', $invoice))->assertSessionHasErrors('payment');
        $this->assertDatabaseMissing('gym_receipts', ['invoice_id' => $invoice]);

        $this->post('/hoa-don/'.$invoice.'/tiep-tuc')->assertSessionHasNoErrors();
        $this->assertDatabaseHas('gym_fulfillments', ['invoice_id' => $invoice, 'cancellation_requested' => false]);
        $this->post(route('gym.invoices.receive', $invoice), [
            'amount' => 1000, 'method' => 'cash', 'token' => (string) Str::uuid(),
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('gym_receipts', ['invoice_id' => $invoice, 'amount' => 1000]);
    }

    public function test_legacy_pt_invoice_cannot_create_a_new_pt_contract(): void
    {
        $this->product();
        $customer = User::factory()->create();
        $staff = User::factory()->employee()->create();
        $invoice = $this->order($customer);
        $this->pay($staff, $invoice);
        DB::table('gym_invoice_lines')->where('invoice_id', $invoice)->update([
            'kind' => 'pt',
            'item_id' => $staff->sql_employee_id,
            'name' => 'Gói PT cũ',
        ]);

        $invoicePage = $this->actingAs($staff)->get(route('gym.invoices.show', $invoice))
            ->assertOk()
            ->assertSee('dịch vụ cũ đã ngừng hỗ trợ')
            ->assertDontSee('Ghi nhận khoản thu')
            ->assertDontSee('Xác nhận đã thu đủ tiền');
        $this->assertSame(1, substr_count($invoicePage->getContent(), 'Hóa đơn này có dịch vụ cũ đã ngừng hỗ trợ.'));
        $this->actingAs($customer)->post(route('gym.invoices.vnpay', $invoice))->assertSessionHasErrors('line');
        $this->actingAs($staff)->post(route('gym.invoices.confirm', $invoice))->assertSessionHasErrors('line');

        $this->assertDatabaseHas('HOADON', ['MAHD' => $invoice, 'TRANGTHAI' => 'Chưa thanh toán']);
        $this->assertDatabaseMissing('DANGKYPT', ['MAKH' => $customer->sql_customer_id]);
        $this->assertDatabaseMissing('CHITIETHOADON', ['MAHD' => $invoice]);
        $this->assertDatabaseHas('gym_receipts', ['invoice_id' => $invoice, 'amount' => 2000]);
    }

    public function test_reservations_prevent_overselling_and_cancellation_releases_only_reserved_stock(): void
    {
        $this->product();
        $first = User::factory()->create();
        $second = User::factory()->create();
        $staff = User::factory()->employee()->create();
        $this->actingAs($second)->post(route('gym.cart.add'), ['kind' => 'product', 'item' => 9001, 'quantity' => 2])->assertSessionHasNoErrors();
        $invoice = $this->order($first, 4);
        $this->actingAs($second)->post(route('gym.checkout'), ['token' => (string) Str::uuid(), 'mode' => 'pickup', 'payment_method' => 'cash'])->assertSessionHasErrors('cart');
        $this->actingAs($staff)->post(route('gym.invoices.cancel', $invoice))->assertSessionHasNoErrors();

        $this->actingAs($second)->post(route('gym.checkout'), ['token' => (string) Str::uuid(), 'mode' => 'pickup', 'payment_method' => 'cash'])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('SANPHAM', ['MASP' => 9001, 'SOLUONGTON' => 5]);
        $this->assertSame(3, app(GymCommerceService::class)->available(9001));
    }

    public function test_checkout_token_is_idempotent_and_cannot_reveal_another_customers_invoice(): void
    {
        $this->product();
        $customer = User::factory()->create();
        $token = (string) Str::uuid();
        $invoice = $this->order($customer, 2, ['token' => $token]);

        $this->post(route('gym.checkout'), ['token' => $token, 'mode' => 'pickup', 'payment_method' => 'cash'])->assertRedirect(route('gym.invoices.show', $invoice));
        $this->actingAs(User::factory()->create())->post(route('gym.checkout'), ['token' => $token, 'mode' => 'pickup', 'payment_method' => 'cash'])->assertNotFound();

        $this->assertSame(1, DB::table('HOADON')->where('MAKH', $customer->sql_customer_id)->count());
    }

    public function test_customers_cannot_edit_other_cart_addresses_or_read_other_invoices(): void
    {
        $this->product();
        $owner = User::factory()->create();
        $invoice = $this->order($owner);
        $this->actingAs($owner)->post(route('gym.cart.add'), ['kind' => 'product', 'item' => 9001, 'quantity' => 1]);
        $line = DB::table('CHITIETGIOHANG')->where('MAKH', $owner->sql_customer_id)->value('MACHITIETGH');
        $address = DB::table('DIACHI')->insertGetId(['MAKH' => $owner->sql_customer_id, 'TINHTHANHPHO' => 'TP.HCM', 'QUANHUYEN' => 'Quận 1', 'PHUONGXA' => 'Bến Nghé', 'DIACHICUTHE' => '12 Đường A', 'LADIACHIMACDINH' => 1, 'NGAYTHEM' => now()]);
        $other = User::factory()->create();

        $this->actingAs($other)->post(route('gym.cart.change', $line), ['quantity' => 0])->assertNotFound();
        $this->delete(route('gym.addresses.delete', $address))->assertNotFound();
        $this->get(route('gym.invoices.show', $invoice))->assertNotFound();
        $this->get(route('gym.invoices.download', $invoice))->assertNotFound();

        $this->assertDatabaseHas('CHITIETGIOHANG', ['MACHITIETGH' => $line, 'MAKH' => $owner->sql_customer_id]);
        $this->assertDatabaseHas('DIACHI', ['MADC' => $address]);
    }

    public function test_delivery_copies_the_address_and_does_not_change_when_address_book_is_edited(): void
    {
        $this->product();
        $customer = User::factory()->create();
        $data = ['TINHTHANHPHO' => 'TP.HCM', 'QUANHUYEN' => 'Quận 1', 'PHUONGXA' => 'Bến Nghé', 'DIACHICUTHE' => '12 Đường A', 'LADIACHIMACDINH' => 1];
        $this->actingAs($customer)->post(route('gym.addresses.save'), $data)->assertSessionHasNoErrors();
        $address = (int) DB::table('DIACHI')->where('MAKH', $customer->sql_customer_id)->value('MADC');
        $invoice = $this->order($customer, 2, ['mode' => 'delivery', 'address_id' => $address, 'recipient' => 'Người nhận', 'phone' => '0901234567']);

        $this->post(route('gym.addresses.save', $address), array_replace($data, ['DIACHICUTHE' => '99 Đường B']))->assertSessionHasNoErrors();

        $snapshot = json_decode(DB::table('gym_fulfillments')->where('invoice_id', $invoice)->value('address'), true);
        $this->assertSame('12 Đường A', $snapshot['DIACHICUTHE']);
        $this->assertDatabaseHas('DIACHI', ['MADC' => $address, 'DIACHICUTHE' => '99 Đường B']);
    }

    public function test_receipt_and_fulfillment_actions_require_staff_permission_and_valid_transitions(): void
    {
        $this->product();
        $customer = User::factory()->create();
        $staff = User::factory()->employee()->create();
        $invoice = $this->order($customer);
        $this->actingAs($customer)->post(route('gym.invoices.confirm', $invoice))->assertForbidden();
        $this->post(route('gym.invoices.receive', $invoice), ['amount' => 2000, 'method' => 'cash', 'token' => (string) Str::uuid()])->assertForbidden();
        $this->pay($staff, $invoice);
        $this->post(route('gym.invoices.confirm', $invoice))->assertSessionHasNoErrors();
        $this->post(route('gym.invoices.fulfill', $invoice), ['state' => 'collected'])->assertSessionHasErrors('state');
        $this->post(route('gym.invoices.fulfill', $invoice), ['state' => 'ready'])->assertSessionHasNoErrors();
        $this->post(route('gym.invoices.fulfill', $invoice), ['state' => 'collected'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('gym_fulfillments', ['invoice_id' => $invoice, 'state' => 'collected']);
    }

    public function test_signed_invoice_detects_modified_content_and_keeps_private_key_out_of_download(): void
    {
        Storage::fake('local');
        $this->product();
        $customer = User::factory()->create();
        $manager = User::factory()->manager()->create();
        $invoice = $this->order($customer);
        $this->pay($manager, $invoice);
        $this->post(route('gym.invoices.confirm', $invoice))->assertSessionHasNoErrors();
        $this->post(route('gym.invoices.sign', $invoice))->assertRedirect()->assertSessionHasNoErrors();
        $bill = DB::table('HOADON')->where('MAHD', $invoice)->first();
        Storage::disk('local')->assertExists($bill->SIGNATURE_PATH);
        $this->actingAs($customer)->get(route('gym.invoices.download', $invoice))->assertOk()->assertDownload('hoa-don-'.$invoice.'.html');
        $this->post(route('gym.invoices.verify', $invoice))->assertSessionHas('success', 'Chữ ký hợp lệ, nội dung không bị thay đổi.');

        Storage::disk('local')->append($bill->FILE_PATH, 'Nội dung đã bị sửa');
        $this->post(route('gym.invoices.verify', $invoice))->assertSessionHas('success', 'Chữ ký không hợp lệ: nội dung tệp đã thay đổi.');

        $this->assertDatabaseHas('HOADON', ['MAHD' => $invoice, 'SIGNATURE_VERIFIED' => 0]);
        $this->assertStringNotContainsString('PRIVATE KEY', Storage::disk('local')->get($bill->FILE_PATH));
    }

    public function test_manager_cannot_reduce_stock_below_reservations_or_upload_active_files_as_images(): void
    {
        $this->product();
        $this->order(User::factory()->create());
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager)->post(route('gym.admin.save', ['products', 9001]), ['TENSP' => 'Bình nước', 'MALOAISP' => 3, 'DONGIA' => 1200, 'SOLUONGTON' => 1, 'stock_reason' => 'Điều chỉnh kiểm kê'])->assertSessionHasErrors('SOLUONGTON');
        $this->post(route('gym.admin.image', 9001), ['image' => UploadedFile::fake()->create('attack.php', 1, 'application/x-php')])->assertSessionHasErrors('image');

        $this->assertDatabaseHas('SANPHAM', ['MASP' => 9001, 'SOLUONGTON' => 5]);
    }

    public function test_package_confirmation_uses_snapshot_duration_and_creates_one_original_registration(): void
    {
        $this->travelTo(now()->setDate(2030, 1, 31)->startOfDay());
        $customer = User::factory()->create();
        $staff = User::factory()->employee()->create();
        $package = GymPackage::factory()->create(['duration_months' => 1, 'price' => 1000]);
        $this->actingAs($customer)->post(route('gym.cart.add'), ['kind' => 'package', 'item' => $package->sql_package_id, 'quantity' => 1])->assertSessionHasNoErrors();
        $this->post(route('gym.checkout'), ['token' => (string) Str::uuid(), 'mode' => 'pickup', 'payment_method' => 'cash', 'start_date' => '2030-01-31'])->assertSessionHasNoErrors();
        $invoice = (int) DB::table('HOADON')->where('MAKH', $customer->sql_customer_id)->max('MAHD');
        $package->update(['duration_months' => 12, 'price' => 5000]);
        $this->pay($staff, $invoice, 1000);

        $this->post(route('gym.invoices.confirm', $invoice))->assertSessionHasNoErrors();
        $this->post(route('gym.invoices.confirm', $invoice))->assertSessionHasNoErrors();

        $this->assertDatabaseHas('DANGKYGOITAP', ['MAKH' => $customer->sql_customer_id, 'NGAYBATDAU' => '2030-01-31 00:00:00', 'NGAYKETTHUC' => '2030-02-28 23:59:59']);
        $this->assertSame(1, DB::table('DANGKYGOITAP')->where('MAKH', $customer->sql_customer_id)->count());
        $this->actingAs($customer)->get(route('my.memberships.index'))->assertSee('2030-02-28');
    }
}
