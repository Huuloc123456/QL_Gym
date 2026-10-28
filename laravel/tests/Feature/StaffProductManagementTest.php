<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class StaffProductManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_stock_change_requires_a_clear_vietnamese_reason_and_does_not_change_inventory(): void
    {
        DB::table('SANPHAM')->insert([
            'MASP' => 9903,
            'TENSP' => 'Sản phẩm kiểm kê',
            'MALOAISP' => 3,
            'DONGIA' => 120000,
            'SOLUONGTON' => 7,
        ]);
        $employee = User::factory()->employee()->create();

        $this->actingAs($employee)->post(route('staff.products.update', 9903), [
            'TENSP' => 'Sản phẩm kiểm kê',
            'MALOAISP' => 3,
            'DONGIA' => 120000,
            'SOLUONGTON' => 10,
        ])->assertSessionHasErrors(['stock_reason' => 'Vui lòng nhập lý do thay đổi tồn kho.']);

        $this->assertDatabaseHas('SANPHAM', ['MASP' => 9903, 'SOLUONGTON' => 7]);
    }

    public function test_employee_without_other_module_permissions_can_manage_products_but_cannot_delete_them(): void
    {
        $employee = User::factory()->employee()->create([
            'can_manage_trial_requests' => false,
            'can_manage_orders' => false,
            'can_manage_check_ins' => false,
        ]);

        $this->actingAs($employee)->get(route('staff.products.index'))
            ->assertOk()
            ->assertSee('Quản lý sản phẩm')
            ->assertSee('Thêm sản phẩm');

        $this->post(route('staff.products.store'), [
            'TENSP' => 'Áo tập nhân viên tạo',
            'MALOAISP' => 3,
            'DONGIA' => 250000,
            'GIAKHUYENMAI' => 220000,
            'SOLUONGTON' => 12,
            'HANG' => 'The Gym',
            'XUATXU' => 'Việt Nam',
            'BAOHANH' => '',
            'MOTACHUNG' => 'Áo tập thoáng khí',
            'MOTACHITIET' => 'Vải co giãn',
        ])->assertRedirect(route('staff.products.index'));

        $product = DB::table('SANPHAM')->where('TENSP', 'Áo tập nhân viên tạo')->first();
        $this->assertNotNull($product);
        $this->assertSame(12, (int) $product->SOLUONGTON);
        $this->delete(route('gym.admin.destroy', ['products', $product->MASP]))->assertForbidden();

        $this->post(route('staff.products.update', $product->MASP), [
            'TENSP' => 'Áo tập nhân viên cập nhật',
            'MALOAISP' => 3,
            'DONGIA' => 250000,
            'GIAKHUYENMAI' => 220000,
            'SOLUONGTON' => 15,
            'HANG' => 'The Gym',
            'XUATXU' => 'Việt Nam',
            'BAOHANH' => '',
            'MOTACHUNG' => 'Áo tập thoáng khí',
            'MOTACHITIET' => 'Vải co giãn',
            'stock_reason' => 'Kiểm kê cuối ngày',
        ])->assertRedirect(route('staff.products.index'));

        $this->assertDatabaseHas('SANPHAM', ['MASP' => $product->MASP, 'TENSP' => 'Áo tập nhân viên cập nhật', 'SOLUONGTON' => 15]);
        $this->actingAs(User::factory()->manager()->create())
            ->from(route('gym.admin.edit', ['products', $product->MASP]))
            ->delete(route('gym.admin.destroy', ['products', $product->MASP]))
            ->assertRedirect(route('gym.admin.index', 'products'));
        $this->assertDatabaseMissing('SANPHAM', ['MASP' => $product->MASP]);
    }

    public function test_employee_can_upload_product_images_and_cannot_reduce_stock_below_reserved_quantity(): void
    {
        Storage::fake('public');
        DB::table('SANPHAM')->insert([
            'MASP' => 9901,
            'TENSP' => 'Bình nước QA',
            'MALOAISP' => 3,
            'DONGIA' => 120000,
            'SOLUONGTON' => 5,
        ]);
        $customer = User::factory()->create();
        $employee = User::factory()->employee()->create();

        $this->actingAs($employee)->post(route('staff.products.image', 9901), [
            'image' => UploadedFile::fake()->image('bottle.png'),
            'main' => '1',
        ])->assertRedirect();

        $this->assertDatabaseHas('HINHANH', ['MASP' => 9901, 'ISMAIN' => 1]);

        $this->actingAs($customer)->post(route('gym.cart.add'), [
            'kind' => 'product',
            'item' => 9901,
            'quantity' => 4,
        ])->assertRedirect(route('gym.cart'));
        $this->post(route('gym.checkout'), [
            'token' => (string) Str::uuid(),
            'mode' => 'pickup',
            'payment_method' => 'cash',
        ])->assertSessionHasNoErrors();

        $this->actingAs($employee)->post(route('staff.products.update', 9901), [
            'TENSP' => 'Bình nước QA',
            'MALOAISP' => 3,
            'DONGIA' => 120000,
            'SOLUONGTON' => 3,
            'stock_reason' => 'Điều chỉnh kiểm kê',
        ])->assertSessionHasErrors('SOLUONGTON');

        $this->assertDatabaseHas('SANPHAM', ['MASP' => 9901, 'SOLUONGTON' => 5]);
    }

    public function test_manager_cannot_delete_a_product_reserved_by_a_pending_invoice_or_its_image(): void
    {
        DB::table('SANPHAM')->insert([
            'MASP' => 9902,
            'TENSP' => 'Sản phẩm đang chờ QA',
            'MALOAISP' => 3,
            'DONGIA' => 120000,
            'SOLUONGTON' => 2,
        ]);
        DB::table('HINHANH')->insert([
            'MAHINH' => (int) DB::table('HINHANH')->max('MAHINH') + 1,
            'MASP' => 9902,
            'URL' => 'products/qa-pending.png',
            'ISMAIN' => 1,
        ]);
        $customer = User::factory()->create();
        $manager = User::factory()->manager()->create();

        $this->actingAs($customer)->post(route('gym.cart.add'), [
            'kind' => 'product', 'item' => 9902, 'quantity' => 1,
        ])->assertSessionHasNoErrors();
        $this->post(route('gym.checkout'), [
            'token' => (string) Str::uuid(), 'mode' => 'pickup', 'payment_method' => 'cash',
        ])->assertSessionHasNoErrors();

        $this->actingAs($manager)
            ->delete(route('gym.admin.destroy', ['products', 9902]))
            ->assertSessionHasErrors('record');

        $this->assertDatabaseHas('SANPHAM', ['MASP' => 9902, 'TENSP' => 'Sản phẩm đang chờ QA']);
        $this->assertDatabaseHas('HINHANH', ['MASP' => 9902, 'URL' => 'products/qa-pending.png']);
    }
}
