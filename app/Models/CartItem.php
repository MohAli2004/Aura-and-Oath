<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItem extends Model
{
    protected $fillable = [
        'cart_id',
        'product_id',
        'product_variant_id',
        'offer_id',
        'quantity',
    ];

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }

    public function unitPrice(): float
    {
        if ($this->offer_id) {
            $offer = $this->relationLoaded('offer') ? $this->offer : $this->offer()->with('products')->first();

            if ($offer && $offer->isLive()) {
                $products = $offer->relationLoaded('products')
                    ? $offer->products
                    : $offer->products()->get();
                $matched = $products->firstWhere('id', $this->product_id);

                if ($matched && $matched->pivot?->offer_price !== null) {
                    return (float) $matched->pivot->offer_price;
                }
            }
        }

        return $this->product->regularPrice($this->variant);
    }

    public function lineTotal(): float
    {
        return $this->unitPrice() * $this->quantity;
    }
}
