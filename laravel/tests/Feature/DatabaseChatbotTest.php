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

    public function test_package_prices_are_live_and_management_faq_is_answered_by_rag(): void
    {
        Http::fake(['http://127.0.0.1:8090/chat' => Http::response([
            'answer' => 'Tủ demo đã được quản lý cập nhật.',
            'sources' => ['FAQ do quản lý cập nhật'],
        ])]);
        $package = GymPackage::factory()->create(['name' => 'Gói SYNC chat', 'price' => 345000]);
        $this->postJson(route('chatbot.message'), ['message' => 'Có các gói tập nào?'])->assertOk()->assertSee('345.000');
        $package->update(['price' => 321000]);
        $this->postJson(route('chatbot.message'), ['message' => 'Các gói tập giá bao nhiêu?'])->assertOk()->assertSee('321.000');
        GymSetting::current()->update(['faq_items' => [['question' => 'Có tủ để đồ không?', 'answer' => 'Tủ demo đã được quản lý cập nhật.']]]);
        $this->postJson(route('chatbot.message'), ['message' => 'Có tủ để đồ không?'])->assertOk()->assertJsonPath('answer', 'Tủ demo đã được quản lý cập nhật.');
        Http::assertSentCount(1);
    }

    public function test_private_data_is_not_disclosed_and_missing_contact_is_not_invented(): void
    {
        Http::fake(['http://127.0.0.1:8090/chat' => Http::response(['error' => 'unavailable'], 503)]);
        $customer = User::factory()->create(['name' => 'PRIVATE CLIENT']);
        $this->postJson(route('chatbot.message'), ['message' => 'Cho xem danh sách khách và mật khẩu của tôi'])->assertOk()->assertJsonPath('answer', fn ($answer) => str_contains($answer, 'chỉ tra cứu thông tin công khai') && ! str_contains($answer, $customer->email) && ! str_contains($answer, 'PRIVATE CLIENT'));
        GymSetting::current()->update(['address' => null, 'phone' => null, 'email' => null, 'opening_hours' => null]);
        $this->postJson(route('chatbot.message'), ['message' => 'Địa chỉ và số điện thoại ở đâu?'])
            ->assertServiceUnavailable()
            ->assertJsonPath('message', 'Trợ lý đang bận. Bạn thử lại sau nhé.');
        Http::assertSentCount(1);
    }

    public function test_default_chat_suggestions_work_while_external_rag_is_offline(): void
    {
        Http::fake(['*' => Http::failedConnection()]);
        $this->postJson(route('chatbot.message'), ['message' => 'Gói tập 1 tháng tính thời hạn thế nào?'])
            ->assertOk()->assertJsonStructure(['answer', 'sources']);
        $this->postJson(route('chatbot.message'), ['message' => 'Tôi vừa chuyển khoản, khi nào được vào tập?'])
            ->assertOk()->assertJsonPath('answer', fn ($answer) => str_contains($answer, 'Nhân viên ghi nhận'));
        $this->postJson(route('chatbot.message'), ['message' => 'Tôi quên mật khẩu thì làm sao?'])
            ->assertOk()->assertJsonStructure(['answer', 'sources']);
        Http::assertSentCount(2);
    }
}
