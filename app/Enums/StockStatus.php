<?php

namespace App\Enums;

enum StockStatus: string
{
    case InStock = 'in_stock';
    case LowStock = 'low_stock';
    case OutOfStock = 'out_of_stock';

    public function label(): string
    {
        return match ($this) {
            self::InStock => __('storefront.in_stock'),
            self::LowStock => __('storefront.low_stock'),
            self::OutOfStock => __('storefront.out_of_stock'),
        };
    }
}
