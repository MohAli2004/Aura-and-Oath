<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case CashOnDelivery = 'cash_on_delivery';
    case WishAccount = 'wish_account';

    public function label(): string
    {
        return match ($this) {
            self::CashOnDelivery => __('storefront.payment_method_cod'),
            self::WishAccount => __('storefront.payment_method_wish'),
        };
    }

    public function requiresTransferConfirmation(): bool
    {
        return $this === self::WishAccount;
    }
}
