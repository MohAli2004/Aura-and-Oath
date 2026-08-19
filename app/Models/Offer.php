<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class Offer extends Model
{
    protected $fillable = [
        'title',
        'slug',
        'description',
        'image_path',
        'is_active',
        'sort_order',
        'starts_at',
        'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Offer $offer) {
            if (empty($offer->slug)) {
                $offer->slug = Str::slug($offer->title).'-'.Str::lower(Str::random(4));
            }
        });
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'offer_products')
            ->withPivot(['offer_price', 'sort_order'])
            ->withTimestamps()
            ->orderByPivot('sort_order')
            ->orderByPivot('id');
    }

    public function offerProducts(): HasMany
    {
        return $this->hasMany(OfferProduct::class)->orderBy('sort_order')->orderBy('id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(function (Builder $q) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function (Builder $q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            });
    }

    public function isLive(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->starts_at && $this->starts_at->isFuture()) {
            return false;
        }

        if ($this->ends_at && $this->ends_at->isPast()) {
            return false;
        }

        return true;
    }

    public function statusLabel(): string
    {
        if (! $this->is_active) {
            return 'Inactive';
        }

        if ($this->starts_at && $this->starts_at->isFuture()) {
            return 'Scheduled';
        }

        if ($this->ends_at && $this->ends_at->isPast()) {
            return 'Ended';
        }

        return 'Live';
    }

    public function regularTotal(): float
    {
        return round((float) $this->products->sum(fn (Product $product) => $product->regularPrice()), 2);
    }

    public function offerTotal(): float
    {
        return round((float) $this->products->sum(fn (Product $product) => (float) ($product->pivot->offer_price ?? 0)), 2);
    }

    public function imageUrl(): string
    {
        return (string) ($this->galleryItems()->first()['image'] ?? app(\App\Services\ImageService::class)->url(null));
    }

    /**
     * Optional admin image first, then each bundle product photo.
     *
     * @return Collection<int, array{id:string,image:string,label:string}>
     */
    public function galleryItems(): Collection
    {
        $images = app(\App\Services\ImageService::class);
        $items = collect();

        if (filled($this->image_path)) {
            $items->push([
                'id' => 'main',
                'image' => $images->url($this->image_path),
                'label' => $this->title,
            ]);
        }

        foreach ($this->products as $product) {
            $items->push([
                'id' => 'product-'.$product->id,
                'image' => $images->url($product->primaryImagePath()),
                'label' => $product->name,
            ]);
        }

        return $items->unique('image')->values();
    }

    public function isPurchasable(): bool
    {
        if (! $this->isLive() || $this->products->count() < 2) {
            return false;
        }

        return $this->products->every(fn (Product $product) => $product->isPurchasable());
    }

    public function availableQuantity(): int
    {
        if (! $this->isPurchasable()) {
            return 0;
        }

        $max = 9999;

        foreach ($this->products as $product) {
            if (! $product->track_inventory) {
                continue;
            }

            $variant = $product->defaultVariantForCart();
            $available = $variant ? $variant->availableStock() : $product->availableStock();
            $max = min($max, $available);
        }

        return max(0, $max);
    }
}
