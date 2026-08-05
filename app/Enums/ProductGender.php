<?php

namespace App\Enums;

enum ProductGender: string
{
    case Women = 'women';
    case Men = 'men';
    case Unisex = 'unisex';

    public function label(): string
    {
        return match ($this) {
            self::Women => 'Women',
            self::Men => 'Men',
            self::Unisex => 'Unisex',
        };
    }
}
