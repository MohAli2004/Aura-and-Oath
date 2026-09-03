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

if (! function_exists('site_flag')) {
    function site_flag(string $key): bool
    {
        return \App\Support\SiteOptions::flag($key);
    }
}

if (! function_exists('store_contact_phone')) {
    function store_contact_phone(): string
    {
        return \App\Support\SiteOptions::fieldValue('contact_phone');
    }
}

if (! function_exists('store_public_phone')) {
    function store_public_phone(): ?string
    {
        if (! site_flag('show_phone_to_customers')) {
            return null;
        }

        $phone = store_contact_phone();

        return $phone !== '' ? $phone : null;
    }
}

if (! function_exists('store_public_email')) {
    function store_public_email(): ?string
    {
        if (! site_flag('show_email_to_customers')) {
            return null;
        }

        $email = \App\Support\SiteOptions::fieldValue('support_email');

        return $email !== '' ? $email : null;
    }
}

if (! function_exists('store_public_whatsapp')) {
    function store_public_whatsapp(): ?string
    {
        if (! site_flag('show_whatsapp_to_customers')) {
            return null;
        }

        $phone = \App\Support\SiteOptions::fieldValue('contact_whatsapp');

        return $phone !== '' ? $phone : null;
    }
}

if (! function_exists('store_whatsapp_url')) {
    function store_whatsapp_url(): ?string
    {
        $phone = store_public_whatsapp();
        if ($phone === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone);

        return $digits ? 'https://wa.me/'.$digits : null;
    }
}

if (! function_exists('store_public_address')) {
    function store_public_address(): ?string
    {
        if (! site_flag('show_address_to_customers')) {
            return null;
        }

        $address = \App\Support\SiteOptions::fieldValue('contact_address');

        return $address !== '' ? $address : null;
    }
}

if (! function_exists('store_public_hours')) {
    function store_public_hours(): ?string
    {
        if (! site_flag('show_hours_to_customers')) {
            return null;
        }

        $hours = \App\Support\SiteOptions::fieldValue('support_hours');

        return $hours !== '' ? $hours : null;
    }
}

if (! function_exists('store_wish')) {
    function store_wish(string $key): string
    {
        return \App\Support\SiteOptions::fieldValue('wish_'.$key);
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

if (! function_exists('csp_nonce')) {
    function csp_nonce(): string
    {
        return (string) (\App\Http\Middleware\SecurityHeaders::$nonce ?? '');
    }
}

if (! function_exists('safe_url')) {
    /**
     * Keep a stored URL usable as an href/redirect target: relative paths, or
     * absolute URLs pointing back at this site. Anything else (javascript:,
     * data:, another host) becomes the fallback.
     */
    function safe_url(?string $url, string $fallback = '/'): string
    {
        $url = trim((string) $url);

        if ($url === '') {
            return $fallback;
        }

        if (str_starts_with($url, '//')) {
            return $fallback;
        }

        if (str_starts_with($url, '/')) {
            return $url;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if (! in_array($scheme, ['http', 'https'], true)) {
            return $fallback;
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $appHost = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));
        $requestHost = strtolower((string) request()?->getHost());

        return in_array($host, array_filter([$appHost, $requestHost]), true)
            ? $url
            : $fallback;
    }
}

if (! function_exists('safe_href')) {
    /**
     * Like safe_url() but external http(s) links are allowed. Only the scheme
     * is policed, so javascript:/data:/vbscript: can never reach an href.
     */
    function safe_href(?string $url, string $fallback = '/'): string
    {
        $url = trim((string) $url);

        if ($url === '') {
            return $fallback;
        }

        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return $url;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https', 'mailto', 'tel'], true) ? $url : $fallback;
    }
}

if (! function_exists('css_url')) {
    /**
     * Strip the characters that would let a stored URL break out of a
     * url('...') value in an inline style attribute.
     */
    function css_url(?string $url): string
    {
        return preg_replace('/[^A-Za-z0-9\-._~:\/?#\[\]@!$&*+,;=%]/', '', (string) $url) ?? '';
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
