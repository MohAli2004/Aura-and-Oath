<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;

class CartService
{
    public function getOrCreateCart(?User $user = null): Cart
    {
        $user ??= Auth::user();

        if ($user) {
            return Cart::query()->firstOrCreate(
                ['user_id' => $user->id],
                ['currency' => config('aura.currency', 'USD')]
            );
        }

        $sessionId = Session::getId();
        $cartId = Session::get('guest_cart_id');

        if ($cartId) {
            $existing = Cart::query()
                ->whereKey($cartId)
                ->whereNull('user_id')
                ->first();

            if ($existing) {
                if ($existing->session_id !== $sessionId) {
                    $existing->update(['session_id' => $sessionId]);
                }

                return $existing;
            }
        }

        $cart = Cart::query()->firstOrCreate(
            ['session_id' => $sessionId, 'user_id' => null],
            ['currency' => config('aura.currency', 'USD')]
        );

        Session::put('guest_cart_id', $cart->id);

        return $cart;
    }

    public function add(Product $product, int $quantity = 1, ?ProductVariant $variant = null): CartItem
    {
        if ($quantity < 1) {
            throw ValidationException::withMessages(['quantity' => 'Quantity must be at least 1.']);
        }

        if (! $product->isPurchasable()) {
            throw ValidationException::withMessages(['product' => 'This product is not available.']);
        }

        if ($product->has_variants && ! $variant) {
            throw ValidationException::withMessages(['variant' => 'Please select a variant.']);
        }

        $available = $variant ? $variant->availableStock() : $product->availableStock();
        if ($product->track_inventory && $available < $quantity) {
            throw ValidationException::withMessages(['quantity' => 'Not enough stock available.']);
        }

        $cart = $this->getOrCreateCart();

        $item = CartItem::query()->firstOrNew([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant?->id,
        ]);

        $newQty = ($item->exists ? $item->quantity : 0) + $quantity;

        if ($product->track_inventory && $available < $newQty) {
            throw ValidationException::withMessages(['quantity' => 'Not enough stock available.']);
        }

        $item->quantity = $newQty;
        $item->save();
        $this->refreshCartCount($cart);

        return $item->fresh(['product', 'variant']);
    }

    public function updateQuantity(CartItem $item, int $quantity): void
    {
        if ($quantity < 1) {
            $cart = $item->cart;
            $item->delete();
            $this->refreshCartCount($cart);

            return;
        }

        $product = $item->product;
        $variant = $item->variant;
        $available = $variant ? $variant->availableStock() : $product->availableStock();

        if ($product->track_inventory && $available < $quantity) {
            throw ValidationException::withMessages(['quantity' => 'Not enough stock available.']);
        }

        $item->update(['quantity' => $quantity]);
        $this->refreshCartCount($item->cart);
    }

    public function remove(CartItem $item): void
    {
        $cart = $item->cart;
        $item->delete();
        $this->refreshCartCount($cart);
    }

    public function clear(?Cart $cart = null): void
    {
        $cart ??= $this->getOrCreateCart();
        $cart->items()->delete();
        $this->refreshCartCount($cart);
    }

    public function mergeGuestCartIntoUser(User $user): void
    {
        $sessionId = Session::getId();
        $guestCartId = Session::get('guest_cart_id');

        $guestCart = null;

        if ($guestCartId) {
            $guestCart = Cart::query()
                ->whereKey($guestCartId)
                ->whereNull('user_id')
                ->with('items')
                ->first();
        }

        if (! $guestCart) {
            $guestCart = Cart::query()
                ->whereNull('user_id')
                ->where('session_id', $sessionId)
                ->with('items')
                ->first();
        }

        if (! $guestCart || $guestCart->items->isEmpty()) {
            Session::forget('guest_cart_id');
            $this->refreshCartCount();

            return;
        }

        $userCart = $this->getOrCreateCart($user);

        foreach ($guestCart->items as $guestItem) {
            $existing = CartItem::query()->firstOrNew([
                'cart_id' => $userCart->id,
                'product_id' => $guestItem->product_id,
                'product_variant_id' => $guestItem->product_variant_id,
            ]);
            $existing->quantity = ($existing->exists ? $existing->quantity : 0) + $guestItem->quantity;
            $existing->save();
        }

        $guestCart->items()->delete();
        $guestCart->delete();
        Session::forget('guest_cart_id');
        $this->refreshCartCount($userCart);
    }

    public function subtotal(?Cart $cart = null): float
    {
        $cart ??= $this->getOrCreateCart();
        $cart->loadMissing(['items.product', 'items.variant']);

        return (float) $cart->items->sum(fn (CartItem $item) => $item->lineTotal());
    }

    public function count(?Cart $cart = null): int
    {
        $cart ??= $this->getOrCreateCart();

        return $cart->itemCount();
    }

    protected function refreshCartCount(?Cart $cart = null): void
    {
        try {
            Session::put('cart_count', $this->count($cart));
        } catch (\Throwable) {
            Session::forget('cart_count');
        }
    }
}
