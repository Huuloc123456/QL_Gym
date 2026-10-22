<?php

namespace Tests\Feature;

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

        $this->postJson('/chatbot/message', ['message' => 'Gói 1 tháng tính thế nào?'])
            ->assertOk()
            ->assertJsonPath('answer', 'Gói 1 tháng có thời hạn theo tháng lịch.')
            ->assertJsonPath('sources.0', 'Ngày bắt đầu và thời hạn gói');

        Http::assertSent(fn (Request $request): bool => $request->url() === 'http://127.0.0.1:8090/chat'
            && $request['message'] === 'Gói 1 tháng tính thế nào?');
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

        $this->postJson('/chatbot/message', ['message' => 'Hỏi về gói tập'])
            ->assertServiceUnavailable()
            ->assertJsonPath('message', 'Trợ lý đang bận. Bạn thử lại sau nhé.');
    }

    public function test_chat_returns_a_safe_message_when_the_rag_service_cannot_be_reached(): void
    {
        Http::fake(['*' => Http::failedConnection()]);

        $this->postJson('/chatbot/message', ['message' => 'Hỏi về gói tập'])
            ->assertServiceUnavailable()
            ->assertJsonPath('message', 'Trợ lý đang bận. Bạn thử lại sau nhé.');
    }

    public function test_homepage_shows_the_chatbot_widget(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('data-gym-chatbot', false)
            ->assertSee('Hỏi The Gym');
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
}
