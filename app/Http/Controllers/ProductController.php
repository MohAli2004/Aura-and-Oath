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
            ->with(['images', 'brand', 'category', 'activeVariants.attributeValues.attribute'])
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

        $related = Product::query()
            ->with(['images', 'brand', 'activeVariants'])
            ->active()
            ->published()
            ->where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->take(4)
            ->get();

        return view('storefront.product', compact('product', 'related'));
    }
}
