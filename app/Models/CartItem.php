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
            $matched = $this->matchedOfferProduct();

            if ($matched && $matched->pivot?->offer_price !== null) {
                $offer = $this->loadedOffer();
                $packTotal = (float) $matched->pivot->offer_price;

                if ($offer?->usesPackTotals()) {
                    $perSet = max(1, (int) ($matched->pivot->quantity ?? 1));

                    return round($packTotal / $perSet, 4);
                }

                return $packTotal;
            }
        }

        return $this->product->regularPrice($this->variant);
    }

    public function lineTotal(): float
    {
        if ($this->offer_id) {
            $matched = $this->matchedOfferProduct();
            $offer = $this->loadedOffer();

            if ($matched && $offer?->usesPackTotals()) {
                $perSet = max(1, (int) ($matched->pivot->quantity ?? 1));
                $packs = intdiv((int) $this->quantity, $perSet);

                return round($packs * (float) $matched->pivot->offer_price, 2);
            }
        }

        return round($this->unitPrice() * $this->quantity, 2);
    }

    protected function loadedOffer(): ?Offer
    {
        if (! $this->offer_id) {
            return null;
        }

        return $this->relationLoaded('offer')
            ? $this->offer
            : $this->offer()->with('products')->first();
    }

    protected function matchedOfferProduct(): ?Product
    {
        $offer = $this->loadedOffer();

        if (! $offer || ! $offer->isLive()) {
            return null;
        }

        $products = $offer->relationLoaded('products')
            ? $offer->products
            : $offer->products()->get();

        return $products->firstWhere('id', $this->product_id);
    }
}
