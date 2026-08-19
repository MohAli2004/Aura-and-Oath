@extends('layouts.storefront')
@section('title', 'Bag — '.config('aura.name'))
@section('content')
<div class="max-w-5xl mx-auto px-4 sm:px-6 py-10">
    <h1 class="font-display text-5xl mb-8">Your bag</h1>
    @if($cart->items->isEmpty())
        <x-empty-state title="Your bag is empty" message="Discover something beautiful." :action="route('shop')" actionLabel="Shop now" />
    @else
        <div class="space-y-8">
            @foreach($offerGroups as $group)
                @php
                    $images = app(\App\Services\ImageService::class);
                    $representative = $group['representative'];
                @endphp
                <div class="border border-beige bg-[#FFFCFA] p-4 sm:p-5">
                    <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4 mb-4">
                        <div>
                            <div class="text-[11px] uppercase tracking-[0.16em] text-blush">Hot offer</div>
                            <div class="font-display text-3xl mt-1">{{ $group['offer']->title ?? 'Offer' }}</div>
                            <p class="text-sm text-taupe mt-1">All products in this set are required to keep the offer price.</p>
                            <div class="mt-2 flex items-baseline gap-2">
                                <span class="text-lg">{{ money($group['offer_total']) }}</span>
                                @if($group['regular_total'] > $group['offer_total'])
                                    <span class="text-sm text-taupe line-through">{{ money($group['regular_total']) }}</span>
                                @endif
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <form method="POST" action="{{ route('cart.update', $representative) }}" class="flex items-center gap-2">
                                @csrf @method('PATCH')
                                <input type="number" name="quantity" value="{{ $group['quantity'] }}" min="0" class="input w-20" aria-label="Offer quantity">
                                <button class="btn btn-secondary" type="submit">Update</button>
                            </form>
                            <form method="POST" action="{{ route('cart.destroy', $representative) }}">
                                @csrf @method('DELETE')
                                <button class="btn btn-secondary" type="submit">Remove offer</button>
                            </form>
                        </div>
                    </div>
                    <div class="space-y-3">
                        @foreach($group['items'] as $item)
                            @php
                                $imagePath = $item->variant?->image_path ?: $item->product?->primaryImagePath();
                                $imageUrl = $images->url($imagePath);
                                $productUrl = $item->product ? route('products.show', $item->product->slug) : null;
                            @endphp
                            <div class="flex gap-4 items-center">
                                @if($productUrl)
                                    <a href="{{ $productUrl }}" class="shrink-0 h-16 w-16 overflow-hidden border border-beige bg-beige/30">
                                        <img src="{{ $imageUrl }}" alt="{{ $item->product->name }}" class="h-full w-full object-cover">
                                    </a>
                                @endif
                                <div class="min-w-0 flex-1">
                                    <div class="font-medium leading-tight">{{ $item->product->name }}</div>
                                    @if($item->variant)
                                        <div class="text-xs text-taupe mt-0.5">{{ $item->variant->displayName() }}</div>
                                    @endif
                                </div>
                                <div class="text-sm">{{ money($item->unitPrice()) }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach

            @foreach($looseItems as $item)
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
                <a href="{{ route('checkout.create') }}" class="btn btn-primary">Sign in to checkout</a>
            @endauth
        </div>
    @endif
</div>
@endsection
