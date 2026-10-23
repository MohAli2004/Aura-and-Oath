<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductReadinessService
{
    /**
     * Issue codes that block publication. Compare-at price is optional and is
     * only listed when a provided value breaks the strikethrough rule.
     *
     * @var array<string, string>
     */
    public const LABELS = [
        'name' => 'Product name',
        'content_approval' => 'Content approval',
        'barcode' => 'Barcode',
        'price' => 'Selling price',
        'cost' => 'Cost',
        'stock' => 'Stock quantity',
        'image' => 'Product image',
        'compare_at_price' => 'Compare-at price',
        'enabled_variant' => 'At least one purchasable variant',
        'variant_barcode' => 'Variant barcode',
        'variant_price' => 'Variant selling price',
        'variant_cost' => 'Variant cost',
        'variant_stock' => 'Variant stock',
        'variant_image' => 'Variant image',
        'variant_option' => 'Variant option',
        'variant_compare_at_price' => 'Variant compare-at price',
        'barcode_duplicate' => 'Unique barcode',
    ];

    /**
     * @return list<string>
     */
    public function issues(Product $product): array
    {
        $product->loadMissing(['images', 'variants.images', 'variants.attributeValues']);

        $issues = [];

        if (! $this->filledText($product->name)) {
            $issues[] = 'name';
        }

        $enabled = $product->variants->filter(fn (ProductVariant $variant) => (bool) $variant->is_active)->values();
        $usesVariants = (bool) $product->has_variants || $enabled->isNotEmpty();

        if ($usesVariants) {
            if ($enabled->isEmpty()) {
                $issues[] = 'enabled_variant';
            }

            $sharedImage = $product->images->isNotEmpty();
            $anyVariantImage = false;

            foreach ($enabled as $variant) {
                if (! $this->barcodeValid($variant->barcode)) {
                    $issues[] = 'variant_barcode';
                } elseif ($this->barcodeTaken($variant->barcode, null, $variant->id)) {
                    $issues[] = 'barcode_duplicate';
                }

                if (! $this->moneyValid($variant->price ?? $product->price)) {
                    $issues[] = 'variant_price';
                }

                if (! $this->moneyValid($variant->cost_price ?? $product->cost_price)) {
                    $issues[] = 'variant_cost';
                }

                if (! $this->stockValid($variant->stock_quantity)) {
                    $issues[] = 'variant_stock';
                }

                if (! $this->filledText($variant->name) && $variant->attributeValues->isEmpty()) {
                    $issues[] = 'variant_option';
                }

                $hasImage = $this->variantHasImage($variant);
                $anyVariantImage = $anyVariantImage || $hasImage;
                if (! $sharedImage && ! $hasImage) {
                    $issues[] = 'variant_image';
                }

                $selling = $variant->price ?? $product->price;
                if ($variant->compare_at_price !== null && ! $this->compareAtValid($variant->compare_at_price, $selling)) {
                    $issues[] = 'variant_compare_at_price';
                }

            }

            if (! $sharedImage && ! $anyVariantImage) {
                $issues[] = 'image';
            }
        } else {
            if (! $this->barcodeValid($product->barcode)) {
                $issues[] = 'barcode';
            } elseif ($this->barcodeTaken($product->barcode, $product->id, null)) {
                $issues[] = 'barcode_duplicate';
            }

            if (! $this->moneyValid($product->price)) {
                $issues[] = 'price';
            }

            if (! $this->moneyValid($product->cost_price)) {
                $issues[] = 'cost';
            }

            if (! $this->stockValid($product->stock_quantity)) {
                $issues[] = 'stock';
            }

            if ($product->images->isEmpty()) {
                $issues[] = 'image';
            }

            if ($product->compare_at_price !== null && ! $this->compareAtValid($product->compare_at_price, $product->price)) {
                $issues[] = 'compare_at_price';
            }
        }

        return array_values(array_unique($issues));
    }

    public function canActivate(Product $product): bool
    {
        return $this->issues($product) === [];
    }

    public function assertCanActivate(Product $product): void
    {
        $issues = $this->issues($product);

        if ($issues === []) {
            return;
        }

        throw ValidationException::withMessages([
            'status' => 'This product cannot be activated until the following are complete: '.implode(', ', $this->labels($issues)).'.',
        ]);
    }

    /**
     * @param  list<string>  $issues
     * @return list<string>
     */
    public function labels(array $issues): array
    {
        return array_map(fn (string $issue) => self::LABELS[$issue] ?? $issue, $issues);
    }

    /**
     * @param  list<string>|null  $issues
     * @return list<string>
     */
    public function remember(Product $product, ?array $issues = null): array
    {
        $issues ??= $this->issues($product);

        $product->forceFill([
            'readiness_issues' => $issues,
            'is_publishable' => $issues === [],
        ])->saveQuietly();

        return $issues;
    }

    public function barcodeTaken(?string $barcode, ?int $ignoreProductId, ?int $ignoreVariantId): bool
    {
        $barcode = trim((string) $barcode);

        if ($barcode === '') {
            return false;
        }

        $productExists = DB::table('products')
            ->where('barcode', $barcode)
            ->whereNull('deleted_at')
            ->when($ignoreProductId, fn ($query) => $query->where('id', '!=', $ignoreProductId))
            ->exists();

        $variantExists = DB::table('product_variants')
            ->where('barcode', $barcode)
            ->whereNull('deleted_at')
            ->when($ignoreVariantId, fn ($query) => $query->where('id', '!=', $ignoreVariantId))
            ->exists();

        return $productExists || $variantExists;
    }

    public function moneyValid(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        if (! is_numeric($value)) {
            return false;
        }

        $cents = (int) round(((float) $value) * 100);

        return $cents >= 0 && $cents <= 999999999999;
    }

    public function compareAtValid(mixed $compareAt, mixed $price): bool
    {
        if ($compareAt === null || $compareAt === '') {
            return true;
        }

        if (! $this->moneyValid($compareAt) || ! $this->moneyValid($price)) {
            return false;
        }

        return (int) round(((float) $compareAt) * 100) > (int) round(((float) $price) * 100);
    }

    public function stockValid(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        if (is_string($value) && ! preg_match('/^\d+$/', $value)) {
            return false;
        }

        if (! is_numeric($value) || (float) $value !== floor((float) $value)) {
            return false;
        }

        $stock = (int) $value;

        return $stock >= 0 && $stock <= 100000000;
    }

    public function barcodeValid(mixed $barcode): bool
    {
        if (! is_string($barcode) && ! is_numeric($barcode)) {
            return false;
        }

        $barcode = trim((string) $barcode);

        if ($barcode === '' || mb_strlen($barcode) > 64) {
            return false;
        }

        return preg_match('/[\x00-\x1F\x7F]/', $barcode) !== 1;
    }

    protected function filledText(mixed $value): bool
    {
        return is_string($value) && trim($value) !== '';
    }

    protected function variantHasImage(ProductVariant $variant): bool
    {
        if ($variant->relationLoaded('images') && $variant->images->isNotEmpty()) {
            return true;
        }

        return filled($variant->getAttributes()['image_path'] ?? null);
    }
}
