<?php

if (! function_exists('money')) {
    function money(float|int|string|null $amount, ?string $currency = null): string
    {
        return \App\Support\Money::format($amount, $currency);
    }
}

if (! function_exists('setting')) {
    function setting(string $key, mixed $default = null): mixed
    {
        return app(\App\Services\SettingsService::class)->get($key, $default);
    }
}

if (! function_exists('aura')) {
    function aura(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return config('aura');
        }

        return config('aura.'.$key, $default);
    }
}

if (! function_exists('store_logo_url')) {
    function store_logo_url(): ?string
    {
        try {
            $path = setting('logo_path');
        } catch (\Throwable) {
            $path = null;
        }

        if ($path) {
            return app(\App\Services\ImageService::class)->url($path);
        }

        return asset('images/logo.png');
    }
}

if (! function_exists('store_favicon_url')) {
    function store_favicon_url(): ?string
    {
        try {
            $path = setting('favicon_path');
        } catch (\Throwable) {
            $path = null;
        }

        if ($path) {
            return app(\App\Services\ImageService::class)->url($path);
        }

        return asset('images/favicon.png');
    }
}

if (! function_exists('store_home_background_url')) {
    function store_home_background_url(): ?string
    {
        try {
            $path = setting('home_background_path');
        } catch (\Throwable) {
            $path = null;
        }

        if ($path) {
            return app(\App\Services\ImageService::class)->url($path);
        }

        return asset('images/home-hero.png');
    }
}
