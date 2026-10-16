<?php

namespace App\Enums;

enum ContentSource: string
{
    case Verified = 'verified';
    case Ai = 'ai';

    public function label(): string
    {
        return match ($this) {
            self::Verified => 'Verified',
            self::Ai => 'AI-generated',
        };
    }
}
