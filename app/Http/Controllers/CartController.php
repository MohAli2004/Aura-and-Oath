<?php

namespace App\Http\Controllers;

use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CartController extends Controller
{
    public function __construct(protected CartService $cartService) {}

    public function index(): View
    {
        $cart = $this->cartService->getOrCreateCart();
        $cart->load(['items.product.images', 'items.product.activeVariants', 'items.variant']);

        return view('storefront.cart', [
            'cart' => $cart,
            'subtotal' => $this->cartService->subtotal($cart),
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'product_variant_id' => ['nullable', 'exists:product_variants,id'],
            'quantity' => ['nullable', 'integer', 'min:1'],
        ]);

        $product = Product::query()->findOrFail($data['product_id']);
        $variant = ! empty($data['product_variant_id'])
            ? ProductVariant::query()->findOrFail($data['product_variant_id'])
            : null;

        $this->cartService->add($product, $data['quantity'] ?? 1, $variant);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'ok' => true,
                'message' => 'Added to bag.',
                'redirect' => route('cart.index'),
            ]);
        }

        return back()->with('success', 'Added to bag.');
    }

    public function storeBatch(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_variant_id' => ['nullable', 'exists:product_variants,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        $product = Product::query()->findOrFail($data['product_id']);

        try {
            DB::transaction(function () use ($product, $data) {
                foreach ($data['items'] as $line) {
                    $variant = ! empty($line['product_variant_id'])
                        ? ProductVariant::query()
                            ->whereKey($line['product_variant_id'])
                            ->where('product_id', $product->id)
                            ->firstOrFail()
                        : null;

                    if ($product->has_variants && ! $variant) {
                        throw ValidationException::withMessages([
                            'items' => 'Please select a valid option for each item.',
                        ]);
                    }

                    $this->cartService->add($product, (int) $line['quantity'], $variant);
                }
            });
        } catch (ValidationException $e) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'ok' => false,
                    'message' => collect($e->errors())->flatten()->first() ?: 'Could not add to bag.',
                    'errors' => $e->errors(),
                ], 422);
            }

            throw $e;
        }

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'ok' => true,
                'message' => 'Added to bag.',
                'redirect' => route('cart.index'),
            ]);
        }

        return redirect()->route('cart.index')->with('success', 'Added to bag.');
    }

    public function update(Request $request, CartItem $item): RedirectResponse
    {
        $this->authorizeCartItem($item);
        $data = $request->validate(['quantity' => ['required', 'integer', 'min:0']]);
        $this->cartService->updateQuantity($item, $data['quantity']);

        return back()->with('success', 'Bag updated.');
    }

    public function destroy(CartItem $item): RedirectResponse
    {
        $this->authorizeCartItem($item);
        $this->cartService->remove($item);

        return back()->with('success', 'Item removed.');
    }

    protected function authorizeCartItem(CartItem $item): void
    {
        $cart = $this->cartService->getOrCreateCart();
        abort_unless($item->cart_id === $cart->id, 403);
    }
}
