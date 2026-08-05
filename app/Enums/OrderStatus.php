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
    case Cancelled = 'cancelled';
    case Returned = 'returned';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::PendingApproval => 'Pending Approval',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::Preparing => 'Preparing',
            self::OnTheWay => 'On the way',
            self::Delivered => 'Delivered',
            self::Cancelled => 'Cancelled',
            self::Returned => 'Returned',
            self::Refunded => 'Refunded',
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

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::PendingApproval => [self::Approved, self::Rejected, self::Cancelled],
            self::Approved => [self::Preparing, self::Cancelled],
            self::Rejected => [],
            self::Preparing => [self::OnTheWay, self::Cancelled],
            self::OnTheWay => [self::Delivered, self::Returned, self::Cancelled],
            self::Delivered => [self::Returned],
            self::Cancelled => [],
            self::Returned => [self::Refunded],
            self::Refunded => [],
        };
    }
}
