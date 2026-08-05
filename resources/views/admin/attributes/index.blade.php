@extends('layouts.admin')
@section('heading', 'Attributes')
@section('content')
<form method="POST" action="{{ route('admin.attributes.store') }}" class="flex flex-wrap gap-2 mb-8">
    @csrf
    <input class="input max-w-xs" name="name" placeholder="Attribute name" required>
    <select name="type" class="input max-w-xs">
        <option value="select">Select</option>
        <option value="color">Color</option>
        <option value="text">Text</option>
        <option value="unit">Unit</option>
    </select>
    <button class="btn btn-primary" type="submit">Add attribute</button>
</form>

<x-admin.bulk-form
    :action="route('admin.attributes.bulk-destroy')"
    :ids="$attributes->pluck('id')"
    confirm="Delete the selected attributes? This cannot be undone."
>
@forelse($attributes as $attribute)
    <div class="border border-beige bg-[#FFFCFA] p-4 mb-4">
        <div class="flex justify-between gap-3 mb-3">
            <div class="flex items-center gap-3 min-w-0">
                <x-admin.bulk-checkbox :id="$attribute->id" />
                <div class="min-w-0">
                    <h2 class="font-display text-2xl">{{ $attribute->name }}</h2>
                    <p class="text-xs uppercase tracking-widest text-taupe mt-0.5">{{ $attribute->type }}</p>
                </div>
            </div>
            <form method="POST" action="{{ route('admin.attributes.destroy', $attribute) }}">@csrf @method('DELETE')<button class="btn btn-danger" type="submit">Delete</button></form>
        </div>
        <div class="flex flex-wrap gap-2 mb-3">
            @forelse($attribute->values as $value)
                <x-badge>{{ $value->value }}</x-badge>
            @empty
                <span class="text-sm text-taupe">No values yet.</span>
            @endforelse
        </div>
        <form method="POST" action="{{ route('admin.attributes.values.store', $attribute) }}" class="flex flex-wrap gap-2">
            @csrf
            <input class="input" name="value" placeholder="{{ $attribute->type === 'unit' ? 'e.g. ml, g' : 'New value' }}" required>
            @if($attribute->type==='color')<input class="input w-28" name="color_hex" placeholder="#HEX">@endif
            <button class="btn btn-secondary" type="submit">Add value</button>
        </form>
        @if($attribute->type === 'unit')
            <p class="mt-2 text-xs text-taupe">Unit values (e.g. ml, g) are used for product size on the product form.</p>
        @endif
    </div>
@empty
    <p class="text-sm text-taupe">No attributes yet. Add one above.</p>
@endforelse
</x-admin.bulk-form>
@endsection
