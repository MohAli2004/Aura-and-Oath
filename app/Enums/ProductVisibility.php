<?php

namespace App\Enums;

enum ProductVisibility: string
{
    case Public = 'public';
    case Hidden = 'hidden';
    case Private = 'private';

    public function label(): string
    {
        return match ($this) {
            self::Public => 'Public',
            self::Hidden => 'Hidden',
            self::Private => 'Private',
        };
    }
}
