@extends('layouts.storefront')
@section('title', __('storefront.page_title_account'))
@section('content')
<div class="max-w-5xl mx-auto px-4 sm:px-6 py-10">
    <h1 class="font-display text-5xl mb-8">{{ __('storefront.account') }}</h1>
    <div class="grid md:grid-cols-2 gap-8">
        <form method="POST" action="{{ route('account.update') }}" class="space-y-4 border border-beige p-6 bg-[#FFFCFA]">
            @csrf @method('PUT')
            <h2 class="font-display text-2xl">{{ __('storefront.profile') }}</h2>
            <x-input :label="__('storefront.name')" name="name" value="{{ old('name', $user->name) }}" required />
            <x-input :label="__('storefront.phone')" name="phone" value="{{ old('phone', $user->phone) }}" />
            <p class="text-sm text-taupe">{{ __('storefront.email') }}: {{ $user->email }}</p>
            <button class="btn btn-primary" type="submit">{{ __('storefront.save') }}</button>
        </form>
        <form method="POST" action="{{ route('account.password') }}" class="space-y-4 border border-beige p-6 bg-[#FFFCFA]">
            @csrf @method('PUT')
            <h2 class="font-display text-2xl">{{ __('storefront.password') }}</h2>
            <x-input :label="__('storefront.current_password')" name="current_password" type="password" required />
            <x-input :label="__('storefront.new_password')" name="password" type="password" required />
            <x-input :label="__('storefront.confirm')" name="password_confirmation" type="password" required />
            <button class="btn btn-primary" type="submit">{{ __('storefront.update_password') }}</button>
        </form>
    </div>
    <div class="mt-10 flex flex-wrap gap-4 text-sm">
        <a class="btn btn-secondary" href="{{ route('account.orders.index') }}">{{ __('storefront.orders') }}</a>
        <a class="btn btn-secondary" href="{{ route('returns.index') }}">{{ __('storefront.returns') }}</a>
        <a class="btn btn-secondary" href="{{ route('account.notifications.index') }}">{{ __('storefront.notifications') }}</a>
        <a class="btn btn-secondary" href="{{ route('account.addresses') }}">{{ __('storefront.page_title_addresses') }}</a>
        <a class="btn btn-secondary" href="{{ route('wishlist.index') }}">{{ __('storefront.wishlist') }}</a>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="btn btn-secondary">{{ __('storefront.sign_out') }}</button>
        </form>
    </div>
    <div class="mt-10">
        <h2 class="font-display text-3xl mb-4">{{ __('storefront.recent_orders') }}</h2>
        @forelse($orders as $order)
            <a href="{{ route('account.orders.show', $order) }}" class="flex justify-between border-b border-beige py-3 text-sm">
                <span>{{ $order->order_number }}</span>
                <span>{{ $order->status->label() }}</span>
                <span>{{ money($order->total) }}</span>
            </a>
        @empty
            <p class="text-taupe">{{ __('storefront.no_orders_yet') }}</p>
        @endforelse
    </div>
</div>
@endsection
