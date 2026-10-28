<?php

namespace Tests\Feature;

use App\Models\GymSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ChatbotEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_chat_message_is_forwarded_to_rag_and_returns_its_answer_and_sources(): void
    {
        config(['services.rag.url' => 'http://127.0.0.1:8090']);
        Http::fake([
            'http://127.0.0.1:8090/chat' => Http::response([
                'answer' => 'Gói 1 tháng có thời hạn theo tháng lịch.',
                'sources' => ['Ngày bắt đầu và thời hạn gói'],
            ]),
        ]);

        $this->postJson('/chatbot/message', ['message' => 'Làm thế nào để bảo vệ tài khoản hội viên?'])
            ->assertOk()
            ->assertJsonPath('answer', 'Gói 1 tháng có thời hạn theo tháng lịch.')
            ->assertJsonPath('sources.0', 'Ngày bắt đầu và thời hạn gói');

        Http::assertSent(fn (Request $request): bool => $request->url() === 'http://127.0.0.1:8090/chat'
            && $request['message'] === 'Làm thế nào để bảo vệ tài khoản hội viên?');
    }

    public function test_chat_rejects_blank_and_overlong_messages_before_calling_rag(): void
    {
        Http::fake();

        $this->postJson('/chatbot/message', ['message' => ''])->assertUnprocessable();
        $this->postJson('/chatbot/message', ['message' => str_repeat('a', 501)])->assertUnprocessable();

        Http::assertNothingSent();
    }

    public function test_chat_returns_a_safe_service_unavailable_message_when_rag_is_offline(): void
    {
        config(['services.rag.url' => 'http://127.0.0.1:8090']);
        Http::fake(['*' => Http::response(['error' => 'private traceback'], 503)]);

        $this->postJson('/chatbot/message', ['message' => 'Giờ mở cửa phòng tập thế nào?'])
            ->assertServiceUnavailable()
            ->assertJsonPath('message', 'Trợ lý đang bận. Bạn thử lại sau nhé.');
    }

    public function test_chat_returns_a_safe_message_when_the_rag_service_cannot_be_reached(): void
    {
        Http::fake(['*' => Http::failedConnection()]);

        $this->postJson('/chatbot/message', ['message' => 'Giờ mở cửa phòng tập thế nào?'])
            ->assertServiceUnavailable()
            ->assertJsonPath('message', 'Trợ lý đang bận. Bạn thử lại sau nhé.');
    }

    public function test_public_gym_hours_are_answered_from_current_settings(): void
    {
        $this->setGymSettings([
            'opening_hours' => 'Thứ Hai đến Chủ nhật, 05:00–23:00',
            'bank_account' => '0325302068',
            'bank_account_name' => 'Le Tran Phu',
        ]);
        Http::fake();

        $this->postJson('/chatbot/message', ['message' => 'Phòng tập mở cửa mấy giờ?'])
            ->assertOk()
            ->assertJsonPath('sources.0', 'Thông tin công khai The Gym')
            ->assertJsonPath('answer', 'Giờ mở cửa hiện được The Gym công bố là Thứ Hai đến Chủ nhật, 05:00–23:00.');

        Http::assertNothingSent();
    }

    public function test_public_contact_answer_uses_only_the_configured_contact_fields(): void
    {
        $this->setGymSettings([
            'phone' => '0900000000',
            'email' => 'hello@thegym.test',
            'bank_account' => '0325302068',
            'bank_account_name' => 'Le Tran Phu',
        ]);
        Http::fake();

        $this->postJson('/chatbot/message', ['message' => 'Liên hệ The Gym bằng cách nào?'])
            ->assertOk()
            ->assertJsonPath('answer', 'Bạn có thể liên hệ The Gym qua số 0900000000 và email hello@thegym.test.');

        Http::assertNothingSent();
    }

    public function test_public_info_answer_does_not_expose_private_bank_details(): void
    {
        $this->setGymSettings([
            'bank_account' => '0325302068',
            'bank_account_name' => 'Le Tran Phu',
        ]);
        Http::fake(['http://127.0.0.1:8090/chat' => Http::response([
            'answer' => 'Hãy xem thông tin thanh toán trong hóa đơn của bạn.',
            'sources' => [],
        ])]);

        $this->postJson('/chatbot/message', ['message' => 'Số tài khoản ngân hàng nào?'])
            ->assertOk()
            ->assertJsonMissing(['answer' => '0325302068']);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'http://127.0.0.1:8090/chat');
    }

    public function test_general_package_policy_questions_still_use_the_knowledge_documents(): void
    {
        Http::fake(['http://127.0.0.1:8090/chat' => Http::response([
            'answer' => 'Thời hạn gói được tính theo quy định đã công bố trên trang gói tập.',
            'sources' => ['Ngày bắt đầu và thời hạn gói'],
        ])]);

        $this->postJson('/chatbot/message', ['message' => 'Gói 1 tháng sử dụng có thời hạn thế nào?'])
            ->assertOk()
            ->assertJsonPath('sources.0', 'Ngày bắt đầu và thời hạn gói');

        Http::assertSent(fn (Request $request): bool => $request->url() === 'http://127.0.0.1:8090/chat');
    }

    public function test_homepage_shows_the_chatbot_widget(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('data-gym-chatbot', false)
            ->assertSee('Hỏi The Gym');
    }

    public function test_chat_throttle_does_not_consume_the_guest_login_allowance(): void
    {
        $customer = User::factory()->create();
        for ($message = 0; $message < 10; $message++) {
            $this->postJson(route('chatbot.message'), ['message' => 'Xin chào'])->assertOk();
        }
        $this->postJson(route('chatbot.message'), ['message' => 'Xin chào'])->assertTooManyRequests();
        $this->post(route('login.store'), ['username' => $customer->username, 'password' => 'password'])->assertRedirect(route('my.memberships.index'));
        $this->assertAuthenticatedAs($customer);
    }

    public function test_chat_panel_uses_a_compact_header_that_matches_the_site(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('id="gymChatTitle"', false)
            ->assertSee('Trợ lý The Gym')
            ->assertDontSee('The Gym · Trợ lý trực tuyến')
            ->assertDontSee('Thông tin gói tập và hội viên, ngay tại đây.')
            ->assertDontSee('RAG The Gym')
            ->assertDontSee('/500')
            ->assertSee('Enter để gửi · Shift+Enter xuống dòng');
    }

    /** @param array<string, mixed> $attributes */
    private function setGymSettings(array $attributes): void
    {
        GymSetting::query()->findOrFail(1)->forceFill($attributes)->save();
    }
}
