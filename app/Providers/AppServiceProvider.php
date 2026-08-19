<?php

namespace App\Providers;

use App\Listeners\MergeGuestCartOnLogin;
use App\Listeners\SendWebPushOnDatabaseNotification;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Policies\OrderPolicy;
use App\Policies\ProductPolicy;
use App\Services\AdminNavBadgeService;
use App\Services\CartService;
use App\Services\OfferService;
use Illuminate\Auth\Events\Login;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->configureApplicationUrl();

        Gate::policy(Order::class, OrderPolicy::class);
        Gate::policy(Product::class, ProductPolicy::class);

        Gate::define('admin', fn ($user) => $user->isAdmin() && $user->is_active);

        Event::listen(Login::class, MergeGuestCartOnLogin::class);
        Event::listen(NotificationSent::class, SendWebPushOnDatabaseNotification::class);

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

            try {
                $navBrands = Cache::remember('storefront.nav_brands', 300, function () {
                    return Brand::query()
                        ->active()
                        ->orderBy('sort_order')
                        ->orderBy('name')
                        ->take(8)
                        ->get(['id', 'name', 'slug', 'logo_path']);
                });
                $navBrandsTotal = Cache::remember('storefront.nav_brands_total', 300, function () {
                    return Brand::query()->active()->count();
                });
            } catch (\Throwable) {
                $navBrands = collect();
                $navBrandsTotal = 0;
            }

            try {
                $hasLiveOffers = app(OfferService::class)->hasLiveOffers();
            } catch (\Throwable) {
                $hasLiveOffers = false;
            }

            $view->with([
                'cartCount' => $cartCount,
                'navCategories' => $navCategories,
                'navBrands' => $navBrands,
                'navBrandsTotal' => $navBrandsTotal,
                'hasLiveOffers' => $hasLiveOffers,
            ]);
        });

        View::composer('layouts.admin', function ($view) {
            $badges = [];

            try {
                $user = Auth::user();
                if ($user && $user->isAdmin()) {
                    $badges = app(AdminNavBadgeService::class)->countsFor($user);
                }
            } catch (\Throwable) {
                $badges = [];
            }

            $view->with('adminNavBadges', $badges);
        });
    }

    /**
     * Force APP_URL only when the browser is already on that host.
     * Forcing localhost while opening the site via a LAN IP breaks CSS/images on phones.
     */
    protected function configureApplicationUrl(): void
    {
        if ($this->app->runningInConsole()) {
            return;
        }

        $root = (string) config('app.url');
        if ($root === '') {
            return;
        }

        $appHost = $this->normalizeHost(parse_url($root, PHP_URL_HOST));
        $requestHost = $this->normalizeHost(request()->getHost());

        if ($appHost !== '' && $appHost === $requestHost) {
            URL::forceRootUrl($root);
        }
    }

    protected function normalizeHost(?string $host): string
    {
        $host = strtolower((string) $host);

        return in_array($host, ['localhost', '127.0.0.1', '::1'], true)
            ? 'localhost'
            : $host;
    }
}
