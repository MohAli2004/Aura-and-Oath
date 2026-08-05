@extends('layouts.admin')
@section('heading', $brand->exists ? 'Edit brand' : 'New brand')
@section('content')
@php
    $hasCustomLogo = filled($brand->logo_path)
        && ! str_contains((string) $brand->logo_path, 'placeholders/brand');
    $initialPreview = $hasCustomLogo ? $brand->logoUrl() : null;
@endphp
<form
    method="POST"
    action="{{ $brand->exists ? route('admin.brands.update', $brand) : route('admin.brands.store') }}"
    enctype="multipart/form-data"
    class="max-w-xl space-y-4"
    x-data="{ preview: @js($initialPreview) }"
>
    @csrf @if($brand->exists) @method('PUT') @endif
    <x-input label="Name" name="name" value="{{ old('name', $brand->name) }}" required />
    <x-input label="Slug" name="slug" value="{{ old('slug', $brand->slug) }}" />
    <div>
        <label class="label" for="description">Description</label>
        <textarea id="description" name="description" class="input" rows="3">{{ old('description', $brand->description) }}</textarea>
    </div>
    <x-input label="Website" name="website" value="{{ old('website', $brand->website) }}" />

    <div>
        <label class="label" for="logo">Brand image / logo</label>
        <input
            id="logo"
            type="file"
            name="logo"
            class="input"
            accept=".png,image/png,.jpg,.jpeg,image/jpeg,.webp,image/webp,.svg,image/svg+xml,.gif,image/gif"
            @change="preview = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : preview"
        >
        <p class="mt-1 text-xs text-taupe leading-snug">PNG recommended (also JPG, WebP, SVG). Shown in the brands list and storefront.</p>
        @error('logo')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
        <div class="mt-3 flex items-center gap-3" x-show="preview" x-cloak>
            <div class="h-16 w-16 shrink-0 overflow-hidden border border-beige bg-ivory/60">
                <img :src="preview" alt="Brand logo preview" class="h-full w-full object-contain">
            </div>
            <span class="text-xs text-taupe">Preview</span>
        </div>
    </div>

    <x-input label="Sort order" name="sort_order" type="number" value="{{ old('sort_order', $brand->sort_order ?? 0) }}" />
    <label class="flex gap-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $brand->is_active ?? true))> Active</label>
    <label class="flex gap-2 text-sm"><input type="checkbox" name="is_featured" value="1" @checked(old('is_featured', $brand->is_featured))> Featured</label>
    <button class="btn btn-primary" type="submit">Save</button>
</form>
@if($brand->exists)
<form class="mt-4" method="POST" action="{{ route('admin.brands.destroy', $brand) }}">@csrf @method('DELETE')<button class="btn btn-danger" onclick="return confirm('Delete?')">Delete</button></form>
@endif
@endsection
