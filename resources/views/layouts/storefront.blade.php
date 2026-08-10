<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('aura.name'))</title>
    @if(store_favicon_url())
        <link rel="icon" href="{{ store_favicon_url() }}">
    @endif
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen flex flex-col bg-ivory text-charcoal overflow-x-hidden" x-data="{ open: false, nav: '' }" @keydown.escape.window="open = false; nav = ''">
    <header class="border-b border-beige/80 bg-[#FFFCFA]/95 backdrop-blur sticky top-0 z-40">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 py-3 sm:py-4 flex items-center justify-between gap-3">
            <div class="min-w-0 shrink">
                <x-brand-logo size="md" class="max-w-[160px] sm:max-w-none" />
            </div>

            <form action="{{ route('search') }}" method="GET" class="hidden md:block flex-1 max-w-md">
                <input type="search" name="q" value="{{ request('q') }}" placeholder="Search products..." class="input" aria-label="Search products">
            </form>

            <nav class="hidden lg:flex items-center gap-5 xl:gap-6 text-sm tracking-wide">
                <div class="relative" @click.outside="nav === 'shop' && (nav = '')">
                    <button type="button" class="inline-flex items-center gap-1 min-h-11" @click="nav = nav === 'shop' ? '' : 'shop'" :aria-expanded="nav === 'shop'">
                        Shop <span class="text-taupe text-xs" :class="nav === 'shop' && 'rotate-90'">›</span>
                    </button>
                    <div
                        x-show="nav === 'shop'"
                        x-cloak
                        class="absolute start-0 top-full z-50 mt-1 min-w-[14rem] border border-beige bg-[#FFFCFA] py-1"
                    >
                        <x-nav-item class="px-4 py-2.5" :href="route('shop')" icon="shop" label="All products" />
                        <x-nav-item class="px-4 py-2.5" :href="route('shop', ['gender' => 'women'])" icon="women" label="Women" />
                        <x-nav-item class="px-4 py-2.5" :href="route('shop', ['gender' => 'men'])" icon="men" label="Men" />
                        <x-nav-item class="px-4 py-2.5" :href="route('shop', ['gender' => 'unisex'])" icon="unisex" label="Unisex" />
                        <div class="my-1 border-t border-beige"></div>
                        <x-nav-item class="px-4 py-2.5" :href="route('shop', ['featured' => 1])" icon="featured" label="Featured" />
                        <x-nav-item class="px-4 py-2.5" :href="route('shop', ['sort' => 'newest'])" icon="new" label="New arrivals" />
                    </div>
                </div>

                @isset($navCategories)
                    @if($navCategories->isNotEmpty())
                        <div class="relative" @click.outside="nav === 'categories' && (nav = '')">
                            <button type="button" class="inline-flex items-center gap-1 min-h-11" @click="nav = nav === 'categories' ? '' : 'categories'" :aria-expanded="nav === 'categories'">
                                Categories <span class="text-taupe text-xs" :class="nav === 'categories' && 'rotate-90'">›</span>
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
                                        <span>{{ $navCategory->name }}</span>
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
                                Brands <span class="text-taupe text-xs" :class="nav === 'brands' && 'rotate-90'">›</span>
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
                                        <span>{{ $navBrand->name }}</span>
                                    </a>
                                @endforeach
                                @if(($navBrandsTotal ?? $navBrands->count()) > 8)
                                    <a
                                        href="{{ route('brands.index') }}"
                                        class="block w-full px-4 py-2.5 text-sm text-taupe hover:bg-beige/40 hover:text-charcoal border-t border-beige"
                                    >
                                        Show more
                                    </a>
                                @endif
                            </div>
                        </div>
                    @endif
                @endisset

                <div class="relative" @click.outside="nav === 'help' && (nav = '')">
                    <button type="button" class="inline-flex items-center gap-1 min-h-11" @click="nav = nav === 'help' ? '' : 'help'" :aria-expanded="nav === 'help'">
                        Help <span class="text-taupe text-xs" :class="nav === 'help' && 'rotate-90'">›</span>
                    </button>
                    <div
                        x-show="nav === 'help'"
                        x-cloak
                        class="absolute start-0 top-full z-50 mt-1 min-w-[14rem] border border-beige bg-[#FFFCFA] py-1"
                    >
                        <x-nav-item class="px-4 py-2.5" :href="route('pages.about')" icon="about" label="About" />
                        <x-nav-item class="px-4 py-2.5" :href="route('pages.contact')" icon="contact" label="Contact" />
                        <x-nav-item class="px-4 py-2.5" :href="route('pages.faq')" icon="faq" label="FAQ" />
                        <x-nav-item class="px-4 py-2.5" :href="route('orders.track')" icon="track" label="Track order" />
                    </div>
                </div>

                @auth
                    <x-notification-bell
                        :feed-url="route('account.notifications.feed')"
                        :mark-read-url="route('account.notifications.read', ['id' => '__ID__'])"
                        :mark-all-url="route('account.notifications.read-all')"
                        :index-url="route('account.notifications.index')"
                    />
                    <div class="relative" @click.outside="nav === 'account' && (nav = '')">
                        <button type="button" class="inline-flex items-center gap-1 min-h-11" @click="nav = nav === 'account' ? '' : 'account'" :aria-expanded="nav === 'account'">
                            Account <span class="text-taupe text-xs" :class="nav === 'account' && 'rotate-90'">›</span>
                        </button>
                        <div
                            x-show="nav === 'account'"
                            x-cloak
                            class="absolute end-0 top-full z-50 mt-1 min-w-[14rem] border border-beige bg-[#FFFCFA] py-1"
                        >
                            <x-nav-item class="px-4 py-2.5" :href="route('account.index')" icon="account" label="My account" />
                            <x-nav-item class="px-4 py-2.5" :href="route('account.notifications.index')" icon="bell" label="Notifications" />
                            <x-nav-item class="px-4 py-2.5" :href="route('wishlist.index')" icon="wishlist" label="Wishlist" />
                            @if(auth()->user()->isAdmin())
                                <x-nav-item class="px-4 py-2.5" :href="route('admin.dashboard')" icon="admin" label="Admin" />
                            @endif
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="flex w-full items-center gap-2.5 px-4 py-2.5 text-start hover:bg-beige/40 hover:text-blush transition-colors">
                                    <x-nav-icon name="logout" />
                                    <span>Sign out</span>
                                </button>
                            </form>
                        </div>
                    </div>
                @else
                    <a href="{{ route('login') }}" class="min-h-11 inline-flex items-center gap-2">
                        <x-nav-icon name="login" class="h-6 w-6" />
                        Sign in
                    </a>
                @endauth

                <a href="{{ route('cart.index') }}" class="relative min-h-11 inline-flex items-center gap-2">
                    <x-nav-icon name="bag" class="h-6 w-6" />
                    Bag
                    @if(($cartCount ?? 0) > 0)
                        <span class="absolute -top-2 -right-3 text-[10px] bg-blush text-white px-1.5 rounded-full">{{ $cartCount }}</span>
                    @endif
                </a>
            </nav>

            <div class="flex items-center gap-2 lg:hidden">
                @auth
                    <x-notification-bell
                        :feed-url="route('account.notifications.feed')"
                        :mark-read-url="route('account.notifications.read', ['id' => '__ID__'])"
                        :mark-all-url="route('account.notifications.read-all')"
                        :index-url="route('account.notifications.index')"
                    />
                @endauth
                <a href="{{ route('cart.index') }}" class="btn btn-secondary px-3 py-2 relative inline-flex items-center gap-2" aria-label="Bag">
                    <x-icon name="bag" class="w-5 h-5" />
                    Bag
                    @if(($cartCount ?? 0) > 0)
                        <span class="absolute -top-1 -right-1 text-[10px] bg-blush text-white px-1.5 rounded-full">{{ $cartCount }}</span>
                    @endif
                </a>
                <button class="btn btn-secondary px-3 py-2 relative z-[100000] inline-flex items-center gap-2" @click="open = true" type="button" aria-label="Open menu">
                    <x-icon name="menu" class="w-5 h-5" />
                    Menu
                </button>
            </div>
        </div>
    </header>

    {{-- Mobile menu: highest layer, outside header stacking context --}}
    <div class="storefront-mobile-nav lg:hidden" x-show="open" x-cloak>
        <div
            class="storefront-mobile-nav__overlay fixed inset-0 z-[99998] bg-charcoal/40"
            @click="open = false"
            x-show="open"
            x-transition.opacity
        ></div>
        <div
            class="storefront-mobile-nav__panel fixed inset-y-0 right-0 z-[99999] w-[min(20rem,92vw)] bg-[#FFFCFA] border-l border-beige p-5 overflow-y-auto shadow-lg"
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
            aria-label="Site menu"
        >
            <div class="flex items-center justify-between mb-5">
                <span class="font-display text-2xl">Menu</span>
                <button type="button" class="btn btn-secondary px-3 py-2 inline-flex items-center gap-2" @click="open = false" aria-label="Close menu">
                    <x-icon name="close" class="w-5 h-5" />
                    Close
                </button>
            </div>

            <form action="{{ route('search') }}" method="GET" class="mb-5">
                <input type="search" name="q" value="{{ request('q') }}" placeholder="Search products..." class="input" aria-label="Search products">
            </form>

            <nav class="space-y-2 text-sm">
                {{-- Shop --}}
                <div class="border border-beige overflow-hidden">
                    <button type="button" class="flex w-full items-center justify-between gap-2 px-3 py-2.5 text-left bg-ivory/50" @click="section = section === 'shop' ? '' : 'shop'">
                        <span class="inline-flex items-center gap-2.5">
                            <x-nav-icon name="shop" />
                            <span class="font-medium">Shop</span>
                        </span>
                        <span class="text-taupe text-xs transition-transform" x-bind:class="section === 'shop' && 'rotate-90'">›</span>
                    </button>
                    <div class="border-t border-beige bg-[#FFFCFA] px-2 py-1.5 space-y-0.5" x-show="section === 'shop'" x-cloak>
                        <x-nav-item class="px-3 py-2.5 rounded-sm" :href="route('shop')" icon="shop" label="All products" @click="open = false" />
                        <p class="px-3 pt-2 pb-1 text-[10px] uppercase tracking-[0.14em] text-taupe">By gender</p>
                        <x-nav-item class="px-3 py-2.5 rounded-sm" :href="route('shop', ['gender' => 'women'])" icon="women" label="Women" @click="open = false" />
                        <x-nav-item class="px-3 py-2.5 rounded-sm" :href="route('shop', ['gender' => 'men'])" icon="men" label="Men" @click="open = false" />
                        <x-nav-item class="px-3 py-2.5 rounded-sm" :href="route('shop', ['gender' => 'unisex'])" icon="unisex" label="Unisex" @click="open = false" />
                        <p class="px-3 pt-2 pb-1 text-[10px] uppercase tracking-[0.14em] text-taupe">Collections</p>
                        <x-nav-item class="px-3 py-2.5 rounded-sm" :href="route('shop', ['featured' => 1])" icon="featured" label="Featured" @click="open = false" />
                        <x-nav-item class="px-3 py-2.5 rounded-sm" :href="route('shop', ['sort' => 'newest'])" icon="new" label="New arrivals" @click="open = false" />
                        <x-nav-item class="px-3 py-2.5 rounded-sm" :href="route('cart.index')" icon="bag" label="Bag ({{ $cartCount ?? 0 }})" @click="open = false" />
                    </div>
                </div>

                {{-- Categories --}}
                @isset($navCategories)
                    @if($navCategories->isNotEmpty())
                        <div class="border border-beige overflow-hidden">
                            <button type="button" class="flex w-full items-center justify-between gap-2 px-3 py-2.5 text-left bg-ivory/50" @click="section = section === 'categories' ? '' : 'categories'">
                                <span class="inline-flex items-center gap-2.5">
                                    <x-nav-icon name="categories" />
                                    <span class="font-medium">Categories</span>
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
                                        <span>{{ $navCategory->name }}</span>
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
                                    <span class="font-medium">Brands</span>
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
                                        <span>{{ $navBrand->name }}</span>
                                    </a>
                                @endforeach
                                @if(($navBrandsTotal ?? $navBrands->count()) > 8)
                                    <a
                                        href="{{ route('brands.index') }}"
                                        class="block w-full px-3 py-2.5 text-sm text-taupe rounded-sm hover:bg-beige/40 hover:text-charcoal border-t border-beige mt-1"
                                        @click="open = false"
                                    >
                                        Show more
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
                            <span class="font-medium">Account</span>
                        </span>
                        <span class="text-taupe text-xs transition-transform" x-bind:class="section === 'account' && 'rotate-90'">›</span>
                    </button>
                    <div class="border-t border-beige bg-[#FFFCFA] px-2 py-1.5 space-y-0.5" x-show="section === 'account'" x-cloak>
                        @auth
                            <x-nav-item class="px-3 py-2.5 rounded-sm" :href="route('account.index')" icon="account" label="My account" @click="open = false" />
                            <x-nav-item class="px-3 py-2.5 rounded-sm" :href="route('account.notifications.index')" icon="bell" label="Notifications" @click="open = false" />
                            <x-nav-item class="px-3 py-2.5 rounded-sm" :href="route('account.orders.index')" icon="orders" label="Orders" @click="open = false" />
                            <x-nav-item class="px-3 py-2.5 rounded-sm" :href="route('wishlist.index')" icon="wishlist" label="Wishlist" @click="open = false" />
                            @if(auth()->user()->isAdmin())
                                <x-nav-item class="px-3 py-2.5 rounded-sm" :href="route('admin.dashboard')" icon="admin" label="Admin portal" @click="open = false" />
                            @endif
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="flex w-full items-center gap-2.5 text-left px-3 py-2.5 rounded-sm hover:bg-beige/40">
                                    <x-nav-icon name="logout" />
                                    <span>Sign out</span>
                                </button>
                            </form>
                        @else
                            <x-nav-item class="px-3 py-2.5 rounded-sm" :href="route('login')" icon="login" label="Sign in" @click="open = false" />
                            <x-nav-item class="px-3 py-2.5 rounded-sm" :href="route('register')" icon="register" label="Create account" @click="open = false" />
                        @endauth
                    </div>
                </div>

                {{-- Help --}}
                <div class="border border-beige overflow-hidden">
                    <button type="button" class="flex w-full items-center justify-between gap-2 px-3 py-2.5 text-left bg-ivory/50" @click="section = section === 'help' ? '' : 'help'">
                        <span class="inline-flex items-center gap-2.5">
                            <x-nav-icon name="help" />
                            <span class="font-medium">Help</span>
                        </span>
                        <span class="text-taupe text-xs transition-transform" x-bind:class="section === 'help' && 'rotate-90'">›</span>
                    </button>
                    <div class="border-t border-beige bg-[#FFFCFA] px-2 py-1.5 space-y-0.5" x-show="section === 'help'" x-cloak>
                        <x-nav-item class="px-3 py-2.5 rounded-sm" :href="route('pages.about')" icon="about" label="About" @click="open = false" />
                        <x-nav-item class="px-3 py-2.5 rounded-sm" :href="route('pages.contact')" icon="contact" label="Contact" @click="open = false" />
                        <x-nav-item class="px-3 py-2.5 rounded-sm" :href="route('pages.faq')" icon="faq" label="FAQ" @click="open = false" />
                        <x-nav-item class="px-3 py-2.5 rounded-sm" :href="route('orders.track')" icon="track" label="Track order" @click="open = false" />
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
                <div class="label mb-3">Shop</div>
                <div class="space-y-1">
                    <x-nav-item class="py-1.5" :href="route('shop')" icon="shop" label="All products" />
                    <x-nav-item class="py-1.5" :href="route('shop', ['gender' => 'women'])" icon="women" label="Women" />
                    <x-nav-item class="py-1.5" :href="route('shop', ['gender' => 'men'])" icon="men" label="Men" />
                    <x-nav-item class="py-1.5" :href="route('shop', ['gender' => 'unisex'])" icon="unisex" label="Unisex" />
                    <x-nav-item class="py-1.5" :href="route('shop', ['featured' => 1])" icon="featured" label="Featured" />
                </div>
            </div>
            <div>
                <div class="label mb-3">Help</div>
                <div class="space-y-1">
                    <x-nav-item class="py-1.5" :href="route('pages.about')" icon="about" label="About" />
                    <x-nav-item class="py-1.5" :href="route('pages.contact')" icon="contact" label="Contact" />
                    <x-nav-item class="py-1.5" :href="route('pages.faq')" icon="faq" label="FAQ" />
                    <x-nav-item class="py-1.5" :href="route('orders.track')" icon="track" label="Track order" />
                    <x-nav-item class="py-1.5" :href="route('pages.show', 'shipping-policy')" icon="shipping" label="Shipping" />
                    <x-nav-item class="py-1.5" :href="route('pages.show', 'returns-policy')" icon="returns" label="Returns" />
                </div>
                <div class="label mt-8 mb-3">Newsletter</div>
                <form method="POST" action="{{ route('newsletter.store') }}" class="space-y-2">
                    @csrf
                    <input type="email" name="email" required placeholder="Email" class="input" aria-label="Newsletter email">
                    <button class="btn btn-primary w-full" type="submit">Subscribe</button>
                </form>
                <p class="mt-4 text-taupe text-xs break-words">{{ config('aura.contact.email') }} · {{ config('aura.contact.phone') }}</p>
            </div>
        </div>
    </footer>
</body>
</html>
