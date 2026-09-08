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
            self::Women => __('storefront.gender_women'),
            self::Men => __('storefront.gender_men'),
            self::Unisex => __('storefront.gender_unisex'),
        };
    }
}
