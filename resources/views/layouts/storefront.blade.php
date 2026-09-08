<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ is_rtl() ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#2C2A28">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="{{ config('aura.name') }}">
    <title>@yield('title', config('aura.name'))</title>
    @php
        $metaDescription = trim($__env->yieldContent('meta_description', config('aura.tagline', '')));
        $ogTitle = trim($__env->yieldContent('og_title', $__env->yieldContent('title', config('aura.name'))));
        $ogImage = trim($__env->yieldContent('og_image', store_logo_url() ?: ''));
        $canonical = trim($__env->yieldContent('canonical', url()->current()));
    @endphp
    @if($metaDescription !== '')
        <meta name="description" content="{{ $metaDescription }}">
    @endif
    <link rel="canonical" href="{{ $canonical }}">
    <meta property="og:type" content="@yield('og_type', 'website')">
    <meta property="og:site_name" content="{{ config('aura.name') }}">
    <meta property="og:title" content="{{ $ogTitle }}">
    @if($metaDescription !== '')
        <meta property="og:description" content="{{ $metaDescription }}">
    @endif
    <meta property="og:url" content="{{ $canonical }}">
    @if($ogImage !== '')
        <meta property="og:image" content="{{ $ogImage }}">
    @endif
    <meta name="twitter:card" content="{{ $ogImage !== '' ? 'summary_large_image' : 'summary' }}">
    <meta name="twitter:title" content="{{ $ogTitle }}">
    @if($metaDescription !== '')
        <meta name="twitter:description" content="{{ $metaDescription }}">
    @endif
    @if($ogImage !== '')
        <meta name="twitter:image" content="{{ $ogImage }}">
    @endif
    @if(store_favicon_url())
        <link rel="icon" href="{{ store_favicon_url() }}">
        <link rel="apple-touch-icon" href="{{ store_favicon_url() }}">
    @endif
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen flex flex-col bg-ivory text-charcoal overflow-x-hidden" x-data="{ open: false, nav: '' }" @keydown.escape.window="open = false; nav = ''">
    <header class="border-b border-beige/80 bg-[#FFFCFA]/95 backdrop-blur sticky top-0 z-40">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 py-3 sm:py-4">
            <div class="flex items-center justify-between gap-3 lg:hidden">
                <div class="min-w-0 shrink">
                    <x-brand-logo size="md" class="max-w-[160px] sm:max-w-none" />
                </div>
                <form action="{{ route('search') }}" method="GET" class="hidden md:block flex-1 max-w-md">
                    <input type="search" name="q" value="{{ request('q') }}" placeholder="{{ __('storefront.search_placeholder') }}" class="input" aria-label="{{ __('storefront.search') }}">
                </form>
                <div class="flex items-center gap-2">
                    <x-locale-switcher class="hidden sm:inline-flex" />
                    @auth
                        <x-notification-bell
                            :feed-url="route('account.notifications.feed')"
                            :mark-read-url="route('account.notifications.read', ['id' => '__ID__'])"
                            :mark-all-url="route('account.notifications.read-all')"
                            :index-url="route('account.notifications.index')"
                        />
                    @endauth
                    <a href="{{ route('cart.index') }}" class="btn btn-secondary px-3 py-2 relative inline-flex items-center gap-2" aria-label="{{ __('storefront.bag') }}">
                        <x-icon name="bag" class="w-5 h-5" />
                        {{ __('storefront.bag') }}
                        <span data-cart-count class="absolute -top-1 -right-1 text-[10px] bg-blush text-white px-1.5 rounded-full" @if(($cartCount ?? 0) < 1) hidden @endif>{{ $cartCount ?? 0 }}</span>
                    </a>
                    <button class="btn btn-secondary px-3 py-2 relative z-10 inline-flex items-center gap-2" @click="open = true" type="button" aria-label="{{ __('storefront.menu') }}">
                        <x-icon name="menu" class="w-5 h-5" />
                        {{ __('storefront.menu') }}
                    </button>
                </div>
            </div>

            <div class="storefront-header-desktop">
                <div class="storefront-header-left">
                    <div class="min-w-0 shrink">
                        <x-brand-logo size="md" />
                    </div>
                    <form action="{{ route('search') }}" method="GET" class="storefront-header-search">
                        <input type="search" name="q" value="{{ request('q') }}" placeholder="{{ __('storefront.search_placeholder') }}" class="input" aria-label="{{ __('storefront.search') }}">
                    </form>
                </div>

                <nav class="storefront-header-center text-sm tracking-wide">
                <div class="relative" @click.outside="nav === 'shop' && (nav = '')">
                    <button type="button" class="inline-flex items-center gap-1 min-h-11" @click="nav = nav === 'shop' ? '' : 'shop'" :aria-expanded="nav === 'shop'">
                        {{ __('storefront.shop') }} <span class="text-taupe text-xs" :class="nav === 'shop' && 'rotate-90'">›</span>
                    </button>
                    <div
                        x-show="nav === 'shop'"
                        x-cloak
                        class="absolute start-0 top-full z-50 mt-1 min-w-[14rem] border border-beige bg-[#FFFCFA] py-1"
                    >
                        <x-nav-item class="px-4 py-2.5" :href="route('shop')" icon="shop" :label="__('storefront.all_products')" />
                        <x-nav-item class="px-4 py-2.5" :href="route('offers.index')" icon="featured" :label="__('storefront.hot_offers')" />
                        <x-nav-item class="px-4 py-2.5" :href="route('shop', ['gender' => 'women'])" icon="women" :label="__('storefront.women')" />
                        <x-nav-item class="px-4 py-2.5" :href="route('shop', ['gender' => 'men'])" icon="men" :label="__('storefront.men')" />
                        <x-nav-item class="px-4 py-2.5" :href="route('shop', ['gender' => 'unisex'])" icon="unisex" :label="__('storefront.unisex')" />
                        <div class="my-1 border-t border-beige"></div>
                        <x-nav-item class="px-4 py-2.5" :href="route('shop', ['featured' => 1])" icon="featured" :label="__('storefront.featured')" />
                        <x-nav-item class="px-4 py-2.5" :href="route('shop', ['sort' => 'newest'])" icon="new" :label="__('storefront.new_arrivals')" />
                    </div>
                </div>

                @if(! empty($hasLiveOffers))
                    <a href="{{ route('offers.index') }}" class="inline-flex items-center min-h-11 text-blush">{{ __('storefront.hot_offers') }}</a>
                @endif

                @isset($navCategories)
                    @if($navCategories->isNotEmpty())
                        <div class="relative" @click.outside="nav === 'categories' && (nav = '')">
                            <button type="button" class="inline-flex items-center gap-1 min-h-11" @click="nav = nav === 'categories' ? '' : 'categories'" :aria-expanded="nav === 'categories'">
                                {{ __('storefront.categories') }} <span class="text-taupe text-xs" :class="nav === 'categories' && 'rotate-90'">›</span>
                            </button>
                            <div
                                x-show="nav === 'categories'"
                                x-cloak
                                class="absolute start-0 top-full z-50 mt-1 min-w-[14rem] max-h-72 overflow-y-auto border border-beige bg-[#FFFCFA] py-1"
                            >
                                @foreach($navCategories as $navCategory)
                                    <a class="flex items-center gap-2.5 px-4 py-2.5 hover:bg-beige/40" href="{{ route('shop', ['category' => $navCategory->slug]) }}">
                                        <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center">
                                            <img
                                                src="{{ $navCategory->iconUrl() }}"
                                                alt=""
                                                width="20"
                                                height="20"
                                                class="h-5 w-5 object-contain"
                                                loading="eager"
                                            >
                                        </span>
                                        <span>{{ $navCategory->localized('name') }}</span>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @endisset

                @isset($navBrands)
                    @if($navBrands->isNotEmpty())
                        <div class="relative" @click.outside="nav === 'brands' && (nav = '')">
                            <button type="button" class="inline-flex items-center gap-1 min-h-11" @click="nav = nav === 'brands' ? '' : 'brands'" :aria-expanded="nav === 'brands'">
                                {{ __('storefront.brands') }} <span class="text-taupe text-xs" :class="nav === 'brands' && 'rotate-90'">›</span>
                            </button>
                            <div
                                x-show="nav === 'brands'"
                                x-cloak
                                class="absolute start-0 top-full z-50 mt-1 min-w-[14rem] max-h-80 overflow-y-auto border border-beige bg-[#FFFCFA] py-1"
                            >
                                @foreach($navBrands as $navBrand)
                                    <a class="flex items-center gap-2.5 px-4 py-2.5 hover:bg-beige/40" href="{{ route('shop', ['brand' => $navBrand->slug]) }}">
                                        <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center overflow-hidden border border-beige bg-ivory/60">
                                            <img
                                                src="{{ $navBrand->logoUrl() }}"
                                                alt=""
                                                width="20"
                                                height="20"
                                                class="h-5 w-5 object-contain"
                                                loading="eager"
                                            >
                                        </span>
                                        <span>{{ $navBrand->localized('name') }}</span>
                                    </a>
                                @endforeach
                                @if(($navBrandsTotal ?? $navBrands->count()) > 8)
                                    <a
                                        href="{{ route('brands.index') }}"
                                        class="block w-full px-4 py-2.5 text-sm text-taupe hover:bg-beige/40 hover:text-charcoal border-t border-beige"
                                    >
                                        {{ __('storefront.show_more') }}
                                    </a>
                                @endif
                            </div>
                        </div>
                    @endif
                @endisset

                <div class="relative" @click.outside="nav === 'help' && (nav = '')">
                    <button type="button" class="inline-flex items-center gap-1 min-h-11" @click="nav = nav === 'help' ? '' : 'help'" :aria-expanded="nav === 'help'">
                        {{ __('storefront.help') }} <span class="text-taupe text-xs" :class="nav === 'help' && 'rotate-90'">›</span>
                    </button>
                    <div
                        x-show="nav === 'help'"
                        x-cloak
                        class="absolute start-0 top-full z-50 mt-1 min-w-[14rem] border border-beige bg-[#FFFCFA] py-1"
                    >
                        <x-nav-item class="px-4 py-2.5" :href="route('pages.about')" icon="about" :label="__('storefront.about')" />
                        <x-nav-item class="px-4 py-2.5" :href="route('pages.contact')" icon="contact" :label="__('storefront.contact')" />
                        <x-nav-item class="px-4 py-2.5" :href="route('pages.faq')" icon="faq" :label="__('storefront.faq')" />
                        <x-nav-item class="px-4 py-2.5" :href="route('orders.track')" icon="track" :label="__('storefront.track_order')" />
                    </div>
                </div>
                </nav>

                <div class="storefront-header-right text-sm tracking-wide">
                <x-locale-switcher />
                @auth
                    <x-notification-bell
                        :feed-url="route('account.notifications.feed')"
                        :mark-read-url="route('account.notifications.read', ['id' => '__ID__'])"
                        :mark-all-url="route('account.notifications.read-all')"
                        :index-url="route('account.notifications.index')"
                    />
                    <div class="relative" @click.outside="nav === 'account' && (nav = '')">
                        <button type="button" class="inline-flex items-center gap-1 min-h-11" @click="nav = nav === 'account' ? '' : 'account'" :aria-expanded="nav === 'account'">
                            {{ __('storefront.account') }} <span class="text-taupe text-xs" :class="nav === 'account' && 'rotate-90'">›</span>
                        </button>
                        <div
                            x-show="nav === 'account'"
                            x-cloak
                            class="absolute end-0 top-full z-50 mt-1 min-w-[14rem] border border-beige bg-[#FFFCFA] py-1"
                        >
                            <x-nav-item class="px-4 py-2.5" :href="route('account.index')" icon="account" :label="__('storefront.my_account')" />
                            <x-nav-item class="px-4 py-2.5" :href="route('account.notifications.index')" icon="bell" :label="__('storefront.notifications')" />
                            <x-nav-item class="px-4 py-2.5" :href="route('account.orders.index')" icon="orders" :label="__('storefront.orders')" />
                            <x-nav-item class="px-4 py-2.5" :href="route('returns.index')" icon="returns" :label="__('storefront.returns')" />
                            <x-nav-item class="px-4 py-2.5" :href="route('wishlist.index')" icon="wishlist" :label="__('storefront.wishlist')" />
                            @if(auth()->user()->isAdmin())
                                <x-nav-item class="px-4 py-2.5" :href="route('admin.dashboard')" icon="admin" :label="__('storefront.admin')" />
                            @endif
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="flex w-full items-center gap-2.5 px-4 py-2.5 text-start hover:bg-beige/40 hover:text-blush transition-colors">
                                    <x-nav-icon name="logout" />
                                    <span>{{ __('storefront.sign_out') }}</span>
                                </button>
                            </form>
                        </div>
                    </div>
                @else
                    <a href="{{ route('login') }}" class="min-h-11 inline-flex items-center gap-2">
                        <x-nav-icon name="login" class="h-6 w-6" />
                        {{ __('storefront.sign_in') }}
                    </a>
                @endauth

                <a href="{{ route('cart.index') }}" class="relative min-h-11 inline-flex items-center gap-2">
                    <x-nav-icon name="bag" class="h-6 w-6" />
                    {{ __('storefront.bag') }}
                    <span data-cart-count class="absolute -top-2 -right-3 text-[10px] bg-blush text-white px-1.5 rounded-full" @if(($cartCount ?? 0) < 1) hidden @endif>{{ $cartCount ?? 0 }}</span>
                </a>
                </div>
            </div>
        </div>
    </header>

    @if(! empty($hasLiveOffers) && ! request()->routeIs('home', 'offers.*'))
        <a href="{{ route('offers.index') }}" class="block bg-blush text-[#FFFCFA] text-center text-xs sm:text-sm tracking-wide py-2.5 px-4 hover:opacity-90">
            {{ __('storefront.hot_offers_banner') }}
        </a>
    @endif

    {{-- Mobile menu: highest layer, outside header stacking context --}}
    <div class="storefront-mobile-nav lg:hidden" x-show="open" x-cloak>
        <div
            class="storefront-mobile-nav__overlay fixed inset-0 z-[99998] bg-charcoal/40"
            @click="open = false"
            x-show="open"
            x-transition.opacity
        ></div>
        <div
            class="storefront-mobile-nav__panel fixed inset-y-0 end-0 z-[99999] w-[min(20rem,92vw)] bg-[#FFFCFA] border-s border-beige p-5 overflow-y-auto shadow-lg"
            x-show="open"
            x-data="{ section: 'shop' }"
            x-transition:enter="transition transform ease-out duration-250"
            x-transition:enter-start="translate-x-full"
            x-transition:enter-end="translate-x-0"
            x-transition:leave="transition transform ease-in duration-200"
            x-transition:leave-start="translate-x-0"
            x-transition:leave-end="translate-x-full"
            role="dialog"
            aria-modal="true"
            aria-label="{{ __('storefront.site_menu') }}"
        >
            <div class="flex items-center justify-between mb-5">
                <span class="font-display text-2xl">{{ __('storefront.menu') }}</span>
                <button type="button" class="btn btn-secondary px-3 py-2 inline-flex items-center gap-2" @click="open = false" aria-label="{{ __('storefront.close') }}">
                    <x-icon name="close" class="w-5 h-5" />
                    {{ __('storefront.close') }}
                </button>
            </div>

            <div class="mb-4">
                <x-locale-switcher />
            </div>

            <form action="{{ route('search') }}" method="GET" class="mb-5">
                <input type="search" name="q" value="{{ request('q') }}" placeholder="{{ __('storefront.search_placeholder') }}" class="input" aria-label="{{ __('storefront.search') }}">
            </form>

            <nav class="space-y-2 text-sm">
                @if(! empty($hasLiveOffers))
                    <a href="{{ route('offers.index') }}" class="flex items-center justify-between gap-2 px-3 py-2.5 border border-blush/40 bg-blush/5 text-blush" @click="open = false">
                        <span class="inline-flex items-center gap-2.5">
                            <x-nav-icon name="featured" />
                            <span class="font-medium">{{ __('storefront.hot_offers') }}</span>
                        </span>
                        <span class="text-xs uppercase tracking-widest">{{ __('storefront.shop_deals') }}</span>
                    </a>
                @endif

                {{-- Shop --}}
                <div class="border border-beige overflow-hidden">
                    <button type="button" class="flex w-full items-center justify-between gap-2 px-3 py-2.5 text-left bg-ivory/50" @click="section = section === 'shop' ? '' : 'shop'">
                        <span class="inline-flex items-center gap-2.5">
                            <x-nav-icon name="shop" />
                            <span class="font-medium">{{ __('storefront.shop') }}</span>
                        </span>
                        <span class="text-taupe text-xs transition-transform" x-bind:class="section === 'shop' && 'rotate-90'">›</span>
                    </button>
                    <div class="border-t border-beige bg-[#FFFCFA] px-2 py-1.5 space-y-0.5" x-show="section === 'shop'" x-cloak>
                        <x-nav-item class="px-3 py-2.5 rounded-sm" :href="route('shop')" icon="shop" :label="__('storefront.all_products')" @click="open = false" />
                        <x-nav-item class="px-3 py-2.5 rounded-sm" :href="route('offers.index')" icon="featured" :label="__('storefront.hot_offers')" @click="open = false" />
                        <p class="px-3 pt-2 pb-1 text-[10px] uppercase tracking-[0.14em] text-taupe">{{ __('storefront.by_gender') }}</p>
                        <x-nav-item class="px-3 py-2.5 rounded-sm" :href="route('shop', ['gender' => 'women'])" icon="women" :label="__('storefront.women')" @click="open = false" />
                        <x-nav-item class="px-3 py-2.5 rounded-sm" :href="route('shop', ['gender' => 'men'])" icon="men" :label="__('storefront.men')" @click="open = false" />
                        <x-nav-item class="px-3 py-2.5 rounded-sm" :href="route('shop', ['gender' => 'unisex'])" icon="unisex" :label="__('storefront.unisex')" @click="open = false" />
                        <p class="px-3 pt-2 pb-1 text-[10px] uppercase tracking-[0.14em] text-taupe">{{ __('storefront.collections') }}</p>
                        <x-nav-item class="px-3 py-2.5 rounded-sm" :href="route('shop', ['featured' => 1])" icon="featured" :label="__('storefront.featured')" @click="open = false" />
                        <x-nav-item class="px-3 py-2.5 rounded-sm" :href="route('shop', ['sort' => 'newest'])" icon="new" :label="__('storefront.new_arrivals')" @click="open = false" />
                        <x-nav-item class="px-3 py-2.5 rounded-sm" :href="route('cart.index')" icon="bag" :label="__('storefront.bag').' ('.($cartCount ?? 0).')'" @click="open = false" />
                    </div>
                </div>

                {{-- Categories --}}
                @isset($navCategories)
                    @if($navCategories->isNotEmpty())
                        <div class="border border-beige overflow-hidden">
                            <button type="button" class="flex w-full items-center justify-between gap-2 px-3 py-2.5 text-left bg-ivory/50" @click="section = section === 'categories' ? '' : 'categories'">
                                <span class="inline-flex items-center gap-2.5">
                                    <x-nav-icon name="categories" />
                                    <span class="font-medium">{{ __('storefront.categories') }}</span>
                                </span>
                                <span class="text-taupe text-xs transition-transform" x-bind:class="section === 'categories' && 'rotate-90'">›</span>
                            </button>
                            <div class="border-t border-beige bg-[#FFFCFA] px-2 py-1.5 space-y-0.5 max-h-48 overflow-y-auto" x-show="section === 'categories'" x-cloak>
                                @foreach($navCategories as $navCategory)
                                    <a class="flex items-center gap-2.5 px-3 py-2.5 rounded-sm hover:bg-beige/40" href="{{ route('shop', ['category' => $navCategory->slug]) }}" @click="open = false">
                                        <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center">
                                            <img
                                                src="{{ $navCategory->iconUrl() }}"
                                                alt=""
                                                width="20"
                                                height="20"
                                                class="h-5 w-5 object-contain"
                                                loading="eager"
                                            >
                                        </span>
                                        <span>{{ $navCategory->localized('name') }}</span>
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @endisset

                @isset($navBrands)
                    @if($navBrands->isNotEmpty())
                        <div class="border border-beige overflow-hidden">
                            <button type="button" class="flex w-full items-center justify-between gap-2 px-3 py-2.5 text-left bg-ivory/50" @click="section = section === 'brands' ? '' : 'brands'">
                                <span class="inline-flex items-center gap-2.5">
                                    <x-nav-icon name="brands" />
                                    <span class="font-medium">{{ __('storefront.brands') }}</span>
                                </span>
                                <span class="text-taupe text-xs transition-transform" x-bind:class="section === 'brands' && 'rotate-90'">›</span>
                            </button>
                            <div class="border-t border-beige bg-[#FFFCFA] px-2 py-1.5 space-y-0.5 max-h-64 overflow-y-auto" x-show="section === 'brands'" x-cloak>
                                @foreach($navBrands as $navBrand)
                                    <a class="flex items-center gap-2.5 px-3 py-2.5 rounded-sm hover:bg-beige/40" href="{{ route('shop', ['brand' => $navBrand->slug]) }}" @click="open = false">
                                        <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center overflow-hidden border border-beige bg-ivory/60">
                                            <img
                                                src="{{ $navBrand->logoUrl() }}"
                                                alt=""
                                                width="20"
                                                height="20"
                                                class="h-5 w-5 object-contain"
                                                loading="eager"
                                            >
                                        </span>
                                        <span>{{ $navBrand->localized('name') }}</span>
                                    </a>
                                @endforeach
                                @if(($navBrandsTotal ?? $navBrands->count()) > 8)
                                    <a
                                        href="{{ route('brands.index') }}"
                                        class="block w-full px-3 py-2.5 text-sm text-taupe rounded-sm hover:bg-beige/40 hover:text-charcoal border-t border-beige mt-1"
                                        @click="open = false"
                                    >
                                        {{ __('storefront.show_more') }}
                                    </a>
                                @endif
                            </div>
                        </div>
                    @endif
                @endisset

                {{-- Account --}}
                <div class="border border-beige overflow-hidden">
                    <button type="button" class="flex w-full items-center justify-between gap-2 px-3 py-2.5 text-left bg-ivory/50" @click="section = section === 'account' ? '' : 'account'">
                        <span class="inline-flex items-center gap-2.5">
                            <x-nav-icon name="account" />
                            <span class="font-medium">{{ __('storefront.account') }}</span>
                        </span>
                        <span class="text-taupe text-xs transition-transform" x-bind:class="section === 'account' && 'rotate-90'">›</span>
                    </button>
                    <div class="border-t border-beige bg-[#FFFCFA] px-2 py-1.5 space-y-0.5" x-show="section === 'account'" x-cloak>
                        @auth
                            <x-nav-item class="px-3 py-2.5 rounded-sm" :href="route('account.index')" icon="account" :label="__('storefront.my_account')" @click="open = false" />
                            <x-nav-item class="px-3 py-2.5 rounded-sm" :href="route('account.notifications.index')" icon="bell" :label="__('storefront.notifications')" @click="open = false" />
                            <x-nav-item class="px-3 py-2.5 rounded-sm" :href="route('account.orders.index')" icon="orders" :label="__('storefront.orders')" @click="open = false" />
                            <x-nav-item class="px-3 py-2.5 rounded-sm" :href="route('returns.index')" icon="returns" :label="__('storefront.returns')" @click="open = false" />
                            <x-nav-item class="px-3 py-2.5 rounded-sm" :href="route('wishlist.index')" icon="wishlist" :label="__('storefront.wishlist')" @click="open = false" />
                            @if(auth()->user()->isAdmin())
                                <x-nav-item class="px-3 py-2.5 rounded-sm" :href="route('admin.dashboard')" icon="admin" :label="__('storefront.admin_portal')" @click="open = false" />
                            @endif
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="flex w-full items-center gap-2.5 text-left px-3 py-2.5 rounded-sm hover:bg-beige/40">
                                    <x-nav-icon name="logout" />
                                    <span>{{ __('storefront.sign_out') }}</span>
                                </button>
                            </form>
                        @else
                            <x-nav-item class="px-3 py-2.5 rounded-sm" :href="route('login')" icon="login" :label="__('storefront.sign_in')" @click="open = false" />
                            <x-nav-item class="px-3 py-2.5 rounded-sm" :href="route('register')" icon="register" :label="__('storefront.create_account')" @click="open = false" />
                        @endauth
                    </div>
                </div>

                {{-- Help --}}
                <div class="border border-beige overflow-hidden">
                    <button type="button" class="flex w-full items-center justify-between gap-2 px-3 py-2.5 text-left bg-ivory/50" @click="section = section === 'help' ? '' : 'help'">
                        <span class="inline-flex items-center gap-2.5">
                            <x-nav-icon name="help" />
                            <span class="font-medium">{{ __('storefront.help') }}</span>
                        </span>
                        <span class="text-taupe text-xs transition-transform" x-bind:class="section === 'help' && 'rotate-90'">›</span>
                    </button>
                    <div class="border-t border-beige bg-[#FFFCFA] px-2 py-1.5 space-y-0.5" x-show="section === 'help'" x-cloak>
                        <x-nav-item class="px-3 py-2.5 rounded-sm" :href="route('pages.about')" icon="about" :label="__('storefront.about')" @click="open = false" />
                        <x-nav-item class="px-3 py-2.5 rounded-sm" :href="route('pages.contact')" icon="contact" :label="__('storefront.contact')" @click="open = false" />
                        <x-nav-item class="px-3 py-2.5 rounded-sm" :href="route('pages.faq')" icon="faq" :label="__('storefront.faq')" @click="open = false" />
                        <x-nav-item class="px-3 py-2.5 rounded-sm" :href="route('orders.track')" icon="track" :label="__('storefront.track_order')" @click="open = false" />
                    </div>
                </div>
            </nav>
        </div>
    </div>

    @if(session('success'))
        <div class="max-w-7xl mx-auto w-full px-4 pt-4"><div class="alert alert-success">{{ session('success') }}</div></div>
    @endif
    @if(session('error'))
        <div class="max-w-7xl mx-auto w-full px-4 pt-4"><div class="alert alert-error">{{ session('error') }}</div></div>
    @endif
    @if($errors->any())
        <div class="max-w-7xl mx-auto w-full px-4 pt-4">
            <div class="alert alert-error">
                <ul class="list-disc ms-4">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        </div>
    @endif

    <main class="flex-1 w-full min-w-0">
        @yield('content')
    </main>

    <footer class="mt-12 sm:mt-16 border-t border-beige bg-[#F3EEE7]">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 py-10 sm:py-12 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-8 text-sm">
            <div class="md:col-span-2">
                <div class="mb-3"><x-brand-logo size="lg" /></div>
                <p class="text-taupe max-w-md">{{ config('aura.tagline') }}</p>
            </div>
            <div>
                <div class="label mb-3">{{ __('storefront.shop') }}</div>
                <div class="space-y-1">
                    <x-nav-item class="py-1.5" :href="route('shop')" icon="shop" :label="__('storefront.all_products')" />
                    <x-nav-item class="py-1.5" :href="route('offers.index')" icon="featured" :label="__('storefront.hot_offers')" />
                    <x-nav-item class="py-1.5" :href="route('shop', ['gender' => 'women'])" icon="women" :label="__('storefront.women')" />
                    <x-nav-item class="py-1.5" :href="route('shop', ['gender' => 'men'])" icon="men" :label="__('storefront.men')" />
                    <x-nav-item class="py-1.5" :href="route('shop', ['gender' => 'unisex'])" icon="unisex" :label="__('storefront.unisex')" />
                    <x-nav-item class="py-1.5" :href="route('shop', ['featured' => 1])" icon="featured" :label="__('storefront.featured')" />
                </div>
            </div>
            <div>
                <div class="label mb-3">{{ __('storefront.help') }}</div>
                <div class="space-y-1">
                    <x-nav-item class="py-1.5" :href="route('pages.about')" icon="about" :label="__('storefront.about')" />
                    <x-nav-item class="py-1.5" :href="route('pages.contact')" icon="contact" :label="__('storefront.contact')" />
                    <x-nav-item class="py-1.5" :href="route('pages.faq')" icon="faq" :label="__('storefront.faq')" />
                    <x-nav-item class="py-1.5" :href="route('orders.track')" icon="track" :label="__('storefront.track_order')" />
                    <x-nav-item class="py-1.5" :href="route('returns.index')" icon="returns" :label="__('storefront.returns')" />
                    <x-nav-item class="py-1.5" :href="route('pages.show', 'privacy-policy')" icon="faq" :label="__('storefront.privacy')" />
                    <x-nav-item class="py-1.5" :href="route('pages.show', 'terms-of-service')" icon="faq" :label="__('storefront.terms')" />
                </div>
                @if(site_flag('show_newsletter'))
                <div class="label mt-8 mb-3">{{ __('storefront.newsletter') }}</div>
                <form
                    method="POST"
                    action="{{ route('newsletter.store') }}"
                    class="space-y-2"
                    x-data="{ submitting: false, done: false }"
                    @submit.prevent="
                        if (submitting || done) return;
                        submitting = true;
                        const form = $event.target;
                        const body = new FormData(form);
                        window.auraHttp(form.action, { method: 'POST', body })
                            .then((data) => {
                                done = true;
                                form.reset();
                                window.dispatchEvent(new CustomEvent('aura:toast', {
                                    detail: { message: data.message || 'Subscribed.', type: 'success' },
                                }));
                            })
                            .catch((error) => {
                                window.dispatchEvent(new CustomEvent('aura:toast', {
                                    detail: { message: error.message || 'Could not subscribe.', type: 'error' },
                                }));
                            })
                            .finally(() => { submitting = false; });
                    "
                >
                    @csrf
                    <input type="email" name="email" required placeholder="Email" class="input" aria-label="Newsletter email" :disabled="submitting || done">
                    <button class="btn btn-primary w-full" type="submit" :disabled="submitting || done">
                        <span x-show="!submitting && !done">{{ __('storefront.subscribe') }}</span>
                        <span x-show="submitting" x-cloak>{{ __('storefront.saving') }}</span>
                        <span x-show="done && !submitting" x-cloak>{{ __('storefront.subscribed') }}</span>
                    </button>
                </form>
                @endif
                <x-store-contact class="mt-4 text-taupe text-xs" />
                @if(site_flag('show_social_links') && count($socialLinks = \App\Support\SiteOptions::socialLinks()))
                    <div class="mt-3 flex flex-wrap gap-x-3 gap-y-1 text-xs">
                        @foreach($socialLinks as $network)
                            <a class="underline decoration-beige underline-offset-2 hover:text-charcoal" href="{{ $network['url'] }}" target="_blank" rel="noopener noreferrer">{{ $network['label'] }}</a>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </footer>
    <x-toast />
    @auth
        <x-push-prompt />
    @endauth
</body>
</html>
