@props(['offer', 'variant' => 'card'])
@php
    $images = app(\App\Services\ImageService::class);
    $mainUrl = $offer->imageUrl();
    $thumbs = $offer->products->map(fn ($product) => [
        'url' => $images->url($product->primaryImagePath()),
        'name' => $product->name,
    ]);
    $isDetail = $variant === 'detail';
@endphp
<div {{ $attributes->class(['w-full min-w-0', 'max-w-xs' => $isDetail]) }}>
    @if($isDetail)
        <div class="relative aspect-[4/5] overflow-hidden bg-beige/40">
            <img src="{{ $mainUrl }}" alt="{{ $offer->title }}" class="absolute inset-0 h-full w-full object-cover">
        </div>
    @else
        <div class="h-40 overflow-hidden bg-beige/40">
            <img src="{{ $mainUrl }}" alt="{{ $offer->title }}" class="h-full w-full object-cover">
        </div>
    @endif
    @if($thumbs->isNotEmpty())
        <div class="mt-2 flex flex-wrap gap-1.5">
            @foreach($thumbs as $thumb)
                @if($isDetail)
                    <div class="h-14 w-14 shrink-0 overflow-hidden border border-beige bg-beige/40">
                        <img src="{{ $thumb['url'] }}" alt="{{ $thumb['name'] }}" class="h-full w-full object-cover">
                    </div>
                @else
                    <div class="h-9 w-9 shrink-0 overflow-hidden border border-beige bg-beige/40">
                        <img src="{{ $thumb['url'] }}" alt="{{ $thumb['name'] }}" class="h-full w-full object-cover">
                    </div>
                @endif
            @endforeach
        </div>
    @endif
</div>
