<?php

namespace Tests\Feature;

use App\Enums\ContentSource;
use App\Enums\ProductStatus;
use App\Enums\ProductVisibility;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\ProductImport\CatalogueSpreadsheet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ProductCatalogueExcelTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_export_or_import_catalogue_excel(): void
    {
        $this->get(route('admin.products.export'))->assertRedirect();
        $this->post(route('admin.products.import'))->assertRedirect();
    }

    public function test_export_includes_variants_and_import_updates_and_creates_by_barcode(): void
    {
        $admin = User::factory()->admin()->create();
        $existing = $this->createProduct([
            'name' => 'Known Cream',
            'slug' => 'known-cream',
            'sku' => 'SKU-KNOWN',
            'barcode' => '6281111111111',
            'price' => 10,
            'cost_price' => 4,
            'stock_quantity' => 3,
            'status' => ProductStatus::Active,
        ]);
        $parent = $this->createProduct([
            'name' => 'Shade Foundation',
            'slug' => 'shade-foundation',
            'sku' => 'SKU-SHADE',
            'barcode' => null,
            'has_variants' => true,
            'price' => null,
            'stock_quantity' => 0,
            'status' => ProductStatus::Active,
        ]);
        ProductVariant::query()->create([
            'product_id' => $parent->id,
            'name' => 'Ivory',
            'sku' => 'SKU-SHADE-IVORY',
            'barcode' => '6282222222222',
            'price' => 22,
            'cost_price' => 8,
            'stock_quantity' => 5,
            'is_active' => true,
            'is_default' => true,
            'sort_order' => 0,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.products.index'))
            ->assertOk()
            ->assertSee('Export Excel', false)
            ->assertSee('Import Excel', false);

        $export = $this->actingAs($admin)->get(route('admin.products.export'));
        $export->assertOk();
        $binary = $export->streamedContent();
        $this->assertStringStartsWith('PK', $binary);
        $exported = app(CatalogueSpreadsheet::class)->productsFromXlsx($binary);
        $names = collect($exported['products'])->pluck('name')->all();
        $this->assertContains('Known Cream', $names);
        $this->assertContains('Shade Foundation', $names);
        $foundation = collect($exported['products'])->firstWhere('name', 'Shade Foundation');
        $this->assertSame('Ivory', $foundation['variants'][0]['option_name'] ?? null);
        $this->assertSame('6282222222222', $foundation['variants'][0]['barcode'] ?? null);

        $spreadsheet = app(CatalogueSpreadsheet::class);
        $rows = $spreadsheet->rows(Product::query()->with(['brand', 'categories', 'variants'])->orderBy('id')->get());
        $priceIndex = array_search('price', CatalogueSpreadsheet::HEADERS, true);
        $nameIndex = array_search('name', CatalogueSpreadsheet::HEADERS, true);
        $barcodeIndex = array_search('barcode', CatalogueSpreadsheet::HEADERS, true);
        $variantBarcodeIndex = array_search('variant_barcode', CatalogueSpreadsheet::HEADERS, true);
        $stockIndex = array_search('stock', CatalogueSpreadsheet::HEADERS, true);
        $descriptionIndex = array_search('description', CatalogueSpreadsheet::HEADERS, true);

        foreach ($rows as &$row) {
            if (($row[$barcodeIndex] ?? '') === '6281111111111') {
                $row[$priceIndex] = '18.00';
                $row[$stockIndex] = '9';
                $row[$descriptionIndex] = 'Filled from spreadsheet';
            }
            if (($row[$variantBarcodeIndex] ?? '') === '6282222222222') {
                $row[$priceIndex] = '30.00';
            }
        }
        unset($row);

        $rows[] = array_replace(array_fill(0, count(CatalogueSpreadsheet::HEADERS), ''), [
            array_search('schema_version', CatalogueSpreadsheet::HEADERS, true) => '1.0.0',
            $nameIndex => 'Brand New Mist',
            $barcodeIndex => '6283333333333',
            $priceIndex => '12.50',
            $stockIndex => '4',
            array_search('cost', CatalogueSpreadsheet::HEADERS, true) => '5.00',
            array_search('has_variants', CatalogueSpreadsheet::HEADERS, true) => 'false',
        ]);

        $csv = $spreadsheet->toCsv($rows);
        $this->actingAs($admin)
            ->post(route('admin.products.import'), [
                'file' => UploadedFile::fake()->createWithContent('products.csv', $csv),
            ])
            ->assertRedirect(route('admin.products.index'))
            ->assertSessionHas('success');

        $this->assertSame('Filled from spreadsheet', $existing->fresh()->description);
        $this->assertSame('10.00', $existing->fresh()->price);
        $this->assertSame(3, (int) $existing->fresh()->stock_quantity);
        $this->assertSame('22.00', $parent->variants()->where('barcode', '6282222222222')->first()->price);
        $this->assertSame(2, Product::query()->whereIn('name', ['Known Cream', 'Shade Foundation'])->count());

        $created = Product::query()->where('barcode', '6283333333333')->firstOrFail();
        $this->assertSame('Brand New Mist', $created->name);
        $this->assertSame(ProductStatus::Draft, $created->status);
        $this->assertTrue($created->is_new);
        $this->assertSame('12.50', $created->price);
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
