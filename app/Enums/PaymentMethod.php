<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case CashOnDelivery = 'cash_on_delivery';
    case WishAccount = 'wish_account';

    public function label(): string
    {
        return match ($this) {
            self::CashOnDelivery => 'Cash on Delivery',
            self::WishAccount => 'Wish Account',
        };
    }

    public function requiresTransferConfirmation(): bool
    {
        return $this === self::WishAccount;
    }
}
