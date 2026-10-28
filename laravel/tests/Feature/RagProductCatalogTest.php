<?php

namespace Tests\Feature;

use App\Models\GymPackage;
use App\Models\RagDocument;
use App\Models\User;
use App\Services\RagProductCatalogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RagProductCatalogTest extends TestCase
{
    use RefreshDatabase;

    private int $catalogTestProductId;

    protected function setUp(): void
    {
        parent::setUp();

        $category = (int) DB::table('LOAISANPHAM')->max('MALOAISP') + 100;
        $product = (int) DB::table('SANPHAM')->max('MASP') + 100;
        $this->catalogTestProductId = $product;
        DB::table('LOAISANPHAM')->insert(['MALOAISP' => $category, 'TENLOAISP' => 'ZZZ Nhóm sản phẩm kiểm thử RAG']);
        DB::table('SANPHAM')->insert([
            ['MASP' => $product, 'TENSP' => 'ZZZ Whey Protein kiểm thử', 'MALOAISP' => $category, 'DONGIA' => 900000, 'GIAKHUYENMAI' => 800000, 'SOLUONGTON' => 5, 'HANG' => 'The Gym', 'XUATXU' => 'Việt Nam', 'BAOHANH' => null, 'MOTACHUNG' => 'Bổ sung protein sau tập.', 'MOTACHITIET' => null],
            ['MASP' => $product + 1, 'TENSP' => 'ZZZ Thảm Yoga kiểm thử', 'MALOAISP' => $category, 'DONGIA' => 300000, 'GIAKHUYENMAI' => null, 'SOLUONGTON' => 0, 'HANG' => 'Fit', 'XUATXU' => 'Việt Nam', 'BAOHANH' => null, 'MOTACHUNG' => 'Thảm tập chống trượt.', 'MOTACHITIET' => null],
        ]);

        config([
            'services.rag.url' => 'http://127.0.0.1:8090',
            'services.rag.admin_token' => 'test-rag-admin-token',
            'services.rag.admin_timeout' => 120,
        ]);
    }

    public function test_product_catalog_questions_get_a_live_database_answer_without_waiting_for_rag_sync(): void
    {
        Http::fake(['127.0.0.1:8090/admin/documents/batch' => Http::response(['updated' => 3])]);

        $response = $this->postJson('/chatbot/message', ['message' => 'Bạn có những sản phẩm gì?']);

        $response->assertOk()
            ->assertJsonPath('sources.0', 'Danh mục sản phẩm The Gym');

        $this->assertCount(4, $response->json('products'));
        $this->assertStringContainsString('/san-pham/', $response->json('products.0.detail_url'));

        $answer = $response->json('answer');
        $this->assertStringContainsString('ZZZ Nhóm sản phẩm kiểm thử RAG', $answer);
        $this->assertStringContainsString('ZZZ Whey Protein kiểm thử', $answer);
        $this->assertStringContainsString('/san-pham', $answer);

        $this->assertDatabaseCount('rag_documents', 0);
        Http::assertNothingSent();
        Http::assertNotSent(fn ($request): bool => str_ends_with($request->url(), '/chat'));
    }

    public function test_package_list_and_prices_are_answered_from_current_active_database_packages(): void
    {
        GymPackage::factory()->create(['name' => 'Gói 1 tháng', 'duration_months' => 1, 'price' => 399000, 'is_active' => true]);
        GymPackage::factory()->create(['name' => 'Gói 12 tháng', 'duration_months' => 12, 'price' => 4389000, 'is_active' => true]);
        GymPackage::factory()->create(['name' => 'Gói đã ngừng bán', 'duration_months' => 3, 'price' => 999000, 'is_active' => false]);
        Http::fake();

        $this->postJson('/chatbot/message', ['message' => 'The Gym có những gói tập nào và giá bao nhiêu?'])
            ->assertOk()
            ->assertJsonPath('sources.0', 'Gói tập hiện đang mở bán')
            ->assertJsonPath('products', [])
            ->assertJsonPath('answer', 'The Gym hiện đang mở bán: Gói 1 tháng — 399.000 VNĐ; Gói 12 tháng — 4.389.000 VNĐ. Bạn có thể xem và đăng ký tại /goi-tap.');

        Http::assertNothingSent();
    }

    public function test_an_unlisted_two_month_package_price_is_only_given_as_a_labeled_estimate(): void
    {
        GymPackage::factory()->create(['name' => 'Gói 1 tháng', 'duration_months' => 1, 'price' => 399000, 'is_active' => true]);
        Http::fake();

        $this->postJson('/chatbot/message', ['message' => 'Gói 2 tháng giá bao nhiêu?'])
            ->assertOk()
            ->assertJsonPath('sources.0', 'Ước tính từ giá gói 1 tháng đang bán')
            ->assertJsonPath('answer', 'The Gym chưa có gói 2 tháng đang mở bán. Nếu chỉ ước tính theo giá gói 1 tháng hiện tại (399.000 VNĐ), 2 tháng tương đương khoảng 798.000 VNĐ; đây không phải giá bán hay gói đăng ký chính thức. Xem gói đang bán tại /goi-tap.');

        Http::assertNothingSent();
    }

    public function test_product_catalog_index_is_not_rebuilt_when_its_mysql_content_has_not_changed(): void
    {
        Http::fake(['127.0.0.1:8090/admin/documents/batch' => Http::response(['updated' => 3])]);

        $catalog = app(RagProductCatalogService::class);
        $catalog->sync();
        $catalog->sync();

        Http::assertSentCount(1);
    }

    public function test_catalog_price_and_stock_status_are_resynchronized_after_product_data_changes(): void
    {
        Http::fake(['127.0.0.1:8090/admin/documents/batch' => Http::response(['updated' => 3])]);
        $catalog = app(RagProductCatalogService::class);
        $catalog->sync();

        DB::table('SANPHAM')->where('MASP', $this->catalogTestProductId)->update([
            'DONGIA' => 1000000,
            'GIAKHUYENMAI' => 700000,
            'SOLUONGTON' => 0,
        ]);
        $catalog->sync();

        $category = RagDocument::query()->where('document_key', 'managed-products-category-'.((int) DB::table('LOAISANPHAM')->max('MALOAISP')))->firstOrFail();
        $this->assertStringContainsString('700.000đ (giá gốc 1.000.000đ)', $category->content);
        $this->assertStringContainsString('Tạm hết hàng', $category->content);
        Http::assertSentCount(2);
    }

    public function test_specific_product_price_questions_use_the_current_catalog_instead_of_stale_rag_answers(): void
    {
        Http::fake([
            '127.0.0.1:8090/admin/documents/batch' => Http::response(['updated' => 3]),
            '127.0.0.1:8090/chat' => Http::response([
                'answer' => 'ZZZ Whey Protein kiểm thử đang được bán theo danh mục hiện tại.',
                'sources' => ['Sản phẩm nhóm ZZZ Nhóm sản phẩm kiểm thử RAG'],
            ]),
        ]);

        $this->postJson('/chatbot/message', ['message' => 'ZZZ Whey Protein kiểm thử giá bao nhiêu?'])
            ->assertOk()
            ->assertJsonPath('answer', 'ZZZ Whey Protein kiểm thử có giá 800.000đ (giá gốc 900.000đ), hiện còn hàng. Xem ảnh và thông tin chi tiết bên dưới; nhấn “Chi tiết” để mở trang sản phẩm.')
            ->assertJsonPath('products.0.price', '800.000đ (giá gốc 900.000đ)');

        Http::assertNothingSent();
    }

    public function test_product_questions_include_a_product_image_and_internal_detail_link(): void
    {
        DB::table('HINHANH')->insert([
            'MAHINH' => (int) DB::table('HINHANH')->max('MAHINH') + 100,
            'MASP' => $this->catalogTestProductId,
            'URL' => 'products/qa-whey.webp',
            'ISMAIN' => 1,
        ]);

        Http::fake([
            '127.0.0.1:8090/admin/documents/batch' => Http::response(['updated' => 3]),
            '127.0.0.1:8090/chat' => Http::response([
                'answer' => 'ZZZ Whey Protein kiểm thử có ảnh và trang chi tiết.',
                'sources' => ['Sản phẩm nhóm ZZZ Nhóm sản phẩm kiểm thử RAG'],
            ]),
        ]);

        $this->postJson('/chatbot/message', ['message' => 'ZZZ Whey Protein kiểm thử cho xem ảnh'])
            ->assertOk()
            ->assertJsonPath('answer', 'Đây là ZZZ Whey Protein kiểm thử, giá 800.000đ (giá gốc 900.000đ), hiện còn hàng. Ảnh và thông tin chi tiết hiển thị bên dưới; nhấn “Chi tiết” để mở trang sản phẩm.')
            ->assertJsonPath('products.0.name', 'ZZZ Whey Protein kiểm thử')
            ->assertJsonPath('products.0.image_url', asset('storage/products/qa-whey.webp'))
            ->assertJsonPath('products.0.detail_url', route('gym.products.show', $this->catalogTestProductId))
            ->assertJsonPath('products.0.price', '800.000đ (giá gốc 900.000đ)')
            ->assertJsonPath('products.0.availability', 'Còn hàng');

        Http::assertNotSent(fn ($request): bool => str_ends_with($request->url(), '/chat'));
    }

    public function test_purchase_how_to_question_is_not_misclassified_as_a_product_list_question(): void
    {
        Http::fake([
            '127.0.0.1:8090/admin/documents/batch' => Http::response(['updated' => 3]),
            '127.0.0.1:8090/chat' => Http::response([
                'answer' => 'Mở /san-pham, đăng nhập, thêm sản phẩm vào giỏ và tạo đơn.',
                'sources' => ['Danh mục sản phẩm và cách mua hàng'],
            ]),
        ]);

        $this->postJson('/chatbot/message', ['message' => 'Mua sản phẩm trên web như thế nào?'])
            ->assertOk()
            ->assertJsonPath('answer', 'Mở /san-pham, đăng nhập, thêm sản phẩm vào giỏ và tạo đơn.')
            ->assertJsonPath('sources.0', 'Hướng dẫn mua hàng');

        Http::assertNotSent(fn ($request): bool => str_ends_with($request->url(), '/chat'));
    }

    public function test_managed_product_catalog_documents_cannot_be_manually_edited_or_disabled(): void
    {
        $manager = User::factory()->manager()->create();
        Http::fake([
            '127.0.0.1:8090/admin/documents' => Http::response(['documents' => []]),
            '127.0.0.1:8090/admin/documents/batch' => Http::response(['updated' => 3]),
        ]);
        RagDocument::query()->create([
            'document_key' => 'managed-products-overview',
            'title' => 'Danh mục sản phẩm The Gym',
            'content' => '# Danh mục sản phẩm The Gym',
            'is_active' => true,
            'synced_at' => now(),
        ]);

        $this->actingAs($manager)->get(route('manager.knowledge.index'))
            ->assertOk()
            ->assertSee('Tự đồng bộ từ danh mục sản phẩm')
            ->assertDontSee(route('manager.knowledge.edit', 'managed-products-overview'));

        $this->actingAs($manager)->put(route('manager.knowledge.update', 'managed-products-overview'), [
            'title' => 'Danh mục giả',
            'content' => 'Nội dung không được ghi đè.',
        ])->assertForbidden();

        $this->actingAs($manager)->patch(route('manager.knowledge.toggle', 'managed-products-overview'), [
            'active' => false,
        ])->assertForbidden();
    }
}
