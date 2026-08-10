@props([
    'size' => 'md',
    'showName' => null,
    'href' => null,
])

@php
    $logoUrl = store_logo_url();
    $storeName = setting('store_name', config('aura.name'));
    $heights = [
        'sm' => 'h-8',
        'md' => 'h-10',
        'lg' => 'h-14',
        'xl' => 'h-16',
    ];
    $height = $heights[$size] ?? $heights['md'];
    $href = $href ?? route('home');
    $showName = $showName ?? ($logoUrl ? false : true);
@endphp

<a href="{{ $href }}" {{ $attributes->merge(['class' => 'inline-flex items-center gap-3 no-underline text-charcoal']) }}>
    @if($logoUrl)
        <img
            src="{{ $logoUrl }}"
            alt="{{ $storeName }}"
            class="{{ $height }} w-auto max-w-[160px] sm:max-w-[200px] object-contain"
        >
    @endif
    @if($showName || ! $logoUrl)
        <span class="font-display tracking-wide {{ $size === 'sm' ? 'text-xl' : ($size === 'lg' || $size === 'xl' ? 'text-3xl' : 'text-2xl') }}">{{ $storeName }}</span>
    @endif
</a>
