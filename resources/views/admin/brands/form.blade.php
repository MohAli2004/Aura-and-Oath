@extends('layouts.admin')
@section('heading', $brand->exists ? 'Edit brand' : 'New brand')
@section('content')
<form method="POST" action="{{ $brand->exists ? route('admin.brands.update', $brand) : route('admin.brands.store') }}" class="max-w-xl space-y-4">
    @csrf @if($brand->exists) @method('PUT') @endif
    <x-input label="Name" name="name" value="{{ old('name', $brand->name) }}" required />
    <x-input label="Slug" name="slug" value="{{ old('slug', $brand->slug) }}" />
    <div><label class="label">Description</label><textarea name="description" class="input" rows="3">{{ old('description', $brand->description) }}</textarea></div>
    <x-input label="Website" name="website" value="{{ old('website', $brand->website) }}" />
    <label class="flex gap-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $brand->is_active ?? true))> Active</label>
    <label class="flex gap-2 text-sm"><input type="checkbox" name="is_featured" value="1" @checked(old('is_featured', $brand->is_featured))> Featured</label>
    <button class="btn btn-primary" type="submit">Save</button>
</form>
@if($brand->exists)
<form class="mt-4" method="POST" action="{{ route('admin.brands.destroy', $brand) }}">@csrf @method('DELETE')<button class="btn btn-danger" onclick="return confirm('Delete?')">Delete</button></form>
@endif
@endsection
