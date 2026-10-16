<?php

namespace App\Models;

use App\Enums\ImportResolution;
use App\Enums\ImportRowStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportRow extends Model
{
    protected $fillable = [
        'import_batch_id',
        'row_index',
        'external_key',
        'name',
        'payload',
        'errors',
        'warnings',
        'missing_fields',
        'match_type',
        'matched_product_id',
        'resolution',
        'status',
        'product_id',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'errors' => 'array',
            'warnings' => 'array',
            'missing_fields' => 'array',
            'resolution' => ImportResolution::class,
            'status' => ImportRowStatus::class,
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class, 'import_batch_id');
    }

    public function matchedProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'matched_product_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
