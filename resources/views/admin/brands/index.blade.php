@extends('layouts.admin')
@section('heading', 'Brands')
@section('content')
<x-admin.bulk-form
    :action="route('admin.brands.bulk-destroy')"
    :ids="$brands->pluck('id')"
    confirm="Delete the selected brands? This cannot be undone."
>
    <x-slot:actions>
        <a href="{{ route('admin.brands.create') }}" class="btn btn-primary">Add brand</a>
    </x-slot:actions>

    <div class="border border-beige bg-[#FFFCFA]">
    @foreach($brands as $brand)
        <div class="flex items-center justify-between gap-3 border-b border-beige p-3 text-sm">
            <div class="flex items-center gap-3 min-w-0">
                <x-admin.bulk-checkbox :id="$brand->id" />
                <div>{{ $brand->name }}</div>
            </div>
            <a class="underline shrink-0" href="{{ route('admin.brands.edit', $brand) }}">Edit</a>
        </div>
    @endforeach
    </div>
</x-admin.bulk-form>
<div class="mt-6">{{ $brands->links() }}</div>
@endsection
