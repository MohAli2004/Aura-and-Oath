@extends('layouts.admin')
@section('heading', $banner->exists ? 'Edit banner' : 'New banner')
@section('content')
@php
    $initialPreview = filled($banner->image_path)
        ? app(\App\Services\ImageService::class)->url($banner->image_path)
        : null;
@endphp
<form
    method="POST"
    action="{{ $banner->exists ? route('admin.banners.update', $banner) : route('admin.banners.store') }}"
    enctype="multipart/form-data"
    class="max-w-xl space-y-4"
>
    @csrf @if($banner->exists) @method('PUT') @endif
    <x-input label="Title" name="title" value="{{ old('title', $banner->title) }}" required />
    <x-input label="Subtitle" name="subtitle" value="{{ old('subtitle', $banner->subtitle) }}" />
    <x-input label="Link URL" name="link_url" value="{{ old('link_url', $banner->link_url) }}" />
    <x-input label="Button text" name="button_text" value="{{ old('button_text', $banner->button_text) }}" />
    <x-input label="Placement" name="placement" value="{{ old('placement', $banner->placement ?? 'home_hero') }}" />
    <div>
        <span class="label" id="image-label">Image</span>
        <x-admin.image-upload
            name="image"
            id="image"
            frame="wide"
            fit="cover"
            alt="Banner image preview"
            empty="Click to upload"
            :src="$initialPreview"
            accept="image/*"
            aria-labelledby="image-label"
        />
        <p class="mt-1 text-xs text-taupe leading-snug">Wide image recommended for hero banners.</p>
        @error('image')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
    </div>
    <label class="flex gap-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $banner->is_active ?? true))> Active</label>
    <button class="btn btn-primary" type="submit">Save</button>
</form>
@endsection
