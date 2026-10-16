<?php

namespace App\Enums;

enum ImportBatchStatus: string
{
    case Validated = 'validated';
    case Processing = 'processing';
    case Completed = 'completed';
    case Partial = 'partial';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Validated => 'Ready to review',
            self::Processing => 'Importing',
            self::Completed => 'Completed',
            self::Partial => 'Completed with failures',
            self::Failed => 'Failed',
        };
    }
}
