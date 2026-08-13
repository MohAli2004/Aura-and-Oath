<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Wishlist;
use App\Models\WishlistItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class WishlistController extends Controller
{
    public function index(): View
    {
        $wishlist = $this->wishlist();
        $wishlist->load(['items.product.images', 'items.product.brand', 'items.product.activeVariants', 'items.variant']);

        return view('storefront.wishlist', compact('wishlist'));
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'product_variant_id' => ['nullable', 'exists:product_variants,id'],
        ]);

        $wishlist = $this->wishlist();
        $variantId = $data['product_variant_id'] ?? null;

        if ($variantId) {
            $variant = ProductVariant::query()->findOrFail($variantId);
            abort_unless((int) $variant->product_id === (int) $data['product_id'], 422);
        }

        $item = WishlistItem::query()->firstOrCreate([
            'wishlist_id' => $wishlist->id,
            'product_id' => $data['product_id'],
            'product_variant_id' => $variantId,
        ]);

        $message = $item->wasRecentlyCreated
            ? 'Saved to wishlist.'
            : 'Already in your wishlist.';

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'ok' => true,
                'created' => $item->wasRecentlyCreated,
                'message' => $message,
                'item_id' => $item->id,
                'count' => $wishlist->items()->count(),
            ]);
        }

        return back()->with('success', $message);
    }

    public function destroy(Request $request, WishlistItem $item): JsonResponse|RedirectResponse
    {
        abort_unless($item->wishlist->user_id === Auth::id(), 403);
        $item->delete();

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'ok' => true,
                'message' => 'Removed from wishlist.',
            ]);
        }

        return back()->with('success', 'Removed from wishlist.');
    }

    protected function wishlist(): Wishlist
    {
        return Wishlist::query()->firstOrCreate(
            ['user_id' => Auth::id()],
            ['name' => 'Default']
        );
    }
}
