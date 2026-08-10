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

if (! function_exists('print_fields')) {
    /**
     * @return list<string>
     */
    function print_fields(string $document): array
    {
        $available = array_keys(config("aura.print.{$document}", []));
        $stored = setting("{$document}_fields");

        if (! is_array($stored)) {
            return $available;
        }

        // Legacy: customer_contact covered name + email + phone.
        if ($document === 'invoice' && in_array('customer_contact', $stored, true)) {
            $stored = array_merge($stored, ['customer_name', 'customer_email', 'customer_phone']);
        }

        // Legacy: ship_to included the customer/recipient name.
        if (in_array('ship_to', $stored, true) && ! in_array('customer_name', $stored, true)) {
            $stored[] = 'customer_name';
        }

        return array_values(array_intersect($available, $stored));
    }
}

if (! function_exists('print_shows')) {
    function print_shows(string $document, string $field): bool
    {
        return in_array($field, print_fields($document), true);
    }
}

if (! function_exists('print_page_size')) {
    function print_page_size(string $document): string
    {
        $default = (string) config("aura.print.defaults.{$document}", 'A4');
        $size = strtoupper((string) setting("{$document}_size", $default));

        return array_key_exists($size, config('aura.print.sizes', []))
            ? $size
            : (array_key_exists($default, config('aura.print.sizes', [])) ? $default : 'A4');
    }
}

if (! function_exists('print_page_dims')) {
    /**
     * @return array{name:string,width:string,height:string,margin:string,compact:bool}
     */
    function print_page_dims(string $document): array
    {
        $name = print_page_size($document);
        $dims = config("aura.print.sizes.{$name}", config('aura.print.sizes.A4'));

        return [
            'name' => $name,
            'width' => $dims['width'],
            'height' => $dims['height'],
            'margin' => $dims['margin'],
            'compact' => $name === 'A5',
        ];
    }
}
