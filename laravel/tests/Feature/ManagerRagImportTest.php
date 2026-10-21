<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ManagerRagImportTest extends TestCase
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

    #[Test]
    public function manager_list_hides_markdown_and_technical_document_ids(): void
    {
        $manager = User::factory()->manager()->create();
        Http::fake([
            '127.0.0.1:8090/admin/documents' => Http::response(['documents' => [[
                'id' => 'technical-id',
                'title' => 'Hướng dẫn hội viên',
                'content' => "# Hướng dẫn hội viên\n\n**Câu trả lời:** Nội dung nội bộ hiển thị ở đây.",
                'active' => true,
                'updated_at' => '2026-10-20T12:30:00+07:00',
            ]]]),
        ]);

        $this->actingAs($manager)->get(route('manager.knowledge.index'))
            ->assertOk()
            ->assertSee('Hướng dẫn hội viên')
            ->assertSee('20/10/2026')
            ->assertDontSee('Mã: technical-id')
            ->assertDontSee('**Câu trả lời:**')
            ->assertDontSee('Thử chatbot')
            ->assertSee('Hỏi The Gym');

        $this->post('/quan-ly/tai-lieu-chatbot/hoi-thu')->assertStatus(405);
    }

    #[Test]
    public function manager_can_add_a_frequently_asked_question_without_markdown(): void
    {
        $manager = User::factory()->manager()->create();
        Http::fake([
            '127.0.0.1:8090/admin/documents' => Http::response(['documents' => []]),
        ]);

        $this->actingAs($manager)->post(route('manager.knowledge.faq.store'), [
            'topic' => 'Hội viên',
            'question' => 'Gói một tháng sử dụng ra sao?',
            'answer' => 'Gói có hiệu lực theo ngày bắt đầu đã chọn.',
        ])->assertRedirect(route('manager.knowledge.index'))->assertSessionHas('success');

        Http::assertSent(fn ($request): bool => $request->method() === 'POST');
        Http::assertSent(fn ($request): bool => $request->url() === 'http://127.0.0.1:8090/admin/documents');
        Http::assertSent(fn ($request): bool => $request->method() === 'POST'
            && $request['id'] === 'hoi-vien-goi-mot-thang-su-dung-ra-sao');
        $savedFaq = Http::recorded(fn ($request): bool => $request->method() === 'POST')->last()[0];
        $this->assertStringContainsString('Gói một tháng sử dụng ra sao?', $savedFaq['content']);
        $this->assertStringContainsString('Gói có hiệu lực theo ngày bắt đầu đã chọn.', $savedFaq['content']);
    }

    #[Test]
    public function manager_previews_a_faq_spreadsheet_then_confirms_all_rows_as_one_batch(): void
    {
        $manager = User::factory()->manager()->create();
        Http::fake([
            '127.0.0.1:8090/admin/documents' => Http::response(['documents' => [[
                'id' => 'existing-faq', 'title' => 'Gói tập', 'content' => '# Gói tập', 'active' => true,
            ]]]),
            '127.0.0.1:8090/admin/documents/batch' => Http::response(['updated' => 2], 200),
        ]);

        $preview = $this->actingAs($manager)->post(route('manager.knowledge.import.preview'), [
            'file' => $this->spreadsheet([
                ['Mã', 'Chủ đề', 'Câu hỏi', 'Câu trả lời', 'Trạng thái'],
                ['existing-faq', 'Gói tập', 'Gói một tháng có hạn bao lâu?', 'Tính theo ngày bắt đầu được chọn.', 'Đang dùng'],
                ['', 'Thanh toán', 'Có thể thanh toán ở đâu?', 'Khách thanh toán tại quầy hoặc chuyển khoản.', 'Tạm ngừng'],
            ]),
        ]);
        $preview->assertRedirect(route('manager.knowledge.import.review'));

        $this->get(route('manager.knowledge.import.review'))
            ->assertOk()
            ->assertSee('1 tài liệu mới')
            ->assertSee('1 tài liệu sẽ được cập nhật')
            ->assertSee('Gói một tháng có hạn bao lâu?');

        $this->post(route('manager.knowledge.import.confirm'))
            ->assertRedirect(route('manager.knowledge.index'))
            ->assertSessionHas('success');

        Http::assertSent(fn ($request): bool => $request->method() === 'POST'
            && $request->url() === 'http://127.0.0.1:8090/admin/documents/batch'
            && count($request['documents']) === 2
            && $request['documents'][0]['id'] === 'existing-faq'
            && $request['documents'][0]['active'] === true
            && $request['documents'][1]['active'] === false);
    }

    #[Test]
    public function import_preview_blocks_duplicate_ids_and_never_sends_a_partial_batch(): void
    {
        $manager = User::factory()->manager()->create();
        Http::fake([
            '127.0.0.1:8090/admin/documents' => Http::response(['documents' => []]),
        ]);

        $this->actingAs($manager)->post(route('manager.knowledge.import.preview'), [
            'file' => $this->spreadsheet([
                ['Mã', 'Chủ đề', 'Câu hỏi', 'Câu trả lời', 'Trạng thái'],
                ['same-id', 'Gói tập', 'Câu hỏi một', 'Câu trả lời một', 'Đang dùng'],
                ['same-id', 'Thanh toán', 'Câu hỏi hai', 'Câu trả lời hai', 'Đang dùng'],
            ]),
        ])->assertRedirect(route('manager.knowledge.import.review'));

        $this->get(route('manager.knowledge.import.review'))
            ->assertOk()
            ->assertSee('Dòng 3')
            ->assertSee('Xử lý các lỗi trước khi xác nhận')
            ->assertDontSee('Xác nhận nhập tài liệu');

        $this->post(route('manager.knowledge.import.confirm'))
            ->assertRedirect(route('manager.knowledge.import.review'))
            ->assertSessionHas('error');
        Http::assertNotSent(fn ($request): bool => $request->url() === 'http://127.0.0.1:8090/admin/documents/batch');
    }

    #[Test]
    public function manager_can_download_the_xlsx_template(): void
    {
        $manager = User::factory()->manager()->create();
        $response = $this->actingAs($manager)->get(route('manager.knowledge.import.template'));

        $response->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $path = tempnam(sys_get_temp_dir(), 'gym-template-');
        file_put_contents($path, $response->streamedContent());
        $spreadsheet = IOFactory::load($path);
        unlink($path);

        $this->assertSame('Câu hỏi', $spreadsheet->getSheet(0)->getTitle());
        $this->assertSame('Mã', $spreadsheet->getSheet(0)->getCell('A1')->getValue());
        $this->assertSame('Cách nhập', $spreadsheet->getSheet(1)->getTitle());
    }

    #[Test]
    public function non_managers_cannot_import_faqs_or_download_the_template(): void
    {
        foreach ([User::factory()->employee()->create(), User::factory()->create()] as $user) {
            $this->actingAs($user)->get(route('manager.knowledge.import.template'))->assertForbidden();
            $this->post(route('manager.knowledge.faq.store'), [])->assertForbidden();
            $this->post(route('manager.knowledge.import.preview'), [])->assertForbidden();
            $this->get(route('manager.knowledge.import.review'))->assertForbidden();
            $this->post(route('manager.knowledge.import.confirm'))->assertForbidden();
        }
        Http::assertNothingSent();
    }

    #[Test]
    public function import_preview_rejects_more_than_one_hundred_data_rows(): void
    {
        $manager = User::factory()->manager()->create();
        $rows = [['Mã', 'Chủ đề', 'Câu hỏi', 'Câu trả lời', 'Trạng thái']];
        foreach (range(1, 101) as $index) {
            $rows[] = ['', 'Gói tập', 'Câu hỏi '.$index, 'Câu trả lời '.$index, 'Đang dùng'];
        }

        $this->actingAs($manager)->post(route('manager.knowledge.import.preview'), [
            'file' => $this->spreadsheet($rows),
        ])->assertRedirect(route('manager.knowledge.import.review'));

        $this->get(route('manager.knowledge.import.review'))
            ->assertOk()
            ->assertSee('Dòng 102')
            ->assertSee('tối đa 100 dòng dữ liệu')
            ->assertDontSee('Xác nhận nhập tài liệu');

        $this->post(route('manager.knowledge.import.confirm'))->assertRedirect(route('manager.knowledge.import.review'));
        Http::assertNothingSent();
    }

    /** @param list<list<string>> $rows */
    private function spreadsheet(array $rows): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray($rows, null, 'A1');
        $path = tempnam(sys_get_temp_dir(), 'gym-faq-').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);
        $contents = file_get_contents($path);
        unlink($path);
        $spreadsheet->disconnectWorksheets();

        return UploadedFile::fake()->createWithContent('faq.xlsx', $contents);
    }
}
