@extends('layouts.storefront')
@section('title', 'Account')
@section('content')
<div class="max-w-5xl mx-auto px-4 sm:px-6 py-10">
    <h1 class="font-display text-5xl mb-8">Account</h1>
    <div class="grid md:grid-cols-2 gap-8">
        <form method="POST" action="{{ route('account.update') }}" class="space-y-4 border border-beige p-6 bg-[#FFFCFA]">
            @csrf @method('PUT')
            <h2 class="font-display text-2xl">Profile</h2>
            <x-input label="Name" name="name" value="{{ old('name', $user->name) }}" required />
            <x-input label="Phone" name="phone" value="{{ old('phone', $user->phone) }}" />
            <p class="text-sm text-taupe">Email: {{ $user->email }}</p>
            <button class="btn btn-primary" type="submit">Save</button>
        </form>
        <form method="POST" action="{{ route('account.password') }}" class="space-y-4 border border-beige p-6 bg-[#FFFCFA]">
            @csrf @method('PUT')
            <h2 class="font-display text-2xl">Password</h2>
            <x-input label="Current password" name="current_password" type="password" required />
            <x-input label="New password" name="password" type="password" required />
            <x-input label="Confirm" name="password_confirmation" type="password" required />
            <button class="btn btn-primary" type="submit">Update password</button>
        </form>
    </div>
    <div class="mt-10 flex flex-wrap gap-4 text-sm">
        <a class="btn btn-secondary" href="{{ route('account.orders.index') }}">Orders</a>
        <a class="btn btn-secondary" href="{{ route('account.notifications.index') }}">Notifications</a>
        <a class="btn btn-secondary" href="{{ route('account.addresses') }}">Addresses</a>
        <a class="btn btn-secondary" href="{{ route('wishlist.index') }}">Wishlist</a>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="btn btn-secondary">Sign out</button>
        </form>
    </div>
    <div class="mt-10">
        <h2 class="font-display text-3xl mb-4">Recent orders</h2>
        @forelse($orders as $order)
            <a href="{{ route('account.orders.show', $order) }}" class="flex justify-between border-b border-beige py-3 text-sm">
                <span>{{ $order->order_number }}</span>
                <span>{{ $order->status->label() }}</span>
                <span>{{ money($order->total) }}</span>
            </a>
        @empty
            <p class="text-taupe">No orders yet.</p>
        @endforelse
    </div>
</div>
@endsection
