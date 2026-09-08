<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ is_rtl() ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#2C2A28">
    <title>@yield('title', config('aura.name'))</title>
    @php
        $metaDescription = seo_truncate(trim($__env->yieldContent('meta_description') ?: config('aura.tagline', '')), 155);
        $canonicalOverride = trim($__env->yieldContent('canonical') ?: '');
        $canonical = seo_canonical($canonicalOverride !== '' ? $canonicalOverride : null);
        $hreflangAlternates = seo_hreflang_alternates($canonical);
        $robotsDirective = trim($__env->yieldContent('robots') ?: 'index, follow');
    @endphp
    @if($metaDescription !== '')
        <meta name="description" content="{{ $metaDescription }}">
    @endif
    @if($robotsDirective !== '')
        <meta name="robots" content="{{ $robotsDirective }}">
    @endif
    <link rel="canonical" href="{{ $canonical }}">
    @foreach($hreflangAlternates as $hreflang => $href)
        <link rel="alternate" hreflang="{{ $hreflang }}" href="{{ $href }}">
    @endforeach
    @if(store_favicon_url())
        <link rel="icon" href="{{ store_favicon_url() }}">
    @endif
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-ivory text-charcoal flex flex-col items-center px-4 py-10 sm:py-14"
      style="background: linear-gradient(160deg, #F7F3EE 0%, #E8DFD4 50%, #F3E8E4 100%);">
    <div class="w-full max-w-md flex flex-col items-center flex-1">
        <div class="mb-5">
            <x-brand-logo size="lg" href="{{ route('home') }}" class="justify-center max-w-full" />
        </div>
        <div class="mb-8">
            <x-locale-switcher class="justify-center" />
        </div>
        @yield('content')
    </div>
</body>
</html>
