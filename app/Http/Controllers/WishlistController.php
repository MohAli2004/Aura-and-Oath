<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Wishlist;
use App\Models\WishlistItem;
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

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'product_variant_id' => ['nullable', 'exists:product_variants,id'],
        ]);

        $wishlist = $this->wishlist();

        WishlistItem::query()->firstOrCreate([
            'wishlist_id' => $wishlist->id,
            'product_id' => $data['product_id'],
            'product_variant_id' => $data['product_variant_id'] ?? null,
        ]);

        return back()->with('success', 'Saved to wishlist.');
    }

    public function destroy(WishlistItem $item): RedirectResponse
    {
        abort_unless($item->wishlist->user_id === Auth::id(), 403);
        $item->delete();

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
