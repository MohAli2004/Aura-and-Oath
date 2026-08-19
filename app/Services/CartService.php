<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Offer;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
            'offer_id' => null,
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

    public function addOffer(Offer $offer, int $quantity = 1): void
    {
        if ($quantity < 1) {
            throw ValidationException::withMessages(['quantity' => 'Quantity must be at least 1.']);
        }

        $offer->loadMissing(['products.activeVariants']);

        if (! $offer->isLive() || $offer->products->count() < 2) {
            throw ValidationException::withMessages(['offer' => 'This offer is not available.']);
        }

        $lines = [];

        foreach ($offer->products as $product) {
            if (! $product->isPurchasable()) {
                throw ValidationException::withMessages([
                    'offer' => $product->name.' is not available, so this offer cannot be added.',
                ]);
            }

            $variant = $product->defaultVariantForCart();

            if ($product->has_variants && ! $variant) {
                throw ValidationException::withMessages([
                    'offer' => $product->name.' needs an option selected. Choose a default option in admin, then try again.',
                ]);
            }

            $available = $variant ? $variant->availableStock() : $product->availableStock();
            if ($product->track_inventory && $available < $quantity) {
                throw ValidationException::withMessages([
                    'quantity' => 'Not enough stock for '.$product->name.' to add this offer.',
                ]);
            }

            $lines[] = ['product' => $product, 'variant' => $variant, 'available' => $available];
        }

        $cart = $this->getOrCreateCart();

        DB::transaction(function () use ($cart, $offer, $lines, $quantity) {
            foreach ($lines as $line) {
                /** @var Product $product */
                $product = $line['product'];
                $variant = $line['variant'];

                $item = CartItem::query()->firstOrNew([
                    'cart_id' => $cart->id,
                    'product_id' => $product->id,
                    'product_variant_id' => $variant?->id,
                    'offer_id' => $offer->id,
                ]);

                $newQty = ($item->exists ? $item->quantity : 0) + $quantity;

                if ($product->track_inventory && $line['available'] < $newQty) {
                    throw ValidationException::withMessages([
                        'quantity' => 'Not enough stock for '.$product->name.' to add this offer.',
                    ]);
                }

                $item->quantity = $newQty;
                $item->save();
            }
        });

        $this->refreshCartCount($cart);
    }

    public function updateQuantity(CartItem $item, int $quantity): void
    {
        if ($item->offer_id) {
            $this->updateOfferQuantity($item, $quantity);

            return;
        }

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

        if ($item->offer_id) {
            CartItem::query()
                ->where('cart_id', $cart->id)
                ->where('offer_id', $item->offer_id)
                ->delete();
            $this->refreshCartCount($cart);

            return;
        }

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
                'offer_id' => $guestItem->offer_id,
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
        $cart->loadMissing(['items.product', 'items.variant', 'items.offer.products']);

        return (float) $cart->items->sum(fn (CartItem $item) => $item->lineTotal());
    }

    public function count(?Cart $cart = null): int
    {
        $cart ??= $this->getOrCreateCart();

        return $cart->itemCount();
    }

    protected function updateOfferQuantity(CartItem $item, int $quantity): void
    {
        $cart = $item->cart;
        $siblings = CartItem::query()
            ->where('cart_id', $cart->id)
            ->where('offer_id', $item->offer_id)
            ->with(['product', 'variant'])
            ->get();

        if ($quantity < 1) {
            CartItem::query()->whereIn('id', $siblings->pluck('id'))->delete();
            $this->refreshCartCount($cart);

            return;
        }

        foreach ($siblings as $sibling) {
            $product = $sibling->product;
            $variant = $sibling->variant;
            $available = $variant ? $variant->availableStock() : $product->availableStock();

            if ($product->track_inventory && $available < $quantity) {
                throw ValidationException::withMessages([
                    'quantity' => 'Not enough stock for '.$product->name.'.',
                ]);
            }
        }

        CartItem::query()->whereIn('id', $siblings->pluck('id'))->update(['quantity' => $quantity]);
        $this->refreshCartCount($cart);
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
