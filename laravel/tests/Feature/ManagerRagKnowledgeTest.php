<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ManagerRagKnowledgeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.rag.url' => 'http://127.0.0.1:8090',
            'services.rag.admin_token' => 'test-rag-admin-token',
            'services.rag.admin_timeout' => 120,
        ]);
    }

    public function test_manager_can_view_and_create_public_chatbot_documents(): void
    {
        $manager = User::factory()->manager()->create();
        Http::fake([
            '127.0.0.1:8090/admin/documents' => Http::response([
                'documents' => [['id' => 'membership-rules', 'title' => 'Quy tắc hội viên', 'content' => '# Quy tắc hội viên']],
            ]),
        ]);

        $this->actingAs($manager)->get(route('manager.knowledge.index'))
            ->assertOk()
            ->assertSee('Quy tắc hội viên');

        $this->post(route('manager.knowledge.store'), [
            'title' => 'Quy định đăng ký',
            'content' => 'Khách có thể đăng ký gói tập tại quầy.',
        ])->assertRedirect(route('manager.knowledge.index'));

        Http::assertSent(fn ($request): bool => $request->method() === 'POST'
            && $request->url() === 'http://127.0.0.1:8090/admin/documents'
            && $request->hasHeader('Authorization', 'Bearer test-rag-admin-token')
            && $request['id'] === 'quy-dinh-dang-ky'
            && str_contains($request['content'], '# Quy định đăng ký'));
    }

    public function test_non_managers_cannot_manage_chatbot_documents(): void
    {
        $employee = User::factory()->employee()->create(['is_active' => true]);
        $customer = User::factory()->create();

        foreach ([$employee, $customer] as $user) {
            $this->actingAs($user)->get(route('manager.knowledge.index'))->assertForbidden();
            $this->post(route('manager.knowledge.store'), [])->assertForbidden();
        }
    }

    public function test_document_validation_prevents_empty_or_oversized_content(): void
    {
        $manager = User::factory()->manager()->create();

        $this->actingAs($manager)->from(route('manager.knowledge.create'))
            ->post(route('manager.knowledge.store'), [
                'title' => 'Tài liệu trống',
                'content' => '',
            ])->assertSessionHasErrors('content');

        $this->post(route('manager.knowledge.store'), [
            'title' => 'Tài liệu quá dài',
            'content' => str_repeat('x', 200_001),
        ])->assertSessionHasErrors('content');
    }

    public function test_rag_failure_is_reported_without_a_success_message(): void
    {
        $manager = User::factory()->manager()->create();
        Http::fake(['127.0.0.1:8090/admin/documents' => Http::response(['error' => 'unavailable'], 503)]);

        $this->actingAs($manager)->from(route('manager.knowledge.create'))
            ->post(route('manager.knowledge.store'), [
                'title' => 'Thông tin gói tập',
                'content' => 'Một nội dung công khai hợp lệ.',
            ])->assertRedirect(route('manager.knowledge.create'))
            ->assertSessionHas('error');
    }
}
