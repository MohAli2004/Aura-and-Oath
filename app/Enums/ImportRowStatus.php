<?php

namespace App\Enums;

enum ImportRowStatus: string
{
    case Pending = 'pending';
    case Invalid = 'invalid';
    case Committed = 'committed';
    case Failed = 'failed';
    case Skipped = 'skipped';
}
