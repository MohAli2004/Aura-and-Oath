<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\RecentlyViewedProduct;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function show(Request $request, string $slug): View
    {
        $product = Product::query()
            ->with(['images', 'brand', 'categories', 'activeVariants.attributeValues.attribute'])
            ->active()
            ->published()
            ->where('slug', $slug)
            ->firstOrFail();

        $sessionId = $request->session()->getId();
        $recentKey = 'recently_viewed.'.$sessionId.'.'.$product->id;
        if (! $request->session()->has($recentKey)) {
            RecentlyViewedProduct::query()->create([
                'user_id' => Auth::id(),
                'session_id' => $sessionId,
                'product_id' => $product->id,
                'viewed_at' => now(),
            ]);
            $request->session()->put($recentKey, true);
        }

        $categoryIds = $product->categories->pluck('id');
        $related = Product::query()
            ->with(['images', 'brand', 'activeVariants'])
            ->active()
            ->published()
            ->when(
                $categoryIds->isNotEmpty(),
                fn ($q) => $q->whereHas('categories', fn ($c) => $c->whereIn('categories.id', $categoryIds)),
                fn ($q) => $q->whereRaw('0 = 1')
            )
            ->where('id', '!=', $product->id)
            ->take(4)
            ->get();

        return view('storefront.product', compact('product', 'related'));
    }
}
