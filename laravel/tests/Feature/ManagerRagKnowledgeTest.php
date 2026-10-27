<?php

namespace Tests\Feature;

use App\Models\RagDocument;
use App\Models\User;
use App\Services\RagKnowledgeService;
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
        RagDocument::query()->create([
            'document_key' => 'membership-rules',
            'title' => 'Quy tắc hội viên',
            'content' => '# Quy tắc hội viên',
            'is_active' => true,
            'synced_at' => now(),
        ]);
        Http::fake([
            '127.0.0.1:8090/admin/documents/batch' => Http::response(['updated' => 1]),
        ]);

        $this->actingAs($manager)->get(route('manager.knowledge.index'))
            ->assertOk()
            ->assertSee('Quy tắc hội viên');

        $this->post(route('manager.knowledge.store'), [
            'title' => 'Quy định đăng ký',
            'content' => 'Khách có thể đăng ký gói tập tại quầy.',
        ])->assertRedirect(route('manager.knowledge.index'));

        $document = RagDocument::query()->where('document_key', 'quy-dinh-dang-ky')->firstOrFail();
        $this->assertStringContainsString('# Quy định đăng ký', $document->content);
        $this->assertNotNull($document->synced_at);
        Http::assertSent(fn ($request): bool => $request->method() === 'POST'
            && $request->url() === 'http://127.0.0.1:8090/admin/documents/batch'
            && $request->hasHeader('Authorization', 'Bearer test-rag-admin-token')
            && collect($request['documents'])->contains(fn (array $item): bool => $item['id'] === 'quy-dinh-dang-ky'));
    }

    public function test_opening_and_searching_the_document_list_only_reads_paginated_mysql_data(): void
    {
        $manager = User::factory()->manager()->create();
        foreach (range(1, 25) as $index) {
            $documentId = sprintf('doc-%03d', $index);
            RagDocument::query()->create([
                'document_key' => $documentId,
                'title' => $index === 25 ? 'Hướng dẫn thanh toán' : 'Hướng dẫn '.$index,
                'content' => '# Nội dung '.$index,
                'is_active' => true,
            ]);
        }
        Http::fake();

        $this->actingAs($manager)->get(route('manager.knowledge.index'))
            ->assertOk()
            ->assertSee('Hướng dẫn 1')
            ->assertSee('page=2', false)
            ->assertDontSee('Hướng dẫn thanh toán');

        $this->get(route('manager.knowledge.index', ['page' => 2]))
            ->assertOk()
            ->assertSee('Hướng dẫn thanh toán');

        $this->get(route('manager.knowledge.index', ['q' => 'thanh toán']))
            ->assertOk()
            ->assertSee('Hướng dẫn thanh toán')
            ->assertDontSee('Hướng dẫn 1');

        Http::assertNothingSent();
    }

    public function test_manager_can_explicitly_sync_pending_documents_without_get_side_effects(): void
    {
        $manager = User::factory()->manager()->create();
        $document = RagDocument::query()->create([
            'document_key' => 'pending-guide',
            'title' => 'Hướng dẫn đang chờ',
            'content' => '# Hướng dẫn đang chờ',
            'is_active' => true,
        ]);
        Http::fake(['127.0.0.1:8090/admin/documents/batch' => Http::response(['updated' => 1])]);

        $this->actingAs($manager)->get(route('manager.knowledge.index'))
            ->assertOk()
            ->assertSee('1 tài liệu đang chờ chatbot cập nhật')
            ->assertSee(route('manager.knowledge.sync'));
        Http::assertNothingSent();

        $this->post(route('manager.knowledge.sync'))
            ->assertRedirect(route('manager.knowledge.index'))
            ->assertSessionHas('success');

        $this->assertNotNull($document->fresh()->synced_at);
    }

    public function test_a_second_sync_request_does_not_overlap_the_active_sync_lock(): void
    {
        RagDocument::query()->create([
            'document_key' => 'single-sync',
            'title' => 'Một lần đồng bộ',
            'content' => '# Một lần đồng bộ',
            'is_active' => true,
        ]);
        Http::fake(function () {
            app(RagKnowledgeService::class)->syncPending();

            return Http::response(['updated' => 1]);
        });

        app(RagKnowledgeService::class)->syncPending();

        Http::assertSentCount(1);
        $this->assertNotNull(RagDocument::query()->firstOrFail()->synced_at);
    }

    public function test_edit_during_rag_request_keeps_the_new_document_revision_pending(): void
    {
        $document = RagDocument::query()->create([
            'document_key' => 'race-test',
            'title' => 'Bản cũ',
            'content' => "# Bản cũ\n\nThông tin cũ.",
            'is_active' => true,
        ]);

        app(RagKnowledgeService::class)->update('race-test', "# Bản cũ\n\nThông tin cũ.");

        Http::fake(function ($request) {
            if ($request->url() === 'http://127.0.0.1:8090/admin/documents/batch') {
                app(RagKnowledgeService::class)->update('race-test', "# Bản mới\n\nThông tin mới.");
            }

            return Http::response(['updated' => 1]);
        });

        app(RagKnowledgeService::class)->syncPending();

        $this->assertSame("# Bản mới\n\nThông tin mới.", $document->fresh()->content);
        $this->assertNull($document->fresh()->synced_at);
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
        Http::fake([
            '127.0.0.1:8090/admin/documents' => Http::response(['error' => 'unavailable'], 503),
            '127.0.0.1:8090/admin/documents/batch' => Http::response(['error' => 'unavailable'], 503),
        ]);

        $this->actingAs($manager)->from(route('manager.knowledge.create'))
            ->post(route('manager.knowledge.store'), [
                'title' => 'Thông tin gói tập',
                'content' => 'Một nội dung công khai hợp lệ.',
            ])->assertRedirect(route('manager.knowledge.index'))
            ->assertSessionHas('error');

        $document = RagDocument::query()->where('document_key', 'thong-tin-goi-tap')->firstOrFail();
        $this->assertNull($document->synced_at);
        $this->assertNotNull($document->sync_error);
    }
}
