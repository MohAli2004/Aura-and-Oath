@extends('layouts.storefront')
@section('title', 'Wishlist')
@section('content')
<div
    class="max-w-6xl mx-auto px-4 sm:px-6 py-10"
    x-data="{
        empty: @js($wishlist->items->isEmpty()),
        async remove(url, event) {
            const card = event.currentTarget.closest('[data-wishlist-item]');
            try {
                const data = await window.auraHttp(url, { method: 'DELETE', body: {} });
                card?.remove();
                if (! document.querySelector('[data-wishlist-item]')) {
                    this.empty = true;
                }
                window.dispatchEvent(new CustomEvent('aura:toast', {
                    detail: { message: data.message || 'Removed from wishlist.', type: 'success' },
                }));
            } catch (error) {
                window.dispatchEvent(new CustomEvent('aura:toast', {
                    detail: { message: error.message || 'Could not remove item.', type: 'error' },
                }));
            }
        },
    }"
>
    <h1 class="font-display text-5xl mb-8">Wishlist</h1>

    <div x-show="empty" x-cloak>
        <x-empty-state title="No saved items" :action="route('shop')" actionLabel="Browse shop" />
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-5" x-show="!empty">
        @foreach($wishlist->items as $item)
            <div data-wishlist-item>
                <x-product-card :product="$item->product" />
                <button
                    class="btn btn-secondary w-full mt-2"
                    type="button"
                    @click="remove(@js(route('wishlist.destroy', $item)), $event)"
                >Remove</button>
            </div>
        @endforeach
    </div>
</div>
@endsection
