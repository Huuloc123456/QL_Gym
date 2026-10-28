<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class RetiredTrainingWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_pt_group_class_and_calendar_routes_are_unavailable_to_every_role(): void
    {
        foreach (['/huan-luyen-vien', '/lop-hoc', '/lich-tap'] as $path) {
            $this->get($path)->assertNotFound();
        }

        foreach ([
            User::factory()->create(),
            User::factory()->employee()->create(),
            User::factory()->manager()->create(),
        ] as $user) {
            foreach (['/huan-luyen-vien', '/lop-hoc', '/lich-tap'] as $path) {
                $this->actingAs($user)->get($path)->assertNotFound();
            }
        }
    }

    public function test_manager_cannot_open_retired_class_or_specialty_catalogues(): void
    {
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager)->get('/quan-ly/danh-muc/classes')->assertNotFound();
        $this->get('/quan-ly/danh-muc/specialties')->assertNotFound();
    }

    public function test_customer_cannot_add_pt_or_class_to_a_new_order(): void
    {
        $customer = User::factory()->create();

        foreach (['pt', 'class'] as $kind) {
            $this->actingAs($customer)->post('/gio-hang', [
                'kind' => $kind,
                'item' => 1,
                'quantity' => 1,
            ])->assertSessionHasErrors('kind');
        }
    }

    public function test_old_class_cart_line_is_clearable_but_cannot_be_checked_out(): void
    {
        $customer = User::factory()->create();
        $line = 9801;
        DB::table('LOPHOC')->insert([
            'MALOP' => 9801,
            'TENLOP' => 'Lớp đã ngừng',
            'MACM' => 1,
            'HOCPHI' => 100000,
            'NGAYBATDAU' => now(),
            'NGAYKETTHUC' => now()->addMonth(),
            'SOBUOI' => 4,
            'SISOTOIDA' => 10,
        ]);
        DB::table('CHITIETGIOHANG')->insert([
            'MACHITIETGH' => $line,
            'MAKH' => $customer->sql_customer_id,
            'MALOP' => 9801,
            'SOLUONG' => 1,
            'DONGIA' => 100000,
            'NGAYTHEM' => now(),
        ]);

        $this->actingAs($customer)->get('/gio-hang')->assertOk()
            ->assertSee('Dịch vụ đã ngừng · lớp #9801')
            ->assertSee('Bỏ khỏi giỏ');
        $this->post('/dat-hang', [
            'token' => (string) Str::uuid(), 'mode' => 'pickup', 'payment_method' => 'cash',
        ])->assertSessionHasErrors('cart');
        $this->post('/gio-hang/'.$line, ['quantity' => 0])->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('CHITIETGIOHANG', ['MACHITIETGH' => $line]);
    }

    public function test_pending_legacy_class_invoice_cannot_accept_payment_vnpay_or_be_confirmed(): void
    {
        $customer = User::factory()->create();
        $employee = User::factory()->employee()->create();
        $manager = User::factory()->manager()->create();
        $invoice = 9801;
        $this->createLegacyClassInvoice($invoice, $customer);

        $this->actingAs($customer)->get('/hoa-don/'.$invoice)->assertOk()
            ->assertViewHas('hasUnsupportedServices', true)
            ->assertDontSee('THANH TOÁN VIETQR');

        $this->actingAs($employee)->post('/hoa-don/'.$invoice.'/thu-tien', [
            'amount' => 100000, 'method' => 'cash', 'token' => (string) Str::uuid(),
        ])->assertSessionHasErrors('line');
        $this->actingAs($customer)->post('/hoa-don/'.$invoice.'/thanh-toan/vnpay')->assertSessionHasErrors('line');
        $this->actingAs($manager)->post('/hoa-don/'.$invoice.'/xac-nhan')->assertSessionHasErrors('line');

        $this->assertDatabaseHas('HOADON', ['MAHD' => $invoice, 'TRANGTHAI' => 'Chưa thanh toán']);
        $this->assertDatabaseMissing('gym_receipts', ['invoice_id' => $invoice]);
        $this->assertDatabaseMissing('CHITIETHOADON', ['MAHD' => $invoice]);
        $this->assertDatabaseMissing('DANGKYLOP', ['MAKH' => $customer->sql_customer_id]);
    }

    public function test_manager_can_still_end_a_package_registration_using_the_package_route(): void
    {
        $manager = User::factory()->manager()->create();
        $customer = User::factory()->create();
        $registration = 9802;
        DB::table('DANGKYGOITAP')->insert([
            'MADKGT' => $registration,
            'MAKH' => $customer->sql_customer_id,
            'MAGOITAP' => 1,
            'NGAYDANGKY' => now(),
            'NGAYBATDAU' => today(),
            'NGAYKETTHUC' => today()->addMonth()->endOfDay(),
            'TRANGTHAI' => 'Còn hiệu lực',
        ]);

        $this->actingAs($manager)->get('/quan-ly/danh-muc/khach-hang/'.$customer->sql_customer_id)
            ->assertOk()->assertSee('Chấm dứt gói')->assertSee('/quan-ly/dang-ky/goi/'.$registration.'/huy');
        $this->post('/quan-ly/dang-ky/goi/'.$registration.'/huy', [
            'reason' => 'Theo yêu cầu kiểm thử',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->post('/quan-ly/dang-ky/class/'.$registration.'/huy', ['reason' => 'Không còn dùng lớp'])->assertNotFound();
        $this->assertDatabaseHas('DANGKYGOITAP', ['MADKGT' => $registration, 'TRANGTHAI' => 'Đã hủy']);
    }

    public function test_role_navigation_does_not_advertise_pt_or_group_classes(): void
    {
        $this->get('/')->assertOk()->assertDontSee('Lớp học');

        foreach ([
            User::factory()->employee()->create(),
            User::factory()->manager()->create(),
        ] as $user) {
            $this->actingAs($user)->get('/staff')->assertOk()
                ->assertDontSee('Lịch lớp học')
                ->assertDontSee('Lớp học');
        }
    }

    private function createLegacyClassInvoice(int $invoice, User $customer): void
    {
        DB::table('HOADON')->insert([
            'MAHD' => $invoice,
            'MAKH' => $customer->sql_customer_id,
            'NGAYLAP' => now(),
            'TONGTIEN' => 100000,
            'GIAMGIA' => 0,
            'THANHTIEN' => 100000,
            'TRANGTHAI' => 'Chưa thanh toán',
        ]);
        DB::table('gym_invoice_lines')->insert([
            'invoice_id' => $invoice,
            'kind' => 'class',
            'item_id' => 9803,
            'name' => 'Lớp lịch sử',
            'quantity' => 1,
            'unit_price' => 100000,
            'details' => json_encode(['legacy' => true]),
        ]);
        DB::table('gym_fulfillments')->insert([
            'invoice_id' => $invoice,
            'mode' => 'pickup',
            'state' => 'pending',
            'payment_method' => 'cash',
            'recipient' => $customer->name,
            'phone' => $customer->phone,
            'shipping_fee' => 0,
            'checkout_token' => (string) Str::uuid(),
            'pricing_snapshot_version' => 0,
            'expires_at' => now()->addDay(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
