<?php

namespace Tests\Feature;

use App\Enums\ContentSource;
use App\Enums\ProductStatus;
use App\Enums\ProductVisibility;
use App\Models\Brand;
use App\Models\Category;
use App\Models\ImportBatch;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use App\Services\ProductReadinessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BulkProductImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
    }

    public function test_guest_cannot_open_the_importer(): void
    {
        $this->get(route('admin.bulk-import.index'))->assertRedirect();
    }

    public function test_single_and_multi_product_import_keeps_drafts_and_arabic(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.bulk-import.upload'), [
                'file' => $this->jsonFile([
                    'schema_version' => '1.0.0',
                    'products' => [
                        [
                            'name' => 'Calm Daily Serum',
                            'name_ar' => 'سيروم يومي هادئ',
                            'description' => '<script>alert(1)</script><p>Hello</p>',
                            'description_ar' => 'وصف عربي',
                            'brand' => 'Missing Brand',
                        ],
                        [
                            'name' => 'Second Product',
                            'name_ar' => 'المنتج الثاني',
                        ],
                    ],
                ]),
            ])
            ->assertRedirect();

        $batch = ImportBatch::query()->firstOrFail();
        $this->actingAs($admin)->post(route('admin.bulk-import.commit', $batch))->assertRedirect();

        $this->assertSame(2, Product::query()->count());
        $serum = Product::query()->where('name', 'Calm Daily Serum')->firstOrFail();
        $this->assertSame(ProductStatus::Draft, $serum->status);
        $this->assertSame(ProductVisibility::Public, $serum->visibility);
        $this->assertTrue($serum->is_new);
        $this->assertSame(ContentSource::Ai, $serum->content_source);
        $this->assertNull($serum->content_approved_at);
        $this->assertSame('سيروم يومي هادئ', $serum->name_ar);
        $this->assertSame('وصف عربي', $serum->description_ar);
        $this->assertStringNotContainsString('script', (string) $serum->description);
        $this->assertStringContainsString('Hello', (string) $serum->description);
        $this->assertNull($serum->barcode);
        $this->assertNull($serum->price);
        $this->assertNull($serum->cost_price);
        $this->assertNull($serum->stock_quantity);
        $this->assertNotNull($serum->import_batch_id);
        Storage::disk('local')->assertExists($batch->stored_path);
        Storage::disk('public')->assertMissing($batch->stored_path);

        $this->actingAs($admin)
            ->get(route('admin.bulk-import.show', $batch))
            ->assertOk()
            ->assertSee('Calm Daily Serum')
            ->assertSee('سيروم يومي هادئ');

        $this->get(route('shop'))->assertOk()->assertDontSee('Calm Daily Serum');
    }

    public function test_unknown_brand_is_created_but_unknown_category_is_skipped(): void
    {
        $admin = User::factory()->admin()->create();
        $knownBrand = Brand::query()->create([
            'name' => 'Known Brand',
            'slug' => 'known-brand',
            'is_active' => true,
        ]);
        $skincare = Category::query()->create([
            'name' => 'Skincare',
            'slug' => 'skincare',
            'is_active' => true,
        ]);

        $batch = $this->upload($admin, [
            'schema_version' => '1.0.0',
            'products' => [
                [
                    'name' => 'First Cream',
                    'brand' => 'New Atelier',
                    'categories' => ['Night Care', 'Skincare'],
                ],
                [
                    'name' => 'Second Cream',
                    'brand' => 'new atelier',
                    'categories' => ['Night Care'],
                ],
                [
                    'name' => 'Third Cream',
                    'brand' => 'Known Brand',
                    'categories' => ['skincare'],
                ],
            ],
        ]);

        $this->actingAs($admin)->post(route('admin.bulk-import.commit', $batch))->assertRedirect();

        $createdBrand = Brand::query()->whereRaw('LOWER(name) = ?', ['new atelier'])->firstOrFail();
        $this->assertTrue($createdBrand->is_active);
        $this->assertSame(2, Brand::query()->count());

        $this->assertNull(Category::query()->whereRaw('LOWER(name) = ?', ['night care'])->first());
        $this->assertSame(1, Category::query()->count());

        $first = Product::query()->where('name', 'First Cream')->firstOrFail();
        $this->assertSame($createdBrand->id, $first->brand_id);
        $this->assertSame([$skincare->id], $first->categories()->pluck('categories.id')->all());
        $this->assertTrue(collect($first->import_meta['warnings'] ?? [])->contains(
            fn ($warning) => is_string($warning) && str_contains($warning, 'Night Care')
        ));

        $second = Product::query()->where('name', 'Second Cream')->firstOrFail();
        $this->assertSame($createdBrand->id, $second->brand_id);
        $this->assertSame([], $second->categories()->pluck('categories.id')->all());

        $third = Product::query()->where('name', 'Third Cream')->firstOrFail();
        $this->assertSame($knownBrand->id, $third->brand_id);
        $this->assertSame([$skincare->id], $third->categories()->pluck('categories.id')->all());
    }

    public function test_shared_price_cost_and_stock_fill_variants_unless_overridden(): void
    {
        $admin = User::factory()->admin()->create();
        $batch = $this->upload($admin, [
            'schema_version' => '1.0.0',
            'products' => [[
                'name' => 'Shared Shade Cream',
                'has_variants' => true,
                'price' => 22,
                'cost' => 7,
                'stock' => 5,
                'variants' => [
                    ['option_name' => 'Ivory'],
                    ['option_name' => 'Sand', 'price' => 30, 'cost' => 11, 'stock' => 2],
                ],
            ]],
        ]);

        $this->actingAs($admin)->post(route('admin.bulk-import.commit', $batch));

        $product = Product::query()->where('name', 'Shared Shade Cream')->firstOrFail();
        $ivory = $product->variants->firstWhere('name', 'Ivory');
        $sand = $product->variants->firstWhere('name', 'Sand');

        $this->assertSame('22.00', $ivory->price);
        $this->assertSame('7.00', $ivory->cost_price);
        $this->assertSame(5, (int) $ivory->stock_quantity);
        $this->assertSame('30.00', $sand->price);
        $this->assertSame('11.00', $sand->cost_price);
        $this->assertSame(2, (int) $sand->stock_quantity);
    }

    public function test_variant_import_keeps_one_parent_and_price_overrides(): void
    {
        $admin = User::factory()->admin()->create();
        $batch = $this->upload($admin, [
            'schema_version' => '1.0.0',
            'products' => [[
                'name' => 'Soft Veil Foundation',
                'name_ar' => 'كريم أساس',
                'has_variants' => true,
                'variant_type' => 'shade',
                'variants' => [
                    ['option_name' => 'Ivory', 'option_name_ar' => 'عاجي', 'price' => 40, 'cost' => 12, 'stock' => 3, 'show_first' => true],
                    ['option_name' => 'Sand', 'price' => 48, 'cost' => 15, 'stock' => 0, 'compare_at_price' => 60],
                ],
            ]],
        ]);

        $this->actingAs($admin)->post(route('admin.bulk-import.commit', $batch));

        $product = Product::query()->where('name', 'Soft Veil Foundation')->firstOrFail();
        $this->assertTrue($product->has_variants);
        $this->assertSame(ProductStatus::Draft, $product->status);
        $this->assertCount(2, $product->variants);
        $this->assertSame('40.00', $product->variants[0]->price);
        $this->assertSame('48.00', $product->variants[1]->price);
        $this->assertSame(0, (int) $product->variants[1]->stock_quantity);
        $this->assertSame('عاجي', $product->variants[0]->name_ar);
        $this->assertSame(1, Product::query()->count());
    }

    public function test_review_page_keeps_multiple_images_for_a_product_and_a_variant(): void
    {
        $admin = User::factory()->admin()->create();
        $batch = $this->upload($admin, [
            'schema_version' => '1.0.0',
            'products' => [
                ['name' => 'Calm Daily Serum'],
                [
                    'name' => 'Soft Veil Foundation',
                    'has_variants' => true,
                    'variant_type' => 'shade',
                    'variants' => [
                        ['option_name' => 'Ivory'],
                    ],
                ],
            ],
        ]);

        $serum = $batch->rows()->where('name', 'Calm Daily Serum')->firstOrFail();
        $foundation = $batch->rows()->where('name', 'Soft Veil Foundation')->firstOrFail();
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

        $this->actingAs($admin)
            ->get(route('admin.bulk-import.show', $batch))
            ->assertOk()
            ->assertSee('name="row_images['.$serum->id.'][]"', false)
            ->assertSee('data-image-preview', false)
            ->assertSee('name="variant_images['.$foundation->id.'][0][]"', false)
            ->assertDontSee('Images for this batch');

        $this->actingAs($admin)->put(route('admin.bulk-import.update', $batch), [
            'rows' => [
                $serum->id => ['resolution' => 'create'],
                $foundation->id => ['resolution' => 'create'],
            ],
            'row_images' => [
                $serum->id => [
                    UploadedFile::fake()->createWithContent('one.png', $png),
                    UploadedFile::fake()->createWithContent('two.png', $png),
                ],
            ],
            'variant_images' => [
                $foundation->id => [
                    [
                        UploadedFile::fake()->createWithContent('ivory-a.png', $png),
                        UploadedFile::fake()->createWithContent('ivory-b.png', $png),
                    ],
                ],
            ],
        ])->assertRedirect();

        $this->assertCount(2, $serum->fresh()->payload['staged_images'] ?? []);
        $this->assertCount(2, $foundation->fresh()->payload['variants'][0]['staged_images'] ?? []);

        $this->actingAs($admin)->post(route('admin.bulk-import.commit', $batch));

        $savedSerum = Product::query()->where('name', 'Calm Daily Serum')->firstOrFail();
        $savedFoundation = Product::query()->where('name', 'Soft Veil Foundation')->firstOrFail();
        $this->assertSame(2, $savedSerum->images()->count());
        $this->assertSame(2, $savedFoundation->variants()->where('name', 'Ivory')->firstOrFail()->images()->count());
    }

    public function test_missing_barcode_price_cost_stock_and_image_block_activation(): void
    {
        $admin = User::factory()->admin()->create();
        $batch = $this->upload($admin, [
            'schema_version' => '1.0.0',
            'products' => [[
                'name' => 'Incomplete Mist',
            ]],
        ]);
        $this->actingAs($admin)->post(route('admin.bulk-import.commit', $batch));
        $product = Product::query()->where('name', 'Incomplete Mist')->firstOrFail();

        $issues = app(ProductReadinessService::class)->issues($product->fresh());
        $this->assertContains('barcode', $issues);
        $this->assertContains('price', $issues);
        $this->assertContains('cost', $issues);
        $this->assertContains('stock', $issues);
        $this->assertContains('image', $issues);
        $this->assertContains('content_approval', $issues);

        $this->actingAs($admin)
            ->post(route('admin.products.activate', $product))
            ->assertSessionHasErrors('status');
        $this->assertSame(ProductStatus::Draft, $product->fresh()->status);
    }

    public function test_zero_stock_and_optional_compare_at_can_publish_when_complete(): void
    {
        $admin = User::factory()->admin()->create();
        $product = $this->readyProduct(['stock_quantity' => 0, 'compare_at_price' => null]);

        $issues = app(ProductReadinessService::class)->issues($product);
        $this->assertNotContains('stock', $issues);
        $this->assertNotContains('compare_at_price', $issues);
        $this->assertSame([], $issues);

        $this->actingAs($admin)
            ->post(route('admin.bulk-import.editor.publish'), ['ids' => [$product->id]])
            ->assertRedirect();

        $this->assertSame(ProductStatus::Active, $product->fresh()->status);
        $this->assertSame(ProductVisibility::Public, $product->fresh()->visibility);
    }

    public function test_editor_activate_saves_and_stays_on_the_product(): void
    {
        $admin = User::factory()->admin()->create();
        $product = $this->readyProduct();
        $url = route('admin.bulk-import.editor', ['product' => $product->id]);

        $this->actingAs($admin)
            ->from($url)
            ->post(route('admin.bulk-import.editor.save'), [
                'activate' => '1',
                'products' => [
                    $product->id => [
                        'name' => 'Activated Gift Set',
                        'approve_content' => '1',
                    ],
                ],
            ])
            ->assertRedirect($url)
            ->assertSessionHas('success', 'Product activated.');

        $this->assertSame('Activated Gift Set', $product->fresh()->name);
        $this->assertSame(ProductStatus::Active, $product->fresh()->status);

        $this->actingAs($admin)
            ->get($url)
            ->assertOk()
            ->assertSee('Activated Gift Set')
            ->assertSee('Active');
    }

    public function test_compare_at_must_be_higher_than_price_when_provided(): void
    {
        $product = $this->readyProduct(['compare_at_price' => 10, 'price' => 20]);
        $issues = app(ProductReadinessService::class)->issues($product);
        $this->assertContains('compare_at_price', $issues);

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)
            ->post(route('admin.products.activate', $product))
            ->assertSessionHasErrors('status');
        $this->assertSame(ProductStatus::Draft, $product->fresh()->status);
    }

    public function test_duplicate_barcode_and_active_product_are_not_overwritten(): void
    {
        $admin = User::factory()->admin()->create();
        $existing = Product::query()->create([
            'name' => 'Existing Serum',
            'slug' => 'existing-serum',
            'sku' => 'SKU-EXISTING',
            'barcode' => '6281111111111',
            'price' => 30,
            'cost_price' => 10,
            'stock_quantity' => 4,
            'status' => ProductStatus::Active,
            'visibility' => ProductVisibility::Public,
            'content_source' => ContentSource::Verified,
            'content_approved_at' => now(),
            'description' => 'Catalogue description',
        ]);

        $batch = $this->upload($admin, [
            'schema_version' => '1.0.0',
            'products' => [
                [
                    'name' => 'AI rewrite',
                    'description' => 'Unverified replacement',
                    'barcode' => '6281111111111',
                    'price' => 99,
                ],
                [
                    'name' => 'Second copy',
                    'barcode' => '6281111111111',
                ],
            ],
        ]);

        $batch->load('rows');
        $this->assertSame('review', $batch->rows[0]->resolution->value);
        $this->assertSame($existing->id, $batch->rows[0]->matched_product_id);

        $this->actingAs($admin)->post(route('admin.bulk-import.commit', $batch));

        $this->assertSame('Catalogue description', $existing->fresh()->description);
        $this->assertSame(ProductStatus::Active, $existing->fresh()->status);
        $this->assertSame(1, Product::query()->count());
    }

    public function test_explicit_update_can_change_an_active_product_without_duplicating_it(): void
    {
        $admin = User::factory()->admin()->create();
        $existing = Product::query()->create([
            'name' => 'Existing Serum',
            'slug' => 'existing-serum',
            'sku' => 'SKU-EXISTING-2',
            'barcode' => '6282222222222',
            'price' => 30,
            'cost_price' => 10,
            'stock_quantity' => 4,
            'status' => ProductStatus::Active,
            'visibility' => ProductVisibility::Public,
            'description' => 'Old text',
        ]);

        $batch = $this->upload($admin, [
            'schema_version' => '1.0.0',
            'products' => [[
                'name' => 'Existing Serum',
                'description' => 'Reviewed update',
                'barcode' => '6282222222222',
                'price' => 33,
            ]],
        ]);
        $row = $batch->rows()->firstOrFail();

        $this->actingAs($admin)->put(route('admin.bulk-import.update', $batch), [
            'rows' => [
                $row->id => ['resolution' => 'update'],
            ],
        ])->assertRedirect();

        $this->actingAs($admin)->post(route('admin.bulk-import.commit', $batch));

        $this->assertSame(1, Product::query()->count());
        $this->assertSame('Reviewed update', $existing->fresh()->description);
        $this->assertSame(ProductStatus::Active, $existing->fresh()->status);
        $this->assertSame('33.00', $existing->fresh()->price);
    }

    public function test_partial_failure_invalid_json_and_repeated_import(): void
    {
        $admin = User::factory()->admin()->create();
        $payload = [
            'schema_version' => '1.0.0',
            'products' => [
                ['name' => 'Kept Product', 'barcode' => '6283333333333'],
                ['brand' => 'No name'],
            ],
        ];

        $batch = $this->upload($admin, $payload);
        $this->actingAs($admin)->post(route('admin.bulk-import.commit', $batch));
        $this->assertSame(1, Product::query()->count());
        $this->assertTrue($batch->fresh()->failure_count >= 1);

        $this->actingAs($admin)
            ->post(route('admin.bulk-import.upload'), [
                'file' => UploadedFile::fake()->createWithContent('bad.json', '{not json'),
            ])
            ->assertSessionHasErrors('file');

        $this->actingAs($admin)
            ->post(route('admin.bulk-import.upload'), [
                'file' => $this->jsonFile($payload, 'again.json'),
            ])
            ->assertRedirect(route('admin.bulk-import.show', $batch));

        $this->assertSame(1, ImportBatch::query()->count());
        $this->actingAs($admin)->post(route('admin.bulk-import.commit', $batch));
        $this->assertSame(1, Product::query()->count());
    }

    public function test_truncated_and_line_break_json_still_import_complete_products(): void
    {
        $admin = User::factory()->admin()->create();
        $cutOff = <<<'JSON'
{
  "schema_version": "1.0",
  "products": [{
    "name": "Cut Off Serum",
    "has_variants": true,
    "variants": [
      {"option_name": "Ivory"}
    ,
      {"option_name": "
JSON;
        $brokenLine = <<<'JSON'
{
  "schema_version": "1.0",
  "products": [{
    "name": "Line Break Serum",
    "description": "First line
second line"
  }]
}
JSON;

        $this->actingAs($admin)
            ->post(route('admin.bulk-import.upload'), [
                'file' => UploadedFile::fake()->createWithContent('cut.json', $cutOff),
            ])
            ->assertRedirect();
        $cut = ImportBatch::query()->where('original_filename', 'cut.json')->firstOrFail();
        $this->assertSame('Cut Off Serum', $cut->rows()->first()->name);
        $this->assertStringContainsString('ended before the JSON was finished', (string) ($cut->source_meta['notes'] ?? ''));

        $this->actingAs($admin)
            ->post(route('admin.bulk-import.upload'), [
                'file' => UploadedFile::fake()->createWithContent('line.json', $brokenLine),
            ])
            ->assertRedirect();
        $line = ImportBatch::query()->where('original_filename', 'line.json')->firstOrFail();
        $this->assertSame('Line Break Serum', $line->rows()->first()->name);
        $this->assertStringContainsString('second line', (string) ($line->rows()->first()->payload['description'] ?? ''));
    }

    public function test_exported_csv_reimport_does_not_duplicate(): void
    {
        $admin = User::factory()->admin()->create();
        $product = $this->readyProduct(['name' => 'Export Me', 'content_source' => ContentSource::Ai, 'import_batch_id' => null]);
        $product->update(['import_batch_id' => ImportBatch::query()->create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'user_id' => $admin->id,
            'original_filename' => 'seed.json',
            'stored_path' => 'imports/seed.json',
            'schema_version' => '1.0.0',
            'idempotency_key' => str_repeat('a', 64),
            'status' => 'completed',
        ])->id]);

        $export = $this->actingAs($admin)->get(route('admin.bulk-import.export', ['format' => 'csv', 'filter' => 'all']));
        $export->assertOk();
        $csv = $export->streamedContent();
        $this->assertStringContainsString('Export Me', $csv);

        $xlsx = $this->actingAs($admin)->get(route('admin.bulk-import.export', ['format' => 'xlsx', 'filter' => 'all']));
        $xlsx->assertOk();
        $this->assertStringStartsWith('PK', $xlsx->streamedContent());

        $batch = $this->actingAs($admin)
            ->post(route('admin.bulk-import.upload'), [
                'file' => UploadedFile::fake()->createWithContent('products.csv', $csv),
            ])
            ->assertRedirect();

        $imported = ImportBatch::query()->where('original_filename', 'products.csv')->firstOrFail();
        $this->assertSame($product->id, $imported->rows()->first()->matched_product_id);
        $this->actingAs($admin)->post(route('admin.bulk-import.commit', $imported));
        $this->assertSame(1, Product::query()->where('name', 'Export Me')->count());
        unset($batch);
    }

    public function test_editor_saves_an_image_for_the_product_and_each_variant(): void
    {
        $admin = User::factory()->admin()->create();
        $product = $this->readyProduct(['has_variants' => true, 'barcode' => null, 'stock_quantity' => 0]);
        ProductImage::query()->where('product_id', $product->id)->delete();
        $variant = $product->variants()->create([
            'name' => 'Ivory',
            'sku' => 'VAR-IVORY-'.uniqid(),
            'barcode' => '6286666666666',
            'price' => 20,
            'cost_price' => 8,
            'stock_quantity' => 0,
            'is_active' => true,
            'is_default' => true,
        ]);
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

        $this->actingAs($admin)
            ->get(route('admin.bulk-import.editor', ['product' => $product->id]))
            ->assertOk()
            ->assertSee('name="images['.$product->id.'][]"', false)
            ->assertSee('name="variant_images['.$variant->id.'][]"', false)
            ->assertSee('data-image-preview', false)
            ->assertSee('name="new_variant_images['.$product->id.'][]"', false);

        $this->actingAs($admin)->post(route('admin.bulk-import.editor.save'), [
            'products' => [$product->id => ['name' => $product->name]],
            'images' => [
                $product->id => [UploadedFile::fake()->createWithContent('parent.png', $png)],
            ],
            'variant_images' => [
                $variant->id => [UploadedFile::fake()->createWithContent('ivory.png', $png)],
            ],
        ])->assertRedirect();

        $this->assertTrue($product->images()->exists());
        $this->assertTrue($variant->fresh()->images()->exists());
        $this->assertNotNull($variant->fresh()->image_path);
    }

    public function test_barcode_filename_attaches_image_and_updates_completeness(): void
    {
        $admin = User::factory()->admin()->create();
        $product = $this->readyProduct(['barcode' => '6284444444444']);
        ProductImage::query()->where('product_id', $product->id)->delete();
        app(ProductReadinessService::class)->remember($product->fresh());
        $this->assertContains('image', $product->fresh()->readiness_issues ?? []);

        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
        $this->actingAs($admin)->post(route('admin.bulk-import.media'), [
            'images' => [UploadedFile::fake()->createWithContent('6284444444444.png', $png)],
        ])->assertRedirect();

        $product->refresh();
        $this->assertTrue($product->images()->exists());
        $this->assertNotContains('image', $product->readiness_issues ?? []);
    }

    public function test_normal_product_form_cannot_activate_an_incomplete_product(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.products.store'), [
            'name' => 'Form Draft',
            'status' => 'active',
            'price' => '',
        ]);

        $product = Product::query()->where('name', 'Form Draft')->firstOrFail();
        $this->assertSame(ProductStatus::Draft, $product->status);
        $this->assertNull($product->price);
    }

    public function test_disabled_variant_does_not_block_a_complete_sibling(): void
    {
        $product = $this->readyProduct(['has_variants' => true, 'barcode' => null, 'stock_quantity' => 0]);
        $product->variants()->create([
            'name' => 'Ready',
            'sku' => 'VAR-READY',
            'barcode' => '6285555555555',
            'price' => 20,
            'cost_price' => 8,
            'stock_quantity' => 0,
            'is_active' => true,
            'is_default' => true,
        ]);
        $product->variants()->create([
            'name' => 'Hidden',
            'sku' => 'VAR-HIDDEN',
            'barcode' => null,
            'price' => null,
            'cost_price' => null,
            'stock_quantity' => null,
            'is_active' => false,
        ]);

        $this->assertSame([], app(ProductReadinessService::class)->issues($product->fresh()));
    }

    public function test_editor_lists_a_page_of_products_and_opens_one_at_a_time(): void
    {
        $admin = User::factory()->admin()->create();

        foreach (range(1, 45) as $number) {
            $this->importedDraft(sprintf('Bulk Row %03d', $number), [
                'readiness_issues' => ['price'],
                'price' => null,
            ]);
        }

        $list = $this->actingAs($admin)->get(route('admin.bulk-import.editor', ['filter' => 'missing_price']));
        $list->assertOk();
        $list->assertSee('Bulk Row 045');
        $list->assertSee('Showing');
        $list->assertDontSee('Bulk Row 001');
        $list->assertDontSee('name="products[', false);

        $page = $this->actingAs($admin)->get(route('admin.bulk-import.editor', ['filter' => 'missing_price', 'page' => 2]));
        $page->assertOk();
        $page->assertSee('Bulk Row 001');
        $page->assertDontSee('Bulk Row 045');

        $product = Product::query()->where('name', 'Bulk Row 045')->firstOrFail();
        $edit = $this->actingAs($admin)->get(route('admin.bulk-import.editor', [
            'filter' => 'missing_price',
            'product' => $product->id,
        ]));
        $edit->assertOk();
        $edit->assertSee('name="products['.$product->id.'][name]"', false);
        $edit->assertSee('Bulk Row 045');
        $edit->assertDontSee('Bulk Row 044');

        $this->actingAs($admin)->post(route('admin.bulk-import.editor.save'), [
            'products' => [
                $product->id => ['name' => 'Renamed Row', 'price' => 18],
            ],
        ])->assertRedirect();

        $this->assertSame('Renamed Row', $product->fresh()->name);
        $this->assertSame('18.00', $product->fresh()->price);
    }

    public function test_editor_search_and_filter_apply_do_not_touch_other_products(): void
    {
        $admin = User::factory()->admin()->create();
        $serum = $this->importedDraft('Night Serum', [
            'barcode' => '6281000000001',
            'readiness_issues' => ['price'],
            'price' => null,
        ]);
        $cream = $this->importedDraft('Day Cream', [
            'barcode' => '6281000000002',
            'readiness_issues' => ['price'],
            'price' => null,
        ]);
        $priced = $this->importedDraft('Already Priced', [
            'barcode' => '6281000000003',
            'readiness_issues' => [],
            'price' => 9,
        ]);
        $catalogue = $this->importedDraft('Shop Only', [
            'content_source' => null,
            'import_batch_id' => null,
            'readiness_issues' => ['price'],
            'price' => null,
        ]);

        $search = $this->actingAs($admin)->get(route('admin.bulk-import.editor', ['q' => '6281000000001']));
        $search->assertOk();
        $search->assertSee('Night Serum');
        $search->assertDontSee('Day Cream');

        $this->actingAs($admin)->post(route('admin.bulk-import.editor.bulk'), [
            'scope' => 'filter',
            'filter' => 'missing_price',
            'price' => 15,
            'cost_price' => 4,
            'stock_quantity' => 0,
        ])->assertRedirect();

        $this->assertSame('15.00', $serum->fresh()->price);
        $this->assertSame('4.00', $cream->fresh()->cost_price);
        $this->assertSame(0, (int) $serum->fresh()->stock_quantity);
        $this->assertSame('9.00', $priced->fresh()->price);
        $this->assertNull($catalogue->fresh()->price);

        $this->actingAs($admin)
            ->get(route('admin.bulk-import.editor', ['product' => $catalogue->id]))
            ->assertNotFound();
    }

    public function test_schema_and_example_are_downloadable_by_an_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get(route('admin.bulk-import.index'))->assertOk()->assertSee('AI Bulk Product Import')->assertDontSee('Upload images');
        $this->actingAs($admin)->get(route('admin.bulk-import.editor'))->assertOk()->assertSee('Import editor');
        $this->actingAs($admin)->get(route('admin.bulk-import.schema'))->assertOk();
        $example = $this->actingAs($admin)->get(route('admin.bulk-import.example'));
        $example->assertOk();
        $this->assertStringContainsString('Calm Daily Serum', $example->streamedContent());
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function upload(User $admin, array $payload): ImportBatch
    {
        $this->actingAs($admin)
            ->post(route('admin.bulk-import.upload'), [
                'file' => $this->jsonFile($payload),
            ])
            ->assertRedirect();

        return ImportBatch::query()->latest('id')->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function jsonFile(array $payload, string $name = 'products.json'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, json_encode($payload, JSON_UNESCAPED_UNICODE));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function readyProduct(array $overrides = []): Product
    {
        $product = Product::query()->create(array_merge([
            'name' => 'Ready Product',
            'slug' => 'ready-'.uniqid(),
            'sku' => 'SKU-'.uniqid(),
            'barcode' => '628'.random_int(1000000000, 9999999999),
            'price' => 25,
            'cost_price' => 9,
            'stock_quantity' => 0,
            'status' => ProductStatus::Draft,
            'visibility' => ProductVisibility::Public,
            'content_source' => ContentSource::Ai,
            'content_approved_at' => now(),
            'import_meta' => ['ai_unverified' => false],
        ], $overrides));

        ProductImage::query()->create([
            'product_id' => $product->id,
            'path' => 'products/test.jpg',
            'alt' => $product->name,
            'sort_order' => 0,
            'is_primary' => true,
        ]);

        app(ProductReadinessService::class)->remember($product->fresh());

        return $product->fresh();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function importedDraft(string $name, array $overrides = []): Product
    {
        return Product::query()->create(array_merge([
            'name' => $name,
            'slug' => 'draft-'.uniqid(),
            'sku' => 'SKU-'.uniqid(),
            'status' => ProductStatus::Draft,
            'visibility' => ProductVisibility::Public,
            'content_source' => ContentSource::Ai,
            'stock_quantity' => null,
        ], $overrides));
    }
}
