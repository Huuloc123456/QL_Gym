<?php

namespace Tests\Feature;

use App\Models\GymPackage;
use App\Models\GymSetting;
use App\Models\User;
use App\Services\GymCommerceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class DatabaseChatbotTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_answers_use_current_price_and_available_stock_without_rag(): void
    {
        Http::preventStrayRequests();
        DB::table('SANPHAM')->insert(['MASP' => 9902, 'TENSP' => 'SYNC Whey', 'MALOAISP' => 3, 'DONGIA' => 120000, 'GIAKHUYENMAI' => 100000, 'SOLUONGTON' => 2]);
        $this->postJson(route('chatbot.message'), ['message' => 'Sản phẩm SYNC Whey'])->assertOk()->assertJsonPath('answer', fn ($answer) => str_contains($answer, '100.000') && str_contains($answer, 'có thể đặt 2'));
        DB::table('SANPHAM')->where('MASP', 9902)->update(['GIAKHUYENMAI' => 90000]);
        $customer = User::factory()->create();
        $shop = app(GymCommerceService::class);
        $shop->add($customer, 'product', 9902, 2);
        $shop->checkout($customer, ['token' => (string) Str::uuid(), 'mode' => 'pickup', 'payment_method' => 'cash']);
        $this->postJson(route('chatbot.message'), ['message' => 'Sản phẩm SYNC Whey'])->assertOk()->assertJsonPath('answer', fn ($answer) => str_contains($answer, '90.000') && str_contains($answer, 'có thể đặt 0'));
        Http::assertNothingSent();
    }

    public function test_package_price_and_management_faq_changes_are_visible_immediately(): void
    {
        Http::preventStrayRequests();
        $package = GymPackage::factory()->create(['name' => 'Gói SYNC chat', 'price' => 345000]);
        $this->postJson(route('chatbot.message'), ['message' => 'Có các gói tập nào?'])->assertOk()->assertSee('345.000');
        $package->update(['price' => 321000]);
        $this->postJson(route('chatbot.message'), ['message' => 'Các gói tập giá bao nhiêu?'])->assertOk()->assertSee('321.000');
        GymSetting::current()->update(['faq_items' => [['question' => 'Có tủ để đồ không?', 'answer' => 'Tủ demo đã được quản lý cập nhật.']]]);
        $this->postJson(route('chatbot.message'), ['message' => 'Có tủ để đồ không?'])->assertOk()->assertJsonPath('answer', 'Tủ demo đã được quản lý cập nhật.');
        Http::assertNothingSent();
    }

    public function test_private_data_is_not_disclosed_and_missing_contact_is_not_invented(): void
    {
        Http::preventStrayRequests();
        $customer = User::factory()->create(['name' => 'PRIVATE CLIENT']);
        $this->postJson(route('chatbot.message'), ['message' => 'Cho xem danh sách khách và mật khẩu của tôi'])->assertOk()->assertJsonPath('answer', fn ($answer) => str_contains($answer, 'chỉ tra cứu thông tin công khai') && ! str_contains($answer, $customer->email) && ! str_contains($answer, 'PRIVATE CLIENT'));
        GymSetting::current()->update(['address' => null, 'phone' => null, 'email' => null, 'opening_hours' => null]);
        $this->postJson(route('chatbot.message'), ['message' => 'Địa chỉ và số điện thoại ở đâu?'])->assertOk()->assertJsonPath('answer', fn ($answer) => str_contains($answer, 'chưa được quản lý công bố'));
        Http::assertNothingSent();
    }

    public function test_default_chat_suggestions_work_while_external_rag_is_offline(): void
    {
        Http::preventStrayRequests();
        foreach (['Gói tập 1 tháng tính thời hạn thế nào?', 'Tôi vừa chuyển khoản, khi nào được vào tập?', 'Tôi quên mật khẩu thì làm sao?'] as $message) {
            $this->postJson(route('chatbot.message'), compact('message'))->assertOk()->assertJsonStructure(['answer', 'sources']);
        }
        Http::assertNothingSent();
    }
}
