@extends('layouts.admin')
@section('heading', 'Settings')
@section('content')
<form method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data" class="max-w-3xl space-y-8">
    @csrf @method('PUT')

    <section class="border border-beige bg-[#FFFCFA] p-6 space-y-4">
        <div>
            <h2 class="font-display text-2xl">Brand logo</h2>
            <p class="text-sm text-taupe mt-1">Shown in the storefront header, footer, login pages, and admin sidebar.</p>
        </div>

        <div>
            <span class="label" id="logo-label">Logo</span>
            <x-admin.image-upload
                name="logo"
                id="logo"
                frame="logo-wide"
                fit="contain"
                alt="Brand logo preview"
                empty="Click to upload"
                :src="$logoUrl"
                accept="image/png,image/jpeg,image/webp,image/svg+xml"
                aria-labelledby="logo-label"
            />
            <p class="text-xs text-taupe mt-1">PNG, JPG, WEBP, or SVG. Max 10MB. Transparent PNG works best.</p>
        </div>

        @if($logoUrl)
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="remove_logo" value="1">
                Remove current logo
            </label>
        @endif
    </section>

    <section class="border border-beige bg-[#FFFCFA] p-6 space-y-4">
        <div>
            <h2 class="font-display text-2xl">Favicon</h2>
            <p class="text-sm text-taupe mt-1">Browser tab icon for the storefront and admin.</p>
        </div>

        <div>
            <span class="label" id="favicon-label">Favicon</span>
            <x-admin.image-upload
                name="favicon"
                id="favicon"
                frame="favicon"
                fit="contain"
                alt="Favicon preview"
                empty="Click to upload"
                :src="$faviconUrl"
                accept="image/png,image/jpeg,image/webp,image/svg+xml,image/x-icon,.ico"
                aria-labelledby="favicon-label"
            />
            <p class="text-xs text-taupe mt-1">PNG or ICO recommended. Max 2MB.</p>
        </div>

        @if($faviconUrl)
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="remove_favicon" value="1">
                Remove current favicon
            </label>
        @endif
    </section>

    <section class="border border-beige bg-[#FFFCFA] p-6 space-y-4">
        <div>
            <h2 class="font-display text-2xl">Homepage background</h2>
            <p class="text-sm text-taupe mt-1">Hero background image on the main storefront page. Uploading a new image replaces the current one.</p>
        </div>

        <div>
            <span class="label" id="home-background-label">Background</span>
            <x-admin.image-upload
                name="home_background"
                id="home_background"
                frame="wide"
                fit="cover"
                alt="Homepage background preview"
                empty="Click to upload"
                :src="$homeBackgroundUrl"
                accept="image/png,image/jpeg,image/webp"
                aria-labelledby="home-background-label"
            />
            <p class="text-xs text-taupe mt-1">JPG, PNG, or WEBP. Max 10MB. Wide images work best.</p>
        </div>

        @if($homeBackgroundUrl)
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="remove_home_background" value="1">
                Remove current background
            </label>
        @endif
    </section>

    <section class="border border-beige bg-[#FFFCFA] p-6 space-y-4">
        <h2 class="font-display text-2xl">Store settings</h2>
        @foreach($settings as $setting)
            <div>
                <label class="label" for="setting-{{ $setting->key }}">
                    {{ str_replace('_', ' ', $setting->key) }}
                    <span class="normal-case tracking-normal text-taupe">({{ $setting->group }})</span>
                </label>
                <input
                    id="setting-{{ $setting->key }}"
                    class="input"
                    name="settings[{{ $setting->key }}]"
                    value="{{ old('settings.'.$setting->key, $setting->value) }}"
                >
            </div>
        @endforeach
    </section>

    <section class="border border-beige bg-[#FFFCFA] p-6 space-y-6">
        <div>
            <h2 class="font-display text-2xl">Print documents</h2>
            <p class="text-sm text-taupe mt-1">Choose which fields appear on invoices and packing slips.</p>
        </div>

        <div class="grid sm:grid-cols-2 gap-6">
            <div class="space-y-3">
                <h3 class="font-medium text-sm uppercase tracking-wider text-taupe">Invoice</h3>
                @php $selectedInvoice = old('invoice_fields', $invoiceFields); @endphp
                @foreach(config('aura.print.invoice') as $key => $label)
                    <label class="flex items-center gap-2 text-sm">
                        <input
                            type="checkbox"
                            name="invoice_fields[]"
                            value="{{ $key }}"
                            @checked(in_array($key, $selectedInvoice, true))
                        >
                        {{ $label }}
                    </label>
                @endforeach
            </div>

            <div class="space-y-3">
                <h3 class="font-medium text-sm uppercase tracking-wider text-taupe">Packing slip</h3>
                @php $selectedPacking = old('packing_slip_fields', $packingSlipFields); @endphp
                @foreach(config('aura.print.packing_slip') as $key => $label)
                    <label class="flex items-center gap-2 text-sm">
                        <input
                            type="checkbox"
                            name="packing_slip_fields[]"
                            value="{{ $key }}"
                            @checked(in_array($key, $selectedPacking, true))
                        >
                        {{ $label }}
                    </label>
                @endforeach
            </div>
        </div>
    </section>

    <button class="btn btn-primary" type="submit">Save settings</button>
</form>
@endsection
