<?php

namespace App\Models;

use App\Enums\ImportBatchStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportBatch extends Model
{
    protected $fillable = [
        'uuid',
        'user_id',
        'original_filename',
        'stored_path',
        'schema_version',
        'idempotency_key',
        'status',
        'total_rows',
        'success_count',
        'failure_count',
        'skipped_count',
        'error_summary',
        'source_meta',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ImportBatchStatus::class,
            'source_meta' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function rows(): HasMany
    {
        return $this->hasMany(ImportRow::class)->orderBy('row_index');
    }

    public function media(): HasMany
    {
        return $this->hasMany(ImportMedia::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
