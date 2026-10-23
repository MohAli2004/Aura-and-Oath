<?php

namespace Tests\Feature;

use App\Enums\ContentSource;
use App\Enums\ProductStatus;
use App\Enums\ProductVisibility;
use App\Models\Product;
use App\Services\ProductSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopNewestVisibleTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_products_appear_first_after_they_are_visible(): void
    {
        $older = $this->createProduct([
            'name' => 'Older Visible Cream',
            'slug' => 'older-visible-cream',
            'is_new' => false,
        ]);
        $older->forceFill([
            'created_at' => now()->subDays(2),
            'published_at' => now()->subDays(2),
        ])->save();

        $newlyVisible = $this->createProduct([
            'name' => 'Newly Visible Serum',
            'slug' => 'newly-visible-serum',
            'is_new' => true,
        ]);
        $newlyVisible->forceFill([
            'created_at' => now()->subDays(10),
            'published_at' => now(),
        ])->save();

        $names = app(ProductSearchService::class)->search()->pluck('name')->all();

        $this->assertSame('Newly Visible Serum', $names[0]);
        $this->assertSame('Older Visible Cream', $names[1]);

        $this->get(route('shop'))
            ->assertOk()
            ->assertSeeInOrder(['Newly Visible Serum', 'Older Visible Cream']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function createProduct(array $overrides = []): Product
    {
        return Product::query()->create(array_merge([
            'name' => 'Test Serum',
            'slug' => 'test-serum-'.uniqid(),
            'sku' => 'SKU-'.uniqid(),
            'barcode' => 'BC'.uniqid(),
            'price' => 100,
            'cost_price' => 40,
            'stock_quantity' => 10,
            'reserved_quantity' => 0,
            'low_stock_threshold' => 2,
            'track_inventory' => true,
            'has_variants' => false,
            'status' => ProductStatus::Active,
            'visibility' => ProductVisibility::Public,
            'stock_status' => 'in_stock',
            'content_source' => ContentSource::Verified,
            'content_approved_at' => now(),
            'published_at' => now(),
        ], $overrides));
    }
}
