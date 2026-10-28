<?php

namespace Tests\Feature;

use App\Models\GymPackage;
use App\Models\GymSetting;
use App\Models\MembershipOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class BankTransferQrTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_bank_transfer_order_shows_a_dynamic_qr_on_the_order_list(): void
    {
        $customer = User::factory()->create();
        $package = GymPackage::factory()->create();
        $order = MembershipOrder::factory()->for($customer)->for($package, 'gymPackage')->create([
            'amount' => 399000,
            'payment_method' => 'bank_transfer',
        ]);
        GymSetting::current()->update([
            'bank_name' => 'Vietcombank',
            'bank_code' => 'vietcombank',
            'bank_account' => '0123456789',
            'bank_account_name' => 'THE GYM',
        ]);

        $this->actingAs($customer)
            ->get(route('my.orders.show', $order))
            ->assertOk()
            ->assertSee('img.vietqr.io/image/', false)
            ->assertSee('amount=399000', false)
            ->assertSee('addInfo=GYM-'.$order->id, false);
    }

    public function test_bank_transfer_order_explains_when_the_manager_has_not_configured_a_bank_qr(): void
    {
        $customer = User::factory()->create();
        $package = GymPackage::factory()->create();
        MembershipOrder::factory()->for($customer)->for($package, 'gymPackage')->create([
            'payment_method' => 'bank_transfer',
        ]);

        $this->actingAs($customer)
            ->get(route('my.orders.show', MembershipOrder::query()->where('user_id', $customer->id)->firstOrFail()))
            ->assertOk()
            ->assertDontSee('img.vietqr.io/image/', false)
            ->assertSee('Phòng tập chưa cấu hình đầy đủ mã VietQR');
    }

    public function test_cash_invoice_with_a_remaining_balance_also_offers_optional_bank_transfer_qr(): void
    {
        DB::table('SANPHAM')->insert([
            'MASP' => 9901,
            'TENSP' => 'Sản phẩm QR kiểm thử',
            'MALOAISP' => 3,
            'DONGIA' => 120000,
            'SOLUONGTON' => 5,
        ]);
        $customer = User::factory()->create();
        GymSetting::current()->update([
            'bank_name' => 'MB bank',
            'bank_code' => 'MB',
            'bank_account' => '0325302068',
            'bank_account_name' => 'THE GYM',
        ]);

        $this->actingAs($customer)->post(route('gym.cart.add'), [
            'kind' => 'product',
            'item' => 9901,
            'quantity' => 1,
        ])->assertRedirect(route('gym.cart'));
        $this->post(route('gym.checkout'), [
            'token' => (string) Str::uuid(),
            'mode' => 'pickup',
            'payment_method' => 'cash',
        ])->assertSessionHasNoErrors();
        $invoice = (int) DB::table('HOADON')->where('MAKH', $customer->sql_customer_id)->max('MAHD');
        $invoiceAmount = (int) DB::table('HOADON')->where('MAHD', $invoice)->value('THANHTIEN');

        $this->get(route('gym.invoices.show', $invoice))
            ->assertOk()
            ->assertSee('Quét mã để chuyển khoản')
            ->assertSee('w-72 max-w-full rounded-xl bg-white object-contain sm:w-80', false)
            ->assertSee('img.vietqr.io/image/mb-0325302068-compact2.png', false)
            ->assertSee('amount='.$invoiceAmount, false)
            ->assertSee('addInfo=GYM%20HD'.$invoice, false);
    }
}
