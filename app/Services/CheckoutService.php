<?php

namespace App\Services;

use App\Enums\AddressType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\CouponUsage;
use App\Models\Order;
use App\Models\OrderAddress;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CheckoutService
{
    public function __construct(
        protected CartService $cartService,
        protected OrderPricingService $pricingService,
        protected InventoryService $inventoryService,
        protected AuditService $audit,
        protected NotificationService $notifications
    ) {}

    /**
     * @param  array{
     *   customer_phone?: ?string,
     *   customer_note?: ?string,
     *   payment_method: string,
     *   delivery_region_id: int,
     *   coupon_code?: ?string,
     *   idempotency_token: string,
     *   shipping: array{full_name: string, phone: string, line1: string, line2?: ?string, city: string, governorate?: ?string, postal_code?: ?string, country?: string},
     *   billing?: ?array
     * }  $data
     */
    public function placeOrder(User $user, array $data): Order
    {
        $existing = Order::query()
            ->where('idempotency_token', $data['idempotency_token'])
            ->first();

        if ($existing) {
            return $existing;
        }

        $cart = $this->cartService->getOrCreateCart($user);
        $cart->load(['items.product', 'items.variant']);

        if ($cart->items->isEmpty()) {
            throw ValidationException::withMessages(['cart' => 'Your cart is empty.']);
        }

        return DB::transaction(function () use ($user, $data, $cart) {
            $pricing = $this->pricingService->quote(
                $cart,
                $data['coupon_code'] ?? null,
                (int) $data['delivery_region_id'],
                $user
            );

            $order = Order::query()->create([
                'order_number' => $this->generateOrderNumber(),
                'idempotency_token' => $data['idempotency_token'],
                'user_id' => $user->id,
                'status' => OrderStatus::PendingApproval,
                'payment_method' => PaymentMethod::from($data['payment_method']),
                'payment_status' => PaymentMethod::from($data['payment_method'])->requiresTransferConfirmation()
                    ? PaymentStatus::AwaitingConfirmation
                    : PaymentStatus::Pending,
                'currency' => config('aura.currency', 'USD'),
                'subtotal' => $pricing['subtotal'],
                'discount_amount' => $pricing['discount_amount'],
                'delivery_fee' => $pricing['delivery_fee'],
                'tax_amount' => $pricing['tax_amount'],
                'total' => $pricing['total'],
                'coupon_id' => $pricing['coupon']?->id,
                'coupon_code' => $pricing['coupon']?->code,
                'delivery_region_id' => $data['delivery_region_id'],
                'customer_name' => $user->name,
                'customer_email' => $user->email,
                'customer_phone' => $data['customer_phone'] ?? $data['shipping']['phone'] ?? $user->phone,
                'customer_note' => $data['customer_note'] ?? null,
            ]);

            foreach ($cart->items as $item) {
                $this->createOrderItemAndReserve($order, $item, $user);
            }

            OrderAddress::query()->create([
                'order_id' => $order->id,
                'type' => AddressType::Shipping,
                ...$this->addressPayload($data['shipping']),
            ]);

            $billing = $data['billing'] ?? $data['shipping'];
            OrderAddress::query()->create([
                'order_id' => $order->id,
                'type' => AddressType::Billing,
                ...$this->addressPayload($billing),
            ]);

            OrderStatusHistory::query()->create([
                'order_id' => $order->id,
                'from_status' => null,
                'to_status' => OrderStatus::PendingApproval,
                'changed_by' => $user->id,
                'note' => 'Order placed',
                'is_customer_visible' => true,
            ]);

            if ($pricing['coupon']) {
                CouponUsage::query()->create([
                    'coupon_id' => $pricing['coupon']->id,
                    'user_id' => $user->id,
                    'order_id' => $order->id,
                    'discount_amount' => $pricing['discount_amount'],
                ]);
                $pricing['coupon']->increment('used_count');
            }

            $this->cartService->clear($cart);
            $this->audit->log('order.placed', $order, null, ['order_number' => $order->order_number], user: $user);
            $this->notifications->notifyOrderPlaced($order);

            return $order->fresh(['items', 'addresses']);
        });
    }

    protected function createOrderItemAndReserve(Order $order, CartItem $item, User $user): void
    {
        $product = $item->product;
        $variant = $item->variant;
        $unitPrice = $product->effectivePrice($variant);

        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant?->id,
            'product_name' => $product->name,
            'variant_name' => $variant?->displayName(),
            'sku' => $variant?->sku ?? $product->sku,
            'barcode' => $variant?->barcode ?? $product->barcode,
            'quantity' => $item->quantity,
            'unit_price' => $unitPrice,
            'line_total' => round($unitPrice * $item->quantity, 2),
            'unit_cost' => $variant?->cost_price ?? $product->cost_price,
        ]);

        if ($product->track_inventory) {
            $this->inventoryService->reserve($product, $item->quantity, $variant, $order, $user);
        }
    }

    protected function addressPayload(array $address): array
    {
        return [
            'full_name' => $address['full_name'],
            'phone' => $address['phone'],
            'line1' => $address['line1'],
            'line2' => $address['line2'] ?? null,
            'city' => $address['city'],
            'governorate' => $address['governorate'] ?? null,
            'postal_code' => $address['postal_code'] ?? null,
            'country' => $address['country'] ?? config('aura.country', 'LB'),
        ];
    }

    protected function generateOrderNumber(): string
    {
        $prefix = config('aura.orders.number_prefix', 'AO');

        do {
            $number = $prefix.'-'.now()->format('ymd').'-'.Str::upper(Str::random(5));
        } while (Order::query()->where('order_number', $number)->exists());

        return $number;
    }

    public function newIdempotencyToken(): string
    {
        return (string) Str::uuid();
    }
}
