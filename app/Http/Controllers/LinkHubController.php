<?php

namespace App\Http\Controllers;

use App\Models\LinkHubButton;
use App\Models\LinkHubSetting;
use Illuminate\View\View;

class LinkHubController extends Controller
{
    public function __invoke(): View
    {
        $settings = LinkHubSetting::current();
        $buttons = LinkHubButton::query()
            ->active()
            ->ordered()
            ->get()
            ->filter(fn (LinkHubButton $button) => $button->isVisible());

        return view('storefront.links.index', [
            'settings' => $settings,
            'buttons' => $buttons,
        ]);
    }
}
