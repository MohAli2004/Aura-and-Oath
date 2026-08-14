@extends('layouts.storefront')
@section('title', 'FAQ')
@section('content')
<div class="max-w-3xl mx-auto px-4 sm:px-6 py-16 space-y-6">
    <h1 class="font-display text-5xl mb-8">FAQ</h1>
    <div><h2 class="font-display text-2xl">How long does delivery take?</h2><p class="text-taupe mt-2">Within Lebanon, delivery takes 1–3 business days depending on your distance from Beirut.</p></div>
    <div><h2 class="font-display text-2xl">What payment methods do you accept?</h2><p class="text-taupe mt-2">Cash on delivery and Wish Account transfer.</p></div>
    <div><h2 class="font-display text-2xl">When is stock reserved?</h2><p class="text-taupe mt-2">When you place an order. If rejected or cancelled before we start preparing it, reserved stock is released.</p></div>
    <div><h2 class="font-display text-2xl">Can I cancel an order?</h2><p class="text-taupe mt-2">Yes — you can cancel until we start preparing it. After that, wait until delivery and use the <a href="{{ route('returns.index') }}" class="underline">Returns</a> page.</p></div>
    <div><h2 class="font-display text-2xl">Can I return an item?</h2><p class="text-taupe mt-2">Only if there is a real problem — a defective or damaged item, a missing item, or a different item than the one you ordered. Used or broken-after-delivery items cannot be returned. Start a return on the <a href="{{ route('returns.index') }}" class="underline">Returns</a> page within 24 hours of delivery, with a photo and details. See our <a href="{{ route('pages.show', 'returns-policy') }}" class="underline">returns policy</a>.</p></div>
</div>
@endsection
