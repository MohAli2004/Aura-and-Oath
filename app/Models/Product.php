<?php

namespace App\Models;

use App\Enums\ProductGender;
use App\Enums\ProductStatus;
use App\Enums\ProductVisibility;
use App\Enums\StockStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class Product extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'category_id',
        'brand_id',
        'name',
        'slug',
        'sku',
        'barcode',
        'short_description',
        'description',
        'ingredients',
        'how_to_use',
        'price',
        'compare_at_price',
        'cost_price',
        'stock_quantity',
        'reserved_quantity',
        'low_stock_threshold',
        'track_inventory',
        'has_variants',
        'status',
        'visibility',
        'gender',
        'stock_status',
        'is_featured',
        'is_bestseller',
        'is_new',
        'weight',
        'size',
        'unit',
        'meta_title',
        'meta_description',
        'published_at',
    ];

    protected $hidden = [
        'cost_price',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'compare_at_price' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'weight' => 'decimal:2',
            'size' => 'decimal:2',
            'track_inventory' => 'boolean',
            'has_variants' => 'boolean',
            'is_featured' => 'boolean',
            'is_bestseller' => 'boolean',
            'is_new' => 'boolean',
            'status' => ProductStatus::class,
            'visibility' => ProductVisibility::class,
            'gender' => ProductGender::class,
            'stock_status' => StockStatus::class,
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Product $product) {
            if (empty($product->slug)) {
                $product->slug = Str::slug($product->name).'-'.Str::lower(Str::random(4));
            }
        });

        static::saved(fn () => Cache::forget('storefront.home'));
        static::deleted(fn () => Cache::forget('storefront.home'));
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('sort_order');
    }

    public function primaryImage(): BelongsTo
    {
        return $this->belongsTo(ProductImage::class, 'id', 'product_id')
            ->where('is_primary', true);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function activeVariants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function attributeValues(): BelongsToMany
    {
        return $this->belongsToMany(AttributeValue::class, 'product_attribute_values')
            ->withPivot('attribute_id')
            ->withTimestamps();
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', ProductStatus::Active)
            ->where('visibility', ProductVisibility::Public);
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    public function scopeBestseller(Builder $query): Builder
    {
        return $query->where('is_bestseller', true);
    }

    public function scopeNewArrivals(Builder $query): Builder
    {
        return $query->where('is_new', true);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->whereNull('published_at')->orWhere('published_at', '<=', now());
        });
    }

    public function scopeGender(Builder $query, ProductGender|string $gender): Builder
    {
        $value = $gender instanceof ProductGender ? $gender->value : $gender;

        return $query->where('gender', $value);
    }

    public function availableStock(): int
    {
        if ($this->has_variants) {
            $variants = $this->relationLoaded('activeVariants')
                ? $this->activeVariants
                : $this->activeVariants()->get();

            return (int) $variants->sum(fn (ProductVariant $v) => $v->availableStock());
        }

        return max(0, (int) $this->stock_quantity - (int) $this->reserved_quantity);
    }

    public function effectivePrice(?ProductVariant $variant = null): float
    {
        if ($variant && $variant->price !== null) {
            return (float) $variant->price;
        }

        if ($this->has_variants) {
            $default = $this->relationLoaded('activeVariants')
                ? $this->activeVariants->first()
                : $this->activeVariants()->first();

            if ($default) {
                return $default->effectivePrice();
            }
        }

        return (float) $this->price;
    }

    public function compareAtPrice(?ProductVariant $variant = null): ?float
    {
        if ($variant && $variant->compare_at_price !== null) {
            return (float) $variant->compare_at_price;
        }

        return $this->compare_at_price !== null ? (float) $this->compare_at_price : null;
    }

    public function primaryImagePath(): ?string
    {
        if ($this->has_variants) {
            $variants = $this->relationLoaded('activeVariants')
                ? $this->activeVariants
                : ($this->relationLoaded('variants')
                    ? $this->variants->where('is_active', true)->values()
                    : $this->activeVariants()->get());

            $withImage = $variants->first(fn (ProductVariant $variant) => filled($variant->image_path));

            return $withImage?->image_path;
        }

        if (! $this->relationLoaded('images')) {
            $this->load('images');
        }

        $image = $this->images->firstWhere('is_primary', true) ?? $this->images->first();

        return $image?->path;
    }

    public function sizeLabel(): ?string
    {
        if ($this->size === null || $this->size === '') {
            return null;
        }

        $amount = rtrim(rtrim(number_format((float) $this->size, 2, '.', ''), '0'), '.');
        $unit = $this->unit ? strtolower(trim((string) $this->unit)) : null;

        return $unit ? "{$amount} {$unit}" : $amount;
    }

    public function refreshStockStatus(): void
    {
        if (! $this->track_inventory) {
            $this->stock_status = StockStatus::InStock;
            $this->save();

            return;
        }

        $available = $this->availableStock();
        $threshold = (int) $this->low_stock_threshold;

        $this->stock_status = match (true) {
            $available <= 0 => StockStatus::OutOfStock,
            $available <= $threshold => StockStatus::LowStock,
            default => StockStatus::InStock,
        };
        $this->save();
    }

    public function isPurchasable(): bool
    {
        return $this->status === ProductStatus::Active
            && $this->visibility === ProductVisibility::Public
            && (! $this->track_inventory || $this->availableStock() > 0);
    }
}
