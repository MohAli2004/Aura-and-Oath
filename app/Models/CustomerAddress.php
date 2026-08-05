<?php

namespace App\Models;

use App\Enums\AddressType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerAddress extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'type',
        'label',
        'full_name',
        'phone',
        'line1',
        'line2',
        'city',
        'governorate',
        'postal_code',
        'country',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'type' => AddressType::class,
            'is_default' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
