<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ContentSource;
use App\Enums\ImportResolution;
use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\ImportBatch;
use App\Models\ImportMedia;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Services\ImageService;
use App\Services\ProductImport\CatalogueSpreadsheet;
use App\Services\ProductImport\ImportMediaService;
use App\Services\ProductImport\ProductImportService;
use App\Services\ProductReadinessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BulkProductImportController extends Controller
{
    public function __construct(
        protected ProductImportService $imports,
        protected ImportMediaService $media,
        protected CatalogueSpreadsheet $spreadsheet,
        protected ProductReadinessService $readiness,
        protected ImageService $images,
    ) {}

    public function index(Request $request): View
    {
        return view('admin.imports.index', [
            'batches' => ImportBatch::query()->latest()->paginate(10, ['*'], 'batches'),
            'filters' => $this->filters(),
            'filter' => $request->string('filter')->toString() ?: 'all',
        ]);
    }

    public function upload(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'max:8192'],
            'force' => ['nullable', 'boolean'],
        ]);

        /** @var UploadedFile $file */
        $file = $request->file('file');
        $batch = $this->imports->ingest($file, $request->user(), $request->boolean('force'));

        $message = $batch->wasRecentlyCreated
            ? 'File checked. Review each product before anything is written to the catalogue.'
            : 'This file was already imported. Open the existing batch so it is not duplicated.';

        return redirect()
            ->route('admin.bulk-import.show', $batch)
            ->with('success', $message);
    }

    public function show(ImportBatch $batch): View
    {
        $batch->load(['rows.matchedProduct', 'media']);

        return view('admin.imports.show', [
            'batch' => $batch,
            'rows' => $batch->rows()->with('matchedProduct')->orderBy('row_index')->paginate(40),
            'resolutions' => ImportResolution::cases(),
        ]);
    }

    public function update(Request $request, ImportBatch $batch): RedirectResponse
    {
        $data = $request->validate([
            'rows' => ['nullable', 'array'],
            'rows.*.resolution' => ['nullable', 'string'],
            'rows.*.barcode' => ['nullable', 'string', 'max:64'],
            'rows.*.price' => ['nullable', 'numeric', 'min:0'],
            'rows.*.cost' => ['nullable', 'numeric', 'min:0'],
            'rows.*.compare_at_price' => ['nullable', 'numeric', 'min:0'],
            'rows.*.stock' => ['nullable', 'integer', 'min:0'],
            'rows.*.variants' => ['nullable', 'array'],
            'rows.*.remove_images' => ['nullable', 'array'],
            'rows.*.remove_images.*' => ['string', 'max:255'],
            'rows.*.variants.*.remove_images' => ['nullable', 'array'],
            'rows.*.variants.*.remove_images.*' => ['string', 'max:255'],
            'row_images' => ['nullable', 'array'],
            'row_images.*' => ['nullable', 'array', 'max:12'],
            'row_images.*.*' => ['image', 'max:4096'],
            'variant_images' => ['nullable', 'array'],
            'variant_images.*' => ['nullable', 'array'],
            'variant_images.*.*' => ['nullable', 'array', 'max:12'],
            'variant_images.*.*.*' => ['image', 'max:4096'],
        ]);

        $this->imports->stageReviewImages(
            $batch,
            $request->file('row_images', []) ?? [],
            $request->file('variant_images', []) ?? [],
            $request->input('rows', []),
        );
        $this->imports->applyEdits($batch, $request->input('rows', []));

        return redirect()
            ->route('admin.bulk-import.show', $batch)
            ->with('success', 'Review choices saved.');
    }

    public function commit(ImportBatch $batch): RedirectResponse
    {
        $this->imports->queueCommit($batch);

        return redirect()
            ->route('admin.bulk-import.show', $batch)
            ->with('success', 'Import started. Rows that still need a decision stay untouched.');
    }

    public function status(ImportBatch $batch): JsonResponse
    {
        return response()->json([
            'status' => $batch->status->value,
            'label' => $batch->status->label(),
            'success' => $batch->success_count,
            'failure' => $batch->failure_count,
            'skipped' => $batch->skipped_count,
            'total' => $batch->total_rows,
        ]);
    }

    public function download(ImportBatch $batch): StreamedResponse
    {
        abort_unless(Storage::disk('local')->exists($batch->stored_path), 404);

        return Storage::disk('local')->download($batch->stored_path, $batch->original_filename);
    }

    public function editor(Request $request): View
    {
        $filter = $this->requestedFilter($request);
        $search = $this->requestedSearch($request);
        $editing = null;

        if ($request->filled('product')) {
            $editing = $this->imports->editorProduct($request->integer('product'));
            abort_unless($editing, 404);
        }

        return view('admin.imports.editor', [
            'products' => $editing ? null : $this->imports->editorProducts($filter, $search),
            'editing' => $editing,
            'filters' => $this->filters(),
            'filter' => $filter,
            'search' => $search,
            'brands' => $editing ? Brand::query()->orderBy('name')->get(['id', 'name']) : collect(),
            'categories' => $editing ? Category::query()->orderBy('name')->get(['id', 'name']) : collect(),
            'media' => $editing ? collect() : ImportMedia::query()->latest()->limit(12)->get(),
        ]);
    }

    public function save(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'products' => ['nullable', 'array'],
            'products.*.name' => ['nullable', 'string', 'max:255'],
            'products.*.name_ar' => ['nullable', 'string', 'max:255'],
            'products.*.brand_id' => ['nullable', 'integer'],
            'products.*.gender' => ['nullable', 'string'],
            'products.*.categories' => ['nullable', 'array'],
            'products.*.categories.*' => ['integer'],
            'products.*.barcode' => ['nullable', 'string', 'max:64'],
            'products.*.price' => ['nullable', 'numeric', 'min:0'],
            'products.*.cost_price' => ['nullable', 'numeric', 'min:0'],
            'products.*.compare_at_price' => ['nullable', 'numeric', 'min:0'],
            'products.*.stock_quantity' => ['nullable', 'integer', 'min:0'],
            'products.*.approve_content' => ['nullable', 'boolean'],
            'products.*.short_description' => ['nullable', 'string'],
            'products.*.short_description_ar' => ['nullable', 'string'],
            'products.*.description' => ['nullable', 'string'],
            'products.*.description_ar' => ['nullable', 'string'],
            'products.*.ingredients' => ['nullable', 'string'],
            'products.*.ingredients_ar' => ['nullable', 'string'],
            'products.*.how_to_use' => ['nullable', 'string'],
            'products.*.how_to_use_ar' => ['nullable', 'string'],
            'products.*.variants' => ['nullable', 'array'],
            'products.*.primary_image_id' => ['nullable', 'integer'],
            'images' => ['nullable', 'array'],
            'images.*' => ['nullable', 'array'],
            'images.*.*' => ['image', 'max:4096'],
            'variant_images' => ['nullable', 'array'],
            'variant_images.*' => ['nullable', 'array'],
            'variant_images.*.*' => ['image', 'max:4096'],
            'new_variant_images' => ['nullable', 'array'],
            'new_variant_images.*' => ['nullable', 'array', 'max:12'],
            'new_variant_images.*.*' => ['image', 'max:4096'],
            'remove_image_ids' => ['nullable', 'array'],
            'remove_image_ids.*' => ['integer'],
            'activate' => ['nullable', 'boolean'],
        ]);

        $this->imports->saveEditor($request->input('products', []));
        $this->syncEditorImages($request);

        if (! $request->boolean('activate')) {
            return back()->with('success', 'Changes saved. Incomplete products remain in Draft.');
        }

        $ids = array_map('intval', array_keys($request->input('products', [])));
        $result = $this->imports->publish($ids);
        $activated = count($result['activated']);
        $held = count($result['rejected']);

        return back()
            ->with('success', $activated > 0
                ? ($activated === 1 ? 'Product activated.' : $activated.' products activated.')
                : 'Changes saved. Incomplete products remain in Draft.')
            ->with('error', $held > 0 ? $held.' product'.($held === 1 ? '' : 's').' stayed in Draft because required information is still missing.' : null)
            ->with('publish_rejections', $result['rejected']);
    }

    public function bulkApply(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'scope' => ['nullable', 'in:selected,filter'],
            'ids' => ['required_unless:scope,filter', 'array', 'min:1'],
            'ids.*' => ['integer'],
            'filter' => ['required_if:scope,filter', 'nullable', 'string', 'max:40'],
            'q' => ['nullable', 'string', 'max:120'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'compare_at_price' => ['nullable', 'numeric', 'min:0'],
            'stock_quantity' => ['nullable', 'integer', 'min:0'],
            'variants' => ['nullable', 'boolean'],
        ]);

        $scope = $data['scope'] ?? 'selected';
        if ($scope === 'filter') {
            $filter = (string) ($data['filter'] ?? '');
            if (! array_key_exists($filter, $this->filters())) {
                return back()->with('error', 'That filter is not available.');
            }
            $count = $this->imports->applyBulkValuesToFilter($filter, trim((string) ($data['q'] ?? '')), $data);
        } else {
            $this->imports->applyBulkValues($data['ids'], $data);
            $count = count($data['ids']);
        }

        if ($count === 0) {
            return back()->with('error', 'No imported products matched.');
        }

        return back()->with('success', 'Shared values applied to '.$count.' product'.($count === 1 ? '' : 's').'. You can still override a single product or variant.');
    }

    public function publish(Request $request): RedirectResponse
    {
        $ids = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ])['ids'];

        $result = $this->imports->publish(array_map('intval', $ids));
        $activated = count($result['activated']);
        $held = count($result['rejected']);

        return back()
            ->with('success', $activated.' product'.($activated === 1 ? '' : 's').' activated.')
            ->with('error', $held > 0 ? $held.' product'.($held === 1 ? '' : 's').' stayed in Draft because required information is still missing.' : null)
            ->with('publish_rejections', $result['rejected']);
    }

    public function uploadMedia(Request $request): RedirectResponse
    {
        $request->validate([
            'batch_id' => ['nullable', 'integer', 'exists:import_batches,id'],
            'images' => ['nullable', 'array', 'max:40'],
            'images.*' => ['image', 'max:4096'],
            'zip' => ['nullable', 'file', 'mimes:zip', 'max:51200'],
        ]);

        $batch = $request->filled('batch_id')
            ? ImportBatch::query()->find($request->integer('batch_id'))
            : null;

        $this->media->storeUploads(
            $batch,
            $request->user(),
            $request->file('images', []) ?? [],
            $request->file('zip'),
        );

        return back()->with('success', 'Images received. Only verified barcode matches were attached. Other files are waiting for review.');
    }

    public function assignMedia(Request $request, ImportMedia $medium): RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'product_variant_id' => ['nullable', 'integer'],
        ]);

        $product = Product::query()->findOrFail($data['product_id']);
        $variant = null;
        if (! empty($data['product_variant_id'])) {
            $variant = ProductVariant::query()
                ->where('product_id', $product->id)
                ->findOrFail($data['product_variant_id']);
        }

        $this->media->assign($medium, $product, $variant);
        $product->refresh()->load(['images', 'variants.images', 'variants.attributeValues']);
        $this->readiness->remember($product);

        return back()->with('success', 'Image assigned. Completeness was updated.');
    }

    public function export(Request $request): StreamedResponse
    {
        $format = $request->string('format')->toString() === 'xlsx' ? 'xlsx' : 'csv';
        $filter = $this->requestedFilter($request);
        $search = $this->requestedSearch($request);
        $products = $this->imports->editorQuery($filter, $search)
            ->with(['brand', 'categories', 'variants'])
            ->orderByDesc('id')
            ->lazy(100);

        $rows = $this->spreadsheet->rows($products);
        $filename = 'aura-oath-products-'.$filter.'-'.now()->format('Ymd-His');

        if ($format === 'xlsx') {
            $binary = $this->spreadsheet->toXlsx($rows);

            return response()->streamDownload(function () use ($binary) {
                echo $binary;
            }, $filename.'.xlsx', [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]);
        }

        $csv = $this->spreadsheet->toCsv($rows);

        return response()->streamDownload(function () use ($csv) {
            echo $csv;
        }, $filename.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function schema(): StreamedResponse
    {
        $path = resource_path('import-schemas/product-import-v1.schema.json');
        abort_unless(is_file($path), 404);

        return response()->streamDownload(function () use ($path) {
            readfile($path);
        }, 'product-import-v1.schema.json', ['Content-Type' => 'application/schema+json']);
    }

    public function example(): StreamedResponse
    {
        $path = resource_path('import-examples/products.example.json');
        abort_unless(is_file($path), 404);

        return response()->streamDownload(function () use ($path) {
            readfile($path);
        }, 'products.example.json', ['Content-Type' => 'application/json']);
    }

    /**
     * @return array<string, string>
     */
    protected function filters(): array
    {
        return [
            'all' => 'All imported',
            'draft' => 'Draft',
            'ready' => 'Ready to publish',
            'active' => 'Active',
            'missing_barcode' => 'Missing barcode',
            'missing_price' => 'Missing price',
            'missing_cost' => 'Missing cost',
            'missing_image' => 'Missing image',
            'missing_stock' => 'Missing stock',
            'missing_approval' => 'Missing content approval',
            'variants' => 'With variants',
            'duplicate' => 'Duplicate warning',
        ];
    }

    protected function requestedFilter(Request $request): string
    {
        $filter = $request->string('filter')->toString() ?: 'all';

        return array_key_exists($filter, $this->filters()) ? $filter : 'all';
    }

    protected function requestedSearch(Request $request): string
    {
        return mb_substr(trim($request->string('q')->toString()), 0, 120);
    }

    protected function importedProduct(int $id): ?Product
    {
        return Product::query()
            ->whereKey($id)
            ->where(function ($query) {
                $query->whereNotNull('import_batch_id')
                    ->orWhere('content_source', ContentSource::Ai);
            })
            ->first();
    }

    protected function syncEditorImages(Request $request): void
    {
        $touchedVariants = [];

        foreach ($request->input('remove_image_ids', []) as $id) {
            $image = ProductImage::query()->find($id);
            if (! $image) {
                continue;
            }
            if ($image->product_variant_id) {
                $touchedVariants[] = (int) $image->product_variant_id;
            }
            $this->images->delete($image->path);
            $image->delete();
        }

        $groups = $request->file('images', []) ?? [];
        foreach ($groups as $productId => $files) {
            $product = $this->importedProduct((int) $productId);
            if (! $product) {
                continue;
            }
            $files = $files instanceof UploadedFile ? [$files] : (array) $files;
            $order = (int) $product->images()->max('sort_order');
            foreach ($files as $file) {
                if (! $file instanceof UploadedFile) {
                    continue;
                }
                $order++;
                $first = $product->images()->doesntExist();
                ProductImage::query()->create([
                    'product_id' => $product->id,
                    'path' => $this->images->store($file, 'products'),
                    'alt' => $product->name,
                    'sort_order' => $first ? 0 : $order,
                    'is_primary' => $first,
                ]);
            }
            $product->refresh()->load(['images', 'variants.images', 'variants.attributeValues']);
            $this->readiness->remember($product);
        }

        $variantGroups = $request->file('variant_images', []) ?? [];
        foreach ($variantGroups as $variantId => $files) {
            $variant = ProductVariant::query()->find($variantId);
            $product = $variant ? $this->importedProduct((int) $variant->product_id) : null;
            if (! $variant || ! $product) {
                continue;
            }
            $files = $files instanceof UploadedFile ? [$files] : (array) $files;
            $order = (int) $variant->images()->max('sort_order');
            foreach ($files as $file) {
                if (! $file instanceof UploadedFile) {
                    continue;
                }
                $order++;
                $first = $variant->images()->doesntExist();
                ProductImage::query()->create([
                    'product_id' => $product->id,
                    'product_variant_id' => $variant->id,
                    'path' => $this->images->store($file, 'products'),
                    'alt' => $variant->name ?: $product->name,
                    'sort_order' => $first ? 0 : $order,
                    'is_primary' => $first,
                ]);
            }
            $touchedVariants[] = (int) $variant->id;
            $product->refresh()->load(['images', 'variants.images', 'variants.attributeValues']);
            $this->readiness->remember($product);
        }

        $newGroups = $request->file('new_variant_images', []) ?? [];
        foreach ($newGroups as $productId => $files) {
            $product = $this->importedProduct((int) $productId);
            if (! $product) {
                continue;
            }
            $variantInput = $request->input('products.'.$productId.'.variants', []);
            $hasNew = collect(is_array($variantInput) ? $variantInput : [])->contains(
                fn ($variant) => is_array($variant) && empty($variant['id']) && trim((string) ($variant['name'] ?? '')) !== ''
            );
            if (! $hasNew) {
                continue;
            }
            $knownIds = collect($variantInput)
                ->pluck('id')
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->all();
            $variant = ProductVariant::query()
                ->where('product_id', $product->id)
                ->when($knownIds !== [], fn ($query) => $query->whereNotIn('id', $knownIds))
                ->latest('id')
                ->first();
            if (! $variant) {
                continue;
            }
            $files = $files instanceof UploadedFile ? [$files] : (array) $files;
            $order = (int) $variant->images()->max('sort_order');
            foreach ($files as $file) {
                if (! $file instanceof UploadedFile) {
                    continue;
                }
                $order++;
                $first = $variant->images()->doesntExist();
                ProductImage::query()->create([
                    'product_id' => $product->id,
                    'product_variant_id' => $variant->id,
                    'path' => $this->images->store($file, 'products'),
                    'alt' => $variant->name ?: $product->name,
                    'sort_order' => $first ? 0 : $order,
                    'is_primary' => $first,
                ]);
            }
            $touchedVariants[] = (int) $variant->id;
            $product->refresh()->load(['images', 'variants.images', 'variants.attributeValues']);
            $this->readiness->remember($product);
        }

        foreach (array_unique($touchedVariants) as $variantId) {
            $variant = ProductVariant::query()->find($variantId);
            if (! $variant) {
                continue;
            }
            $variant->update([
                'image_path' => $variant->images()->orderBy('sort_order')->value('path'),
            ]);
        }

        foreach ($request->input('products', []) as $productId => $input) {
            if (! is_array($input) || empty($input['primary_image_id'])) {
                continue;
            }
            $image = ProductImage::query()
                ->where('product_id', $productId)
                ->find($input['primary_image_id']);
            if (! $image) {
                continue;
            }
            ProductImage::query()->where('product_id', $productId)->whereNull('product_variant_id')->update(['is_primary' => false]);
            $image->update(['is_primary' => true]);
        }
    }
}
