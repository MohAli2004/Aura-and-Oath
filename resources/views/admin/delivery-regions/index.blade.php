@extends('layouts.admin')
@section('heading', 'Delivery regions')
@section('content')
<x-admin.bulk-form
    :action="route('admin.delivery-regions.bulk-destroy')"
    :ids="$regions->pluck('id')"
    confirm="Delete the selected delivery regions? This cannot be undone."
>
    <x-slot:actions>
        <a href="{{ route('admin.delivery-regions.create') }}" class="btn btn-primary">Add region</a>
    </x-slot:actions>

    <div class="border border-beige bg-[#FFFCFA]">
    @forelse($regions as $region)
        <div class="flex items-center justify-between gap-3 border-b border-beige p-3 text-sm">
            <div class="flex items-center gap-3 min-w-0">
                <x-admin.bulk-checkbox :id="$region->id" />
                <div class="min-w-0">
                    <div>
                        <strong>{{ $region->name }}</strong>
                        @if($region->code)
                            <span class="text-taupe">· {{ $region->code }}</span>
                        @endif
                        <span class="text-taupe">· {{ money($region->fee) }}</span>
                        @unless($region->is_active)
                            <span class="text-taupe">· Inactive</span>
                        @endunless
                    </div>
                    @if($region->description)
                        <div class="truncate text-xs text-taupe mt-0.5">{{ $region->description }}</div>
                    @endif
                </div>
            </div>
            <a class="underline shrink-0" href="{{ route('admin.delivery-regions.edit', $region) }}">Edit</a>
        </div>
    @empty
        <div class="p-4 text-sm text-taupe">No delivery regions yet.</div>
    @endforelse
    </div>
</x-admin.bulk-form>
<div class="mt-6">{{ $regions->links() }}</div>
@endsection
