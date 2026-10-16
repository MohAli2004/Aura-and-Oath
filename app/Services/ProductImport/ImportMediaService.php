<?php

namespace App\Services\ProductImport;

use App\Models\ImportBatch;
use App\Models\ImportMedia;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\ImageService;
use App\Services\ProductReadinessService;
use Illuminate\Http\UploadedFile;
use ZipArchive;

class ImportMediaService
{
    public function __construct(
        protected ImageService $images,
        protected ProductReadinessService $readiness,
    ) {}

    /**
     * @param  list<UploadedFile>  $files
     * @return list<ImportMedia>
     */
    public function storeUploads(?ImportBatch $batch, User $user, array $files, ?UploadedFile $zip = null): array
    {
        $stored = [];

        foreach ($files as $file) {
            if ($file instanceof UploadedFile) {
                $stored[] = $this->storeFile($batch, $user, $file);
            }
        }

        if ($zip instanceof UploadedFile) {
            foreach ($this->extractZip($zip) as $file) {
                $stored[] = $this->storeFile($batch, $user, $file);
            }
        }

        return $stored;
    }

    public function keepImage(UploadedFile $file): string
    {
        return $this->images->store($file, 'products');
    }

    public function deleteStored(?string $path): void
    {
        $this->images->delete($path);
    }

    public function assign(ImportMedia $media, Product $product, ?ProductVariant $variant = null): void
    {
        $this->link($media, $product, $variant, 'assigned', 'Assigned by an administrator.');
    }

    public function attachVerified(ImportBatch $batch, Product $product): void
    {
        $product->loadMissing('variants');
        $codes = $this->codesFor($product);

        $files = ImportMedia::query()
            ->where('import_batch_id', $batch->id)
            ->whereNull('product_id')
            ->get();

        foreach ($files as $file) {
            $code = $this->matchingCode($file->original_filename, $codes);
            if ($code === null) {
                continue;
            }

            $variant = $product->variants->first(fn (ProductVariant $item) => (string) $item->barcode === $code);
            $this->link(
                $file,
                $product,
                $variant,
                'verified',
                'Filename matched verified barcode '.$code.'.'
            );
        }
    }

    protected function storeFile(?ImportBatch $batch, User $user, UploadedFile $file): ImportMedia
    {
        $safeName = basename(str_replace('\\', '/', $file->getClientOriginalName()));
        $path = $this->images->store($file, 'products');

        $media = ImportMedia::query()->create([
            'import_batch_id' => $batch?->id,
            'user_id' => $user->id,
            'original_filename' => $safeName,
            'stored_path' => $path,
            'match_status' => 'unmatched',
        ]);

        $this->match($media, $batch);

        return $media->fresh() ?? $media;
    }

    protected function match(ImportMedia $media, ?ImportBatch $batch): void
    {
        $codes = $this->knownBarcodes($batch);
        $code = $this->matchingCode($media->original_filename, $codes);

        if ($code !== null) {
            $product = Product::query()->where('barcode', $code)->first();
            $variant = null;
            if (! $product) {
                $variant = ProductVariant::query()->where('barcode', $code)->first();
                $product = $variant?->product;
            } else {
                $variant = null;
            }

            if ($product) {
                $this->link($media, $product, $variant, 'verified', 'Filename matched verified barcode '.$code.'.');

                return;
            }

            $media->update([
                'match_status' => 'verified',
                'note' => 'Filename contains barcode '.$code.'. It will attach when that product is imported.',
            ]);

            return;
        }

        $suggestion = $this->uncertainProduct($media->original_filename, $batch);
        if ($suggestion !== null) {
            $media->update([
                'match_status' => 'uncertain',
                'suggested_product_id' => $suggestion['product_id'],
                'note' => $suggestion['note'],
            ]);
        }
    }

    protected function link(ImportMedia $media, Product $product, ?ProductVariant $variant, string $status, string $note): void
    {
        $exists = ProductImage::query()
            ->where('product_id', $product->id)
            ->where('path', $media->stored_path)
            ->exists();

        if (! $exists) {
            $sort = (int) ProductImage::query()
                ->where('product_id', $product->id)
                ->when(
                    $variant,
                    fn ($query) => $query->where('product_variant_id', $variant->id),
                    fn ($query) => $query->whereNull('product_variant_id'),
                )
                ->max('sort_order');

            $isFirst = ! ProductImage::query()
                ->where('product_id', $product->id)
                ->when(
                    $variant,
                    fn ($query) => $query->where('product_variant_id', $variant->id),
                    fn ($query) => $query->whereNull('product_variant_id'),
                )
                ->exists();

            ProductImage::query()->create([
                'product_id' => $product->id,
                'product_variant_id' => $variant?->id,
                'path' => $media->stored_path,
                'alt' => $variant?->name ?: $product->name,
                'sort_order' => $isFirst ? 0 : $sort + 1,
                'is_primary' => $isFirst,
            ]);
        }

        if ($variant) {
            $variant->update(['image_path' => $media->stored_path]);
        }

        $media->update([
            'product_id' => $product->id,
            'product_variant_id' => $variant?->id,
            'match_status' => $status,
            'note' => $note,
        ]);

        $product->refresh()->load(['images', 'variants.images', 'variants.attributeValues']);
        $this->readiness->remember($product);
    }

    /**
     * @return list<string>
     */
    protected function knownBarcodes(?ImportBatch $batch): array
    {
        $codes = Product::query()->whereNotNull('barcode')->pluck('barcode')
            ->merge(ProductVariant::query()->whereNotNull('barcode')->pluck('barcode'))
            ->all();

        if ($batch) {
            foreach ($batch->rows as $row) {
                $payload = $row->payload ?? [];
                if (! empty($payload['barcode'])) {
                    $codes[] = (string) $payload['barcode'];
                }
                foreach ($payload['variants'] ?? [] as $variant) {
                    if (! empty($variant['barcode'])) {
                        $codes[] = (string) $variant['barcode'];
                    }
                }
            }
        }

        return array_values(array_unique(array_map('strval', $codes)));
    }

    /**
     * @return list<string>
     */
    protected function codesFor(Product $product): array
    {
        $codes = [];
        if ($product->barcode) {
            $codes[] = (string) $product->barcode;
        }
        foreach ($product->variants as $variant) {
            if ($variant->barcode) {
                $codes[] = (string) $variant->barcode;
            }
        }

        return $codes;
    }

    /**
     * @param  list<string>  $codes
     */
    protected function matchingCode(string $filename, array $codes): ?string
    {
        $tokens = preg_split('/[^A-Za-z0-9]+/', pathinfo($filename, PATHINFO_FILENAME)) ?: [];
        $tokens[] = pathinfo($filename, PATHINFO_FILENAME);

        foreach ($tokens as $token) {
            $token = trim((string) $token);
            if ($token === '' || strlen($token) < 4) {
                continue;
            }
            foreach ($codes as $code) {
                if (strlen($code) === strlen($token) && hash_equals($code, $token)) {
                    return $code;
                }
            }
        }

        return null;
    }

    /**
     * @return array{product_id: ?int, note: string}|null
     */
    protected function uncertainProduct(string $filename, ?ImportBatch $batch): ?array
    {
        if (! $batch) {
            return null;
        }

        $haystack = mb_strtolower(pathinfo($filename, PATHINFO_FILENAME));
        if (mb_strlen($haystack) < 8) {
            return null;
        }

        foreach ($batch->rows as $row) {
            $payload = $row->payload ?? [];
            $name = mb_strtolower(trim((string) ($payload['name'] ?? '')));
            $brand = mb_strtolower(trim((string) ($payload['brand'] ?? '')));
            if ($name === '' || $brand === '' || mb_strlen($name) < 4) {
                continue;
            }
            if (str_contains($haystack, $name) && str_contains($haystack, $brand)) {
                return [
                    'product_id' => $row->product_id,
                    'note' => 'Possible name match for "'.$payload['name'].'". Not attached automatically.',
                ];
            }
        }

        return null;
    }

    /**
     * @return list<UploadedFile>
     */
    protected function extractZip(UploadedFile $zipFile): array
    {
        $zip = new ZipArchive();
        if ($zip->open($zipFile->getRealPath()) !== true) {
            throw new \InvalidArgumentException('The ZIP archive could not be opened.');
        }

        if ($zip->numFiles > 200) {
            $zip->close();
            throw new \InvalidArgumentException('The ZIP archive contains too many files.');
        }

        $total = 0;
        $entries = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            $name = (string) ($stat['name'] ?? '');
            if ($name === '' || str_ends_with($name, '/') || str_contains($name, '..') || str_starts_with($name, '/')) {
                continue;
            }
            $size = (int) ($stat['size'] ?? 0);
            $total += $size;
            if ($size > 8 * 1024 * 1024 || $total > 100 * 1024 * 1024) {
                $zip->close();
                throw new \InvalidArgumentException('The ZIP archive is too large.');
            }
            $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (! in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'avif', 'ico'], true)) {
                continue;
            }
            $entries[] = $name;
        }

        $directory = storage_path('app/private/imports/unzipped/'.bin2hex(random_bytes(8)));
        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            $zip->close();
            throw new \RuntimeException('Could not prepare a directory for the ZIP archive.');
        }

        $files = [];
        foreach ($entries as $name) {
            $contents = $zip->getFromName($name);
            if (! is_string($contents) || $contents === '') {
                continue;
            }
            $target = $directory.'/'.bin2hex(random_bytes(6)).'-'.basename($name);
            file_put_contents($target, $contents);
            $files[] = new UploadedFile($target, basename($name), null, null, true);
        }
        $zip->close();

        return $files;
    }
}
