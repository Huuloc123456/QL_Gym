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
            ->delete(route('gym.admin.destroy', ['products', $product->MASP]))
            ->assertRedirect();
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
}
