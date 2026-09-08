<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetStorefrontLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('admin', 'admin/*')) {
            app()->setLocale(config('aura.default_locale', 'en'));

            return $next($request);
        }

        $allowed = config('aura.locales', ['en', 'ar']);
        $default = config('aura.default_locale', 'en');

        $locale = $request->query('lang')
            ?? $request->session()->get('locale')
            ?? $request->cookie('locale')
            ?? $default;

        if (! is_string($locale) || ! in_array($locale, $allowed, true)) {
            $locale = $default;
        }

        app()->setLocale($locale);
        $request->session()->put('locale', $locale);

        /** @var Response $response */
        $response = $next($request);

        if ($request->has('lang') && in_array($request->query('lang'), $allowed, true)) {
            $response->headers->setCookie(
                cookie('locale', $locale, 60 * 24 * 365, '/', null, null, false, false, 'lax')
            );
        }

        return $response;
    }
}
