<?php

namespace App\Enums;

enum ImportResolution: string
{
    case Create = 'create';
    case Update = 'update';
    case AddVariants = 'add_variants';
    case Skip = 'skip';
    case Review = 'review';

    public function label(): string
    {
        return match ($this) {
            self::Create => 'Create new product',
            self::Update => 'Update existing product',
            self::AddVariants => 'Add missing variants',
            self::Skip => 'Skip',
            self::Review => 'Needs review',
        };
    }
}
