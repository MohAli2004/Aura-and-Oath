<?php

namespace App\Enums;

enum ReturnRequestStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Declined = 'declined';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending review',
            self::Approved => 'Approved',
            self::Declined => 'Declined',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Pending => 'pending',
            self::Approved => 'success',
            self::Declined => 'danger',
        };
    }
}
