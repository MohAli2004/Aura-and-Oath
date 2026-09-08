<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $allowed = config('aura.locales', ['en', 'ar']);
        $locale = (string) $request->input('locale', $request->route('locale', ''));

        if (! in_array($locale, $allowed, true)) {
            abort(400, 'Invalid locale.');
        }

        $request->session()->put('locale', $locale);
        app()->setLocale($locale);

        return redirect()
            ->back(fallback: route('home'))
            ->withCookie(cookie('locale', $locale, 60 * 24 * 365, '/', null, null, false, false, 'lax'));
    }
}
