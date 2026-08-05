@extends('layouts.admin')
@section('heading', 'Banners')
@section('content')
<x-admin.bulk-form
    :action="route('admin.banners.bulk-destroy')"
    :ids="$banners->pluck('id')"
    confirm="Delete the selected banners? This cannot be undone."
>
    <x-slot:actions>
        <a href="{{ route('admin.banners.create') }}" class="btn btn-primary">Add banner</a>
    </x-slot:actions>

    @foreach($banners as $banner)
        <div class="flex items-center justify-between gap-3 border border-beige bg-[#FFFCFA] p-3 mb-2 text-sm">
            <div class="flex items-center gap-3 min-w-0">
                <x-admin.bulk-checkbox :id="$banner->id" />
                <div>{{ $banner->title }} <span class="text-taupe">· {{ $banner->placement }}</span></div>
            </div>
            <a class="underline shrink-0" href="{{ route('admin.banners.edit', $banner) }}">Edit</a>
        </div>
    @endforeach
</x-admin.bulk-form>
<div class="mt-6">{{ $banners->links() }}</div>
@endsection
