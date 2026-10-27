<?php

namespace Tests\Feature;

use App\Models\GymProduct;
use App\Models\GymProductCategory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GymProductModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_and_category_models_map_the_original_sql_schema_and_relationships(): void
    {
        DB::table('LOAISANPHAM')->insert([
            'MALOAISP' => 9901,
            'TENLOAISP' => 'Danh mục model QA',
        ]);
        DB::table('SANPHAM')->insert([
            'MASP' => 9901,
            'TENSP' => 'Sản phẩm model QA',
            'MALOAISP' => 9901,
            'DONGIA' => 125000,
            'SOLUONGTON' => 4,
        ]);

        $product = GymProduct::query()->findOrFail(9901);
        $category = GymProductCategory::query()->findOrFail(9901);

        $this->assertInstanceOf(BelongsTo::class, $product->category());
        $this->assertInstanceOf(HasMany::class, $category->products());
        $this->assertSame('Danh mục model QA', $product->category->TENLOAISP);
        $this->assertSame('Sản phẩm model QA', $category->products->sole()->TENSP);
        $this->assertFalse($product->usesTimestamps());
        $this->assertFalse($category->usesTimestamps());
    }
}
