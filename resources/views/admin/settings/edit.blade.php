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

        @if($logoUrl)
            <div class="flex items-center gap-4 p-4 border border-beige bg-ivory/60">
                <img src="{{ $logoUrl }}" alt="Current logo" class="h-16 w-auto max-w-[220px] object-contain">
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="remove_logo" value="1">
                    Remove current logo
                </label>
            </div>
        @else
            <p class="text-sm text-taupe">No logo uploaded yet. The store name will be shown as text.</p>
        @endif

        <div>
            <label class="label" for="logo">Upload logo</label>
            <input id="logo" class="input" type="file" name="logo" accept="image/png,image/jpeg,image/webp,image/svg+xml">
            <p class="text-xs text-taupe mt-1">PNG, JPG, WEBP, or SVG. Max 10MB. Transparent PNG works best.</p>
        </div>
    </section>

    <section class="border border-beige bg-[#FFFCFA] p-6 space-y-4">
        <div>
            <h2 class="font-display text-2xl">Favicon</h2>
            <p class="text-sm text-taupe mt-1">Browser tab icon for the storefront and admin.</p>
        </div>

        @if($faviconUrl)
            <div class="flex items-center gap-4 p-4 border border-beige bg-ivory/60">
                <img src="{{ $faviconUrl }}" alt="Current favicon" class="h-10 w-10 object-contain">
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="remove_favicon" value="1">
                    Remove current favicon
                </label>
            </div>
        @endif

        <div>
            <label class="label" for="favicon">Upload favicon</label>
            <input id="favicon" class="input" type="file" name="favicon" accept="image/png,image/jpeg,image/webp,image/svg+xml,image/x-icon,.ico">
            <p class="text-xs text-taupe mt-1">PNG or ICO recommended. Max 2MB.</p>
        </div>
    </section>

    <section
        class="border border-beige bg-[#FFFCFA] p-6 space-y-4"
        x-data="{
            preview: @js($homeBackgroundUrl),
            onFile(event) {
                const file = event.target.files?.[0];
                if (!file) return;
                if (this.preview && String(this.preview).startsWith('blob:')) {
                    URL.revokeObjectURL(this.preview);
                }
                this.preview = URL.createObjectURL(file);
            }
        }"
    >
        <div>
            <h2 class="font-display text-2xl">Homepage background</h2>
            <p class="text-sm text-taupe mt-1">Hero background image on the main storefront page. Uploading a new image replaces the current one.</p>
        </div>

        <div class="overflow-hidden border border-beige bg-ivory/60 aspect-[16/7] max-w-xl">
            <img x-show="preview" x-cloak :src="preview" alt="Homepage background preview" class="h-full w-full object-cover">
            <div x-show="!preview" class="flex h-full items-center justify-center text-sm text-taupe">No background uploaded</div>
        </div>

        @if($homeBackgroundUrl)
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="remove_home_background" value="1">
                Remove current background
            </label>
        @endif

        <div>
            <label class="label" for="home_background">Upload background</label>
            <input id="home_background" class="input" type="file" name="home_background" accept="image/png,image/jpeg,image/webp" @change="onFile($event)">
            <p class="text-xs text-taupe mt-1">JPG, PNG, or WEBP. Max 10MB. Wide images work best.</p>
        </div>
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

    <button class="btn btn-primary" type="submit">Save settings</button>
</form>
@endsection
