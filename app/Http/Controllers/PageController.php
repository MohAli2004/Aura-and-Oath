<?php

namespace App\Http\Controllers;

use App\Models\Page;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PageController extends Controller
{
    public function about(): View
    {
        return view('storefront.pages.about');
    }

    public function contact(): View
    {
        return view('storefront.pages.contact');
    }

    public function contactSubmit(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        // Could store or email — acknowledge for now.
        return back()->with('success', 'Thank you. We will get back to you soon.');
    }

    public function faq(): View
    {
        return view('storefront.pages.faq');
    }

    public function show(string $slug): View
    {
        $page = Page::query()->published()->where('slug', $slug)->firstOrFail();

        return view('storefront.pages.show', compact('page'));
    }
}
