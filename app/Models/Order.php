<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ReturnRequestStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class Order extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'order_number',
        'idempotency_token',
        'user_id',
        'status',
        'payment_method',
        'payment_status',
        'whish_external_id',
        'whish_transaction_id',
        'whish_collect_url',
        'currency',
        'subtotal',
        'discount_amount',
        'delivery_fee',
        'tax_amount',
        'total',
        'coupon_id',
        'coupon_code',
        'delivery_region_id',
        'customer_name',
        'customer_email',
        'customer_phone',
        'customer_note',
        'internal_note',
        'rejection_reason',
        'approved_at',
        'approved_by',
        'shipped_at',
        'delivered_at',
        'cancelled_at',
        'tracking_number',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'payment_method' => PaymentMethod::class,
            'payment_status' => PaymentStatus::class,
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'delivery_fee' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'approved_at' => 'datetime',
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function acceptedItems(): HasMany
    {
        return $this->hasMany(OrderItem::class)->accepted();
    }

    public function rejectedItems(): HasMany
    {
        return $this->hasMany(OrderItem::class)->rejected();
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(OrderAddress::class);
    }

    public function shippingAddress(): HasOne
    {
        return $this->hasOne(OrderAddress::class)->where('type', 'shipping');
    }

    public function billingAddress(): HasOne
    {
        return $this->hasOne(OrderAddress::class)->where('type', 'billing');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->latest();
    }

    public function notes(): HasMany
    {
        return $this->hasMany(OrderNote::class)->latest();
    }

    public function returnRequests(): HasMany
    {
        return $this->hasMany(ReturnRequest::class)->latest();
    }

    public function pendingReturnRequest(): HasOne
    {
        return $this->hasOne(ReturnRequest::class)->ofMany(
            ['id' => 'max'],
            fn ($query) => $query->where('status', ReturnRequestStatus::Pending)
        );
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function deliveryRegion(): BelongsTo
    {
        return $this->belongsTo(DeliveryRegion::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function scopePendingApproval(Builder $query): Builder
    {
        return $query->where('status', OrderStatus::PendingApproval);
    }

    public function scopeCountsTowardRevenue(Builder $query): Builder
    {
        return $query
            ->whereNotIn('status', OrderStatus::excludedFromRevenueValues())
            ->whereNotIn('payment_status', [
                PaymentStatus::Refunded->value,
                PaymentStatus::Failed->value,
            ]);
    }

    public function isOwnedBy(?User $user): bool
    {
        return $user && $this->user_id && (int) $this->user_id === (int) $user->id;
    }

    public function canMarkAsPaid(): bool
    {
        return in_array($this->status, [
            OrderStatus::PendingApproval,
            OrderStatus::Approved,
            OrderStatus::Preparing,
            OrderStatus::OnTheWay,
            OrderStatus::Delivered,
            OrderStatus::ReturnRequested,
        ], true);
    }

    public function returnWindowHours(): int
    {
        return max(1, (int) config('aura.orders.return_window_hours', 24));
    }

    public function returnWindowClosesAt(): ?Carbon
    {
        return $this->delivered_at?->copy()->addHours($this->returnWindowHours());
    }

    public function isWithinReturnWindow(): bool
    {
        $closesAt = $this->returnWindowClosesAt();

        return $closesAt !== null && now()->lte($closesAt);
    }

    /** @return Collection<int, OrderItem> */
    public function returnableItems(): Collection
    {
        $this->loadMissing('items');

        return $this->items
            ->filter(fn (OrderItem $item) => $item->isAccepted())
            ->values();
    }

    public function hasPendingReturnRequest(): bool
    {
        if ($this->relationLoaded('pendingReturnRequest')) {
            return $this->pendingReturnRequest !== null;
        }

        return $this->returnRequests()
            ->where('status', ReturnRequestStatus::Pending)
            ->exists();
    }

    public function canCustomerCancel(): bool
    {
        return in_array($this->status, [
            OrderStatus::PendingApproval,
            OrderStatus::Approved,
        ], true);
    }

    public function canRequestReturn(): bool
    {
        return $this->returnIneligibilityReason() === null;
    }

    public function returnIneligibilityReason(): ?string
    {
        if ($this->status === OrderStatus::Cancelled) {
            return 'This order has been cancelled.';
        }

        if (in_array($this->status, [OrderStatus::Returned, OrderStatus::Refunded], true)) {
            return 'This order has already been returned.';
        }

        if ($this->status === OrderStatus::ReturnRequested || $this->hasPendingReturnRequest()) {
            return 'A return has already been requested. We will review it shortly.';
        }

        if ($this->status !== OrderStatus::Delivered) {
            return 'Returns can be requested after the order is delivered.';
        }

        if (! $this->isWithinReturnWindow()) {
            $hours = $this->returnWindowHours();

            return "The return window ({$hours} hours after delivery) has closed.";
        }

        if ($this->returnableItems()->isEmpty()) {
            return 'There are no items left to return on this order.';
        }

        return null;
    }

    public function canTogglePayment(): bool
    {
        if (in_array($this->status, [
            OrderStatus::Cancelled,
            OrderStatus::Refunded,
            OrderStatus::Rejected,
        ], true)) {
            return false;
        }

        return $this->canMarkAsPaid();
    }
}
