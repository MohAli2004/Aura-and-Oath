<?php

namespace Tests\Feature;

use App\Enums\ContentSource;
use Database\Seeders\CategorySeeder;
use App\Enums\ProductStatus;
use App\Enums\ProductVisibility;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontCategoryListTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_seeder_keeps_only_the_shop_list(): void
    {
        Category::query()->create([
            'name' => 'Face Masks',
            'slug' => 'face-masks',
            'is_active' => true,
        ]);

        $this->seed(CategorySeeder::class);

        $this->assertEqualsCanonicalizing(
            collect(CategorySeeder::keepList())->pluck('name')->all(),
            Category::query()->orderBy('name')->pluck('name')->all()
        );
        $this->assertNull(Category::query()->where('slug', 'face-masks')->first());
    }

    public function test_shop_hides_categories_that_have_no_live_products(): void
    {
        $empty = Category::query()->create([
            'name' => 'Empty Shelf',
            'slug' => 'empty-shelf',
            'is_active' => true,
        ]);
        $filled = Category::query()->create([
            'name' => 'Lip Care',
            'slug' => 'lip-care',
            'is_active' => true,
        ]);
        $draftOnly = Category::query()->create([
            'name' => 'Draft Only',
            'slug' => 'draft-only',
            'is_active' => true,
        ]);

        $live = $this->createProduct(['name' => 'Live Lip Balm', 'slug' => 'live-lip-balm']);
        $live->categories()->sync([$filled->id]);

        $draft = $this->createProduct([
            'name' => 'Hidden Draft Balm',
            'slug' => 'hidden-draft-balm',
            'status' => ProductStatus::Draft,
        ]);
        $draft->categories()->sync([$draftOnly->id]);

        $this->get(route('shop'))
            ->assertOk()
            ->assertSee('Lip Care', false)
            ->assertDontSee('Empty Shelf', false)
            ->assertDontSee('Draft Only', false);

        $this->get('/')
            ->assertOk()
            ->assertSee('Lip Care', false)
            ->assertDontSee('Empty Shelf', false);
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
