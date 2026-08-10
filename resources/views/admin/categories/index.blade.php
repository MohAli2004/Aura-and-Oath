@extends('layouts.admin')
@section('heading', 'Categories')
@section('title', 'Categories')
@section('content')
<x-admin.bulk-form
    :action="route('admin.categories.bulk-destroy')"
    :ids="$categories->pluck('id')"
    confirm="Delete the selected categories? This cannot be undone."
>
    <x-slot:actions>
        <a href="{{ route('admin.categories.create') }}" class="btn btn-primary">Add category</a>
    </x-slot:actions>

    <div class="border border-beige bg-[#FFFCFA]">
    @foreach($categories as $category)
        <div class="flex items-center justify-between gap-3 border-b border-beige p-3 text-sm">
            <div class="flex items-center gap-3 min-w-0">
                <x-admin.bulk-checkbox :id="$category->id" />
                <div class="h-10 w-10 shrink-0 overflow-hidden border border-beige bg-ivory/60">
                    <img src="{{ $category->iconUrl() }}" alt="" class="h-full w-full object-contain">
                </div>
                <div class="truncate">{{ $category->name }} @if($category->parent)<span class="text-taupe">/ {{ $category->parent->name }}</span>@endif</div>
            </div>
            <a class="underline shrink-0" href="{{ route('admin.categories.edit', $category) }}">Edit</a>
        </div>
    @endforeach
    </div>
</x-admin.bulk-form>
<div class="mt-6">{{ $categories->links() }}</div>
@endsection
