<?php

namespace App\Services\ProductImport;

use App\Enums\ImportResolution;
use App\Enums\ProductStatus;
use App\Models\ImportRow;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Str;

class ProductMatcher
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  list<string>  $warnings
     * @return array{match_type: string, matched_product_id: ?int, resolution: string, warnings: list<string>}
     */
    public function match(array $payload, array $warnings = []): array
    {
        $barcodeHit = $this->matchBarcode($payload);
        if ($barcodeHit !== null) {
            return $this->result('barcode', $barcodeHit, $warnings, 'Exact barcode match. An active product is not changed unless you choose Update.');
        }

        $sku = trim((string) ($payload['sku'] ?? ''));
        if ($sku !== '') {
            $product = Product::query()->where('sku', $sku)->first();
            if ($product) {
                return $this->result('sku', $product, $warnings, 'Matched an existing SKU.');
            }

            $variant = ProductVariant::query()->where('sku', $sku)->first();
            if ($variant) {
                return $this->result('sku', $variant->product, $warnings, 'Matched an existing variant SKU.');
            }
        }

        $catalogueId = $payload['catalogue_product_id'] ?? null;
        if (is_int($catalogueId)) {
            $product = Product::query()->find($catalogueId);
            if ($product) {
                return $this->result('catalogue', $product, $warnings, 'Matched the catalogue id from a previous export.');
            }
        }

        $external = trim((string) ($payload['external_id'] ?? ''));
        if ($external !== '' && ! ctype_digit($external)) {
            $previous = ImportRow::query()
                ->where('external_key', $external)
                ->where('status', 'committed')
                ->whereNotNull('product_id')
                ->latest('id')
                ->first();

            if ($previous?->product) {
                return $this->result('external_id', $previous->product, $warnings, 'Matched a previous import with the same external id.');
            }
        }

        $suggestion = $this->suggest($payload);
        if ($suggestion) {
            $warnings[] = 'Brand + name + size look similar to an existing product. This is a suggestion, not proof it is the same item.';
            $overlap = $this->variantOverlap($payload, $suggestion);
            if ($overlap) {
                $warnings[] = 'Some variant names overlap with the suggested product.';
            }

            return [
                'match_type' => 'suggestion',
                'matched_product_id' => $suggestion->id,
                'resolution' => ImportResolution::Review->value,
                'warnings' => array_values(array_unique($warnings)),
            ];
        }

        return [
            'match_type' => 'none',
            'matched_product_id' => null,
            'resolution' => ImportResolution::Create->value,
            'warnings' => array_values(array_unique($warnings)),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function identityKey(array $payload): string
    {
        $brand = $this->normalize($payload['brand'] ?? '');
        $name = $this->normalize($payload['name'] ?? '');
        $size = $this->normalize(trim(((string) ($payload['size'] ?? '')).' '.((string) ($payload['unit'] ?? ''))));

        return $brand.'|'.$name.'|'.$size;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, Product>
     */
    public function productsForBarcodes(array $payload): array
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

        $found = [];
        foreach (array_unique($codes) as $code) {
            $product = Product::query()->where('barcode', $code)->first();
            if ($product) {
                $found[$product->id] = $product;

                continue;
            }

            $variant = ProductVariant::query()->where('barcode', $code)->first();
            if ($variant?->product) {
                $found[$variant->product->id] = $variant->product;
            }
        }

        return $found;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function findByBarcode(array $payload): ?Product
    {
        $found = $this->productsForBarcodes($payload);

        return count($found) === 1 ? array_values($found)[0] : null;
    }

    protected function matchBarcode(array $payload): ?Product
    {
        return $this->findByBarcode($payload);
    }

    /**
     * @param  list<string>  $warnings
     * @return array{match_type: string, matched_product_id: int, resolution: string, warnings: list<string>}
     */
    protected function result(string $type, Product $product, array $warnings, string $message): array
    {
        $warnings[] = $message;
        if ($product->status === ProductStatus::Active) {
            $warnings[] = 'This match is an active catalogue product. It will not be overwritten unless you explicitly choose Update.';
        }

        return [
            'match_type' => $type,
            'matched_product_id' => $product->id,
            'resolution' => ImportResolution::Review->value,
            'warnings' => array_values(array_unique($warnings)),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function suggest(array $payload): ?Product
    {
        $name = $this->normalize($payload['name'] ?? '');
        if ($name === '') {
            return null;
        }

        $brand = $this->normalize($payload['brand'] ?? '');
        $size = $this->normalize(trim(((string) ($payload['size'] ?? '')).' '.((string) ($payload['unit'] ?? ''))));

        $candidates = Product::query()
            ->with('brand')
            ->whereRaw('LOWER(name) = ?', [$name])
            ->limit(20)
            ->get();

        foreach ($candidates as $candidate) {
            $candidateBrand = $this->normalize($candidate->brand?->name ?? '');
            if ($brand !== '' && $candidateBrand !== '' && $brand !== $candidateBrand) {
                continue;
            }

            $candidateSize = $this->normalize(trim(((string) $candidate->size).' '.((string) $candidate->unit)));
            if ($size !== '' && $candidateSize !== '' && $size !== $candidateSize) {
                continue;
            }

            return $candidate;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function variantOverlap(array $payload, Product $product): bool
    {
        $incoming = [];
        foreach ($payload['variants'] ?? [] as $variant) {
            $name = $this->normalize($variant['option_name'] ?? '');
            if ($name !== '') {
                $incoming[$name] = true;
            }
        }

        if ($incoming === []) {
            return false;
        }

        $product->loadMissing('variants');
        foreach ($product->variants as $variant) {
            if (isset($incoming[$this->normalize($variant->name ?? '')])) {
                return true;
            }
        }

        return false;
    }

    protected function normalize(mixed $value): string
    {
        $value = Str::of((string) $value)->lower()->squish()->toString();

        return $value;
    }
}
