@extends('layouts.admin')
@section('heading', $category->exists ? 'Edit category' : 'New category')
@section('content')
@php
    $hasCustomIcon = filled($category->image_path)
        && ! str_contains((string) $category->image_path, 'placeholders/category');
    $initialPreview = $hasCustomIcon ? $category->iconUrl() : null;
@endphp
<form
    method="POST"
    action="{{ $category->exists ? route('admin.categories.update', $category) : route('admin.categories.store') }}"
    enctype="multipart/form-data"
    class="max-w-xl space-y-4"
>
    @csrf @if($category->exists) @method('PUT') @endif
    <x-input label="Name" name="name" value="{{ old('name', $category->name) }}" required />
    <x-input label="Slug" name="slug" value="{{ old('slug', $category->slug) }}" />
    <div><label class="label">Parent</label>
        <select name="parent_id" class="input"><option value="">—</option>
            @foreach($parents as $parent)<option value="{{ $parent->id }}" @selected(old('parent_id',$category->parent_id)==$parent->id)>{{ $parent->name }}</option>@endforeach
        </select>
    </div>
    <div><label class="label">Description</label><textarea name="description" class="input" rows="3">{{ old('description', $category->description) }}</textarea></div>

    <div>
        <span class="label" id="icon-label">Icon</span>
        <x-admin.image-upload
            name="icon"
            id="icon"
            frame="square-sm"
            alt="Category icon preview"
            empty="Click to upload"
            :src="$initialPreview"
            accept=".png,image/png,.jpg,.jpeg,image/jpeg,.webp,image/webp,.svg,image/svg+xml,.gif,image/gif"
            aria-labelledby="icon-label"
        />
        <p class="mt-1 text-xs text-taupe leading-snug">PNG recommended (also JPG, WebP, SVG). Shown in shop navigation and category cards.</p>
        @error('icon')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
    </div>

    <x-input label="Sort order" name="sort_order" type="number" value="{{ old('sort_order', $category->sort_order ?? 0) }}" />
    <label class="flex gap-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $category->is_active ?? true))> Active</label>
    <label class="flex gap-2 text-sm"><input type="checkbox" name="is_featured" value="1" @checked(old('is_featured', $category->is_featured))> Featured</label>
    <button class="btn btn-primary" type="submit">Save</button>
</form>
@if($category->exists)
<form class="mt-4" method="POST" action="{{ route('admin.categories.destroy', $category) }}">@csrf @method('DELETE')<button class="btn btn-danger" onclick="return confirm('Delete?')">Delete</button></form>
@endif
@endsection
