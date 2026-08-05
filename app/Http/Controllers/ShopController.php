<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Category;
use App\Services\ProductSearchService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class ShopController extends Controller
{
    public function __invoke(Request $request, ProductSearchService $search): View
    {
        return view('storefront.shop', [
            'products' => $search->search($request->all(), (int) config('aura.shop.per_page', 12)),
            'categories' => Cache::remember('storefront.shop_categories', 300, function () {
                return Category::query()->active()->orderBy('sort_order')->get();
            }),
            'brands' => Cache::remember('storefront.shop_brands', 300, function () {
                return Brand::query()->active()->orderBy('name')->get();
            }),
            'filters' => $request->all(),
        ]);
    }
}
