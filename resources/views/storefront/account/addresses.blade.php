@extends('layouts.storefront')
@section('title', 'Addresses')
@section('content')
<div class="max-w-4xl mx-auto px-4 sm:px-6 py-10">
    <h1 class="font-display text-5xl mb-8">Addresses</h1>
    <div class="space-y-4 mb-10">
        @forelse($addresses as $address)
            <div class="border border-beige p-4 flex justify-between gap-4">
                <div>
                    <div class="font-medium">{{ $address->label ?? 'Address' }} @if($address->is_default)<x-badge>Default</x-badge>@endif</div>
                    <div class="text-sm text-taupe mt-1">{{ $address->full_name }} · {{ $address->phone }}</div>
                    <div class="text-sm">{{ $address->formatted() }}</div>
                </div>
                <form method="POST" action="{{ route('account.addresses.destroy', $address) }}">@csrf @method('DELETE')
                    <button class="btn btn-secondary" type="submit">Delete</button>
                </form>
            </div>
        @empty
            <x-empty-state title="No addresses" message="Add one for faster checkout." />
        @endforelse
    </div>
    <form method="POST" action="{{ route('account.addresses.store') }}" class="grid sm:grid-cols-2 gap-4 border border-beige p-6 bg-[#FFFCFA]">
        @csrf
        <h2 class="font-display text-2xl sm:col-span-2">Add address</h2>
        <x-input label="Label" name="label" />
        <x-input label="Full name" name="full_name" required />
        <x-input label="Phone" name="phone" required />
        <div class="sm:col-span-2"><x-input label="Line 1" name="line1" required /></div>
        <div class="sm:col-span-2"><x-input label="Line 2" name="line2" /></div>
        <x-input label="City" name="city" required />
        <x-input label="District" name="governorate" />
        <label class="flex items-center gap-2 text-sm sm:col-span-2"><input type="checkbox" name="is_default" value="1"> Default</label>
        <button class="btn btn-primary sm:col-span-2" type="submit">Save address</button>
    </form>
</div>
@endsection
