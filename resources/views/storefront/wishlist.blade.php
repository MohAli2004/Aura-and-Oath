@extends('layouts.storefront')
@section('title', __('storefront.page_title_wishlist'))
@section('content')
<div
    class="max-w-6xl mx-auto px-4 sm:px-6 py-10"
    @aura-wishlist-removed.window="syncEmpty()"
    x-data="{
        empty: @js($wishlist->items->isEmpty()),
        syncEmpty() {
            this.empty = ! document.querySelector('[data-wishlist-item]');
        },
        async remove(url, event) {
            const card = event.currentTarget.closest('[data-wishlist-item]');
            try {
                const data = await window.auraHttp(url, { method: 'DELETE', body: {} });
                card?.remove();
                if (! document.querySelector('[data-wishlist-item]')) {
                    this.empty = true;
                }
                window.dispatchEvent(new CustomEvent('aura:toast', {
                    detail: { message: data.message || window.auraI18n?.removedFromWishlist || @js(__('storefront.flash_removed_wishlist')), type: 'success' },
                }));
            } catch (error) {
                window.dispatchEvent(new CustomEvent('aura:toast', {
                    detail: { message: error.message || @js(__('storefront.js_could_not_remove')), type: 'error' },
                }));
            }
        },
    }"
>
    <h1 class="font-display text-5xl mb-8">{{ __('storefront.wishlist') }}</h1>

    <div x-show="empty" x-cloak>
        <x-empty-state :title="__('storefront.wishlist_empty_title')" :action="route('shop')" :actionLabel="__('storefront.browse_shop')" />
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-5" x-show="!empty">
        @foreach($wishlist->items as $item)
            <div data-wishlist-item>
                <x-product-card :product="$item->product" :in-wishlist="true" />
                <button
                    class="btn btn-secondary w-full mt-2"
                    type="button"
                    @click="remove(@js(route('wishlist.destroy', $item)), $event)"
                >{{ __('storefront.remove') }}</button>
            </div>
        @endforeach
    </div>
</div>
@endsection
