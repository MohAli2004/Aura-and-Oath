@extends('layouts.admin')
@section('heading', 'Coupons')
@section('content')
<x-admin.bulk-form
    :action="route('admin.coupons.bulk-destroy')"
    :ids="$coupons->pluck('id')"
    confirm="Delete the selected coupons? This cannot be undone."
>
    <x-slot:actions>
        <a href="{{ route('admin.coupons.create') }}" class="btn btn-primary">Add coupon</a>
    </x-slot:actions>

    <div class="border border-beige bg-[#FFFCFA]">
    @foreach($coupons as $coupon)
        <div class="flex items-center justify-between gap-3 border-b border-beige p-3 text-sm">
            <div class="flex items-center gap-3 min-w-0">
                <x-admin.bulk-checkbox :id="$coupon->id" />
                <div><strong>{{ $coupon->code }}</strong> · {{ $coupon->discount_type->label() }} {{ $coupon->discount_value }}</div>
            </div>
            <a class="underline shrink-0" href="{{ route('admin.coupons.edit', $coupon) }}">Edit</a>
        </div>
    @endforeach
    </div>
</x-admin.bulk-form>
<div class="mt-6">{{ $coupons->links() }}</div>
@endsection
