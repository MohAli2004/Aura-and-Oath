<?php

namespace Tests\Feature;

use App\Enums\ProductStatus;
use App\Enums\ProductVisibility;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocaleStorefrontTest extends TestCase
{
    use RefreshDatabase;

    protected function createProduct(array $overrides = []): Product
    {
        return Product::query()->create(array_merge([
            'name' => 'English Serum',
            'name_ar' => 'سيروم إنجليزي',
            'slug' => 'english-serum-'.uniqid(),
            'sku' => 'SKU-'.uniqid(),
            'barcode' => 'BC'.uniqid(),
            'price' => 48,
            'cost_price' => 20,
            'stock_quantity' => 10,
            'reserved_quantity' => 0,
            'low_stock_threshold' => 2,
            'track_inventory' => true,
            'has_variants' => false,
            'status' => ProductStatus::Active,
            'visibility' => ProductVisibility::Public,
            'stock_status' => 'in_stock',
            'published_at' => now(),
        ], $overrides));
    }

    public function test_switching_to_arabic_shows_localized_product_name(): void
    {
        $product = $this->createProduct([
            'slug' => 'localized-serum',
            'name' => 'Silk Serum Glow',
            'name_ar' => 'سيروم حريري متوهج',
        ]);

        $this->post(route('locale.update'), ['locale' => 'ar'])
            ->assertRedirect();

        $this->get(route('products.show', $product->slug))
            ->assertOk()
            ->assertSee('سيروم حريري متوهج', false)
            ->assertDontSee('Silk Serum Glow');
    }

    public function test_arabic_falls_back_to_english_when_name_ar_empty(): void
    {
        $product = $this->createProduct([
            'slug' => 'fallback-serum',
            'name' => 'Fallback Serum',
            'name_ar' => null,
        ]);

        $this->post(route('locale.update'), ['locale' => 'ar'])
            ->assertRedirect();

        $this->get(route('products.show', $product->slug))
            ->assertOk()
            ->assertSee('Fallback Serum');
    }

    public function test_search_finds_arabic_product_name(): void
    {
        $product = $this->createProduct([
            'slug' => 'arabic-search-serum',
            'name' => 'Hidden English Name',
            'name_ar' => 'اسم عربي للبحث',
        ]);

        $this->post(route('locale.update'), ['locale' => 'ar']);

        $this->get(route('search', ['q' => 'اسم عربي']))
            ->assertOk()
            ->assertSee('اسم عربي للبحث', false)
            ->assertSee(route('products.show', $product->slug), false);
    }
}
