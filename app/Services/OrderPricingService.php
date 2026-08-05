<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\Coupon;
use App\Models\User;

class OrderPricingService
{
    public function __construct(
        protected CartService $cartService,
        protected CouponService $couponService,
        protected DeliveryFeeService $deliveryFeeService,
        protected SettingsService $settings
    ) {}

    /**
     * @return array{
     *   subtotal: float,
     *   discount_amount: float,
     *   delivery_fee: float,
     *   tax_amount: float,
     *   total: float,
     *   coupon: ?Coupon
     * }
     */
    public function quote(Cart $cart, ?string $couponCode = null, ?int $deliveryRegionId = null, ?User $user = null): array
    {
        $cart->loadMissing(['items.product', 'items.variant']);
        $subtotal = round($this->cartService->subtotal($cart), 2);

        $coupon = null;
        $discount = 0.0;

        if ($couponCode) {
            $coupon = $this->couponService->findValid($couponCode);
            $discount = $this->couponService->validateForCart($coupon, $cart, $subtotal, $user);
        }

        $deliveryFee = $this->deliveryFeeService->feeForRegion($deliveryRegionId);
        $taxRate = (float) $this->settings->get('tax_rate', 0);
        $taxable = max(0, $subtotal - $discount);
        $tax = round($taxable * ($taxRate / 100), 2);
        $total = round($taxable + $deliveryFee + $tax, 2);

        return [
            'subtotal' => $subtotal,
            'discount_amount' => $discount,
            'delivery_fee' => $deliveryFee,
            'tax_amount' => $tax,
            'total' => $total,
            'coupon' => $coupon,
        ];
    }
}
