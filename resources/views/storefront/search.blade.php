@extends('layouts.storefront')
@section('title', 'Search — '.config('aura.name'))
@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 py-10">
    <h1 class="font-display text-5xl mb-2">Search</h1>
    <p class="text-taupe mb-8">Results for “{{ $q }}”</p>
    <form method="GET" class="mb-8 max-w-xl"><input class="input" type="search" name="q" value="{{ $q }}" placeholder="Search..."></form>
    @if($products->isEmpty())
        <x-empty-state title="No matches" message="Try another keyword." :action="route('shop')" actionLabel="Browse shop" />
    @else
        <div class="grid grid-cols-2 md:grid-cols-4 gap-5">
            @foreach($products as $product)<x-product-card :product="$product" />@endforeach
        </div>
        <div class="mt-10">{{ $products->links() }}</div>
    @endif
</div>
@endsection
