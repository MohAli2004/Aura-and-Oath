@extends('layouts.storefront')
@section('title', 'Wishlist')
@section('content')
<div class="max-w-6xl mx-auto px-4 sm:px-6 py-10">
    <h1 class="font-display text-5xl mb-8">Wishlist</h1>
    @if($wishlist->items->isEmpty())
        <x-empty-state title="No saved items" :action="route('shop')" actionLabel="Browse shop" />
    @else
        <div class="grid grid-cols-2 md:grid-cols-4 gap-5">
            @foreach($wishlist->items as $item)
                <div>
                    <x-product-card :product="$item->product" />
                    <form method="POST" action="{{ route('wishlist.destroy', $item) }}" class="mt-2">@csrf @method('DELETE')
                        <button class="btn btn-secondary w-full" type="submit">Remove</button>
                    </form>
                </div>
            @endforeach
        </div>
    @endif
</div>
@endsection
