@extends('layouts.admin')
@section('heading', $banner->exists ? 'Edit banner' : 'New banner')
@section('content')
<form method="POST" action="{{ $banner->exists ? route('admin.banners.update', $banner) : route('admin.banners.store') }}" enctype="multipart/form-data" class="max-w-xl space-y-4">
    @csrf @if($banner->exists) @method('PUT') @endif
    <x-input label="Title" name="title" value="{{ old('title', $banner->title) }}" required />
    <x-input label="Subtitle" name="subtitle" value="{{ old('subtitle', $banner->subtitle) }}" />
    <x-input label="Link URL" name="link_url" value="{{ old('link_url', $banner->link_url) }}" />
    <x-input label="Button text" name="button_text" value="{{ old('button_text', $banner->button_text) }}" />
    <x-input label="Placement" name="placement" value="{{ old('placement', $banner->placement ?? 'home_hero') }}" />
    <div><label class="label">Image</label><input type="file" name="image" class="input" accept="image/*"></div>
    <label class="flex gap-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $banner->is_active ?? true))> Active</label>
    <button class="btn btn-primary" type="submit">Save</button>
</form>
@endsection
