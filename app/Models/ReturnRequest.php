<?php

namespace App\Models;

use App\Enums\ReturnRequestStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReturnRequest extends Model
{
    protected $fillable = [
        'order_id',
        'user_id',
        'status',
        'reason',
        'photo_path',
        'reviewed_at',
        'reviewed_by',
        'review_note',
    ];

    protected function casts(): array
    {
        return [
            'status' => ReturnRequestStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ReturnRequestItem::class);
    }

    public function isPending(): bool
    {
        return $this->status === ReturnRequestStatus::Pending;
    }

    public function photoUrl(): ?string
    {
        if (! filled($this->photo_path)) {
            return null;
        }

        return app(\App\Services\ImageService::class)->url($this->photo_path);
    }
}
