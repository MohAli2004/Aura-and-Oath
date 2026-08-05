<?php

namespace App\Models;

use App\Enums\AddressType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderAddress extends Model
{
    protected $fillable = [
        'order_id',
        'type',
        'full_name',
        'phone',
        'line1',
        'line2',
        'city',
        'governorate',
        'postal_code',
        'country',
    ];

    protected function casts(): array
    {
        return [
            'type' => AddressType::class,
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function formatted(): string
    {
        return collect([
            $this->line1,
            $this->line2,
            $this->city,
            $this->governorate,
            $this->postal_code,
            $this->country,
        ])->filter()->implode(', ');
    }
}
