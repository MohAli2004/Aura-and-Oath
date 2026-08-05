<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\ImageService;
use App\Services\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function __construct(
        protected SettingsService $settings,
        protected ImageService $images,
    ) {}

    public function edit(): View
    {
        return view('admin.settings.edit', [
            'settings' => Setting::query()
                ->whereNotIn('key', ['logo_path', 'favicon_path', 'home_background_path'])
                ->orderBy('group')
                ->orderBy('key')
                ->get(),
            'logoPath' => $this->settings->get('logo_path'),
            'faviconPath' => $this->settings->get('favicon_path'),
            'homeBackgroundPath' => $this->settings->get('home_background_path'),
            'logoUrl' => store_logo_url(),
            'faviconUrl' => store_favicon_url(),
            'homeBackgroundUrl' => store_home_background_url(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'settings' => ['nullable', 'array'],
            'settings.*' => ['nullable'],
            'logo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,svg', 'max:10240'],
            'favicon' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,ico,svg', 'max:2048'],
            'home_background' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'remove_logo' => ['nullable', 'boolean'],
            'remove_favicon' => ['nullable', 'boolean'],
            'remove_home_background' => ['nullable', 'boolean'],
        ]);

        foreach ($data['settings'] ?? [] as $key => $value) {
            if (in_array($key, ['logo_path', 'favicon_path', 'home_background_path'], true)) {
                continue;
            }

            $existing = Setting::query()->where('key', $key)->first();
            $type = $existing?->type ?? 'string';
            $group = $existing?->group ?? 'general';
            $this->settings->set($key, $value ?? '', $type, $group, $existing?->is_public ?? true);
        }

        if ($request->boolean('remove_logo')) {
            $this->images->delete($this->settings->get('logo_path'));
            $this->settings->set('logo_path', '', 'string', 'general', true);
        }

        if ($request->file('logo')) {
            $this->images->delete($this->settings->get('logo_path'));
            $path = $this->images->store($request->file('logo'), 'branding');
            $this->settings->set('logo_path', $path, 'string', 'general', true);
        }

        if ($request->boolean('remove_favicon')) {
            $this->images->delete($this->settings->get('favicon_path'));
            $this->settings->set('favicon_path', '', 'string', 'general', true);
        }

        if ($request->file('favicon')) {
            $this->images->delete($this->settings->get('favicon_path'));
            $path = $this->images->store($request->file('favicon'), 'branding');
            $this->settings->set('favicon_path', $path, 'string', 'general', true);
        }

        if ($request->boolean('remove_home_background')) {
            $this->images->delete($this->settings->get('home_background_path'));
            $this->settings->set('home_background_path', '', 'string', 'general', true);
        }

        if ($request->file('home_background')) {
            $this->images->delete($this->settings->get('home_background_path'));
            $path = $this->images->store($request->file('home_background'), 'branding');
            $this->settings->set('home_background_path', $path, 'string', 'general', true);
        }

        return back()->with('success', 'Settings saved.');
    }
}
