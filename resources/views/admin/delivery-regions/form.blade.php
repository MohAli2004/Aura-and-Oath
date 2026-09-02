@extends('layouts.admin')
@section('heading', $region->exists ? 'Edit delivery region' : 'New delivery region')
@section('content')
<form method="POST" action="{{ $region->exists ? route('admin.delivery-regions.update', $region) : route('admin.delivery-regions.store') }}" class="max-w-xl space-y-4">
    @csrf @if($region->exists) @method('PUT') @endif
    <x-input label="Name" name="name" value="{{ old('name', $region->name) }}" required />
    <x-input label="Code" name="code" value="{{ old('code', $region->code) }}" hint="Short unique code, e.g. BRT." />
    <x-input label="Delivery fee" name="fee" type="number" step="0.01" min="0" value="{{ old('fee', $region->fee) }}" required />
    <div>
        <label class="label" for="description">Description <span class="normal-case tracking-wide text-[10px] font-normal text-taupe">Optional</span></label>
        <textarea id="description" name="description" class="input" rows="3">{{ old('description', $region->description) }}</textarea>
        @error('description')<p class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
    </div>
    <div class="grid grid-cols-2 gap-4">
        <x-input label="Est. days min" name="estimated_days_min" type="number" min="0" value="{{ old('estimated_days_min', $region->estimated_days_min ?? 1) }}" required />
        <x-input label="Est. days max" name="estimated_days_max" type="number" min="0" value="{{ old('estimated_days_max', $region->estimated_days_max ?? 5) }}" required />
    </div>
    <x-input label="Sort order" name="sort_order" type="number" min="0" value="{{ old('sort_order', $region->sort_order ?? 0) }}" />
    <x-admin.toggle
        name="is_active"
        label="Show this delivery choice to customers"
        hint="Hidden regions stay in admin so you can show them again later."
        :checked="old('is_active', $region->is_active ?? true)"
    />
    <button class="btn btn-primary" type="submit">Save</button>
</form>
@if($region->exists)
<form class="mt-4" method="POST" action="{{ route('admin.delivery-regions.destroy', $region) }}">@csrf @method('DELETE')<button class="btn btn-danger" onclick="return confirm('Delete?')">Delete</button></form>
@endif
@endsection
