<?php

namespace App\Services\ProductImport;

use App\Enums\ContentSource;
use App\Enums\ImportBatchStatus;
use App\Enums\ImportResolution;
use App\Enums\ImportRowStatus;
use App\Enums\ProductGender;
use App\Enums\ProductStatus;
use App\Enums\ProductVisibility;
use App\Jobs\ProcessProductImport;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Brand;
use App\Models\Category;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\AuditService;
use App\Services\BarcodeService;
use App\Services\ProductReadinessService;
use App\Services\ProductVariantService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProductImportService
{
    public function __construct(
        protected ProductPayloadNormalizer $normalizer,
        protected ProductMatcher $matcher,
        protected CatalogueSpreadsheet $spreadsheet,
        protected ImportMediaService $media,
        protected ProductReadinessService $readiness,
        protected ProductVariantService $variants,
        protected BarcodeService $barcodes,
        protected AuditService $audit,
    ) {}

    /**
     * @return array{created: int, updated: int, skipped: int, errors: list<string>}
     */
    public function syncExcel(UploadedFile $file): array
    {
        $name = basename(str_replace('\\', '/', $file->getClientOriginalName()));
        if (preg_match('/\.(php|phtml|phar|exe|js|html)(\.|$)/i', $name)) {
            throw ValidationException::withMessages(['file' => 'This file type is not allowed.']);
        }

        $extension = strtolower($file->getClientOriginalExtension());
        if (! in_array($extension, ['csv', 'xlsx'], true)) {
            throw ValidationException::withMessages(['file' => 'Upload the Excel or CSV file exported from this page.']);
        }

        $contents = $file->get();
        if (! is_string($contents) || $contents === '') {
            throw ValidationException::withMessages(['file' => 'The file is empty.']);
        }

        try {
            $document = $extension === 'xlsx'
                ? $this->spreadsheet->productsFromXlsx($contents)
                : $this->spreadsheet->productsFromCsv($contents);
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages(['file' => $e->getMessage()]);
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];

        DB::transaction(function () use ($document, &$created, &$updated, &$skipped, &$errors) {
            foreach ($document['products'] ?? [] as $index => $raw) {
                if (! is_array($raw)) {
                    $skipped++;

                    continue;
                }

                $normalized = $this->normalizer->product($raw, (int) $index);
                if ($normalized['errors'] !== []) {
                    $skipped++;
                    $label = is_string($raw['name'] ?? null) ? $raw['name'] : 'Row '.((int) $index + 1);
                    $errors[] = $label.': '.implode(' ', $normalized['errors']);

                    continue;
                }

                $payload = $normalized['payload'];
                $warnings = $normalized['warnings'];
                $matches = $this->matcher->productsForBarcodes($payload);

                try {
                    if (count($matches) > 1) {
                        $skipped++;
                        $errors[] = ($payload['name'] ?? 'Product').': barcodes match more than one product.';

                        continue;
                    }

                    if (count($matches) === 1) {
                        $this->persistExistingProduct(array_values($matches)[0], $payload, $warnings, markAi: false);
                        $updated++;

                        continue;
                    }

                    $this->persistNewProduct($payload, $warnings, [
                        'batch_id' => null,
                        'content_source' => ContentSource::Verified,
                        'ai_unverified' => false,
                        'is_new' => true,
                        'status' => ProductStatus::Draft,
                    ]);
                    $created++;
                } catch (ValidationException $e) {
                    $skipped++;
                    $errors[] = ($payload['name'] ?? 'Product').': '.collect($e->errors())->flatten()->implode(' ');
                }
            }
        });

        return compact('created', 'updated', 'skipped', 'errors');
    }

    public function catalogueExportXlsx(): string
    {
        $products = Product::query()
            ->with(['brand', 'categories', 'variants'])
            ->orderBy('name')
            ->get();

        return $this->spreadsheet->toXlsx($this->spreadsheet->rows($products));
    }

    public function ingest(UploadedFile $file, User $user, bool $force = false): ImportBatch
    {
        $name = basename(str_replace('\\', '/', $file->getClientOriginalName()));
        if (preg_match('/\.(php|phtml|phar|exe|js|html|svg)(\.|$)/i', $name)) {
            throw ValidationException::withMessages(['file' => 'This file type is not allowed.']);
        }

        $extension = strtolower($file->getClientOriginalExtension());
        if (! in_array($extension, ['json', 'csv', 'xlsx'], true)) {
            throw ValidationException::withMessages(['file' => 'Upload a .json, .csv, or .xlsx file.']);
        }

        $contents = file_get_contents($file->getRealPath() ?: '');
        if (! is_string($contents) || $contents === '') {
            throw ValidationException::withMessages(['file' => 'The file is empty.']);
        }

        if (strlen($contents) > ProductPayloadNormalizer::MAX_JSON_BYTES) {
            throw ValidationException::withMessages(['file' => 'The file is larger than 8 MB.']);
        }

        $hash = hash('sha256', $contents);
        if (! $force) {
            $existing = ImportBatch::query()->where('idempotency_key', $hash)->latest('id')->first();
            if ($existing) {
                return $existing;
            }
        }

        try {
            $document = match ($extension) {
                'csv' => $this->spreadsheet->productsFromCsv($contents),
                'xlsx' => $this->spreadsheet->productsFromXlsx($contents),
                default => $this->decodeJson($contents),
            };
            $parsed = $this->normalizer->document($document);
        } catch (\InvalidArgumentException|\JsonException $exception) {
            throw ValidationException::withMessages(['file' => $exception->getMessage()]);
        }

        $uuid = (string) Str::uuid();
        $path = 'imports/'.$uuid.'.'.$extension;
        \Illuminate\Support\Facades\Storage::disk('local')->put($path, $contents);

        $batch = ImportBatch::query()->create([
            'uuid' => $uuid,
            'user_id' => $user->id,
            'original_filename' => $name,
            'stored_path' => $path,
            'schema_version' => $parsed['schema_version'],
            'idempotency_key' => $hash,
            'status' => ImportBatchStatus::Validated,
            'total_rows' => count($parsed['products']),
            'source_meta' => $parsed['source'],
        ]);

        $prepared = [];
        $seenBarcodes = [];
        $seenKeys = [];

        foreach ($parsed['products'] as $index => $product) {
            if (! is_array($product)) {
                $prepared[] = $this->invalidRow($index, 'Product '.($index + 1).' is not an object.');

                continue;
            }

            $normalized = $this->normalizer->product($product, $index);
            $normalized['warnings'] = array_values(array_unique([
                ...$parsed['warnings'],
                ...$normalized['warnings'],
            ]));

            foreach ($this->barcodesIn($normalized['payload']) as $code) {
                if (isset($seenBarcodes[$code])) {
                    $normalized['errors'][] = 'Barcode '.$code.' is repeated in this file.';
                }
                $seenBarcodes[$code] = true;
            }

            $identity = $this->matcher->identityKey($normalized['payload']);
            if ($identity !== '||' && isset($seenKeys[$identity])) {
                $normalized['warnings'][] = 'Another row in this file has the same brand, name, and size. Confirm it is not a duplicate before creating it.';
                $normalized['duplicate_in_file'] = true;
            }
            $seenKeys[$identity] = true;

            $match = $normalized['errors'] === []
                ? $this->matcher->match($normalized['payload'], $normalized['warnings'])
                : [
                    'match_type' => 'none',
                    'matched_product_id' => null,
                    'resolution' => ImportResolution::Skip->value,
                    'warnings' => $normalized['warnings'],
                ];

            if (! empty($normalized['duplicate_in_file']) && $match['resolution'] === ImportResolution::Create->value) {
                $match['resolution'] = ImportResolution::Review->value;
                $match['match_type'] = $match['match_type'] === 'none' ? 'suggestion' : $match['match_type'];
            }

            $prepared[] = [
                'row_index' => $index,
                'external_key' => $normalized['payload']['external_id'] ?? null,
                'name' => $normalized['payload']['name'] ?? ('Row '.($index + 1)),
                'payload' => $normalized['payload'],
                'errors' => $normalized['errors'],
                'warnings' => $match['warnings'],
                'missing_fields' => $normalized['missing'],
                'match_type' => $match['match_type'],
                'matched_product_id' => $match['matched_product_id'],
                'resolution' => $normalized['errors'] === [] ? $match['resolution'] : ImportResolution::Skip->value,
                'status' => $normalized['errors'] === [] ? ImportRowStatus::Pending->value : ImportRowStatus::Invalid->value,
                'duplicate' => ($match['match_type'] ?? 'none') !== 'none' || ! empty($normalized['duplicate_in_file']),
            ];
        }

        foreach ($prepared as $row) {
            $batch->rows()->create([
                'row_index' => $row['row_index'],
                'external_key' => $row['external_key'],
                'name' => $row['name'],
                'payload' => $row['payload'],
                'errors' => $row['errors'],
                'warnings' => $row['warnings'],
                'missing_fields' => $row['missing_fields'],
                'match_type' => $row['match_type'],
                'matched_product_id' => $row['matched_product_id'],
                'resolution' => $row['resolution'],
                'status' => $row['status'],
            ]);
        }

        $batch->update([
            'failure_count' => $batch->rows()->where('status', ImportRowStatus::Invalid)->count(),
        ]);

        $this->audit->log('product_import.uploaded', $batch, null, [
            'filename' => $name,
            'rows' => $batch->total_rows,
        ]);

        $batch->load('rows');

        return $batch;
    }

    /**
     * @param  array<int|string, array<string, mixed>>  $edits
     */
    /**
     * @param  array<int|string, mixed>  $productFiles
     * @param  array<int|string, mixed>  $variantFiles
     * @param  array<int|string, mixed>  $edits
     */
    public function stageReviewImages(ImportBatch $batch, array $productFiles, array $variantFiles, array $edits): void
    {
        if (in_array($batch->status, [ImportBatchStatus::Processing, ImportBatchStatus::Completed], true)) {
            return;
        }

        $batch->load('rows');

        foreach ($batch->rows as $row) {
            $payload = $row->payload ?? [];
            $edit = $edits[$row->id] ?? $edits[(string) $row->id] ?? [];
            if (! is_array($edit)) {
                $edit = [];
            }

            $payload['staged_images'] = $this->mergeStagedImages(
                $payload['staged_images'] ?? [],
                $edit['remove_images'] ?? [],
                $productFiles[$row->id] ?? $productFiles[(string) $row->id] ?? [],
            );

            foreach ($payload['variants'] ?? [] as $index => $variant) {
                if (! is_array($variant)) {
                    continue;
                }
                $variantEdit = $edit['variants'][$index] ?? $edit['variants'][(string) $index] ?? [];
                if (! is_array($variantEdit)) {
                    $variantEdit = [];
                }
                $rowFiles = $variantFiles[$row->id] ?? $variantFiles[(string) $row->id] ?? [];
                if (! is_array($rowFiles)) {
                    $rowFiles = [];
                }
                $files = $rowFiles[$index] ?? $rowFiles[(string) $index] ?? [];
                $variant['staged_images'] = $this->mergeStagedImages(
                    $variant['staged_images'] ?? [],
                    $variantEdit['remove_images'] ?? [],
                    $files,
                );
                $payload['variants'][$index] = $variant;
            }

            $row->update(['payload' => $payload]);
        }
    }

    public function applyEdits(ImportBatch $batch, array $edits): void
    {
        if (in_array($batch->status, [ImportBatchStatus::Processing, ImportBatchStatus::Completed], true)) {
            return;
        }

        foreach ($batch->rows as $row) {
            $edit = $edits[$row->id] ?? null;
            if (! is_array($edit)) {
                continue;
            }

            $payload = $row->payload ?? [];
            foreach (['barcode', 'price', 'cost', 'compare_at_price', 'stock'] as $field) {
                if (array_key_exists($field, $edit)) {
                    $payload[$field] = $edit[$field] === '' ? null : $edit[$field];
                }
            }

            if (isset($edit['variants']) && is_array($edit['variants'])) {
                foreach ($payload['variants'] ?? [] as $index => $variant) {
                    $variantEdit = $edit['variants'][$index] ?? $edit['variants'][(string) $index] ?? null;
                    if (! is_array($variantEdit)) {
                        continue;
                    }
                    foreach (['barcode', 'price', 'cost', 'compare_at_price', 'stock', 'option_name'] as $field) {
                        if (array_key_exists($field, $variantEdit)) {
                            $variant[$field] = $variantEdit[$field] === '' ? null : $variantEdit[$field];
                        }
                    }
                    $payload['variants'][$index] = $variant;
                }
            }

            $normalized = $this->normalizer->product($payload, $row->row_index);
            $match = $normalized['errors'] === []
                ? $this->matcher->match($normalized['payload'], $normalized['warnings'])
                : [
                    'match_type' => 'none',
                    'matched_product_id' => null,
                    'resolution' => ImportResolution::Skip->value,
                    'warnings' => $normalized['warnings'],
                ];

            $resolution = $edit['resolution'] ?? $row->resolution?->value;
            if (! in_array($resolution, array_column(ImportResolution::cases(), 'value'), true)) {
                $resolution = $match['resolution'];
            }
            if ($normalized['errors'] !== []) {
                $resolution = ImportResolution::Skip->value;
            }

            $row->update([
                'payload' => $normalized['payload'],
                'name' => $normalized['payload']['name'] ?? $row->name,
                'external_key' => $normalized['payload']['external_id'] ?? null,
                'errors' => $normalized['errors'],
                'warnings' => $match['warnings'],
                'missing_fields' => $normalized['missing'],
                'match_type' => $match['match_type'],
                'matched_product_id' => $match['matched_product_id'],
                'resolution' => $resolution,
                'status' => $normalized['errors'] === [] ? ImportRowStatus::Pending : ImportRowStatus::Invalid,
                'error_message' => null,
            ]);
        }
    }

    public function queueCommit(ImportBatch $batch): void
    {
        if ($batch->status === ImportBatchStatus::Processing) {
            return;
        }

        $batch->update([
            'status' => ImportBatchStatus::Processing,
            'started_at' => now(),
            'error_summary' => null,
        ]);

        ProcessProductImport::dispatch($batch->id);
    }

    public function commit(ImportBatch $batch): void
    {
        $batch->refresh();
        $batch->update([
            'status' => ImportBatchStatus::Processing,
            'started_at' => $batch->started_at ?? now(),
        ]);

        foreach ($batch->rows()->orderBy('row_index')->get() as $row) {
            $this->commitRow($batch, $row);
        }

        $this->finish($batch);
    }

    public function commitRow(ImportBatch $batch, ImportRow $row): void
    {
        try {
            DB::transaction(function () use ($batch, $row) {
                $locked = ImportRow::query()->whereKey($row->id)->lockForUpdate()->first();
                if (! $locked || $locked->status !== ImportRowStatus::Pending) {
                    return;
                }

                $resolution = $locked->resolution;
                if ($resolution === ImportResolution::Review) {
                    return;
                }
                if ($resolution === ImportResolution::Skip) {
                    $locked->update([
                        'status' => ImportRowStatus::Skipped,
                        'error_message' => null,
                    ]);

                    return;
                }

                $product = match ($resolution) {
                    ImportResolution::Update => $this->updateExisting($batch, $locked),
                    ImportResolution::AddVariants => $this->addVariants($batch, $locked),
                    default => $this->createProduct($batch, $locked),
                };

                $this->attachStagedImages($product, $locked->payload ?? []);
                $this->media->attachVerified($batch, $product);
                $product->refresh()->load(['images', 'variants.images', 'variants.attributeValues']);
                $this->readiness->remember($product);
                $product->refreshStockStatus();

                $locked->update([
                    'status' => ImportRowStatus::Committed,
                    'product_id' => $product->id,
                    'error_message' => null,
                ]);
            });
        } catch (\Throwable $exception) {
            Log::warning('product_import.row_failed', [
                'batch_id' => $batch->id,
                'row_id' => $row->id,
                'message' => $exception->getMessage(),
            ]);

            $row->refresh();
            if ($row->status !== ImportRowStatus::Committed) {
                $row->update([
                    'status' => ImportRowStatus::Failed,
                    'error_message' => $exception instanceof ValidationException
                        ? collect($exception->errors())->flatten()->implode(' ')
                        : 'This row could not be imported. Check the values and try again.',
                ]);
            }
        }
    }

    /**
     * A page of the import queue. Rows stay small on purpose: the full edit form is loaded for one product at a time.
     */
    public function editorProducts(string $filter, string $search = '')
    {
        return $this->editorQuery($filter, $search)
            ->withCount('variants')
            ->orderByDesc('id')
            ->paginate(40)
            ->withQueryString();
    }

    public function editorProduct(int $id): ?Product
    {
        return $this->editorQuery('all')
            ->with(['brand', 'categories', 'images', 'variants.images'])
            ->find($id);
    }

    /**
     * @param  array{price?: mixed, cost_price?: mixed, compare_at_price?: mixed, stock_quantity?: mixed, variants?: bool}  $values
     */
    public function applyBulkValuesToFilter(string $filter, string $search, array $values): int
    {
        $count = 0;

        $this->editorQuery($filter, $search)
            ->select('id')
            ->orderBy('id')
            ->chunkById(40, function ($products) use ($values, &$count) {
                $ids = array_map('intval', $products->modelKeys());
                $this->applyBulkValues($ids, $values);
                $count += count($ids);
            });

        return $count;
    }

    public function editorQuery(string $filter, string $search = '')
    {
        $query = Product::query()->where(function ($builder) {
            $builder->whereNotNull('import_batch_id')
                ->orWhere('content_source', ContentSource::Ai);
        });

        $query = match ($filter) {
            'draft' => $query->where('status', ProductStatus::Draft),
            'ready' => $query->where('status', ProductStatus::Draft)->where('is_publishable', true),
            'active' => $query->where('status', ProductStatus::Active),
            'missing_barcode' => $this->missing($query, ['barcode', 'variant_barcode']),
            'missing_price' => $this->missing($query, ['price', 'variant_price']),
            'missing_cost' => $this->missing($query, ['cost', 'variant_cost']),
            'missing_image' => $this->missing($query, ['image', 'variant_image']),
            'missing_stock' => $this->missing($query, ['stock', 'variant_stock']),
            'missing_approval' => $this->missing($query, ['content_approval']),
            'variants' => $query->where('has_variants', true),
            'duplicate' => $query->where('import_meta->duplicate_warning', true),
            default => $query,
        };

        $search = trim($search);
        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function ($builder) use ($like) {
                $builder->where('name', 'like', $like)
                    ->orWhere('name_ar', 'like', $like)
                    ->orWhere('sku', 'like', $like)
                    ->orWhere('barcode', 'like', $like)
                    ->orWhereHas('variants', function ($variants) use ($like) {
                        $variants->where('name', 'like', $like)
                            ->orWhere('sku', 'like', $like)
                            ->orWhere('barcode', 'like', $like);
                    });
            });
        }

        return $query;
    }

    /**
     * @param  array<int|string, array<string, mixed>>  $products
     */
    public function saveEditor(array $products): void
    {
        foreach ($products as $id => $input) {
            if (! is_array($input)) {
                continue;
            }

            $product = Product::query()->with('variants')->find($id);
            if (! $product || ($product->import_batch_id === null && $product->content_source !== ContentSource::Ai)) {
                continue;
            }

            DB::transaction(function () use ($product, $input) {
                $data = [];
                foreach ([
                    'name' => 'name',
                    'name_ar' => 'name_ar',
                    'short_description' => 'short_description',
                    'short_description_ar' => 'short_description_ar',
                    'description' => 'description',
                    'description_ar' => 'description_ar',
                    'ingredients' => 'ingredients',
                    'ingredients_ar' => 'ingredients_ar',
                    'how_to_use' => 'how_to_use',
                    'how_to_use_ar' => 'how_to_use_ar',
                ] as $inputKey => $column) {
                    if (array_key_exists($inputKey, $input)) {
                        $data[$column] = $this->nullableString($input[$inputKey]);
                    }
                }

                if (array_key_exists('brand_id', $input)) {
                    $data['brand_id'] = $input['brand_id'] !== '' ? (int) $input['brand_id'] : null;
                }
                if (array_key_exists('gender', $input) && is_string($input['gender'])) {
                    $gender = ProductGender::tryFrom($input['gender']);
                    if ($gender) {
                        $data['gender'] = $gender;
                    }
                }

                $usesVariants = $product->has_variants || ! empty($input['variants']);
                if (! $usesVariants) {
                    foreach (['barcode' => 'barcode', 'price' => 'price', 'cost_price' => 'cost_price', 'compare_at_price' => 'compare_at_price'] as $inputKey => $column) {
                        if (array_key_exists($inputKey, $input)) {
                            $data[$column] = $inputKey === 'barcode'
                                ? $this->nullableString($input[$inputKey])
                                : $this->nullableMoney($input[$inputKey]);
                        }
                    }
                    if (array_key_exists('stock_quantity', $input)) {
                        $data['stock_quantity'] = $this->nullableStock($input['stock_quantity']);
                    }
                    $this->assertCompare($data['compare_at_price'] ?? $product->compare_at_price, $data['price'] ?? $product->price);
                    if (! empty($data['barcode'])) {
                        $this->barcodes->assertUnique($data['barcode'], $product->id);
                    }
                }

                if (! empty($input['approve_content'])) {
                    $data['content_approved_at'] = now();
                }

                if ($data !== []) {
                    $product->update($data);
                }

                if (isset($input['categories']) && is_array($input['categories'])) {
                    $ids = array_values(array_filter(array_map('intval', $input['categories'])));
                    $product->categories()->sync($ids);
                    $product->update(['category_id' => $ids[0] ?? null]);
                }

                if (isset($input['variants']) && is_array($input['variants'])) {
                    $this->syncEditorVariants($product, $input['variants']);
                }

                $product->refresh()->load(['images', 'variants.images', 'variants.attributeValues']);
                $this->readiness->remember($product);
            });
        }
    }

    /**
     * @param  list<int>  $ids
     * @param  array{price?: mixed, cost_price?: mixed, compare_at_price?: mixed, stock_quantity?: mixed, variants?: bool}  $values
     */
    public function applyBulkValues(array $ids, array $values): void
    {
        $products = Product::query()
            ->with('variants')
            ->whereIn('id', $ids)
            ->where(function ($query) {
                $query->whereNotNull('import_batch_id')->orWhere('content_source', ContentSource::Ai);
            })
            ->get();

        foreach ($products as $product) {
            if (! $product->has_variants) {
                $data = [];
                foreach (['price', 'cost_price', 'compare_at_price'] as $field) {
                    if (array_key_exists($field, $values) && $values[$field] !== '' && $values[$field] !== null) {
                        $data[$field] = $this->nullableMoney($values[$field]);
                    }
                }
                if (array_key_exists('stock_quantity', $values) && $values['stock_quantity'] !== '' && $values['stock_quantity'] !== null) {
                    $data['stock_quantity'] = $this->nullableStock($values['stock_quantity']);
                }
                $this->assertCompare($data['compare_at_price'] ?? $product->compare_at_price, $data['price'] ?? $product->price);
                if ($data !== []) {
                    $product->update($data);
                }
            } elseif (! empty($values['variants'])) {
                foreach ($product->variants as $variant) {
                    $data = [];
                    if (isset($values['price']) && $values['price'] !== '') {
                        $data['price'] = $this->nullableMoney($values['price']);
                    }
                    if (isset($values['cost_price']) && $values['cost_price'] !== '') {
                        $data['cost_price'] = $this->nullableMoney($values['cost_price']);
                    }
                    if (isset($values['compare_at_price']) && $values['compare_at_price'] !== '') {
                        $data['compare_at_price'] = $this->nullableMoney($values['compare_at_price']);
                    }
                    if (isset($values['stock_quantity']) && $values['stock_quantity'] !== '') {
                        $data['stock_quantity'] = $this->nullableStock($values['stock_quantity']);
                    }
                    $this->assertCompare($data['compare_at_price'] ?? $variant->compare_at_price, $data['price'] ?? $variant->price ?? $product->price);
                    if ($data !== []) {
                        $variant->update($data);
                    }
                }
            }

            $product->refresh()->load(['images', 'variants.images', 'variants.attributeValues']);
            $this->readiness->remember($product);
        }
    }

    /**
     * @param  list<int>  $ids
     * @return array{activated: list<int>, rejected: array<int, string>}
     */
    public function publish(array $ids): array
    {
        $activated = [];
        $rejected = [];

        $products = Product::query()->whereIn('id', $ids)->get();
        foreach ($products as $product) {
            $issues = $this->readiness->issues($product);
            $this->readiness->remember($product, $issues);

            if ($issues !== []) {
                $rejected[$product->id] = 'Kept in Draft. Missing: '.implode(', ', $this->readiness->labels($issues)).'.';

                continue;
            }

            $product->update([
                'status' => ProductStatus::Active,
                'published_at' => $product->published_at ?? now(),
                'content_approved_at' => $product->content_approved_at ?? now(),
            ]);
            $this->audit->log('product.activated', $product, null, ['source' => 'bulk_import']);
            $activated[] = $product->id;
        }

        return ['activated' => $activated, 'rejected' => $rejected];
    }

    /**
     * @param  list<string>  $existing
     * @param  list<mixed>  $remove
     */
    protected function mergeStagedImages(array $existing, array $remove, mixed $files): array
    {
        $remove = array_map(fn ($path) => is_string($path) ? $path : '', $remove);
        $paths = [];
        foreach ($existing as $path) {
            if (! is_string($path) || $path === '') {
                continue;
            }
            if (in_array($path, $remove, true)) {
                $this->media->deleteStored($path);

                continue;
            }
            $paths[] = $path;
        }

        $uploads = $files instanceof UploadedFile ? [$files] : (is_array($files) ? $files : []);
        foreach ($uploads as $file) {
            if ($file instanceof UploadedFile) {
                $paths[] = $this->media->keepImage($file);
            }
        }

        return array_values(array_unique($paths));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function attachStagedImages(Product $product, array $payload): void
    {
        $this->attachImagePaths($product, null, $payload['staged_images'] ?? []);

        $product->load('variants');
        foreach ($payload['variants'] ?? [] as $variantPayload) {
            if (! is_array($variantPayload)) {
                continue;
            }
            $paths = $variantPayload['staged_images'] ?? [];
            if ($paths === []) {
                continue;
            }
            $name = $variantPayload['option_name'] ?? null;
            $variant = $product->variants->first(fn (ProductVariant $item) => $item->name === $name);
            if ($variant) {
                $this->attachImagePaths($product, $variant, $paths);
            }
        }
    }

    /**
     * @param  list<mixed>  $paths
     */
    protected function attachImagePaths(Product $product, ?ProductVariant $variant, array $paths): void
    {
        foreach ($paths as $path) {
            if (! is_string($path) || $path === '') {
                continue;
            }
            if (ProductImage::query()->where('product_id', $product->id)->where('path', $path)->exists()) {
                continue;
            }

            $gallery = ProductImage::query()
                ->where('product_id', $product->id)
                ->when(
                    $variant,
                    fn ($query) => $query->where('product_variant_id', $variant->id),
                    fn ($query) => $query->whereNull('product_variant_id'),
                );
            $isFirst = ! (clone $gallery)->exists();
            $sort = (int) (clone $gallery)->max('sort_order');

            ProductImage::query()->create([
                'product_id' => $product->id,
                'product_variant_id' => $variant?->id,
                'path' => $path,
                'alt' => $variant?->name ?: $product->name,
                'sort_order' => $isFirst ? 0 : $sort + 1,
                'is_primary' => $isFirst,
            ]);
        }

        if ($variant) {
            $first = $variant->images()->orderBy('sort_order')->value('path');
            if (is_string($first) && $first !== '') {
                $variant->update(['image_path' => $first]);
            }
        }
    }

    protected function createProduct(ImportBatch $batch, ImportRow $row): Product
    {
        $payload = $row->payload ?? [];
        $warnings = $row->warnings ?? [];
        $product = $this->persistNewProduct($payload, $warnings, [
            'batch_id' => $batch->id,
            'content_source' => ContentSource::Ai,
            'ai_unverified' => true,
            'is_new' => true,
            'status' => ProductStatus::Draft,
            'duplicate_warning' => in_array($row->match_type, ['suggestion', 'barcode', 'sku', 'catalogue', 'external_id'], true),
        ]);
        $row->warnings = $warnings;

        return $product;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<string>  $warnings
     * @param  array{batch_id: ?int, content_source: ContentSource, ai_unverified: bool, is_new: bool, status: ProductStatus, duplicate_warning?: bool}  $options
     */
    protected function persistNewProduct(array $payload, array &$warnings, array $options): Product
    {
        [$brandId, $categoryIds, $unit] = $this->resolveRelations($payload, $warnings);

        $sku = $this->usableSku($payload['sku'] ?? null) ?? $this->barcodes->generateSku();
        $this->barcodes->assertSkuUnique($sku);

        if (! ($payload['has_variants'] ?? false) && ! empty($payload['barcode'])) {
            $this->barcodes->assertUnique((string) $payload['barcode']);
        }

        $product = Product::query()->create([
            'category_id' => $categoryIds[0] ?? null,
            'brand_id' => $brandId,
            'import_batch_id' => $options['batch_id'],
            'name' => $payload['name'],
            'name_ar' => $payload['name_ar'] ?? null,
            'slug' => $this->uniqueSlug($payload['slug'] ?? null, (string) $payload['name']),
            'sku' => $sku,
            'barcode' => ($payload['has_variants'] ?? false) ? null : ($payload['barcode'] ?? null),
            'short_description' => $payload['short_description'] ?? null,
            'short_description_ar' => $payload['short_description_ar'] ?? null,
            'description' => $payload['description'] ?? null,
            'description_ar' => $payload['description_ar'] ?? null,
            'ingredients' => $payload['ingredients'] ?? null,
            'ingredients_ar' => $payload['ingredients_ar'] ?? null,
            'how_to_use' => $payload['how_to_use'] ?? null,
            'how_to_use_ar' => $payload['how_to_use_ar'] ?? null,
            'meta_title' => $payload['meta_title'] ?? null,
            'meta_title_ar' => $payload['meta_title_ar'] ?? null,
            'meta_description' => $payload['meta_description'] ?? null,
            'meta_description_ar' => $payload['meta_description_ar'] ?? null,
            'price' => $payload['price'] ?? null,
            'compare_at_price' => $payload['compare_at_price'] ?? null,
            'cost_price' => $payload['cost'] ?? null,
            'stock_quantity' => ($payload['has_variants'] ?? false) ? 0 : ($payload['stock'] ?? null),
            'track_inventory' => true,
            'has_variants' => (bool) ($payload['has_variants'] ?? false),
            'status' => $options['status'],
            'visibility' => ProductVisibility::Public,
            'gender' => $payload['gender'] ?? ProductGender::Unisex->value,
            'size' => $payload['size'] ?? null,
            'unit' => $unit,
            'weight' => $payload['weight'] ?? null,
            'content_source' => $options['content_source'],
            'content_approved_at' => $options['ai_unverified'] ? null : now(),
            'is_featured' => false,
            'is_bestseller' => false,
            'is_new' => $options['is_new'],
            'import_meta' => [
                'external_id' => $payload['external_id'] ?? null,
                'sources' => $payload['sources'] ?? [],
                'warnings' => $warnings,
                'variant_type' => $payload['variant_type'] ?? null,
                'duplicate_warning' => (bool) ($options['duplicate_warning'] ?? false),
                'ai_unverified' => $options['ai_unverified'],
            ],
        ]);

        $product->categories()->sync($categoryIds);

        if ($payload['has_variants'] ?? false) {
            $this->variants->syncVariants($product, $this->variantPayloads($payload, $warnings));
        }

        $this->readiness->remember($product->refresh()->load(['images', 'variants.images', 'variants.attributeValues']));

        return $product;
    }

    protected function updateExisting(ImportBatch $batch, ImportRow $row): Product
    {
        $product = Product::query()->find($row->matched_product_id);
        if (! $product) {
            throw ValidationException::withMessages(['product' => 'Choose a matched product before updating.']);
        }

        $payload = $row->payload ?? [];
        $warnings = $row->warnings ?? [];
        $this->persistExistingProduct($product, $payload, $warnings, markAi: true, batchId: $product->import_batch_id ?? $batch->id);
        $row->warnings = $warnings;

        return $product->refresh();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<string>  $warnings
     */
    protected function persistExistingProduct(Product $product, array $payload, array &$warnings, bool $markAi, ?int $batchId = null): Product
    {
        [$brandId, $categoryIds, $unit] = $this->resolveRelations($payload, $warnings);
        $data = [];

        foreach ([
            'name' => 'name',
            'name_ar' => 'name_ar',
            'short_description' => 'short_description',
            'short_description_ar' => 'short_description_ar',
            'description' => 'description',
            'description_ar' => 'description_ar',
            'ingredients' => 'ingredients',
            'ingredients_ar' => 'ingredients_ar',
            'how_to_use' => 'how_to_use',
            'how_to_use_ar' => 'how_to_use_ar',
        ] as $from => $to) {
            if (! empty($payload[$from])) {
                $data[$to] = $payload[$from];
            }
        }

        if ($brandId) {
            $data['brand_id'] = $brandId;
        }
        if ($unit) {
            $data['unit'] = $unit;
        }
        if (! empty($payload['size'])) {
            $data['size'] = $payload['size'];
        }
        if (! empty($payload['gender'])) {
            $data['gender'] = $payload['gender'];
        }

        if (! ($payload['has_variants'] ?? false)) {
            foreach (['barcode' => 'barcode', 'price' => 'price', 'cost' => 'cost_price', 'compare_at_price' => 'compare_at_price', 'stock' => 'stock_quantity'] as $from => $to) {
                if (($payload[$from] ?? null) !== null && $payload[$from] !== '') {
                    $data[$to] = $payload[$from];
                }
            }
            if (array_key_exists('stock', $payload) && $payload['stock'] === 0) {
                $data['stock_quantity'] = 0;
            }
            if (! empty($data['barcode'])) {
                $this->barcodes->assertUnique((string) $data['barcode'], $product->id);
            }
        }

        $meta = $product->import_meta ?? [];
        $meta['sources'] = $payload['sources'] ?? ($meta['sources'] ?? []);
        $meta['warnings'] = array_values(array_unique([...(array) ($meta['warnings'] ?? []), ...$warnings]));
        if ($markAi) {
            $meta['duplicate_warning'] = true;
            $meta['ai_unverified'] = true;
            $data['content_source'] = ContentSource::Ai;
            $data['content_approved_at'] = null;
            $data['import_batch_id'] = $batchId ?? $product->import_batch_id;
        }
        $data['import_meta'] = $meta;

        $product->update($data);
        if ($categoryIds !== []) {
            $product->categories()->sync($categoryIds);
            $product->update(['category_id' => $categoryIds[0]]);
        }

        if ($payload['has_variants'] ?? false) {
            $this->mergeVariants($product, $payload, $warnings, false);
        }

        $this->readiness->remember($product->refresh()->load(['images', 'variants.images', 'variants.attributeValues']));

        return $product;
    }

    protected function addVariants(ImportBatch $batch, ImportRow $row): Product
    {
        $product = Product::query()->find($row->matched_product_id);
        if (! $product) {
            throw ValidationException::withMessages(['product' => 'Choose a matched product before adding variants.']);
        }

        $payload = $row->payload ?? [];
        $warnings = $row->warnings ?? [];
        $this->mergeVariants($product, $payload, $warnings, true);
        $product->update([
            'import_batch_id' => $product->import_batch_id ?? $batch->id,
        ]);

        return $product->refresh();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<string>  $warnings
     */
    protected function mergeVariants(Product $product, array $payload, array &$warnings, bool $addOnly): void
    {
        $product->loadMissing('variants');
        $incoming = $this->variantPayloads($payload, $warnings);

        foreach ($incoming as $data) {
            $existing = $product->variants->first(function (ProductVariant $variant) use ($data) {
                if (! empty($data['barcode']) && (string) $variant->barcode === (string) $data['barcode']) {
                    return true;
                }

                return mb_strtolower(trim((string) $variant->name)) === mb_strtolower(trim((string) ($data['name'] ?? '')));
            });

            if ($existing && $addOnly) {
                continue;
            }

            if ($existing) {
                $update = [];
                foreach (['barcode', 'price', 'compare_at_price', 'cost_price', 'name_ar'] as $field) {
                    if (array_key_exists($field, $data) && $data[$field] !== null && $data[$field] !== '') {
                        $update[$field] = $data[$field];
                    }
                }
                if (array_key_exists('stock_quantity', $data) && $data['stock_quantity'] !== null) {
                    $update['stock_quantity'] = $data['stock_quantity'];
                }
                if (! empty($data['barcode'])) {
                    $this->barcodes->assertUnique((string) $data['barcode'], null, $existing->id);
                }
                if ($update !== []) {
                    $existing->update($update);
                }
                if (! empty($data['attribute_value_ids'])) {
                    $sync = [];
                    foreach ($data['attribute_value_ids'] as $attributeId => $valueId) {
                        $sync[(int) $valueId] = ['attribute_id' => (int) $attributeId];
                    }
                    $existing->attributeValues()->sync($sync);
                }

                continue;
            }

            if (! empty($data['barcode'])) {
                $this->barcodes->assertUnique((string) $data['barcode']);
            }
            if (empty($data['sku'])) {
                $data['sku'] = $this->barcodes->generateVariantSku($product->sku, $product->variants->count() + 1);
            }
            $created = ProductVariant::query()->create([
                'product_id' => $product->id,
                'name' => $data['name'] ?? null,
                'name_ar' => $data['name_ar'] ?? null,
                'sku' => $data['sku'],
                'barcode' => $data['barcode'] ?? null,
                'price' => $data['price'] ?? null,
                'compare_at_price' => $data['compare_at_price'] ?? null,
                'cost_price' => $data['cost_price'] ?? null,
                'stock_quantity' => $data['stock_quantity'] ?? null,
                'is_active' => $data['is_active'] ?? true,
                'sort_order' => $product->variants->count(),
                'is_default' => $product->variants->isEmpty(),
            ]);
            if (! empty($data['attribute_value_ids'])) {
                $sync = [];
                foreach ($data['attribute_value_ids'] as $attributeId => $valueId) {
                    $sync[(int) $valueId] = ['attribute_id' => (int) $attributeId];
                }
                $created->attributeValues()->sync($sync);
            }
        }

        $product->update(['has_variants' => $product->variants()->exists(), 'barcode' => null, 'stock_quantity' => 0]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<string>  $warnings
     * @return list<array<string, mixed>>
     */
    protected function variantPayloads(array $payload, array &$warnings): array
    {
        $rows = [];
        $defaultAssigned = false;
        foreach ($payload['variants'] ?? [] as $index => $variant) {
            $showFirst = ! empty($variant['show_first']) && ! $defaultAssigned;
            if ($showFirst) {
                $defaultAssigned = true;
            }
            $rows[] = [
                'name' => $variant['option_name'] ?? null,
                'name_ar' => $variant['name_ar'] ?? null,
                'sku' => $this->usableSku($variant['sku'] ?? null),
                'barcode' => $variant['barcode'] ?? null,
                'price' => $variant['price'] ?? null,
                'compare_at_price' => $variant['compare_at_price'] ?? null,
                'cost_price' => $variant['cost'] ?? null,
                'stock_quantity' => $variant['stock'] ?? null,
                'is_active' => $variant['is_active'] ?? true,
                'is_default' => $showFirst,
                'attribute_value_ids' => $this->attributeValues($variant, $payload['variant_type'] ?? null, $warnings),
            ];
            unset($index);
        }

        if ($rows !== [] && ! $defaultAssigned) {
            $rows[0]['is_default'] = true;
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $variant
     * @param  list<string>  $warnings
     * @return array<int, int>
     */
    protected function attributeValues(array $variant, ?string $variantType, array &$warnings): array
    {
        $map = [];
        $pairs = [
            'size' => $variant['size'] ?? null,
            'shade' => $variant['shade'] ?? null,
            'scent' => $variant['scent'] ?? null,
        ];

        if ($variantType && $variantType !== 'option' && empty($pairs[$variantType]) && ! empty($variant['option_name'])) {
            $pairs[$variantType] = $variant['option_name'];
        }

        foreach ($pairs as $slug => $label) {
            if (! is_string($label) || trim($label) === '') {
                continue;
            }
            $attribute = Attribute::query()->where('slug', $slug)->first();
            if (! $attribute) {
                $warnings[] = 'The '.$slug.' attribute is not configured, so "'.$label.'" was kept as the option name only.';

                continue;
            }

            $existing = AttributeValue::query()
                ->where('attribute_id', $attribute->id)
                ->whereRaw('LOWER(value) = ?', [mb_strtolower(trim($label))])
                ->first();

            if (! $existing) {
                $slugValue = Str::slug($label) ?: 'option';
                $base = $slugValue;
                $suffix = 2;
                while (AttributeValue::query()->where('attribute_id', $attribute->id)->where('slug', $slugValue)->exists()) {
                    $slugValue = $base.'-'.$suffix;
                    $suffix++;
                }
                $existing = AttributeValue::query()->create([
                    'attribute_id' => $attribute->id,
                    'value' => trim($label),
                    'slug' => $slugValue,
                ]);
                $warnings[] = 'AI-suggested '.$slug.' value "'.$label.'" was added and should be reviewed.';
            }

            $map[$attribute->id] = $existing->id;
        }

        return $map;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<string>  $warnings
     * @return array{0: ?int, 1: list<int>, 2: ?string}
     */
    protected function resolveRelations(array $payload, array &$warnings): array
    {
        $brandId = null;
        if (! empty($payload['brand'])) {
            $brand = $this->findOrCreateBrand((string) $payload['brand'], $warnings);
            $brandId = $brand->id;
        }

        $categoryIds = [];
        foreach ($payload['categories'] ?? [] as $name) {
            if (! is_string($name) || trim($name) === '') {
                continue;
            }
            $category = $this->findExistingCategory($name, $warnings);
            if ($category) {
                $categoryIds[] = $category->id;
            }
        }

        $unit = $payload['unit'] ?? null;
        if (is_string($unit) && $unit !== '' && ! in_array(strtolower($unit), Attribute::measureUnits(), true)) {
            $warnings[] = 'Unit "'.$unit.'" is not one of the configured measure units and was left blank.';
            $unit = null;
        }

        return [$brandId, array_values(array_unique($categoryIds)), $unit ? strtolower($unit) : null];
    }

    /**
     * @param  array<int|string, array<string, mixed>>  $variants
     */
    protected function syncEditorVariants(Product $product, array $variants): void
    {
        $payload = [];
        foreach (array_values($variants) as $variant) {
            if (! empty($variant['remove'])) {
                continue;
            }
            if (trim((string) ($variant['name'] ?? '')) === '' && empty($variant['id'])) {
                continue;
            }
            $payload[] = [
                'id' => $variant['id'] ?? null,
                'name' => $this->nullableString($variant['name'] ?? null),
                'name_ar' => $this->nullableString($variant['name_ar'] ?? null),
                'barcode' => $this->nullableString($variant['barcode'] ?? null),
                'price' => $this->nullableMoney($variant['price'] ?? null),
                'cost_price' => $this->nullableMoney($variant['cost_price'] ?? null),
                'compare_at_price' => $this->nullableMoney($variant['compare_at_price'] ?? null),
                'stock_quantity' => $this->nullableStock($variant['stock_quantity'] ?? null),
                'is_active' => ! empty($variant['is_active']),
                'is_default' => ! empty($variant['is_default']),
            ];
        }

        foreach ($payload as $variant) {
            $selling = $variant['price'] ?? $product->price;
            $this->assertCompare($variant['compare_at_price'], $selling);
        }

        $this->variants->syncVariants($product, $payload);
    }

    protected function finish(ImportBatch $batch): void
    {
        $batch->refresh();
        $success = $batch->rows()->where('status', ImportRowStatus::Committed)->count();
        $failed = $batch->rows()->whereIn('status', [ImportRowStatus::Failed, ImportRowStatus::Invalid])->count();
        $skipped = $batch->rows()->where('status', ImportRowStatus::Skipped)->count();
        $pending = $batch->rows()->where('status', ImportRowStatus::Pending)->count();

        $status = match (true) {
            $failed > 0 => ImportBatchStatus::Partial,
            $pending > 0 => ImportBatchStatus::Partial,
            default => ImportBatchStatus::Completed,
        };

        $batch->update([
            'status' => $status,
            'success_count' => $success,
            'failure_count' => $failed,
            'skipped_count' => $skipped,
            'finished_at' => now(),
        ]);

        $this->audit->log('product_import.committed', $batch, null, [
            'success' => $success,
            'failed' => $failed,
            'skipped' => $skipped,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function decodeJson(string $contents): array
    {
        $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents) ?? $contents;
        if (! str_starts_with(ltrim($contents), '{')) {
            throw new \InvalidArgumentException('The JSON file must be an object with a products array.');
        }

        $contents = $this->escapeControlCharactersInJsonStrings($contents);

        try {
            return $this->decodeJsonString($contents);
        } catch (\JsonException $exception) {
            $repaired = $this->repairTruncatedJson($contents);
            if ($repaired !== null) {
                $decoded = $this->decodeJsonString($repaired);
                $source = is_array($decoded['source'] ?? null) ? $decoded['source'] : [];
                $note = 'The file ended before the JSON was finished. Only complete products and options were kept. Download the file again if products are missing.';
                $source['notes'] = trim(($source['notes'] ?? '').' '.$note);
                $decoded['source'] = $source;

                return $decoded;
            }

            throw new \InvalidArgumentException('The JSON file could not be read. It looks incomplete or contains a broken character. '.$exception->getMessage(), 0, $exception);
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function decodeJsonString(string $contents): array
    {
        $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($decoded)) {
            throw new \InvalidArgumentException('The JSON file must contain an object.');
        }

        return $decoded;
    }

    protected function escapeControlCharactersInJsonStrings(string $json): string
    {
        $out = '';
        $inString = false;
        $escaped = false;
        $length = strlen($json);

        for ($i = 0; $i < $length; $i++) {
            $char = $json[$i];
            if ($inString) {
                if ($escaped) {
                    $out .= $char;
                    $escaped = false;

                    continue;
                }
                if ($char === '\\') {
                    $out .= $char;
                    $escaped = true;

                    continue;
                }
                if ($char === '"') {
                    $inString = false;
                    $out .= $char;

                    continue;
                }
                $code = ord($char);
                if ($code < 32) {
                    $out .= match ($char) {
                        "\n" => '\\n',
                        "\r" => '\\r',
                        "\t" => '\\t',
                        default => sprintf('\\u%04x', $code),
                    };

                    continue;
                }
            } elseif ($char === '"') {
                $inString = true;
            }

            $out .= $char;
        }

        return $out;
    }

    protected function repairTruncatedJson(string $json): ?string
    {
        $length = strlen($json);
        $attempts = 0;

        for ($pos = $length - 1; $pos > 0 && $attempts < 40; $pos--) {
            if ($json[$pos] !== '}' && $json[$pos] !== ']') {
                continue;
            }
            $attempts++;
            $closed = $this->closeJson(substr($json, 0, $pos + 1));
            if ($closed === null) {
                continue;
            }
            try {
                $decoded = $this->decodeJsonString($closed);
            } catch (\JsonException|\InvalidArgumentException) {
                continue;
            }
            if (isset($decoded['products']) && is_array($decoded['products']) && $decoded['products'] !== []) {
                return $closed;
            }
        }

        return null;
    }

    protected function closeJson(string $json): ?string
    {
        $stack = [];
        $inString = false;
        $escaped = false;
        $length = strlen($json);

        for ($i = 0; $i < $length; $i++) {
            $char = $json[$i];
            if ($inString) {
                if ($escaped) {
                    $escaped = false;

                    continue;
                }
                if ($char === '\\') {
                    $escaped = true;

                    continue;
                }
                if ($char === '"') {
                    $inString = false;
                }

                continue;
            }

            if ($char === '"') {
                $inString = true;

                continue;
            }
            if ($char === '{' || $char === '[') {
                $stack[] = $char;

                continue;
            }
            if ($char === '}' || $char === ']') {
                $open = array_pop($stack);
                $expected = $char === '}' ? '{' : '[';
                if ($open !== $expected) {
                    return null;
                }
            }
        }

        if ($inString || $stack === []) {
            return null;
        }

        $closers = '';
        for ($i = count($stack) - 1; $i >= 0; $i--) {
            $closers .= $stack[$i] === '{' ? '}' : ']';
        }

        return $json.$closers;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<string>
     */
    protected function barcodesIn(array $payload): array
    {
        $codes = [];
        if (! empty($payload['barcode'])) {
            $codes[] = (string) $payload['barcode'];
        }
        foreach ($payload['variants'] ?? [] as $variant) {
            if (! empty($variant['barcode'])) {
                $codes[] = (string) $variant['barcode'];
            }
        }

        return $codes;
    }

    /**
     * @return array<string, mixed>
     */
    protected function invalidRow(int $index, string $error): array
    {
        return [
            'row_index' => $index,
            'external_key' => null,
            'name' => 'Row '.($index + 1),
            'payload' => [],
            'errors' => [$error],
            'warnings' => [],
            'missing_fields' => ['name'],
            'match_type' => 'none',
            'matched_product_id' => null,
            'resolution' => ImportResolution::Skip->value,
            'status' => ImportRowStatus::Invalid->value,
            'duplicate' => false,
        ];
    }

    protected function usableSku(mixed $sku): ?string
    {
        if (! is_string($sku) || trim($sku) === '') {
            return null;
        }

        return $this->barcodes->skuExists(trim($sku)) ? null : trim($sku);
    }

    protected function findOrCreateBrand(string $name, array &$warnings): Brand
    {
        $label = trim($name);
        $needle = mb_strtolower($label);
        $brand = Brand::query()
            ->where(function ($query) use ($needle) {
                $query->whereRaw('LOWER(name) = ?', [$needle])
                    ->orWhereRaw('LOWER(name_ar) = ?', [$needle]);
            })
            ->first();

        if ($brand) {
            if (! $brand->is_active) {
                $warnings[] = 'Brand "'.$brand->name.'" is inactive.';
            }

            return $brand;
        }

        $brand = Brand::query()->create([
            'name' => $label,
            'slug' => $this->uniqueRelationSlug(Brand::class, $label, 'brand'),
            'is_active' => true,
        ]);
        $warnings[] = 'Brand "'.$brand->name.'" was not in the brand list, so it was created.';

        return $brand;
    }

    protected function findExistingCategory(string $name, array &$warnings): ?Category
    {
        $label = trim($name);
        if ($label === '') {
            return null;
        }

        $needle = mb_strtolower($label);
        $slug = Str::slug($label);
        $category = Category::query()
            ->where(function ($query) use ($needle, $slug) {
                $query->whereRaw('LOWER(name) = ?', [$needle])
                    ->orWhereRaw('LOWER(name_ar) = ?', [$needle])
                    ->orWhereRaw('LOWER(slug) = ?', [$needle]);
                if ($slug !== '' && $slug !== $needle) {
                    $query->orWhere('slug', $slug);
                }
            })
            ->first();

        if ($category) {
            return $category;
        }

        $warnings[] = 'Category "'.$label.'" is not in your category list, so it was skipped.';

        return null;
    }

    /**
     * @param  class-string<Brand|Category>  $model
     */
    protected function uniqueRelationSlug(string $model, string $name, string $fallback): string
    {
        $base = Str::slug($name);
        if ($base === '') {
            $base = $fallback;
        }

        $slug = $base;
        $suffix = 2;
        while ($model::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    protected function uniqueSlug(?string $requested, string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($requested ?: $name);
        if ($base === '') {
            $base = 'product';
        }
        $slug = $base;
        $guard = 0;
        while (Product::withTrashed()->where('slug', $slug)->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base.'-'.Str::lower(Str::random(4));
            $guard++;
            if ($guard > 8) {
                $slug = $base.'-'.Str::lower(Str::random(8));
                break;
            }
        }

        return $slug;
    }

    protected function missing($query, array $codes)
    {
        return $query->where(function ($builder) use ($codes) {
            foreach ($codes as $code) {
                $builder->orWhereJsonContains('readiness_issues', $code);
            }
        });
    }

    protected function nullableString(mixed $value): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    protected function nullableMoney(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (! is_numeric($value) || (float) $value < 0) {
            throw ValidationException::withMessages(['price' => 'Prices and costs must be zero or greater.']);
        }

        return number_format((float) $value, 2, '.', '');
    }

    protected function nullableStock(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (! is_numeric($value) || (float) $value < 0 || ((float) $value) !== floor((float) $value)) {
            throw ValidationException::withMessages(['stock_quantity' => 'Stock must be a whole number. Zero is allowed.']);
        }

        return (int) $value;
    }

    protected function assertCompare(mixed $compareAt, mixed $price): void
    {
        if ($compareAt === null || $compareAt === '') {
            return;
        }
        if (! $this->readiness->compareAtValid($compareAt, $price)) {
            throw ValidationException::withMessages([
                'compare_at_price' => 'Compare-at price must be greater than the selling price.',
            ]);
        }
    }
}
