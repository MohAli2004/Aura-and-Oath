@extends('layouts.storefront')
@section('title', 'FAQ')
@section('content')
<div class="max-w-3xl mx-auto px-4 sm:px-6 py-16 space-y-6">
    <h1 class="font-display text-5xl mb-8">FAQ</h1>
    <div><h2 class="font-display text-2xl">How long does delivery take?</h2><p class="text-taupe mt-2">Typically 1–6 business days depending on your region.</p></div>
    <div><h2 class="font-display text-2xl">What payment methods do you accept?</h2><p class="text-taupe mt-2">Cash on delivery and Wish Account transfer.</p></div>
    <div><h2 class="font-display text-2xl">When is stock reserved?</h2><p class="text-taupe mt-2">When you place an order. If rejected or cancelled before approval, reserved stock is released.</p></div>
    <div><h2 class="font-display text-2xl">Can I return an item?</h2><p class="text-taupe mt-2">Yes — see our <a href="{{ route('pages.show', 'returns-policy') }}" class="underline">returns policy</a>.</p></div>
</div>
@endsection
