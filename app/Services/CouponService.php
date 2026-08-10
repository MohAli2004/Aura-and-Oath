<?php

namespace App\Services;

use App\Enums\DiscountType;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Coupon;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class CouponService
{
    public function findValid(string $code): Coupon
    {
        $coupon = Coupon::query()
            ->whereRaw('UPPER(code) = ?', [strtoupper(trim($code))])
            ->first();

        if (! $coupon || ! $coupon->isValid()) {
            throw ValidationException::withMessages([
                'coupon' => 'This coupon is invalid or expired.',
            ]);
        }

        return $coupon;
    }

    public function validateForCart(Coupon $coupon, Cart $cart, float $subtotal, ?User $user = null): float
    {
        if (! $coupon->isValid()) {
            throw ValidationException::withMessages(['coupon' => 'This coupon is invalid or expired.']);
        }

        if ($coupon->min_order_amount !== null && $subtotal < (float) $coupon->min_order_amount) {
            throw ValidationException::withMessages([
                'coupon' => 'Minimum order amount for this coupon is '.money($coupon->min_order_amount).'.',
            ]);
        }

        if ($user && $coupon->usage_limit_per_user !== null) {
            $used = $coupon->usages()->where('user_id', $user->id)->count();
            if ($used >= $coupon->usage_limit_per_user) {
                throw ValidationException::withMessages(['coupon' => 'You have already used this coupon the maximum number of times.']);
            }
        }

        $eligibleSubtotal = $this->eligibleSubtotal($coupon, $cart);
        if ($eligibleSubtotal <= 0) {
            throw ValidationException::withMessages(['coupon' => 'This coupon does not apply to items in your cart.']);
        }

        return $this->calculateDiscount($coupon, $eligibleSubtotal);
    }

    public function calculateDiscount(Coupon $coupon, float $amount): float
    {
        $discount = match ($coupon->discount_type) {
            DiscountType::Percentage => $amount * ((float) $coupon->discount_value / 100),
            DiscountType::Fixed => min((float) $coupon->discount_value, $amount),
        };

        if ($coupon->max_discount_amount !== null) {
            $discount = min($discount, (float) $coupon->max_discount_amount);
        }

        return round(max(0, $discount), 2);
    }

    protected function eligibleSubtotal(Coupon $coupon, Cart $cart): float
    {
        $coupon->loadMissing(['products', 'categories']);
        $productIds = $coupon->products->pluck('id');
        $categoryIds = $coupon->categories->pluck('id');

        if ($productIds->isEmpty() && $categoryIds->isEmpty()) {
            return (float) $cart->items->sum(fn (CartItem $item) => $item->lineTotal());
        }

        return (float) $cart->items
            ->filter(function (CartItem $item) use ($productIds, $categoryIds) {
                if ($productIds->contains($item->product_id)) {
                    return true;
                }

                if ($categoryIds->isEmpty()) {
                    return false;
                }

                $item->product?->loadMissing('categories');

                return $item->product?->categories
                    ->pluck('id')
                    ->intersect($categoryIds)
                    ->isNotEmpty() ?? false;
            })
            ->sum(fn (CartItem $item) => $item->lineTotal());
    }
}
