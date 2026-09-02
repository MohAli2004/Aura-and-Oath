<?php

namespace App\Support;

use App\Enums\PaymentMethod;

class SiteOptions
{
    /**
     * Boolean storefront flags. Missing DB rows use these defaults.
     * Phone is hidden from customers until an admin turns it on.
     *
     * @return array<string, bool>
     */
    public static function flags(): array
    {
        return [
            'show_phone_to_customers' => false,
            'show_email_to_customers' => true,
            'show_whatsapp_to_customers' => false,
            'show_address_to_customers' => false,
            'show_hours_to_customers' => false,
            'show_social_links' => true,
            'show_newsletter' => true,
            'payment_cod_enabled' => true,
            'payment_wish_enabled' => true,
            'home_show_hot_offers' => true,
            'home_show_featured' => true,
            'home_show_shop_by_gender' => true,
            'home_show_women' => true,
            'home_show_men' => true,
            'home_show_unisex' => true,
            'home_show_categories' => true,
            'home_show_bestsellers' => true,
            'home_show_new_arrivals' => true,
        ];
    }

    /**
     * Editable string/decimal settings and their groups.
     *
     * @return array<string, array{type: string, group: string, public: bool}>
     */
    public static function fields(): array
    {
        return [
            'store_name' => ['type' => 'string', 'group' => 'general', 'public' => true],
            'support_email' => ['type' => 'string', 'group' => 'general', 'public' => true],
            'contact_phone' => ['type' => 'string', 'group' => 'contact', 'public' => true],
            'contact_whatsapp' => ['type' => 'string', 'group' => 'contact', 'public' => true],
            'contact_address' => ['type' => 'string', 'group' => 'contact', 'public' => true],
            'support_hours' => ['type' => 'string', 'group' => 'contact', 'public' => true],
            'tax_rate' => ['type' => 'decimal', 'group' => 'store', 'public' => true],
            'currency' => ['type' => 'string', 'group' => 'store', 'public' => true],
            'currency_symbol' => ['type' => 'string', 'group' => 'store', 'public' => true],
            'default_delivery_note' => ['type' => 'string', 'group' => 'shipping', 'public' => true],
            'social_instagram' => ['type' => 'string', 'group' => 'social', 'public' => true],
            'social_facebook' => ['type' => 'string', 'group' => 'social', 'public' => true],
            'social_tiktok' => ['type' => 'string', 'group' => 'social', 'public' => true],
            'social_youtube' => ['type' => 'string', 'group' => 'social', 'public' => true],
            'wish_account_name' => ['type' => 'string', 'group' => 'payments', 'public' => true],
            'wish_account_number' => ['type' => 'string', 'group' => 'payments', 'public' => true],
            'wish_instructions' => ['type' => 'string', 'group' => 'payments', 'public' => true],
        ];
    }

    /**
     * @return array<string, array{type: string, group: string, public: bool}>
     */
    public static function flagMeta(): array
    {
        return collect(self::flags())
            ->map(fn () => ['type' => 'boolean', 'group' => 'storefront', 'public' => true])
            ->replace([
                'show_phone_to_customers' => ['type' => 'boolean', 'group' => 'contact', 'public' => true],
                'show_email_to_customers' => ['type' => 'boolean', 'group' => 'contact', 'public' => true],
                'show_whatsapp_to_customers' => ['type' => 'boolean', 'group' => 'contact', 'public' => true],
                'show_address_to_customers' => ['type' => 'boolean', 'group' => 'contact', 'public' => true],
                'show_hours_to_customers' => ['type' => 'boolean', 'group' => 'contact', 'public' => true],
                'show_social_links' => ['type' => 'boolean', 'group' => 'social', 'public' => true],
                'payment_cod_enabled' => ['type' => 'boolean', 'group' => 'payments', 'public' => true],
                'payment_wish_enabled' => ['type' => 'boolean', 'group' => 'payments', 'public' => true],
            ])
            ->all();
    }

    public static function defaultFlag(string $key): bool
    {
        return self::flags()[$key] ?? false;
    }

    public static function flag(string $key): bool
    {
        try {
            return (bool) setting($key, self::defaultFlag($key));
        } catch (\Throwable) {
            return self::defaultFlag($key);
        }
    }

    /**
     * @return list<PaymentMethod>
     */
    public static function enabledPaymentMethods(): array
    {
        $methods = [];

        if (self::flag('payment_cod_enabled')) {
            $methods[] = PaymentMethod::CashOnDelivery;
        }

        if (self::flag('payment_wish_enabled')) {
            $methods[] = PaymentMethod::WishAccount;
        }

        return $methods;
    }

    /**
     * @return list<string>
     */
    public static function enabledPaymentMethodValues(): array
    {
        return array_map(fn (PaymentMethod $method) => $method->value, self::enabledPaymentMethods());
    }

    /**
     * Seed / fallback values for string fields.
     *
     * @return array<string, string>
     */
    public static function fieldDefaults(): array
    {
        return [
            'store_name' => (string) config('aura.name', 'Aura & Oath'),
            'support_email' => (string) config('aura.contact.email', ''),
            'contact_phone' => (string) config('aura.contact.phone', ''),
            'contact_whatsapp' => (string) config('aura.contact.whatsapp', ''),
            'contact_address' => (string) config('aura.contact.address', ''),
            'support_hours' => (string) config('aura.contact.support_hours', ''),
            'tax_rate' => '0',
            'currency' => (string) config('aura.currency', 'USD'),
            'currency_symbol' => (string) config('aura.currency_symbol', '$'),
            'default_delivery_note' => 'Delivery within Lebanon in 1–3 business days, depending on distance.',
            'social_instagram' => (string) config('aura.social.instagram', ''),
            'social_facebook' => (string) config('aura.social.facebook', ''),
            'social_tiktok' => (string) config('aura.social.tiktok', ''),
            'social_youtube' => (string) config('aura.social.youtube', ''),
            'wish_account_name' => (string) config('aura.payments.wish.account_name', ''),
            'wish_account_number' => (string) config('aura.payments.wish.account_number', ''),
            'wish_instructions' => (string) config('aura.payments.wish.instructions', ''),
        ];
    }

    public static function fieldValue(string $key): string
    {
        try {
            $all = app(\App\Services\SettingsService::class)->all();
        } catch (\Throwable) {
            return self::fieldDefaults()[$key] ?? '';
        }

        if (array_key_exists($key, $all)) {
            return trim((string) ($all[$key] ?? ''));
        }

        return self::fieldDefaults()[$key] ?? '';
    }

    /**
     * @return array<string, string>
     */
    public static function socialLinks(): array
    {
        if (! self::flag('show_social_links')) {
            return [];
        }

        $links = [];
        foreach (['instagram' => 'Instagram', 'facebook' => 'Facebook', 'tiktok' => 'TikTok', 'youtube' => 'YouTube'] as $network => $label) {
            $url = self::fieldValue('social_'.$network);
            if ($url !== '') {
                $links[$network] = ['label' => $label, 'url' => $url];
            }
        }

        return $links;
    }
}
