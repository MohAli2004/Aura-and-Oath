@props(['offer', 'size' => 'md'])
@php
    $images = app(\App\Services\ImageService::class);
    $products = $offer->products->take(4);
    $sizeClasses = match ($size) {
        'lg' => 'offer-stack--lg',
        'sm' => 'offer-stack--sm',
        default => '',
    };
@endphp
<div {{ $attributes->class(['offer-stack', $sizeClasses]) }} aria-hidden="true">
    @foreach($products as $index => $product)
        <div class="offer-stack__item" style="--stack-index: {{ $index }};">
            <img
                src="{{ $images->url($product->primaryImagePath()) }}"
                alt=""
                loading="lazy"
                decoding="async"
            >
        </div>
    @endforeach
    @if($offer->products->count() > 4)
        <div class="offer-stack__more">+{{ $offer->products->count() - 4 }}</div>
    @endif
</div>
