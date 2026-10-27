<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ContentSource;
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
use App\Services\ProductReadinessService;
use App\Services\ProductImport\ProductImportService;
use App\Services\ProductSearchService;
use App\Services\ProductVariantService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductController extends Controller
{
    use BulkDestroysResources;

    public function __construct(
        protected ProductSearchService $search,
        protected BarcodeService $barcodes,
        protected ImageService $images,
        protected ProductVariantService $variants,
        protected AuditService $audit,
        protected ProductReadinessService $readiness,
        protected ProductImportService $imports,
    ) {}

    public function exportExcel(): StreamedResponse
    {
        $binary = $this->imports->catalogueExportXlsx();
        $filename = 'aura-oath-products-'.now()->format('Ymd-His').'.xlsx';

        return response()->streamDownload(function () use ($binary) {
            echo $binary;
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function importExcel(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:8192'],
        ]);

        /** @var UploadedFile $file */
        $file = $request->file('file');
        $result = $this->imports->syncExcel($file);

        $message = 'Spreadsheet imported: '.$result['updated'].' updated, '.$result['created'].' added.';
        if ($result['skipped'] > 0) {
            $message .= ' '.$result['skipped'].' skipped.';
        }

        $redirect = redirect()->route('admin.products.index')->with('success', $message);
        if ($result['errors'] !== []) {
            $redirect->with('error', implode(' ', array_slice($result['errors'], 0, 8)));
        }

        return $redirect;
    }

    public function index(Request $request): View
    {
        return view('admin.products.index', [
            'products' => $this->search->adminSearch($request->all(), list: 'active'),
            ...$this->productListCounts(),
        ]);
    }

    public function inactive(Request $request): View
    {
        return view('admin.products.inactive', [
            'products' => $this->search->adminSearch($request->all(), list: 'inactive'),
            ...$this->productListCounts(),
        ]);
    }

    public function trashed(Request $request): View
    {
        return view('admin.products.trashed', [
            'products' => $this->search->adminSearch($request->all(), onlyTrashed: true),
            ...$this->productListCounts(),
        ]);
    }

    public function create(): View
    {
        return view('admin.products.form', [
            'product' => new Product([
                'status' => ProductStatus::Active,
                'visibility' => ProductVisibility::Public,
                'gender' => ProductGender::Unisex,
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
        $data = $request->safe()->except([
            'image',
            'images',
            'variant_images',
            'pending_image',
            'pending_images',
            'pending_variant_images',
            'existing_image_ids',
            'existing_variant_image_ids',
            'categories',
        ]);
        $categoryIds = array_values(array_unique(array_map('intval', $request->input('categories', []) ?: [])));
        $data['category_id'] = $categoryIds[0] ?? null;

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
        $data['content_source'] = ContentSource::Verified;
        $data['content_approved_at'] = now();
        $product = Product::query()->create($data);
        $product->categories()->sync($categoryIds);

        if ($hasVariants) {
            $variantIds = $this->variants->syncVariants($product, $variants);
            $this->handleProductImages($request, $product);
            $this->handleVariantImages($request, $variantIds);
        } else {
            $this->handleProductImages($request, $product);
            $this->variants->syncVariants($product, []);
        }

        $this->clearPendingUploads('new');
        $product->refreshStockStatus();
        $this->audit->log('product.created', $product);

        return $this->redirectAfterPublication($product, $data['status'] ?? null, 'admin.products.index', 'Product created.');
    }

    public function edit(Request $request, Product $product): View
    {
        $product->load(['images', 'variants.images', 'variants.attributeValues.attribute', 'categories']);

        return view('admin.products.form', [
            'returnTo' => $this->safeReturnUrl((string) $request->query('return_to', '')),
            'product' => $product,
            'categories' => Category::query()->orderBy('name')->get(),
            'brands' => Brand::query()->orderBy('name')->get(),
            'optionUnits' => $this->optionUnits(),
            'measureUnits' => Attribute::measureUnits(),
        ]);
    }

    public function update(ProductRequest $request, Product $product): RedirectResponse
    {
        $wasInactive = $product->status !== ProductStatus::Active;
        $data = $request->safe()->except([
            'image',
            'images',
            'variant_images',
            'pending_image',
            'pending_images',
            'pending_variant_images',
            'existing_image_ids',
            'existing_variant_image_ids',
            'categories',
        ]);
        $categoryIds = array_values(array_unique(array_map('intval', $request->input('categories', []) ?: [])));
        $data['category_id'] = $categoryIds[0] ?? null;
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

        $data['content_approved_at'] = now();
        $product->update($data);
        $product->categories()->sync($categoryIds);

        if ($hasVariants) {
            $variantIds = $this->variants->syncVariants($product, $variants);
            $this->handleProductImages($request, $product);
            $this->handleVariantImages($request, $variantIds);
        } else {
            $this->handleProductImages($request, $product);
            $this->variants->syncVariants($product, []);
        }

        $this->clearPendingUploads((string) $product->id);
        $product->refreshStockStatus();
        $this->audit->log('product.updated', $product);

        $returnTo = $wasInactive ? 'admin.products.inactive' : 'admin.products.index';

        return $this->redirectAfterPublication(
            $product,
            $data['status'] ?? null,
            $returnTo,
            'Product updated.',
            $this->safeReturnUrl((string) $request->input('return_to', ''))
        );
    }

    /**
     * The product-list page (with its page number and filters) the admin came from.
     * Only same-site product list URLs are accepted, so this can't be used as an open redirect.
     */
    protected function safeReturnUrl(string $url): ?string
    {
        $url = trim($url);
        if ($url === '' || strlen($url) > 2000 || preg_match('/[\x00-\x1F\\\\]/', $url)) {
            return null;
        }

        if (! preg_match('#^/admin/products(/(inactive|trashed))?(\?[^\#]*)?$#', $url)) {
            return null;
        }

        return $url;
    }

    public function destroy(Product $product): RedirectResponse
    {
        $this->audit->log('product.deleted', $product);
        $product->delete();

        return redirect()->route('admin.products.index')->with('success', 'Product moved to Deleted products.');
    }

    public function restore(Product $product): RedirectResponse
    {
        abort_unless($product->trashed(), 404);

        $product->restore();
        $this->audit->log('product.restored', $product);

        return redirect()->route('admin.products.index')->with('success', 'Product restored.');
    }

    public function activate(Product $product): RedirectResponse
    {
        abort_if($product->trashed() || $product->status === ProductStatus::Active, 404);

        $issues = $this->readiness->issues($product);
        $this->readiness->remember($product, $issues);
        if ($issues !== []) {
            return back()->withErrors([
                'status' => 'This product stays in Draft. Still needed: '.implode(', ', $this->readiness->labels($issues)).'.',
            ]);
        }

        $product->update([
            'status' => ProductStatus::Active,
            'published_at' => $product->published_at ?? now(),
            'content_approved_at' => $product->content_approved_at ?? now(),
        ]);
        $this->audit->log('product.activated', $product);

        return redirect()->route('admin.products.inactive')->with('success', 'Product activated.');
    }

    public function forceDestroy(Product $product): RedirectResponse
    {
        abort_unless($product->trashed(), 404);

        $this->permanentlyDelete($product);

        return redirect()->route('admin.products.trashed')->with('success', 'Product permanently deleted.');
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

    public function bulkRestore(Request $request): RedirectResponse
    {
        $ids = $this->trashedProductIds($request);
        $products = Product::onlyTrashed()->whereIn('id', $ids)->get();

        foreach ($products as $product) {
            $product->restore();
            $this->audit->log('product.restored', $product);
        }

        $count = $products->count();
        $noun = $count === 1 ? 'product' : 'products';

        return redirect()
            ->route('admin.products.index')
            ->with('success', "{$count} {$noun} restored.");
    }

    public function bulkActivate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'scope' => ['nullable', 'in:all,selected'],
            'ids' => ['required_unless:scope,all', 'array', 'min:1'],
            'ids.*' => ['integer'],
            'q' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'string', 'max:40'],
            'gender' => ['nullable', 'string', 'max:40'],
        ]);

        $query = Product::query()->where('status', '!=', ProductStatus::Active);

        if (($data['scope'] ?? 'selected') === 'all') {
            $inactive = [ProductStatus::Draft->value, ProductStatus::Archived->value];
            if (! empty($data['status']) && in_array($data['status'], $inactive, true)) {
                $query->where('status', $data['status']);
            }
            if (! empty($data['q'])) {
                $like = '%'.trim($data['q']).'%';
                $query->where(function ($builder) use ($like) {
                    $builder->where('name', 'like', $like)
                        ->orWhere('sku', 'like', $like)
                        ->orWhere('barcode', 'like', $like)
                        ->orWhereHas('variants', function ($variants) use ($like) {
                            $variants->where('barcode', 'like', $like)
                                ->orWhere('sku', 'like', $like);
                        });
                });
            }
            if (! empty($data['gender'])) {
                $query->gender($data['gender']);
            }
        } else {
            $query->whereIn('id', $data['ids']);
        }

        $activated = 0;
        $held = 0;
        foreach ($query->get() as $product) {
            $issues = $this->readiness->issues($product);
            $this->readiness->remember($product, $issues);
            if ($issues !== []) {
                $held++;

                continue;
            }

            $product->update([
                'status' => ProductStatus::Active,
                'published_at' => $product->published_at ?? now(),
                'content_approved_at' => $product->content_approved_at ?? now(),
            ]);
            $this->audit->log('product.activated', $product);
            $activated++;
        }

        $message = "{$activated} ".($activated === 1 ? 'product' : 'products').' activated.';
        if ($held > 0) {
            $message .= " {$held} ".($held === 1 ? 'product stays' : 'products stay').' in Draft because required information is missing.';
        }

        return redirect()->route('admin.products.inactive')->with('success', $message);
    }

    public function bulkForceDestroy(Request $request): RedirectResponse
    {
        $ids = $this->trashedProductIds($request);
        $products = Product::onlyTrashed()->whereIn('id', $ids)->get();

        foreach ($products as $product) {
            $this->permanentlyDelete($product);
        }

        $count = $products->count();
        $noun = $count === 1 ? 'product' : 'products';

        return redirect()
            ->route('admin.products.trashed')
            ->with('success', "{$count} {$noun} permanently deleted.");
    }

    protected function redirectAfterPublication(Product $product, mixed $requestedStatus, string $route, string $success, ?string $returnUrl = null): RedirectResponse
    {
        $status = $requestedStatus instanceof ProductStatus
            ? $requestedStatus
            : ProductStatus::tryFrom((string) $requestedStatus);

        $product->refresh()->load(['images', 'variants.images', 'variants.attributeValues']);
        $issues = $this->readiness->issues($product);
        $this->readiness->remember($product, $issues);

        if ($status !== ProductStatus::Active || $issues === []) {
            if ($status === ProductStatus::Active && $product->published_at === null) {
                $product->update(['published_at' => now()]);
            }

            $redirect = match (true) {
                $returnUrl !== null => redirect()->to($returnUrl),
                $route === 'admin.products.edit' => redirect()->route($route, $product),
                default => redirect()->route($route),
            };

            return $redirect->with('success', $success);
        }

        if ($product->status !== ProductStatus::Draft) {
            $product->update(['status' => ProductStatus::Draft]);
            $this->readiness->remember($product);
        }

        $message = 'This product stays in Draft. Still needed: '.implode(', ', $this->readiness->labels($issues)).'.';

        return redirect()
            ->route('admin.products.edit', array_filter(['product' => $product, 'return_to' => $returnUrl]))
            ->withInput()
            ->withErrors(['status' => $message])
            ->with('error', $message);
    }

    /**
     * @return array{activeCount: int, inactiveCount: int, trashedCount: int}
     */
    protected function productListCounts(): array
    {
        return [
            'activeCount' => Product::query()->where('status', ProductStatus::Active)->count(),
            'inactiveCount' => Product::query()->where('status', '!=', ProductStatus::Active)->count(),
            'trashedCount' => Product::onlyTrashed()->count(),
        ];
    }

    /**
     * @return list<int>
     */
    protected function trashedProductIds(Request $request): array
    {
        return $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ])['ids'];
    }

    protected function permanentlyDelete(Product $product): void
    {
        DB::transaction(function () use ($product) {
            $product->load([
                'allImages',
                'variants' => fn ($query) => $query->withTrashed(),
            ]);

            foreach ($product->allImages as $image) {
                $this->images->delete($image->path);
            }

            foreach ($product->variants as $variant) {
                $this->images->delete($variant->image_path);
            }

            $this->audit->log('product.force_deleted', $product, [
                'name' => $product->name,
                'sku' => $product->sku,
            ]);

            $product->forceDelete();
        });
    }

    protected function handleProductImages(Request $request, Product $product): void
    {
        $keepIds = $this->intIds($request->input('existing_image_ids', []));
        $newPaths = $this->resolveNewImagePaths(
            $request->input('pending_images', []),
            $request->file('images', []) ?? [],
            $request->file('image'),
        );

        $this->syncGallery($product->id, null, $keepIds, $newPaths, $product->name);
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

        $existingByIndex = $request->input('existing_variant_image_ids', []);
        if (! is_array($existingByIndex)) {
            $existingByIndex = [];
        }

        foreach ($variantIds as $index => $variantId) {
            $variant = ProductVariant::query()->with('images')->find($variantId);
            if (! $variant) {
                continue;
            }

            $pending = $pendingVariants[$index] ?? $pendingVariants[(string) $index] ?? [];
            if (is_string($pending) && $pending !== '') {
                $pending = [$pending];
            }
            if (! is_array($pending)) {
                $pending = [];
            }

            $uploaded = $files[$index] ?? $files[(string) $index] ?? [];
            if ($uploaded instanceof \Illuminate\Http\UploadedFile) {
                $uploaded = [$uploaded];
            }
            if (! is_array($uploaded)) {
                $uploaded = [];
            }

            $keepIds = $this->intIds($existingByIndex[$index] ?? $existingByIndex[(string) $index] ?? []);
            $newPaths = $this->resolveNewImagePaths($pending, $uploaded);
            $hadGallery = $variant->images->isNotEmpty();
            $legacyPath = $variant->getAttributes()['image_path'] ?? null;

            $this->syncGallery($variant->product_id, $variant->id, $keepIds, $newPaths, $variant->displayName());

            $first = $variant->images()->orderBy('sort_order')->first();

            if ($first) {
                if ($legacyPath && $legacyPath !== $first->path && $variant->images()->where('path', $legacyPath)->doesntExist()) {
                    $this->images->delete($legacyPath);
                }
                $variant->update(['image_path' => $first->path]);
            } elseif ($hadGallery || $keepIds !== [] || $newPaths !== []) {
                if ($legacyPath) {
                    $this->images->delete($legacyPath);
                }
                $variant->update(['image_path' => null]);
            }
        }
    }

    /**
     * @param  list<int>  $keepIds
     * @param  list<string>  $newPaths
     */
    protected function syncGallery(int $productId, ?int $variantId, array $keepIds, array $newPaths, string $alt): void
    {
        $query = ProductImage::query()
            ->where('product_id', $productId)
            ->when(
                $variantId === null,
                fn ($q) => $q->whereNull('product_variant_id'),
                fn ($q) => $q->where('product_variant_id', $variantId),
            );

        $ownedIds = $query->clone()->pluck('id')->map(fn ($id) => (int) $id)->all();
        $keepIds = array_values(array_filter($keepIds, fn (int $id) => in_array($id, $ownedIds, true)));

        foreach ($query->clone()->get() as $image) {
            if (! in_array((int) $image->id, $keepIds, true)) {
                $this->images->delete($image->path);
                $image->delete();
            }
        }

        $order = 0;
        foreach ($keepIds as $id) {
            ProductImage::query()->whereKey($id)->update([
                'sort_order' => $order,
                'is_primary' => $order === 0,
            ]);
            $order++;
        }

        foreach ($newPaths as $path) {
            ProductImage::query()->create([
                'product_id' => $productId,
                'product_variant_id' => $variantId,
                'path' => $path,
                'alt' => $alt,
                'sort_order' => $order,
                'is_primary' => $order === 0,
            ]);
            $order++;
        }
    }

    /**
     * @param  mixed  $pending
     * @param  mixed  $files
     * @return list<string>
     */
    protected function resolveNewImagePaths(mixed $pending, mixed $files, mixed $legacyFile = null): array
    {
        $pendingList = [];
        if (is_string($pending) && $pending !== '') {
            $pendingList = [$pending];
        } elseif (is_array($pending)) {
            $pendingList = array_values(array_filter($pending, fn ($path) => is_string($path) && $path !== ''));
        }

        if ($pendingList !== []) {
            $paths = [];
            foreach ($pendingList as $path) {
                if ($this->images->isTempPath($path)) {
                    $promoted = $this->images->promoteTemp($path, 'products');
                    if ($promoted) {
                        $paths[] = $promoted;
                    }
                }
            }

            return $paths;
        }

        $fileList = [];
        if ($files instanceof \Illuminate\Http\UploadedFile) {
            $fileList = [$files];
        } elseif (is_array($files)) {
            $fileList = $files;
        }
        if ($legacyFile instanceof \Illuminate\Http\UploadedFile) {
            $fileList[] = $legacyFile;
        }

        $paths = [];
        foreach ($fileList as $file) {
            if ($file instanceof \Illuminate\Http\UploadedFile) {
                $paths[] = $this->images->store($file, 'products');
            }
        }

        return $paths;
    }

    /**
     * @return list<int>
     */
    protected function intIds(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map('intval', $value), fn (int $id) => $id > 0));
    }

    protected function clearPendingUploads(string $formKey): void
    {
        session()->forget([
            "product_form.{$formKey}.pending_image",
            "product_form.{$formKey}.pending_images",
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
