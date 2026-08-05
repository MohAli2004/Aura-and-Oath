<?php

namespace App\Providers;

use App\Listeners\MergeGuestCartOnLogin;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Policies\OrderPolicy;
use App\Policies\ProductPolicy;
use App\Services\CartService;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::policy(Order::class, OrderPolicy::class);
        Gate::policy(Product::class, ProductPolicy::class);

        Gate::define('admin', fn ($user) => $user->isAdmin() && $user->is_active);

        Event::listen(Login::class, MergeGuestCartOnLogin::class);

        view()->composer('layouts.storefront', function ($view) {
            try {
                $cartCount = session()->get('cart_count');
                if ($cartCount === null) {
                    $cartCount = app(CartService::class)->count();
                    session()->put('cart_count', $cartCount);
                }
            } catch (\Throwable) {
                $cartCount = 0;
            }

            try {
                $navCategories = Cache::remember('storefront.nav_categories', 300, function () {
                    return Category::query()
                        ->active()
                        ->orderBy('sort_order')
                        ->orderBy('name')
                        ->take(8)
                        ->get(['id', 'name', 'slug', 'image_path']);
                });
            } catch (\Throwable) {
                $navCategories = collect();
            }

            $view->with([
                'cartCount' => $cartCount,
                'navCategories' => $navCategories,
            ]);
        });
    }
}
