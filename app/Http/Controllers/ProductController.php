<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\RecentlyViewedProduct;
use App\Services\ProductRecommendationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function show(Request $request, string $slug, ProductRecommendationService $recommendations): View
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

        $related = $recommendations->forProduct(
            $product,
            Auth::user(),
            $sessionId,
            4,
        );

        return view('storefront.product', compact('product', 'related'));
    }
}
