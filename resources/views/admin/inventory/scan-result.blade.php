@extends('layouts.admin')
@section('heading', 'Barcode scan')
@section('title', 'Scan result')
@section('content')
<div class="border border-beige bg-[#FFFCFA] p-6 max-w-xl">
    <div class="text-xs uppercase tracking-widest text-taupe mb-2">{{ $barcode }}</div>
    <h2 class="font-display text-3xl mb-2">{{ $result['name'] }}</h2>
    <p class="text-sm mb-4">SKU: {{ $result['sku'] }}</p>
    <p>Stock: {{ $result['stock_quantity'] }} · Reserved: {{ $result['reserved_quantity'] }} · Available: {{ $result['available_stock'] }}</p>
    <div class="mt-6 flex gap-2">
        <a href="{{ route('admin.products.edit', $result['product']) }}" class="btn btn-primary">Edit product</a>
        <a href="{{ route('admin.inventory.index') }}" class="btn btn-secondary">Back</a>
    </div>
</div>
@endsection
