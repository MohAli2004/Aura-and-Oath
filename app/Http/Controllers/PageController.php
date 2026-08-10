<?php

namespace App\Http\Controllers;

use App\Mail\ContactMessage;
use App\Models\Page;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use Throwable;

class PageController extends Controller
{
    public function __construct(protected NotificationService $notifications) {}

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
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $contactEmail = config('aura.contact.email');

        // In-app admin notification first so contact requests are never lost if mail fails.
        $this->notifications->notifyContactMessage(
            $data['name'],
            $data['email'],
            $data['message']
        );

        try {
            Mail::to($contactEmail)->send(new ContactMessage(
                senderName: $data['name'],
                senderEmail: $data['email'],
                contactMessage: $data['message'],
            ));
        } catch (Throwable $e) {
            Log::error('Contact form mail failed', [
                'exception' => $e,
            ]);

            return back()
                ->withInput()
                ->with(
                    'error',
                    "We couldn't send your message right now. Please try again later or email us directly at {$contactEmail}."
                );
        }

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
