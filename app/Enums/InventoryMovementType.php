<?php

namespace App\Enums;

enum InventoryMovementType: string
{
    case Purchase = 'purchase';
    case Sale = 'sale';
    case Reservation = 'reservation';
    case ReleaseReservation = 'release_reservation';
    case AdjustmentAdd = 'adjustment_add';
    case AdjustmentReduce = 'adjustment_reduce';
    case Damaged = 'damaged';
    case Returned = 'returned';
    case Correction = 'correction';
    case OrderDeduction = 'order_deduction';

    public function label(): string
    {
        return match ($this) {
            self::Purchase => 'Purchase',
            self::Sale => 'Sale',
            self::Reservation => 'Reservation',
            self::ReleaseReservation => 'Release Reservation',
            self::AdjustmentAdd => 'Adjustment (+)',
            self::AdjustmentReduce => 'Adjustment (−)',
            self::Damaged => 'Damaged',
            self::Returned => 'Returned',
            self::Correction => 'Correction',
            self::OrderDeduction => 'Order Deduction',
        };
    }
}
