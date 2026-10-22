<?php

namespace App\Services;

use App\Models\RagDocument;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class RagProductCatalogService
{
    private const OVERVIEW_DOCUMENT_ID = 'managed-products-overview';

    private const DOCUMENT_PREFIX = RagKnowledgeService::PRODUCT_CATALOG_PREFIX;

    public function __construct(private RagKnowledgeService $knowledge) {}

    /**
     * Keep chatbot catalog documents synchronized with the public product data.
     *
     * @param  list<array{id: string, content: string, active: bool}>  $additionalDocuments
     */
    public function sync(array $additionalDocuments = []): void
    {
        $this->stage($additionalDocuments);
        $this->knowledge->syncPending();
    }

    /**
     * Persist the current product catalog into MySQL before it is sent to RAG.
     *
     * @param  list<array{id: string, content: string, active: bool}>  $additionalDocuments
     */
    public function stage(array $additionalDocuments = []): void
    {
        if (! Schema::hasTable('SANPHAM') || ! Schema::hasTable('LOAISANPHAM')) {
            return;
        }

        $documents = [...$this->buildDocuments($this->products()), ...$additionalDocuments];
        $existing = RagDocument::withTrashed()
            ->where('document_key', 'like', self::DOCUMENT_PREFIX.'%')
            ->get()
            ->keyBy('document_key');
        $upserts = [];

        foreach ($documents as $document) {
            $stored = $existing->get($document['id']);
            if ($stored
                && $stored->deleted_at === null
                && $stored->is_active
                && $stored->content === $document['content']
                && $stored->synced_at !== null) {
                continue;
            }

            $upserts[] = $document;
        }

        $currentIds = collect($documents)->pluck('id')->all();
        foreach ($existing->whereNotIn('document_key', $currentIds) as $stored) {
            if (! $stored->is_active || $stored->deleted_at !== null) {
                continue;
            }

            $upserts[] = [
                'id' => $stored->document_key,
                'content' => $stored->content,
                'active' => false,
            ];
        }

        if ($upserts !== []) {
            $this->knowledge->batchUpsert($upserts);
        }
    }

    /**
     * Return a deterministic live answer for broad product-catalog questions.
     */
    public function answerOverviewQuestion(string $question): ?string
    {
        $normalizedQuestion = $this->normalize($question);
        if (! preg_match('/\b(?:san pham|hang hoa|danh muc)\b/u', $normalizedQuestion)
            || preg_match('/\b(?:mua|dat hang|gio hang|gia|bao nhieu|con hang|het hang|con khong|giam gia|khuyen mai|thuong hieu|xuat xu|mo ta|cach|huong dan|lam sao|the nao)\b/u', $normalizedQuestion)
            || ! preg_match('/\b(?:co|ban|liet ke|danh muc|danh sach|nhung|cac)\b/u', $normalizedQuestion)
            || ! preg_match('/\b(?:gi|nao|gom|liet ke|danh muc|danh sach)\b/u', $normalizedQuestion)) {
            return null;
        }

        $products = $this->products();
        if ($products->isEmpty()) {
            return 'Hiện The Gym chưa có sản phẩm nào trong danh mục. Bạn có thể xem danh mục mới nhất tại /san-pham.';
        }

        $groups = $products->groupBy(fn (object $product): string => (string) ($product->TENLOAISP ?: 'Sản phẩm khác'));
        $summary = $groups->map(function (Collection $items, string $category): string {
            $examples = $items->take(2)->pluck('TENSP')->implode(', ');

            return $category.($examples !== '' ? ' (ví dụ: '.$examples.')' : '');
        })->implode('; ');

        return 'The Gym hiện có '.$products->count().' sản phẩm thuộc '.
            $groups->count().' nhóm: '.$summary.'. Xem toàn bộ danh mục, giá và tình trạng còn hàng mới nhất tại /san-pham. Bạn muốn tìm sản phẩm trong nhóm nào?';
    }

    /**
     * Return a small set of live product cards relevant to a product question.
     *
     * @return list<array{id: int, name: string, image_url: string, image_placeholder_url: string, detail_url: string, price: string, availability: string}>
     */
    public function cardsForQuestion(string $question, int $limit = 4): array
    {
        $products = $this->products();
        if ($products->isEmpty()) {
            return [];
        }

        $normalizedQuestion = $this->normalize($question);
        $selected = collect();
        $exactMatches = $products->filter(
            fn (object $product): bool => str_contains($normalizedQuestion, $this->normalize((string) $product->TENSP)),
        );

        if ($exactMatches->isNotEmpty()) {
            $selected = $exactMatches->take($limit)->values();
        } elseif ($this->answerOverviewQuestion($question) !== null) {
            $selected = $products
                ->groupBy(fn (object $product): string => (string) ($product->MALOAISP ?? '0'))
                ->map(fn (Collection $items): object => $items->first())
                ->take($limit)
                ->values();
        } else {
            $categories = $products
                ->pluck('TENLOAISP')
                ->filter()
                ->unique()
                ->filter(fn (string $category): bool => str_contains($normalizedQuestion, $this->normalize($category)));

            if ($categories->isNotEmpty()) {
                $selected = $products
                    ->filter(fn (object $product): bool => $categories->contains($product->TENLOAISP))
                    ->take($limit)
                    ->values();
            } else {
                $questionTerms = $this->productTerms($normalizedQuestion);
                $selected = $products
                    ->map(function (object $product) use ($questionTerms): array {
                        $productTerms = $this->productTerms($this->normalize((string) $product->TENSP));
                        $matches = count(array_intersect($questionTerms, $productTerms));
                        $minimumMatches = count($questionTerms) === 1 ? 1 : min(2, count($productTerms));

                        return ['product' => $product, 'score' => $matches >= $minimumMatches ? $matches : 0];
                    })
                    ->filter(fn (array $match): bool => $match['score'] > 0)
                    ->sortByDesc('score')
                    ->take($limit)
                    ->pluck('product')
                    ->values();
            }
        }

        return $selected->map(function (object $product): array {
            $id = (int) $product->MASP;

            return [
                'id' => $id,
                'name' => (string) $product->TENSP,
                'image_url' => OriginalGymSql::image($product->image_url ?? null, $id),
                'image_placeholder_url' => asset('images/product-placeholder.svg'),
                'detail_url' => route('gym.products.show', $id),
                'price' => $this->price($product),
                'availability' => $product->available_quantity > 0 ? 'Còn hàng' : 'Tạm hết hàng',
            ];
        })->all();
    }

    /**
     * Answer image and detail requests directly so text cannot contradict the matching product cards.
     *
     * @param  list<array{id: int, name: string, image_url: string, image_placeholder_url: string, detail_url: string, price: string, availability: string}>  $cards
     */
    public function answerProductCardQuestion(string $question, array $cards): ?string
    {
        if ($cards === [] || ! preg_match('/\b(?:hinh|anh|xem|chi tiet|mo ta)\b/u', $this->normalize($question))) {
            return null;
        }

        if (count($cards) === 1) {
            $product = $cards[0];

            return 'Đây là '.$product['name'].', giá '.$product['price'].', hiện '.mb_strtolower($product['availability']).'. Ảnh và thông tin chi tiết hiển thị bên dưới; nhấn “Chi tiết” để mở trang sản phẩm.';
        }

        return 'Mình tìm thấy một số sản phẩm phù hợp. Xem ảnh bên dưới và nhấn “Chi tiết” ở sản phẩm muốn xem trên website.';
    }

    public function isProductRelatedQuestion(string $question): bool
    {
        $normalizedQuestion = $this->normalize($question);
        if (preg_match('/\b(?:san pham|hang hoa|danh muc san pham|thuc pham bo sung|phu kien tap|dung cu tap|quan ao tap|whey|protein|preworkout|casein|gio hang|mua hang)\b/u', $normalizedQuestion)) {
            return true;
        }

        if (! Schema::hasTable('SANPHAM')) {
            return false;
        }

        foreach (DB::table('SANPHAM')->pluck('TENSP') as $productName) {
            if ($productName !== '' && str_contains($normalizedQuestion, $this->normalize((string) $productName))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return Collection<int, object>
     */
    private function products(): Collection
    {
        if (! Schema::hasTable('SANPHAM') || ! Schema::hasTable('LOAISANPHAM')) {
            return collect();
        }

        $query = DB::table('SANPHAM as p')
            ->leftJoin('LOAISANPHAM as c', 'c.MALOAISP', '=', 'p.MALOAISP')
            ->select([
                'p.MASP',
                'p.TENSP',
                'p.MALOAISP',
                'p.DONGIA',
                'p.GIAKHUYENMAI',
                'p.SOLUONGTON',
                'p.HANG',
                'p.XUATXU',
                'p.BAOHANH',
                'p.MOTACHUNG',
                'p.MOTACHITIET',
                'c.TENLOAISP',
            ])
            ->orderBy('c.MALOAISP')
            ->orderBy('p.TENSP');

        if (Schema::hasTable('HINHANH')) {
            $query->selectSub(
                DB::table('HINHANH')
                    ->select('URL')
                    ->whereColumn('MASP', 'p.MASP')
                    ->orderByDesc('ISMAIN')
                    ->orderBy('MAHINH')
                    ->limit(1),
                'image_url',
            );
        }

        $products = $query->get();

        if ($products->isEmpty() || ! Schema::hasTable('gym_invoice_lines') || ! Schema::hasTable('HOADON')) {
            return $products->map(function (object $product): object {
                $product->available_quantity = max(0, (int) $product->SOLUONGTON);

                return $product;
            });
        }

        $reserved = DB::table('gym_invoice_lines as l')
            ->join('HOADON as h', 'h.MAHD', '=', 'l.invoice_id')
            ->where('l.kind', 'product')
            ->where('h.TRANGTHAI', 'Chưa thanh toán')
            ->selectRaw('l.item_id, COALESCE(SUM(l.quantity), 0) AS reserved_quantity')
            ->groupBy('l.item_id')
            ->get()
            ->keyBy('item_id');

        return $products->map(function (object $product) use ($reserved): object {
            $product->available_quantity = max(
                0,
                (int) $product->SOLUONGTON - (int) ($reserved->get($product->MASP)->reserved_quantity ?? 0),
            );

            return $product;
        });
    }

    /**
     * @param  Collection<int, object>  $products
     * @return list<array{id: string, content: string, active: bool}>
     */
    private function buildDocuments(Collection $products): array
    {
        $groups = $products->groupBy(fn (object $product): string => (string) ($product->MALOAISP ?? '0'));
        $summaryLines = [
            '# Danh mục sản phẩm The Gym',
            '',
            '**Nguồn dữ liệu:** Danh mục sản phẩm hiện hành trong MySQL của website The Gym. Tài liệu này được tự đồng bộ trước khi chatbot trả lời.',
            '**Chủ đề/câu hỏi:** The Gym có những sản phẩm gì? Có bán sản phẩm nào? Có thực phẩm bổ sung, quần áo tập, phụ kiện hay dụng cụ tập không? Danh sách sản phẩm The Gym và cách tìm sản phẩm.',
            '',
        ];

        if ($products->isEmpty()) {
            $summaryLines[] = 'Hiện danh mục chưa có sản phẩm. Hãy kiểm tra trang /san-pham để xem trạng thái mới nhất.';
        } else {
            $summaryLines[] = 'Hiện có '.$products->count().' sản phẩm thuộc '.$groups->count().' nhóm:';
            foreach ($groups as $items) {
                $category = (string) ($items->first()->TENLOAISP ?: 'Sản phẩm khác');
                $examples = $items->take(3)->pluck('TENSP')->implode(', ');
                $summaryLines[] = '- '.$category.': '.$items->count().' sản phẩm'.($examples !== '' ? '; ví dụ '.$examples : '').'.';
            }
        }
        $summaryLines[] = '';
        $summaryLines[] = 'Xem danh mục, giá và tình trạng còn hàng mới nhất tại /san-pham. Không nêu số lượng tồn cụ thể trong câu trả lời công khai; trạng thái còn hàng có thể thay đổi.';

        $documents = [[
            'id' => self::OVERVIEW_DOCUMENT_ID,
            'content' => implode("\n", $summaryLines),
            'active' => true,
        ]];

        foreach ($groups as $categoryId => $items) {
            $category = (string) ($items->first()->TENLOAISP ?: 'Sản phẩm khác');
            $lines = [
                '# Sản phẩm nhóm '.$category,
                '',
                '**Nguồn dữ liệu:** Sản phẩm hiện hành đọc từ MySQL; danh sách và giá được tự đồng bộ trước khi chatbot trả lời.',
                '**Chủ đề/câu hỏi:** Sản phẩm '.$category.' tại The Gym; sản phẩm nào thuộc nhóm '.$category.'; giá, thương hiệu, xuất xứ và tình trạng còn hàng của sản phẩm.',
                '',
            ];

            foreach ($items as $product) {
                $lines[] = '- '.$product->TENSP.' — '.$this->price($product).'; '.
                    ($product->available_quantity > 0 ? 'Còn hàng' : 'Tạm hết hàng').'.';
                if ($product->HANG) {
                    $lines[] = '  Thương hiệu: '.$product->HANG.'.';
                }
                if ($product->XUATXU) {
                    $lines[] = '  Xuất xứ: '.$product->XUATXU.'.';
                }
                if ($product->BAOHANH) {
                    $lines[] = '  Bảo hành: '.$product->BAOHANH.'.';
                }
                $description = $this->description($product);
                if ($description !== '') {
                    $lines[] = '  Mô tả: '.$description;
                }
            }

            $lines[] = '';
            $lines[] = 'Danh mục đầy đủ và tình trạng mới nhất tại /san-pham?category='.$categoryId.'. Giá hoặc tình trạng có thể thay đổi sau khi dữ liệu được đồng bộ.';
            $documents[] = [
                'id' => self::DOCUMENT_PREFIX.'category-'.(int) $categoryId,
                'content' => implode("\n", $lines),
                'active' => true,
            ];
        }

        return $documents;
    }

    private function price(object $product): string
    {
        $price = (int) $product->DONGIA;
        $salePrice = $product->GIAKHUYENMAI;
        if ($salePrice !== null && (int) $salePrice < $price) {
            return number_format((int) $salePrice, 0, ',', '.').'đ (giá gốc '.number_format($price, 0, ',', '.').'đ)';
        }

        return number_format($price, 0, ',', '.').'đ';
    }

    private function description(object $product): string
    {
        $description = trim(preg_replace('/\s+/u', ' ', strip_tags((string) ($product->MOTACHITIET ?: $product->MOTACHUNG))) ?? '');

        return Str::limit($description, 360, '…');
    }

    private function normalize(string $text): string
    {
        return Str::ascii(str_replace(['đ', 'Đ'], ['d', 'D'], mb_strtolower($text)));
    }

    /** @return list<string> */
    private function productTerms(string $text): array
    {
        $ignored = ['anh', 'ban', 'bao', 'cho', 'co', 'cua', 'danh', 'gia', 'hang', 'hinh', 'la', 'mua', 'nhieu', 'nao', 'pham', 'san', 'tap', 'the', 'toi', 'xem', 'web'];
        $terms = preg_split('/[^a-z0-9]+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_unique(array_filter(
            $terms,
            fn (string $term): bool => mb_strlen($term) >= 3 && ! in_array($term, $ignored, true),
        )));
    }
}
