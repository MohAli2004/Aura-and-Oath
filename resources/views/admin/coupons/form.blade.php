@extends('layouts.admin')
@section('heading', $coupon->exists ? 'Edit coupon' : 'New coupon')
@section('content')
<form method="POST" action="{{ $coupon->exists ? route('admin.coupons.update', $coupon) : route('admin.coupons.store') }}" class="max-w-xl space-y-4">
    @csrf @if($coupon->exists) @method('PUT') @endif
    <x-input label="Code" name="code" value="{{ old('code', $coupon->code) }}" required />
    <x-input label="Name" name="name" value="{{ old('name', $coupon->name) }}" />
    <div><label class="label">Type</label>
        <select name="discount_type" class="input">
            <option value="percentage" @selected(old('discount_type', $coupon->discount_type?->value)==='percentage')>Percentage</option>
            <option value="fixed" @selected(old('discount_type', $coupon->discount_type?->value)==='fixed')>Fixed</option>
        </select>
    </div>
    <x-input label="Value" name="discount_value" type="number" step="0.01" value="{{ old('discount_value', $coupon->discount_value) }}" required />
    <x-input label="Min order" name="min_order_amount" type="number" step="0.01" value="{{ old('min_order_amount', $coupon->min_order_amount) }}" />
    <x-input label="Max discount" name="max_discount_amount" type="number" step="0.01" value="{{ old('max_discount_amount', $coupon->max_discount_amount) }}" />
    <x-input label="Usage limit" name="usage_limit" type="number" value="{{ old('usage_limit', $coupon->usage_limit) }}" />
    <x-input label="Per user limit" name="usage_limit_per_user" type="number" value="{{ old('usage_limit_per_user', $coupon->usage_limit_per_user) }}" />
    <label class="flex gap-2 text-sm"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $coupon->is_active ?? true))> Active</label>
    <button class="btn btn-primary" type="submit">Save</button>
</form>
@if($coupon->exists)
<form class="mt-4" method="POST" action="{{ route('admin.coupons.destroy', $coupon) }}">@csrf @method('DELETE')<button class="btn btn-danger" onclick="return confirm('Delete?')">Delete</button></form>
@endif
@endsection
