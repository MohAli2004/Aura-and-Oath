<?php

namespace App\Http\Controllers;

use App\Enums\ProductGender;
use App\Models\Banner;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        $data = Cache::remember('storefront.home', 300, function () {
            $base = fn () => Product::query()
                ->with(['images', 'brand', 'activeVariants'])
                ->active()
                ->published()
                ->latest();

            return [
                'banners' => Banner::query()->active()->placement('home_hero')->orderBy('sort_order')->get(),
                'featured' => $base()->featured()->take(8)->get(),
                'bestsellers' => $base()->bestseller()->take(8)->get(),
                'newArrivals' => $base()->newArrivals()->take(8)->get(),
                'womenProducts' => $base()->gender(ProductGender::Women)->take(8)->get(),
                'menProducts' => $base()->gender(ProductGender::Men)->take(8)->get(),
                'unisexProducts' => $base()->gender(ProductGender::Unisex)->take(8)->get(),
                'categories' => Category::query()->active()->orderBy('sort_order')->take(8)->get(),
                'brands' => Brand::query()->active()->featured()->orderBy('sort_order')->take(8)->get(),
            ];
        });

        $data['hotOffers'] = rescue(
            fn () => app(\App\Services\OfferService::class)->liveOffers(8),
            collect()
        );

        return view('storefront.home', $data);
    }
}
