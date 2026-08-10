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
        $hiddenKeys = [
            'logo_path',
            'favicon_path',
            'home_background_path',
            'invoice_fields',
            'packing_slip_fields',
            'invoice_size',
            'packing_slip_size',
        ];

        return view('admin.settings.edit', [
            'settings' => Setting::query()
                ->whereNotIn('key', $hiddenKeys)
                ->orderBy('group')
                ->orderBy('key')
                ->get(),
            'logoPath' => $this->settings->get('logo_path'),
            'faviconPath' => $this->settings->get('favicon_path'),
            'homeBackgroundPath' => $this->settings->get('home_background_path'),
            'logoUrl' => store_logo_url(),
            'faviconUrl' => store_favicon_url(),
            'homeBackgroundUrl' => store_home_background_url(),
            'invoiceFields' => print_fields('invoice'),
            'packingSlipFields' => print_fields('packing_slip'),
            'invoiceSize' => print_page_size('invoice'),
            'packingSlipSize' => print_page_size('packing_slip'),
            'printSizes' => array_keys(config('aura.print.sizes', [])),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $invoiceKeys = array_keys(config('aura.print.invoice', []));
        $packingKeys = array_keys(config('aura.print.packing_slip', []));
        $sizeKeys = array_keys(config('aura.print.sizes', []));

        $data = $request->validate([
            'settings' => ['nullable', 'array'],
            'settings.*' => ['nullable'],
            'invoice_fields' => ['nullable', 'array'],
            'invoice_fields.*' => ['string', 'in:'.implode(',', $invoiceKeys)],
            'packing_slip_fields' => ['nullable', 'array'],
            'packing_slip_fields.*' => ['string', 'in:'.implode(',', $packingKeys)],
            'invoice_size' => ['required', 'string', 'in:'.implode(',', $sizeKeys)],
            'packing_slip_size' => ['required', 'string', 'in:'.implode(',', $sizeKeys)],
            'logo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,svg', 'max:10240'],
            'favicon' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,ico,svg', 'max:2048'],
            'home_background' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'remove_logo' => ['nullable', 'boolean'],
            'remove_favicon' => ['nullable', 'boolean'],
            'remove_home_background' => ['nullable', 'boolean'],
        ]);

        foreach ($data['settings'] ?? [] as $key => $value) {
            if (in_array($key, [
                'logo_path',
                'favicon_path',
                'home_background_path',
                'invoice_fields',
                'packing_slip_fields',
                'invoice_size',
                'packing_slip_size',
            ], true)) {
                continue;
            }

            $existing = Setting::query()->where('key', $key)->first();
            $type = $existing?->type ?? 'string';
            $group = $existing?->group ?? 'general';
            $this->settings->set($key, $value ?? '', $type, $group, $existing?->is_public ?? true);
        }

        $this->settings->set(
            'invoice_fields',
            array_values(array_intersect($invoiceKeys, $data['invoice_fields'] ?? [])),
            'json',
            'print',
            false,
        );

        $this->settings->set(
            'packing_slip_fields',
            array_values(array_intersect($packingKeys, $data['packing_slip_fields'] ?? [])),
            'json',
            'print',
            false,
        );

        $this->settings->set('invoice_size', $data['invoice_size'], 'string', 'print', false);
        $this->settings->set('packing_slip_size', $data['packing_slip_size'], 'string', 'print', false);

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
