@extends('layouts.admin')
@section('heading', 'Hot offers')
@section('title', 'Hot offers')
@section('content')
<x-admin.bulk-form
    :action="route('admin.offers.bulk-destroy')"
    :ids="$offers->pluck('id')"
    confirm="Delete the selected offers?"
>
    <x-slot:actions>
        <a href="{{ route('admin.offers.create') }}" class="btn btn-primary">Add offer</a>
    </x-slot:actions>

    @forelse($offers as $offer)
        <div class="flex items-center justify-between gap-3 border border-beige bg-[#FFFCFA] p-3 mb-2 text-sm">
            <div class="flex items-center gap-3 min-w-0">
                <x-admin.bulk-checkbox :id="$offer->id" />
                <div class="h-12 w-16 shrink-0 overflow-hidden border border-beige bg-ivory/60">
                    @if(filled($offer->image_path))
                        <img src="{{ app(\App\Services\ImageService::class)->url($offer->image_path) }}" alt="" class="h-full w-full object-cover">
                    @else
                        <div class="flex h-full items-center justify-center text-[10px] text-taupe">No image</div>
                    @endif
                </div>
                <div class="min-w-0">
                    <div class="truncate font-medium">{{ $offer->title }}</div>
                    <div class="text-taupe text-xs">
                        {{ $offer->products_count }} {{ Str::plural('product', $offer->products_count) }}
                        · {{ $offer->statusLabel() }}
                        @if($offer->is_active && $offer->starts_at && $offer->starts_at->isFuture())
                            · starts {{ $offer->starts_at->timezone(config('app.timezone'))->format('M j, Y H:i') }}
                        @endif
                    </div>
                </div>
            </div>
            <a class="underline shrink-0" href="{{ route('admin.offers.edit', $offer) }}">Edit</a>
        </div>
    @empty
        <p class="text-sm text-taupe">No offers yet. Create a group of products with special prices.</p>
    @endforelse
</x-admin.bulk-form>
<x-admin.pagination :paginator="$offers" noun="offer" />
@endsection
