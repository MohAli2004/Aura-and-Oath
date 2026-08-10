<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin') — {{ setting('store_name', config('aura.name')) }}</title>
    @if(store_favicon_url())
        <link rel="icon" href="{{ store_favicon_url() }}">
    @endif
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-ivory text-charcoal overflow-x-hidden">
@php
    $adminLinkGroups = [
        'Overview' => [
            ['admin.dashboard', 'Dashboard', 'dashboard'],
            ['admin.reports.index', 'Reports', 'reports'],
        ],
        'Catalog' => [
            ['admin.products.index', 'Products', 'products'],
            ['admin.categories.index', 'Categories', 'categories'],
            ['admin.brands.index', 'Brands', 'brands'],
            ['admin.attributes.index', 'Attributes', 'attributes'],
            ['admin.inventory.index', 'Inventory', 'inventory'],
            ['admin.barcodes.index', 'Barcode Lookup', 'barcode'],
        ],
        'Sales' => [
            ['admin.orders.index', 'Orders', 'orders'],
            ['admin.customers.index', 'Customers', 'customers'],
            ['admin.coupons.index', 'Coupons', 'coupons'],
        ],
        'Marketing' => [
            ['admin.banners.index', 'Banners', 'banners'],
        ],
        'System' => [
            ['admin.notifications.index', 'Notifications', 'bell'],
            ['admin.settings.edit', 'Settings', 'settings'],
            ['admin.audit-logs.index', 'Audit Log', 'audit'],
        ],
    ];
@endphp

<div
    class="min-h-screen flex"
    x-data="{
        sidebarOpen: false,
        desktopCollapsed: localStorage.getItem('aura_admin_sidebar') === '1',
        toggleDesktop() {
            this.desktopCollapsed = !this.desktopCollapsed;
            localStorage.setItem('aura_admin_sidebar', this.desktopCollapsed ? '1' : '0');
        },
        closeMobile() { this.sidebarOpen = false; }
    }"
    @keydown.escape.window="sidebarOpen = false"
>
    <div
        class="fixed inset-0 z-[99998] bg-charcoal/40 backdrop-blur-[1px] lg:hidden"
        x-show="sidebarOpen"
        x-transition.opacity
        x-cloak
        @click="closeMobile()"
    ></div>

    <aside
        class="admin-sidebar fixed inset-y-0 left-0 z-[99999] flex w-[min(18rem,88vw)] flex-col border-r border-beige bg-[#FFFCFA] p-4 shadow-lg transition-[transform,width] duration-300 ease-out lg:translate-x-0"
        :class="{
            'translate-x-0': sidebarOpen,
            '-translate-x-full': !sidebarOpen,
            'lg:w-64': !desktopCollapsed,
            'lg:w-20': desktopCollapsed
        }"
    >
        <div class="mb-6 flex items-center justify-between gap-2">
            <div class="min-w-0 flex-1 overflow-hidden" :class="desktopCollapsed && 'lg:hidden'">
                <x-brand-logo size="sm" href="{{ route('admin.dashboard') }}" />
            </div>
            <button type="button" class="btn btn-secondary px-2 py-2 lg:hidden" @click="closeMobile()" aria-label="Close menu">
                <x-icon name="close" class="w-5 h-5" />
            </button>
            <button
                type="button"
                class="btn btn-secondary px-2 py-2 hidden lg:inline-flex"
                @click="toggleDesktop()"
                :aria-label="desktopCollapsed ? 'Expand sidebar' : 'Collapse sidebar'"
                :title="desktopCollapsed ? 'Expand' : 'Collapse'"
            >
                <x-icon name="chevron-left" class="w-5 h-5" x-show="!desktopCollapsed" />
                <x-icon name="chevron-right" class="w-5 h-5" x-show="desktopCollapsed" x-cloak />
            </button>
        </div>

        <nav class="flex-1 space-y-4 overflow-y-auto text-sm pb-6">
            @foreach($adminLinkGroups as $groupLabel => $links)
                <div>
                    <div class="px-3 mb-1 text-[10px] uppercase tracking-[0.16em] text-taupe" :class="desktopCollapsed && 'lg:text-center lg:px-1'">
                        <span :class="desktopCollapsed && 'lg:hidden'">{{ $groupLabel }}</span>
                        <span class="hidden" :class="desktopCollapsed && 'lg:inline'" x-show="desktopCollapsed">•</span>
                    </div>
                    <div class="space-y-1">
                        @foreach($links as [$route, $label, $icon])
                            <a
                                href="{{ route($route) }}"
                                @click="closeMobile()"
                                class="flex items-center justify-start gap-3 px-3 py-2.5 rounded-sm min-h-11 {{ request()->routeIs(str_replace('.index','.*', $route)) || request()->routeIs($route) ? 'active' : '' }}"
                                :class="desktopCollapsed && 'lg:justify-center lg:px-2'"
                                title="{{ $label }}"
                            >
                                <x-icon :name="$icon" class="w-5 h-5 text-taupe" />
                                <span class="truncate" :class="desktopCollapsed && 'lg:hidden'">{{ $label }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endforeach

            <div class="pt-2 border-t border-beige">
                <a href="{{ route('home') }}" @click="closeMobile()" class="flex items-center gap-3 px-3 py-2.5 text-taupe min-h-11" :class="desktopCollapsed && 'lg:justify-center lg:px-2'" title="Storefront">
                    <x-icon name="storefront" class="w-5 h-5" />
                    <span :class="desktopCollapsed && 'lg:hidden'">Storefront</span>
                </a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="flex w-full items-center gap-3 px-3 py-2.5 text-left min-h-11 rounded-sm hover:bg-beige/40" :class="desktopCollapsed && 'lg:justify-center lg:px-2'" title="Logout">
                        <x-icon name="logout" class="w-5 h-5 text-taupe" />
                        <span :class="desktopCollapsed && 'lg:hidden'">Logout</span>
                    </button>
                </form>
            </div>
        </nav>
    </aside>

    <div
        class="flex min-w-0 flex-1 flex-col transition-[padding] duration-300"
        :class="desktopCollapsed ? 'lg:pl-20' : 'lg:pl-64'"
    >
        <header class="sticky top-0 z-30 flex items-center justify-between gap-3 border-b border-beige bg-[#FFFCFA]/95 px-3 py-3 backdrop-blur sm:px-6">
            <div class="flex min-w-0 items-center gap-3">
                <button type="button" class="btn btn-secondary px-3 py-2 lg:hidden inline-flex items-center gap-2" @click="sidebarOpen = true" aria-label="Open menu">
                    <x-icon name="menu" class="w-4 h-4" />
                    <span>Menu</span>
                </button>
                <h1 class="font-display truncate text-xl sm:text-2xl">@yield('heading', 'Admin')</h1>
            </div>
            <div class="flex items-center gap-3">
                <x-notification-bell
                    :feed-url="route('admin.notifications.feed')"
                    :mark-read-url="route('admin.notifications.read', ['id' => '__ID__'])"
                    :mark-all-url="route('admin.notifications.read-all')"
                    :index-url="route('admin.notifications.index')"
                />
                <div class="truncate text-xs text-taupe sm:text-sm">{{ auth()->user()->name }}</div>
            </div>
        </header>

        <div class="p-3 sm:p-6">
            @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
            @if(session('error'))<div class="alert alert-error">{{ session('error') }}</div>@endif
            @if($errors->any())
                <div class="alert alert-error"><ul class="list-disc ms-4">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
            @endif
            <div class="admin-content">
                @yield('content')
            </div>
        </div>
    </div>
</div>
</body>
</html>
