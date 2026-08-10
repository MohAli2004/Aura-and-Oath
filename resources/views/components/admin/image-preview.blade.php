@props([
    'alt' => 'Image preview',
    'empty' => 'No image',
    'fit' => 'contain',
    'frame' => 'square-md',
    'src' => null,
    'alpine' => null,
])
@php
    // Size classes MUST appear as full literal strings in @class below (Tailwind 4 scan).
    $knownFrames = ['square-sm', 'square-md', 'square-lg', 'favicon', 'logo-wide', 'wide', 'logo', 'icon'];
    $fitClass = $fit === 'cover' ? 'object-cover' : 'object-contain';
    $emptyTextClass = in_array($frame, ['square-sm', 'icon', 'favicon'], true)
        ? 'text-xs'
        : 'text-sm';
    $customFrame = ! in_array($frame, $knownFrames, true) ? $frame : null;
@endphp
@if($alpine)
    <div
        {{ $attributes->class([
            'relative flex h-48 w-48 max-w-full overflow-hidden border border-dashed border-beige bg-ivory/60' => in_array($frame, ['square-lg', 'logo'], true),
            'relative flex h-40 w-40 max-w-full overflow-hidden border border-dashed border-beige bg-ivory/60' => $frame === 'square-md',
            'relative flex h-28 w-28 max-w-full overflow-hidden border border-dashed border-beige bg-ivory/60' => in_array($frame, ['square-sm', 'icon'], true),
            'relative flex h-12 w-12 max-w-full overflow-hidden border border-dashed border-beige bg-ivory/60' => $frame === 'favicon',
            'relative flex h-20 w-56 max-w-full overflow-hidden border border-dashed border-beige bg-ivory/60' => $frame === 'logo-wide',
            'relative flex aspect-video w-full max-w-xl overflow-hidden border border-dashed border-beige bg-ivory/60' => $frame === 'wide',
            $customFrame => filled($customFrame),
        ]) }}
        :class="({{ $alpine }}) ? 'border-solid' : 'border-dashed'"
    >
        <img
            x-show="{{ $alpine }}"
            x-cloak
            :src="{{ $alpine }}"
            alt="{{ $alt }}"
            class="absolute inset-0 h-full w-full {{ $fitClass }}"
        >
        <div
            x-show="!({{ $alpine }})"
            class="absolute inset-0 flex items-center justify-center px-2 text-center {{ $emptyTextClass }} text-taupe"
        >{{ $empty }}</div>
    </div>
@else
    <div {{ $attributes->class([
        'relative flex h-48 w-48 max-w-full overflow-hidden border border-beige bg-ivory/60' => in_array($frame, ['square-lg', 'logo'], true),
        'relative flex h-40 w-40 max-w-full overflow-hidden border border-beige bg-ivory/60' => $frame === 'square-md',
        'relative flex h-28 w-28 max-w-full overflow-hidden border border-beige bg-ivory/60' => in_array($frame, ['square-sm', 'icon'], true),
        'relative flex h-12 w-12 max-w-full overflow-hidden border border-beige bg-ivory/60' => $frame === 'favicon',
        'relative flex h-20 w-56 max-w-full overflow-hidden border border-beige bg-ivory/60' => $frame === 'logo-wide',
        'relative flex aspect-video w-full max-w-xl overflow-hidden border border-beige bg-ivory/60' => $frame === 'wide',
        $customFrame => filled($customFrame),
        $src ? 'border-solid' : 'border-dashed',
    ]) }}>
        @if($src)
            <img src="{{ $src }}" alt="{{ $alt }}" class="absolute inset-0 h-full w-full {{ $fitClass }}">
        @else
            <div class="absolute inset-0 flex items-center justify-center px-2 text-center {{ $emptyTextClass }} text-taupe">{{ $empty }}</div>
        @endif
    </div>
@endif
