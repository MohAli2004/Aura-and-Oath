<?php

namespace App\Enums;

enum OrderStatus: string
{
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Preparing = 'preparing';
    case OnTheWay = 'on_the_way';
    case Delivered = 'delivered';
    case ReturnRequested = 'return_requested';
    case Cancelled = 'cancelled';
    case Returned = 'returned';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::PendingApproval => __('storefront.order_status_pending_approval'),
            self::Approved => __('storefront.order_status_approved'),
            self::Rejected => __('storefront.order_status_rejected'),
            self::Preparing => __('storefront.order_status_preparing'),
            self::OnTheWay => __('storefront.order_status_on_the_way'),
            self::Delivered => __('storefront.order_status_delivered'),
            self::ReturnRequested => __('storefront.order_status_return_requested'),
            self::Cancelled => __('storefront.order_status_cancelled'),
            self::Returned => __('storefront.order_status_returned'),
            self::Refunded => __('storefront.order_status_refunded'),
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::PendingApproval => 'pending',
            self::Approved, self::Preparing => 'preparing',
            self::OnTheWay => 'shipping',
            self::Delivered => 'success',
            self::ReturnRequested => 'pending',
            self::Rejected, self::Cancelled => 'danger',
            self::Returned => 'returned',
            self::Refunded => 'muted',
        };
    }

    public function rowClass(): string
    {
        return match ($this) {
            self::PendingApproval => 'bg-[#FBF3E6]',
            self::Approved, self::Preparing => 'bg-[#F3F0EA]',
            self::OnTheWay => 'bg-[#EEF4F8]',
            self::Delivered => 'bg-[#EEF5EE]',
            self::ReturnRequested => 'bg-[#FBF3E6]',
            self::Rejected, self::Cancelled => 'bg-[#F7EEEE]',
            self::Returned => 'bg-[#F8F1E8]',
            self::Refunded => 'bg-[#F3F1EF]',
        };
    }

    public function isCustomerVisible(): bool
    {
        return true;
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedTransitions(), true);
    }

    public function countsTowardRevenue(): bool
    {
        return ! in_array($this, self::excludedFromRevenue(), true);
    }

    /** @return list<self> */
    public static function excludedFromRevenue(): array
    {
        return [
            self::Cancelled,
            self::Rejected,
            self::Returned,
            self::Refunded,
        ];
    }

    /** @return list<string> */
    public static function excludedFromRevenueValues(): array
    {
        return array_map(
            static fn (self $status) => $status->value,
            self::excludedFromRevenue()
        );
    }

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::PendingApproval => [self::Preparing, self::Rejected, self::Cancelled],
            self::Approved => [self::Preparing, self::OnTheWay, self::Cancelled], // legacy orders
            self::Rejected => [],
            self::Preparing => [self::OnTheWay, self::Cancelled],
            self::OnTheWay => [self::Delivered, self::Returned, self::Cancelled],
            self::Delivered => [self::Returned, self::ReturnRequested],
            self::ReturnRequested => [self::Returned, self::Delivered],
            self::Cancelled => [],
            self::Returned => [self::Refunded],
            self::Refunded => [],
        };
    }
}
