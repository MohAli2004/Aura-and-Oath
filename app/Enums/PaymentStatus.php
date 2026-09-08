<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case AwaitingConfirmation = 'awaiting_confirmation';
    case Paid = 'paid';
    case Failed = 'failed';
    case Refunded = 'refunded';
    case PartiallyRefunded = 'partially_refunded';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('storefront.payment_status_pending'),
            self::AwaitingConfirmation => __('storefront.payment_status_awaiting_confirmation'),
            self::Paid => __('storefront.payment_status_paid'),
            self::Failed => __('storefront.payment_status_failed'),
            self::Refunded => __('storefront.payment_status_refunded'),
            self::PartiallyRefunded => __('storefront.payment_status_partially_refunded'),
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Pending => 'pending',
            self::AwaitingConfirmation => 'preparing',
            self::Paid => 'success',
            self::Failed => 'danger',
            self::Refunded, self::PartiallyRefunded => 'muted',
        };
    }
}
