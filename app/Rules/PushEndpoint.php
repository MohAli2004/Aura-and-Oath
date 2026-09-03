<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;

/**
 * The browser gives us a push endpoint that the server later sends POSTs to,
 * so an unchecked value is a server-side request forgery hole (internal hosts,
 * cloud metadata, port scanning). Only real push services are accepted.
 */
class PushEndpoint implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $url = is_string($value) ? trim($value) : '';
        $parts = parse_url($url);

        if ($parts === false || empty($parts['host'])) {
            $fail('The push endpoint is not a valid URL.');

            return;
        }

        if (strtolower($parts['scheme'] ?? '') !== 'https') {
            $fail('The push endpoint must use HTTPS.');

            return;
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            $fail('The push endpoint is not supported.');

            return;
        }

        $host = strtolower($parts['host']);
        $allowed = (array) config('security.push_endpoint_hosts', []);

        foreach ($allowed as $pattern) {
            if (Str::is(strtolower((string) $pattern), $host)) {
                return;
            }
        }

        $fail('The push endpoint is not a recognised push service.');
    }
}
