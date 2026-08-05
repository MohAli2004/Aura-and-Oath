<?php

namespace App\Support;

class Money
{
    public static function format(float|int|string|null $amount, ?string $currency = null): string
    {
        $symbol = config('aura.currency_symbol', '$');
        $code = $currency ?? config('aura.currency', 'USD');

        try {
            if (function_exists('setting')) {
                $code = $currency ?? (setting('currency') ?: $code);
                $symbol = setting('currency_symbol') ?: $symbol;
            }
        } catch (\Throwable) {
            // Fall back to config when settings are unavailable.
        }

        $value = number_format((float) ($amount ?? 0), 2);

        if ($code === 'USD' || $symbol === '$') {
            return '$'.$value;
        }

        return trim($symbol.' '.$value);
    }

    public static function toDecimal(float|int|string|null $amount): string
    {
        return number_format((float) ($amount ?? 0), 2, '.', '');
    }
}
