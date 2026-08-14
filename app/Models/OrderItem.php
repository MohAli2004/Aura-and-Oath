<?php

namespace App\Models;

use App\Enums\OrderItemStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'product_id',
        'product_variant_id',
        'product_name',
        'variant_name',
        'sku',
        'barcode',
        'quantity',
        'unit_price',
        'line_total',
        'unit_cost',
        'status',
        'rejection_reason',
        'rejected_at',
    ];

    protected $hidden = [
        'unit_cost',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'line_total' => 'decimal:2',
            'unit_cost' => 'decimal:2',
            'status' => OrderItemStatus::class,
            'rejected_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function scopeAccepted(Builder $query): Builder
    {
        return $query->whereNotIn('status', [
            OrderItemStatus::Rejected,
            OrderItemStatus::Returned,
        ]);
    }

    public function scopeRejected(Builder $query): Builder
    {
        return $query->where('status', OrderItemStatus::Rejected);
    }

    public function isRejected(): bool
    {
        return $this->status === OrderItemStatus::Rejected;
    }

    public function isReturned(): bool
    {
        return $this->status === OrderItemStatus::Returned;
    }

    public function isAccepted(): bool
    {
        return ! $this->isRejected() && ! $this->isReturned();
    }
}
