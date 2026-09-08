<?php

namespace App\Support;

use App\Models\Offer;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Request;

class Seo
{
    /** @var list<string> */
    private const CANONICAL_QUERY_KEYS = [
        'category',
        'brand',
        'gender',
        'featured',
    ];

    /** @var list<string> */
    private const STRIP_QUERY_PREFIXES = [
        'utm_',
        'fbclid',
        'gclid',
        'mc_',
    ];

    public static function truncate(string $text, int $max = 160): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', strip_tags($text)) ?? '');

        if ($text === '') {
            return '';
        }

        if (mb_strlen($text) <= $max) {
            return $text;
        }

        return rtrim(mb_substr($text, 0, $max - 1)).'…';
    }

    public static function ogLocale(?string $locale = null): string
    {
        $locale ??= app()->getLocale();

        return match ($locale) {
            'ar' => 'ar_LB',
            default => 'en_US',
        };
    }

    /**
     * @return list<string>
     */
    public static function ogLocaleAlternates(?string $current = null): array
    {
        $current ??= app()->getLocale();
        $locales = config('aura.locales', ['en', 'ar']);

        return collect($locales)
            ->reject(fn (string $locale) => $locale === $current)
            ->map(fn (string $locale) => self::ogLocale($locale))
            ->values()
            ->all();
    }

    public static function absoluteUrl(string $url): string
    {
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        return url($url);
    }

    public static function canonicalFromRequest(?Request $request = null): string
    {
        $request ??= request();
        $query = self::canonicalQuery($request->query());

        $base = $request->url();

        if ($query === []) {
            return $base;
        }

        return $base.'?'.http_build_query($query);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, string>
     */
    public static function canonicalQuery(array $query): array
    {
        $filtered = [];

        foreach (self::CANONICAL_QUERY_KEYS as $key) {
            if (! isset($query[$key]) || $query[$key] === '' || $query[$key] === null) {
                continue;
            }

            $filtered[$key] = (string) $query[$key];
        }

        return $filtered;
    }

    /**
     * @return array<string, string>
     */
    public static function hreflangAlternates(string $canonicalUrl): array
    {
        $base = strtok($canonicalUrl, '?') ?: $canonicalUrl;
        $default = (string) config('aura.default_locale', 'en');
        $locales = config('aura.locales', ['en', 'ar']);
        $alternates = [];

        foreach ($locales as $locale) {
            $alternates[$locale] = $base.'?lang='.$locale;
        }

        $alternates['x-default'] = $alternates[$default] ?? $alternates['en'];

        return $alternates;
    }

    public static function shouldNoindex(?Request $request = null): bool
    {
        $request ??= request();

        if ($request->is('admin', 'admin/*')) {
            return true;
        }

        $patterns = [
            'cart',
            'cart/*',
            'checkout',
            'checkout/*',
            'account',
            'account/*',
            'wishlist',
            'wishlist/*',
            'login',
            'register',
            'forgot-password',
            'reset-password',
            'reset-password/*',
            'auth/*',
            'search',
            'track-order',
            'returns',
            'push/*',
            'payments/*',
        ];

        return $request->is(...$patterns);
    }

    /**
     * @return array<string, mixed>
     */
    public static function organizationSchema(): array
    {
        $logo = store_logo_url();

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => config('aura.name'),
            'url' => url('/'),
            'description' => config('aura.tagline'),
        ];

        if ($logo) {
            $schema['logo'] = self::absoluteUrl($logo);
        }

        $email = store_public_email();
        if ($email) {
            $schema['email'] = $email;
        }

        $phone = store_public_phone();
        if ($phone) {
            $schema['telephone'] = $phone;
        }

        $address = store_public_address();
        if ($address) {
            $schema['address'] = [
                '@type' => 'PostalAddress',
                'addressLocality' => $address,
                'addressCountry' => config('aura.country', 'LB'),
            ];
        }

        $social = collect(SiteOptions::socialLinks())->pluck('url')->filter()->values()->all();
        if ($social !== []) {
            $schema['sameAs'] = $social;
        }

        return $schema;
    }

    /**
     * @return array<string, mixed>
     */
    public static function websiteSchema(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => config('aura.name'),
            'url' => url('/'),
            'inLanguage' => collect(config('aura.locales', ['en', 'ar']))
                ->map(fn (string $locale) => self::ogLocale($locale))
                ->values()
                ->all(),
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => [
                    '@type' => 'EntryPoint',
                    'urlTemplate' => route('search').'?q={search_term_string}',
                ],
                'query-input' => 'required name=search_term_string',
            ],
        ];
    }

    /**
     * @param  list<array{name: string, url: string}>  $items
     * @return array<string, mixed>
     */
    public static function breadcrumbSchema(array $items): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => collect($items)
                ->values()
                ->map(fn (array $item, int $index) => [
                    '@type' => 'ListItem',
                    'position' => $index + 1,
                    'name' => $item['name'],
                    'item' => self::absoluteUrl($item['url']),
                ])
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function productSchema(Product $product, string $pageUrl, string $imageUrl): array
    {
        $images = app(\App\Services\ImageService::class);
        $image = self::absoluteUrl($imageUrl !== '' ? $imageUrl : ($images->url($product->primaryImagePath()) ?? ''));

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $product->localized('name'),
            'description' => self::truncate(
                $product->localized('meta_description')
                    ?: ($product->localized('short_description') ?: strip_tags((string) $product->localized('description'))),
                5000
            ),
            'sku' => $product->sku,
            'url' => self::absoluteUrl($pageUrl),
        ];

        if ($image !== self::absoluteUrl('')) {
            $schema['image'] = [$image];
        }

        if ($product->brand) {
            $schema['brand'] = [
                '@type' => 'Brand',
                'name' => $product->brand->localized('name'),
            ];
        }

        $schema['offers'] = self::productOffersSchema($product);

        return $schema;
    }

    /**
     * @return array<string, mixed>
     */
    private static function productOffersSchema(Product $product): array
    {
        $currency = (string) config('aura.currency', 'USD');
        $availability = $product->isPurchasable()
            ? 'https://schema.org/InStock'
            : 'https://schema.org/OutOfStock';

        if ($product->has_variants && $product->activeVariants->count() > 1) {
            $prices = $product->activeVariants
                ->map(fn (ProductVariant $variant) => $product->effectivePrice($variant))
                ->filter(fn ($price) => $price > 0)
                ->values();

            if ($prices->isEmpty()) {
                $prices = collect([(float) $product->price]);
            }

            return [
                '@type' => 'AggregateOffer',
                'priceCurrency' => $currency,
                'lowPrice' => number_format((float) $prices->min(), 2, '.', ''),
                'highPrice' => number_format((float) $prices->max(), 2, '.', ''),
                'offerCount' => $product->activeVariants->count(),
                'availability' => $availability,
                'url' => self::absoluteUrl(route('products.show', $product->slug)),
            ];
        }

        $variant = $product->activeVariants->first();
        $price = $product->effectivePrice($variant);

        return [
            '@type' => 'Offer',
            'priceCurrency' => $currency,
            'price' => number_format($price, 2, '.', ''),
            'availability' => $availability,
            'url' => self::absoluteUrl(route('products.show', $product->slug)),
            'sku' => $variant?->sku ?: $product->sku,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function offerSchema(Offer $offer, string $pageUrl, string $imageUrl): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $offer->localized('title'),
            'description' => self::truncate(strip_tags((string) $offer->localized('description')), 5000),
            'url' => self::absoluteUrl($pageUrl),
            'image' => [self::absoluteUrl($imageUrl)],
            'offers' => [
                '@type' => 'Offer',
                'priceCurrency' => (string) config('aura.currency', 'USD'),
                'price' => number_format((float) $offer->offerTotal(), 2, '.', ''),
                'availability' => $offer->isPurchasable()
                    ? 'https://schema.org/InStock'
                    : 'https://schema.org/OutOfStock',
                'url' => self::absoluteUrl($pageUrl),
            ],
        ];
    }

    public static function encodeJsonLd(array $schema): string
    {
        return json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    public static function sitemapLastmod(?\DateTimeInterface $date = null): string
    {
        return ($date ?? now())->format('Y-m-d');
    }

    /**
     * @param  array<string, string>  $alternates
     */
    public static function sitemapUrlXml(string $loc, ?string $lastmod = null, ?array $alternates = null): string
    {
        $xml = '  <url>'."\n";
        $xml .= '    <loc>'.e($loc).'</loc>'."\n";

        if ($lastmod) {
            $xml .= '    <lastmod>'.e($lastmod).'</lastmod>'."\n";
        }

        if ($alternates) {
            foreach ($alternates as $hreflang => $href) {
                $xml .= '    <xhtml:link rel="alternate" hreflang="'.e($hreflang).'" href="'.e($href).'" />'."\n";
            }
        }

        $xml .= '  </url>';

        return $xml;
    }
}
