<?php

namespace App\Services;

use App\Enums\InventoryMovementType;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class InventoryService
{
    public function __construct(
        protected AuditService $audit
    ) {}

    public function reserve(Product $product, int $quantity, ?ProductVariant $variant = null, ?Order $order = null, ?User $user = null): void
    {
        if ($quantity <= 0) {
            throw new RuntimeException('Reserve quantity must be positive.');
        }

        DB::transaction(function () use ($product, $quantity, $variant, $order, $user) {
            $stockable = $this->lockStockable($product, $variant);

            $available = max(0, (int) $stockable->stock_quantity - (int) $stockable->reserved_quantity);
            if ($available < $quantity) {
                throw new RuntimeException('Insufficient available stock to reserve.');
            }

            $stockBefore = (int) $stockable->stock_quantity;
            $reservedBefore = (int) $stockable->reserved_quantity;
            $stockable->reserved_quantity = $reservedBefore + $quantity;
            $stockable->save();

            $this->record(
                $product,
                $variant,
                InventoryMovementType::Reservation,
                $quantity,
                $stockBefore,
                (int) $stockable->stock_quantity,
                $reservedBefore,
                (int) $stockable->reserved_quantity,
                $order,
                $user,
                $order?->order_number
            );

            $product->refresh();
            $product->refreshStockStatus();
        });
    }

    public function release(Product $product, int $quantity, ?ProductVariant $variant = null, ?Order $order = null, ?User $user = null): void
    {
        if ($quantity <= 0) {
            throw new RuntimeException('Release quantity must be positive.');
        }

        DB::transaction(function () use ($product, $quantity, $variant, $order, $user) {
            $stockable = $this->lockStockable($product, $variant);

            $stockBefore = (int) $stockable->stock_quantity;
            $reservedBefore = (int) $stockable->reserved_quantity;
            $releaseQty = min($quantity, $reservedBefore);
            $stockable->reserved_quantity = $reservedBefore - $releaseQty;
            $stockable->save();

            $this->record(
                $product,
                $variant,
                InventoryMovementType::ReleaseReservation,
                -$releaseQty,
                $stockBefore,
                (int) $stockable->stock_quantity,
                $reservedBefore,
                (int) $stockable->reserved_quantity,
                $order,
                $user,
                $order?->order_number
            );

            $product->refresh();
            $product->refreshStockStatus();
        });
    }

    public function convertReservationToSale(Product $product, int $quantity, ?ProductVariant $variant = null, ?Order $order = null, ?User $user = null): void
    {
        if ($quantity <= 0) {
            throw new RuntimeException('Sale quantity must be positive.');
        }

        DB::transaction(function () use ($product, $quantity, $variant, $order, $user) {
            $stockable = $this->lockStockable($product, $variant);

            $stockBefore = (int) $stockable->stock_quantity;
            $reservedBefore = (int) $stockable->reserved_quantity;

            if ($reservedBefore < $quantity) {
                throw new RuntimeException('Cannot convert more than reserved quantity.');
            }

            if ($stockBefore < $quantity) {
                throw new RuntimeException('Cannot deduct more than stock quantity.');
            }

            $stockable->reserved_quantity = $reservedBefore - $quantity;
            $stockable->stock_quantity = $stockBefore - $quantity;
            $stockable->save();

            $this->record(
                $product,
                $variant,
                InventoryMovementType::OrderDeduction,
                -$quantity,
                $stockBefore,
                (int) $stockable->stock_quantity,
                $reservedBefore,
                (int) $stockable->reserved_quantity,
                $order,
                $user,
                $order?->order_number
            );

            $product->refresh();
            $product->refreshStockStatus();
        });
    }

    public function revertSaleToReservation(Product $product, int $quantity, ?ProductVariant $variant = null, ?Order $order = null, ?User $user = null): void
    {
        if ($quantity <= 0) {
            throw new RuntimeException('Revert quantity must be positive.');
        }

        DB::transaction(function () use ($product, $quantity, $variant, $order, $user) {
            $stockable = $this->lockStockable($product, $variant);

            $stockBefore = (int) $stockable->stock_quantity;
            $reservedBefore = (int) $stockable->reserved_quantity;

            $stockable->stock_quantity = $stockBefore + $quantity;
            $stockable->reserved_quantity = $reservedBefore + $quantity;
            $stockable->save();

            $this->record(
                $product,
                $variant,
                InventoryMovementType::SaleRevert,
                $quantity,
                $stockBefore,
                (int) $stockable->stock_quantity,
                $reservedBefore,
                (int) $stockable->reserved_quantity,
                $order,
                $user,
                $order?->order_number,
                'Undo approval — sale reverted to reservation'
            );

            $product->refresh();
            $product->refreshStockStatus();
        });
    }

    public function adjust(
        Product $product,
        int $quantityChange,
        InventoryMovementType $type,
        ?ProductVariant $variant = null,
        ?User $user = null,
        ?string $notes = null,
        ?string $reference = null
    ): void {
        if ($quantityChange === 0) {
            return;
        }

        DB::transaction(function () use ($product, $quantityChange, $type, $variant, $user, $notes, $reference) {
            $stockable = $this->lockStockable($product, $variant);

            $stockBefore = (int) $stockable->stock_quantity;
            $reservedBefore = (int) $stockable->reserved_quantity;
            $newStock = $stockBefore + $quantityChange;

            if ($newStock < 0) {
                throw new RuntimeException('Stock cannot go negative.');
            }

            if ($newStock < $reservedBefore) {
                throw new RuntimeException('Stock cannot fall below reserved quantity.');
            }

            $stockable->stock_quantity = $newStock;
            $stockable->save();

            $this->record(
                $product,
                $variant,
                $type,
                $quantityChange,
                $stockBefore,
                $newStock,
                $reservedBefore,
                $reservedBefore,
                null,
                $user,
                $reference,
                $notes
            );

            $product->refresh();
            $product->refreshStockStatus();
        });
    }

    public function restore(Product $product, int $quantity, ?ProductVariant $variant = null, ?Order $order = null, ?User $user = null, ?string $notes = null): void
    {
        $this->adjust(
            $product,
            $quantity,
            InventoryMovementType::Returned,
            $variant,
            $user,
            $notes ?? 'Return restock (resellable)',
            $order?->order_number
        );
    }

    protected function lockStockable(Product $product, ?ProductVariant $variant): Product|ProductVariant
    {
        if ($variant) {
            return ProductVariant::query()->whereKey($variant->id)->lockForUpdate()->firstOrFail();
        }

        return Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();
    }

    protected function record(
        Product $product,
        ?ProductVariant $variant,
        InventoryMovementType $type,
        int $quantityChange,
        int $stockBefore,
        int $stockAfter,
        int $reservedBefore,
        int $reservedAfter,
        ?Order $order = null,
        ?User $user = null,
        ?string $reference = null,
        ?string $notes = null
    ): InventoryMovement {
        return InventoryMovement::query()->create([
            'product_id' => $product->id,
            'product_variant_id' => $variant?->id,
            'type' => $type,
            'quantity_change' => $quantityChange,
            'stock_before' => $stockBefore,
            'stock_after' => $stockAfter,
            'reserved_before' => $reservedBefore,
            'reserved_after' => $reservedAfter,
            'order_id' => $order?->id,
            'user_id' => $user?->id,
            'reference' => $reference,
            'notes' => $notes,
        ]);
    }
}
