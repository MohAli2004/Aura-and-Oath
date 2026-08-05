<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;

class ProductVariantService
{
    public function __construct(
        protected BarcodeService $barcodeService,
        protected ImageService $images
    ) {}

    /**
     * @return list<int> Kept variant IDs in the same order as the input array.
     */
    public function syncVariants(Product $product, array $variants): array
    {
        return DB::transaction(function () use ($product, $variants) {
            $keepIds = [];
            $defaultIndex = null;

            foreach ($variants as $index => $data) {
                if (! empty($data['is_default'])) {
                    $defaultIndex = $index;
                    break;
                }
            }
            if ($defaultIndex === null && count($variants) > 0) {
                $defaultIndex = 0;
            }

            foreach ($variants as $index => $data) {
                if (empty($data['sku'])) {
                    $data['sku'] = $this->barcodeService->generateVariantSku(
                        $product->sku,
                        $index + 1
                    );
                }

                if (! empty($data['sku'])) {
                    $this->barcodeService->assertSkuUnique(
                        $data['sku'],
                        null,
                        $data['id'] ?? null
                    );
                }
                if (! empty($data['barcode'])) {
                    $this->barcodeService->assertUnique(
                        $data['barcode'],
                        null,
                        $data['id'] ?? null
                    );
                }

                $variant = null;
                if (! empty($data['id'])) {
                    $variant = ProductVariant::query()
                        ->where('product_id', $product->id)
                        ->whereKey($data['id'])
                        ->first();
                }

                $payload = [
                    'product_id' => $product->id,
                    'name' => $data['name'] ?? null,
                    'sku' => $data['sku'],
                    'barcode' => $data['barcode'] ?? null,
                    'price' => $data['price'] ?? null,
                    'compare_at_price' => $data['compare_at_price'] ?? null,
                    'cost_price' => $data['cost_price'] ?? null,
                    'stock_quantity' => $data['stock_quantity'] ?? 0,
                    'low_stock_threshold' => $data['low_stock_threshold'] ?? 5,
                    'weight' => $data['weight'] ?? null,
                    'is_active' => $data['is_active'] ?? true,
                    'sort_order' => $index,
                    'is_default' => $defaultIndex === $index,
                ];

                if ($variant) {
                    $variant->update($payload);
                } else {
                    $variant = ProductVariant::query()->create($payload);
                }

                if (array_key_exists('attribute_value_ids', $data)) {
                    $sync = [];
                    foreach ($data['attribute_value_ids'] ?? [] as $attributeId => $valueId) {
                        if ($valueId === null || $valueId === '') {
                            continue;
                        }
                        $sync[(int) $valueId] = ['attribute_id' => (int) $attributeId];
                    }
                    $variant->attributeValues()->sync($sync);
                }

                $keepIds[] = $variant->id;
            }

            $removed = ProductVariant::query()
                ->where('product_id', $product->id)
                ->whereNotIn('id', $keepIds)
                ->get();

            foreach ($removed as $old) {
                if ($old->image_path) {
                    $this->images->delete($old->image_path);
                }
                $old->delete();
            }

            $hasVariants = count($keepIds) > 0;
            $product->update([
                'has_variants' => $hasVariants,
                // Main product stock is unused when variants exist.
                'stock_quantity' => $hasVariants ? 0 : $product->stock_quantity,
            ]);

            if ($hasVariants) {
                $this->clearProductImages($product);
            }

            return $keepIds;
        });
    }

    protected function clearProductImages(Product $product): void
    {
        foreach ($product->images()->get() as $existing) {
            $this->images->delete($existing->path);
            $existing->delete();
        }
    }
}
