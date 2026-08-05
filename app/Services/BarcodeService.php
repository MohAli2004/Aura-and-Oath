<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BarcodeService
{
    public function lookup(string $barcode): ?array
    {
        $barcode = trim($barcode);

        if ($barcode === '') {
            return null;
        }

        $variant = ProductVariant::query()
            ->with('product')
            ->where('barcode', $barcode)
            ->first();

        if ($variant) {
            return [
                'type' => 'variant',
                'barcode' => $barcode,
                'product' => $variant->product,
                'variant' => $variant,
                'sku' => $variant->sku,
                'name' => $variant->product->name.' — '.$variant->displayName(),
                'available_stock' => $variant->availableStock(),
                'stock_quantity' => $variant->stock_quantity,
                'reserved_quantity' => $variant->reserved_quantity,
            ];
        }

        $product = Product::query()
            ->where('barcode', $barcode)
            ->first();

        if ($product) {
            return [
                'type' => 'product',
                'barcode' => $barcode,
                'product' => $product,
                'variant' => null,
                'sku' => $product->sku,
                'name' => $product->name,
                'available_stock' => $product->availableStock(),
                'stock_quantity' => $product->stock_quantity,
                'reserved_quantity' => $product->reserved_quantity,
            ];
        }

        return null;
    }

    public function assertUnique(string $barcode, ?int $ignoreProductId = null, ?int $ignoreVariantId = null): void
    {
        $barcode = trim($barcode);

        $productExists = Product::query()
            ->where('barcode', $barcode)
            ->when($ignoreProductId, fn ($q) => $q->where('id', '!=', $ignoreProductId))
            ->exists();

        $variantExists = ProductVariant::query()
            ->where('barcode', $barcode)
            ->when($ignoreVariantId, fn ($q) => $q->where('id', '!=', $ignoreVariantId))
            ->exists();

        if ($productExists || $variantExists) {
            throw ValidationException::withMessages([
                'barcode' => 'This barcode is already in use.',
            ]);
        }
    }

    public function assertSkuUnique(string $sku, ?int $ignoreProductId = null, ?int $ignoreVariantId = null): void
    {
        if ($this->skuExists($sku, $ignoreProductId, $ignoreVariantId)) {
            throw ValidationException::withMessages([
                'sku' => 'This SKU is already in use.',
            ]);
        }
    }

    public function detectFormat(string $barcode): string
    {
        $barcode = preg_replace('/\s+/', '', $barcode) ?? '';
        $len = strlen($barcode);

        if (ctype_digit($barcode)) {
            return match ($len) {
                8 => 'EAN-8',
                12 => 'UPC-A',
                13 => 'EAN-13',
                14 => 'ITF-14',
                default => 'Numeric',
            };
        }

        return 'Code128/Alphanumeric';
    }

    public function generateInternal(string $prefix = 'AO'): string
    {
        do {
            $code = $prefix.now()->format('ymd').str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        } while ($this->lookup($code) !== null);

        return $code;
    }

    public function generateSku(string $prefix = 'AO'): string
    {
        do {
            $sku = $prefix.'-'.strtoupper(Str::random(4)).'-'.str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        } while ($this->skuExists($sku));

        return $sku;
    }

    public function generateVariantSku(string $productSku, int $index = 1): string
    {
        $base = trim($productSku) !== '' ? $productSku : $this->generateSku();
        $attempt = 0;

        do {
            $suffix = $attempt === 0
                ? 'V'.$index
                : 'V'.$index.'-'.strtoupper(Str::random(3));
            $sku = $base.'-'.$suffix;
            $attempt++;
        } while ($this->skuExists($sku));

        return $sku;
    }

    public function skuExists(string $sku, ?int $ignoreProductId = null, ?int $ignoreVariantId = null): bool
    {
        $sku = trim($sku);

        if ($sku === '') {
            return false;
        }

        $productExists = Product::query()
            ->where('sku', $sku)
            ->when($ignoreProductId, fn ($q) => $q->where('id', '!=', $ignoreProductId))
            ->exists();

        $variantExists = ProductVariant::query()
            ->where('sku', $sku)
            ->when($ignoreVariantId, fn ($q) => $q->where('id', '!=', $ignoreVariantId))
            ->exists();

        return $productExists || $variantExists;
    }
}
