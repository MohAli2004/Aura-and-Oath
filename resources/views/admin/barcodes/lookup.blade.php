@extends('layouts.admin')
@section('heading', 'Barcode lookup')
@section('content')
<form method="GET" class="flex gap-2 max-w-xl mb-8">
    <input class="input" name="barcode" value="{{ $barcode }}" placeholder="Enter or scan barcode" autofocus>
    <button class="btn btn-primary" type="submit">Lookup</button>
</form>
@if($barcode)
    @if($result)
        <div class="border border-beige p-5 bg-[#FFFCFA] max-w-xl">
            <div class="text-xs uppercase tracking-widest text-taupe mb-1">{{ $format }}</div>
            <h2 class="font-display text-3xl">{{ $result['name'] }}</h2>
            <p class="mt-2 text-sm">SKU {{ $result['sku'] }} · Available {{ $result['available_stock'] }}</p>
            <a class="btn btn-secondary mt-4" href="{{ route('admin.products.edit', $result['product']) }}">Open product</a>
            <a class="btn btn-secondary mt-4" href="{{ route('admin.barcodes.labels', ['codes' => $barcode]) }}">Print label</a>
        </div>
    @else
        <x-alert type="error">No product found for this barcode.</x-alert>
    @endif
@endif
@endsection
