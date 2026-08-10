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
>
    @csrf @if($brand->exists) @method('PUT') @endif
    <x-input label="Name" name="name" value="{{ old('name', $brand->name) }}" required />
    <x-input label="Slug" name="slug" value="{{ old('slug', $brand->slug) }}" />
    <div>
        <label class="label" for="description">Description</label>
        <textarea id="description" name="description" class="input" rows="3">{{ old('description', $brand->description) }}</textarea>
    </div>
    <x-input label="Website" name="website" value="{{ old('website', $brand->website) }}" />

    @php
        $selectedCategoryIds = collect(old('categories', $brand->exists ? $brand->categories->pluck('id')->all() : []))
            ->map(fn ($id) => (int) $id)
            ->all();
    @endphp
    <div>
        <span class="label">Categories</span>
        <p class="mb-2 text-xs text-taupe leading-snug">Select one or more categories for this brand.</p>
        @if($categories->isEmpty())
            <p class="text-sm text-taupe">No categories yet. Create categories first.</p>
        @else
            <div class="max-h-48 space-y-2 overflow-y-auto border border-beige bg-ivory/40 p-3">
                @foreach($categories as $category)
                    <label class="flex gap-2 text-sm">
                        <input
                            type="checkbox"
                            name="categories[]"
                            value="{{ $category->id }}"
                            @checked(in_array($category->id, $selectedCategoryIds, true))
                        >
                        {{ $category->name }}
                    </label>
                @endforeach
            </div>
        @endif
        @error('categories')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
        @error('categories.*')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
    </div>

    <div>
        <span class="label" id="logo-label">Brand image / logo</span>
        <x-admin.image-upload
            name="logo"
            id="logo"
            frame="square-lg"
            alt="Brand logo preview"
            empty="No logo — click to upload"
            :src="$initialPreview"
            accept=".png,image/png,.jpg,.jpeg,image/jpeg,.webp,image/webp,.svg,image/svg+xml,.gif,image/gif"
            aria-labelledby="logo-label"
        />
        <p class="mt-1 text-xs text-taupe leading-snug">PNG recommended (also JPG, WebP, SVG). Shown in the brands list and storefront.</p>
        @error('logo')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
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
