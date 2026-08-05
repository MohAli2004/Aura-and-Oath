@extends('layouts.admin')
@section('heading', $category->exists ? 'Edit category' : 'New category')
@section('content')
<form
    method="POST"
    action="{{ $category->exists ? route('admin.categories.update', $category) : route('admin.categories.store') }}"
    enctype="multipart/form-data"
    class="max-w-xl space-y-4"
    x-data="{ preview: @js($category->exists ? $category->iconUrl() : null) }"
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
        <label class="label" for="icon">Icon</label>
        <input
            id="icon"
            type="file"
            name="icon"
            class="input"
            accept=".png,image/png,.jpg,.jpeg,image/jpeg,.webp,image/webp,.svg,image/svg+xml,.gif,image/gif"
            @change="preview = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : preview"
        >
        <p class="mt-1 text-xs text-taupe leading-snug">PNG recommended (also JPG, WebP, SVG). Shown in shop navigation and category cards.</p>
        @error('icon')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
        <div class="mt-3 flex items-center gap-3" x-show="preview" x-cloak>
            <div class="flex h-14 w-14 items-center justify-center border border-beige bg-ivory/60 p-2">
                <img :src="preview" alt="Category icon preview" class="max-h-full max-w-full object-contain">
            </div>
            <span class="text-xs text-taupe">Preview</span>
        </div>
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
