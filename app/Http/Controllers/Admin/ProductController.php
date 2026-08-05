<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProductGender;
use App\Enums\ProductStatus;
use App\Enums\ProductVisibility;
use App\Http\Controllers\Admin\Concerns\BulkDestroysResources;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductRequest;
use App\Models\Attribute;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Services\AuditService;
use App\Services\BarcodeService;
use App\Services\ImageService;
use App\Services\ProductSearchService;
use App\Services\ProductVariantService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ProductController extends Controller
{
    use BulkDestroysResources;

    public function __construct(
        protected ProductSearchService $search,
        protected BarcodeService $barcodes,
        protected ImageService $images,
        protected ProductVariantService $variants,
        protected AuditService $audit
    ) {}

    public function index(Request $request): View
    {
        return view('admin.products.index', [
            'products' => $this->search->adminSearch($request->all()),
        ]);
    }

    public function create(): View
    {
        return view('admin.products.form', [
            'product' => new Product([
                'status' => ProductStatus::Active,
                'visibility' => ProductVisibility::Public,
                'gender' => ProductGender::Unisex,
                'price' => 0,
                'is_new' => true,
            ]),
            'categories' => Category::query()->orderBy('name')->get(),
            'brands' => Brand::query()->orderBy('name')->get(),
            'optionUnits' => $this->optionUnits(),
            'measureUnits' => Attribute::measureUnits(),
        ]);
    }

    public function store(ProductRequest $request): RedirectResponse
    {
        $data = $request->safe()->except(['image', 'variant_images', 'pending_image', 'pending_variant_images']);
        if (empty($data['sku'])) {
            $data['sku'] = $this->barcodes->generateSku();
        }
        $this->barcodes->assertSkuUnique($data['sku']);

        $variants = json_decode($request->input('variants_json', '[]'), true) ?: [];
        $hasVariants = is_array($variants) && count($variants) > 0;

        if ($hasVariants) {
            $data['stock_quantity'] = 0;
            $data['barcode'] = null;
        } elseif (! empty($data['barcode'])) {
            $this->barcodes->assertUnique($data['barcode']);
        }

        $data['slug'] = $data['slug'] ?? Str::slug($data['name']).'-'.Str::lower(Str::random(4));
        $product = Product::query()->create($data);

        if ($hasVariants) {
            $variantIds = $this->variants->syncVariants($product, $variants);
            $this->handleVariantImages($request, $variantIds);
        } else {
            $this->handleImage($request, $product);
            $this->variants->syncVariants($product, []);
        }

        $this->clearPendingUploads('new');
        $product->refreshStockStatus();
        $this->audit->log('product.created', $product);

        return redirect()->route('admin.products.index')->with('success', 'Product created.');
    }

    public function edit(Product $product): View
    {
        $product->load(['images', 'variants.attributeValues.attribute']);

        return view('admin.products.form', [
            'product' => $product,
            'categories' => Category::query()->orderBy('name')->get(),
            'brands' => Brand::query()->orderBy('name')->get(),
            'optionUnits' => $this->optionUnits(),
            'measureUnits' => Attribute::measureUnits(),
        ]);
    }

    public function update(ProductRequest $request, Product $product): RedirectResponse
    {
        $data = $request->safe()->except(['image', 'variant_images', 'pending_image', 'pending_variant_images']);
        // Keep the existing SKU; do not require manual entry on edit.
        $data['sku'] = $product->sku;

        $variants = json_decode($request->input('variants_json', '[]'), true) ?: [];
        $hasVariants = is_array($variants) && count($variants) > 0;

        if ($hasVariants) {
            $data['stock_quantity'] = 0;
            $data['barcode'] = null;
        } elseif (! empty($data['barcode'])) {
            $this->barcodes->assertUnique($data['barcode'], $product->id);
        }

        $product->update($data);

        if ($hasVariants) {
            $variantIds = $this->variants->syncVariants($product, $variants);
            $this->handleVariantImages($request, $variantIds);
        } else {
            $this->handleImage($request, $product);
            $this->variants->syncVariants($product, []);
        }

        $this->clearPendingUploads((string) $product->id);
        $product->refreshStockStatus();
        $this->audit->log('product.updated', $product);

        return redirect()->route('admin.products.index')->with('success', 'Product updated.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $this->audit->log('product.deleted', $product);
        $product->delete();

        return redirect()->route('admin.products.index')->with('success', 'Product deleted.');
    }

    public function bulkDestroy(Request $request): RedirectResponse
    {
        return $this->bulkDestroyModels(
            $request,
            Product::class,
            'products',
            'admin.products.index',
            'product',
            fn (Product $product) => $this->audit->log('product.deleted', $product),
        );
    }

    protected function handleImage(Request $request, Product $product): void
    {
        $formKey = $request->route('product')?->getKey()
            ? (string) $request->route('product')->getKey()
            : 'new';
        $pending = $request->input('pending_image') ?: session("product_form.{$formKey}.pending_image");
        $path = null;

        if ($request->hasFile('image')) {
            if (is_string($pending) && $this->images->isTempPath($pending)) {
                $this->images->delete($pending);
            }
            $path = $this->images->store($request->file('image'), 'products');
        } elseif (is_string($pending) && $pending !== '') {
            $path = $this->images->promoteTemp($pending, 'products');
        }

        if (! $path) {
            return;
        }

        foreach ($product->images()->get() as $existing) {
            $this->images->delete($existing->path);
            $existing->delete();
        }

        ProductImage::query()->create([
            'product_id' => $product->id,
            'path' => $path,
            'alt' => $product->name,
            'sort_order' => 0,
            'is_primary' => true,
        ]);
    }

    /**
     * @param  list<int>  $variantIds
     */
    protected function handleVariantImages(Request $request, array $variantIds): void
    {
        $files = $request->file('variant_images', []) ?? [];
        $formKey = $request->route('product')?->getKey()
            ? (string) $request->route('product')->getKey()
            : 'new';
        $pendingVariants = $request->input('pending_variant_images', []);
        if (! is_array($pendingVariants) || $pendingVariants === []) {
            $pendingVariants = session("product_form.{$formKey}.pending_variant_images", []);
        }
        if (! is_array($pendingVariants)) {
            $pendingVariants = [];
        }

        foreach ($variantIds as $index => $variantId) {
            $pending = $pendingVariants[$index] ?? $pendingVariants[(string) $index] ?? null;
            $path = null;

            if (isset($files[$index]) && $files[$index]) {
                if (is_string($pending) && $this->images->isTempPath($pending)) {
                    $this->images->delete($pending);
                }
                $path = $this->images->store($files[$index], 'products');
            } elseif (is_string($pending) && $pending !== '') {
                $path = $this->images->promoteTemp($pending, 'products');
            }

            if (! $path) {
                continue;
            }

            $variant = ProductVariant::query()->find($variantId);
            if (! $variant) {
                continue;
            }

            if ($variant->image_path && $variant->image_path !== $path) {
                $this->images->delete($variant->image_path);
            }

            $variant->update(['image_path' => $path]);
        }
    }

    protected function clearPendingUploads(string $formKey): void
    {
        session()->forget([
            "product_form.{$formKey}.pending_image",
            "product_form.{$formKey}.pending_variant_images",
        ]);
    }

    /**
     * Size / Shade / Scent option units for the variants UI.
     *
     * @return list<array{id:int,slug:string,name:string,values:list<array{id:int,value:string}>}>
     */
    protected function optionUnits(): array
    {
        return Attribute::query()
            ->with('values')
            ->whereIn('slug', ['size', 'shade', 'scent'])
            ->get()
            ->sortBy(fn (Attribute $attribute) => match ($attribute->slug) {
                'size' => 0,
                'shade' => 1,
                'scent' => 2,
                default => 99,
            })
            ->values()
            ->map(fn (Attribute $attribute) => [
                'id' => $attribute->id,
                'slug' => $attribute->slug,
                'name' => $attribute->name,
                'values' => $attribute->values->map(fn ($value) => [
                    'id' => $value->id,
                    'value' => $value->value,
                ])->values()->all(),
            ])
            ->all();
    }
}
