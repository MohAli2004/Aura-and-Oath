<?php

namespace App\Support;

use App\Enums\PaymentMethod;

class TrustMessaging
{
    public static function returnWindowHours(): int
    {
        return max(1, (int) config('aura.orders.return_window_hours', 24));
    }

    public static function returnWindowLabel(): string
    {
        $hours = self::returnWindowHours();

        return $hours === 24
            ? __('storefront.return_window_24h')
            : __('storefront.return_window_hours', ['count' => $hours]);
    }

    public static function deliveryNote(): string
    {
        $note = trim(SiteOptions::fieldValue('default_delivery_note'));

        return $note !== '' ? $note : __('storefront.delivery_default');
    }

    public static function countryName(): string
    {
        return (string) config('aura.country_name', 'Lebanon');
    }

    /**
     * @return list<string>
     */
    public static function paymentMethodLabels(): array
    {
        return array_map(
            fn (PaymentMethod $method) => $method->label(),
            SiteOptions::enabledPaymentMethods()
        );
    }

    /**
     * Compact reassurance lines for product, cart, and checkout.
     *
     * @return list<string>
     */
    public static function reassuranceLines(): array
    {
        $lines = [__('storefront.trust_secure_checkout')];

        if (SiteOptions::flag('payment_cod_enabled')) {
            $lines[] = __('storefront.trust_cod');
        }

        if (SiteOptions::flag('payment_wish_enabled')) {
            $lines[] = __('storefront.trust_wish');
        }

        $lines[] = __('storefront.trust_returns', ['window' => self::returnWindowLabel()]);

        return $lines;
    }

    /**
     * @return list<array{title: string, body: string}>
     */
    public static function homeFacts(): array
    {
        $facts = [
            [
                'title' => __('storefront.trust_delivery_title', ['country' => self::countryName()]),
                'body' => self::deliveryNote(),
            ],
        ];

        $payments = self::paymentMethodLabels();
        if ($payments !== []) {
            $facts[] = [
                'title' => __('storefront.trust_payment_title'),
                'body' => implode(' '.__('storefront.and').' ', $payments).'.',
            ];
        }

        $facts[] = [
            'title' => __('storefront.trust_returns_title'),
            'body' => __('storefront.trust_returns_body', ['window' => self::returnWindowLabel()]),
        ];

        $facts[] = [
            'title' => __('storefront.trust_stock_title'),
            'body' => __('storefront.trust_stock_body'),
        ];

        return $facts;
    }
}
