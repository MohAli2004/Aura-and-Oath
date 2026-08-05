@extends('layouts.storefront')
@section('title', 'Bag — '.config('aura.name'))
@section('content')
<div class="max-w-5xl mx-auto px-4 sm:px-6 py-10">
    <h1 class="font-display text-5xl mb-8">Your bag</h1>
    @if($cart->items->isEmpty())
        <x-empty-state title="Your bag is empty" message="Discover something beautiful." :action="route('shop')" actionLabel="Shop now" />
    @else
        <div class="space-y-4">
            @foreach($cart->items as $item)
                @php
                    $images = app(\App\Services\ImageService::class);
                    $imagePath = $item->variant?->image_path ?: $item->product?->primaryImagePath();
                    $imageUrl = $images->url($imagePath);
                    $productUrl = $item->product
                        ? route('products.show', $item->product->slug)
                        : null;
                @endphp
                <div class="flex flex-col sm:flex-row gap-4 justify-between border-b border-beige py-4">
                    <div class="flex gap-4 min-w-0">
                        @if($productUrl)
                            <a href="{{ $productUrl }}" class="shrink-0 h-20 w-20 sm:h-24 sm:w-24 overflow-hidden border border-beige bg-beige/30">
                                <img src="{{ $imageUrl }}" alt="{{ $item->product->name }}" class="h-full w-full object-cover">
                            </a>
                        @else
                            <div class="shrink-0 h-20 w-20 sm:h-24 sm:w-24 overflow-hidden border border-beige bg-beige/30">
                                <img src="{{ $imageUrl }}" alt="" class="h-full w-full object-cover">
                            </div>
                        @endif
                        <div class="min-w-0">
                            @if($productUrl)
                                <a href="{{ $productUrl }}" class="font-display text-2xl leading-tight hover:text-gold transition">
                                    {{ $item->product->name }}
                                </a>
                            @else
                                <div class="font-display text-2xl">{{ $item->product->name ?? 'Product' }}</div>
                            @endif
                            @if($item->variant)
                                <div class="text-sm text-taupe mt-1">{{ $item->variant->displayName() }}</div>
                            @endif
                            <div class="text-sm mt-1">{{ money($item->unitPrice()) }}</div>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <form method="POST" action="{{ route('cart.update', $item) }}" class="flex items-center gap-2">
                            @csrf @method('PATCH')
                            <input type="number" name="quantity" value="{{ $item->quantity }}" min="0" class="input w-20">
                            <button class="btn btn-secondary" type="submit">Update</button>
                        </form>
                        <form method="POST" action="{{ route('cart.destroy', $item) }}">@csrf @method('DELETE')
                            <button class="btn btn-secondary" type="submit">Remove</button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="mt-8 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div class="text-xl">Subtotal: <strong>{{ money($subtotal) }}</strong></div>
            @auth
                <a href="{{ route('checkout.create') }}" class="btn btn-primary">Checkout</a>
            @else
                <a href="{{ route('login') }}" class="btn btn-primary">Sign in to checkout</a>
            @endauth
        </div>
    @endif
</div>
@endsection
