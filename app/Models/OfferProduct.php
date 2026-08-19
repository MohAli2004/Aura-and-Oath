<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfferProduct extends Model
{
    protected $fillable = [
        'offer_id',
        'product_id',
        'offer_price',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'offer_price' => 'decimal:2',
        ];
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
