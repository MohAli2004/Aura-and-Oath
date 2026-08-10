@props([
    'accept' => 'image/png,image/jpeg,image/webp,image/gif,image/svg+xml',
    'alt' => 'Image preview',
    'alpine' => null,
    'changeText' => 'Change image',
    'empty' => 'Click to upload',
    'fit' => 'contain',
    'frame' => 'square-md',
    'id' => null,
    'name' => null,
    'src' => null,
])
@php
    // Frame keys only — size classes MUST appear as full literal strings in @class below
    // so Tailwind 4 content scanning can emit them (PHP array string values are purged).
    $knownFrames = ['square-sm', 'square-md', 'square-lg', 'favicon', 'logo-wide', 'wide', 'logo', 'icon'];
    $fitClass = $fit === 'cover' ? 'object-cover' : 'object-contain';
    $emptyTextClass = in_array($frame, ['square-sm', 'icon', 'favicon'], true)
        ? 'text-xs'
        : 'text-sm';
    $inputId = $id ?? ($name ? preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) $name) : null);
    $previewExpr = $alpine ?? 'preview';
    $selfManaged = $alpine === null;
    $wideFrame = in_array($frame, ['wide', 'logo-wide'], true);
    $customFrame = ! in_array($frame, $knownFrames, true) ? $frame : null;
@endphp

@if($selfManaged)
<div
    x-data="{
        preview: @js($src),
        onFileSelect(event) {
            const file = event.target.files?.[0];
            if (!file) return;
            if (this.preview && String(this.preview).startsWith('blob:')) {
                URL.revokeObjectURL(this.preview);
            }
            this.preview = URL.createObjectURL(file);
        }
    }"
    @class([
        'max-w-full',
        $wideFrame ? 'block w-full' : 'inline-block',
    ])
>
@endif
    {{--
      Size classes are full literal strings in @class so Tailwind emits them.
      Squares use both width AND height (not aspect-ratio alone) so absolute children cannot collapse the frame to 0 height.
    --}}
    <label
        @class([
            // Brand logos (~12rem)
            'relative flex h-48 w-48 max-w-full cursor-pointer overflow-hidden border border-dashed border-beige bg-ivory/60 transition' => in_array($frame, ['square-lg', 'logo'], true),
            // Product images (~10rem)
            'relative flex h-40 w-40 max-w-full cursor-pointer overflow-hidden border border-dashed border-beige bg-ivory/60 transition' => $frame === 'square-md',
            // Category / variant thumbs (~7rem)
            'relative flex h-28 w-28 max-w-full cursor-pointer overflow-hidden border border-dashed border-beige bg-ivory/60 transition' => in_array($frame, ['square-sm', 'icon'], true),
            // Favicon (~3rem)
            'relative flex h-12 w-12 max-w-full cursor-pointer overflow-hidden border border-dashed border-beige bg-ivory/60 transition' => $frame === 'favicon',
            // Settings site logo (horizontal)
            'relative flex h-20 w-56 max-w-full cursor-pointer overflow-hidden border border-dashed border-beige bg-ivory/60 transition' => $frame === 'logo-wide',
            // Banners / hero backgrounds (16:9)
            'relative flex aspect-video w-full max-w-xl cursor-pointer overflow-hidden border border-dashed border-beige bg-ivory/60 transition' => $frame === 'wide',
            // Escape hatch for custom frame class strings
            $customFrame => filled($customFrame),
            'group',
            'hover:border-[color-mix(in_srgb,var(--color-taupe)_70%,transparent)]',
            'has-[:focus-visible]:border-[var(--color-gold)]',
            'has-[:focus-visible]:shadow-[0_0_0_3px_color-mix(in_srgb,var(--color-gold)_25%,transparent)]',
        ])
        :class="({{ $previewExpr }}) ? 'border-solid' : 'border-dashed'"
        @if($inputId) for="{{ $inputId }}" @endif
    >
        {{-- Invisible file input covers the full frame as the click target (no native "Choose file" UI). --}}
        <input
            type="file"
            @if($inputId) id="{{ $inputId }}" @endif
            @if($name !== null) name="{{ $name }}" @endif
            accept="{{ $accept }}"
            {{ $attributes->class([
                'absolute inset-0 z-10 h-full w-full cursor-pointer opacity-0',
            ]) }}
            @if($selfManaged)
                x-on:change="onFileSelect($event)"
            @endif
        >

        <img
            x-show="{{ $previewExpr }}"
            x-cloak
            :src="{{ $previewExpr }}"
            alt="{{ $alt }}"
            class="pointer-events-none absolute inset-0 h-full w-full {{ $fitClass }}"
        >

        <div
            x-show="!({{ $previewExpr }})"
            class="pointer-events-none absolute inset-0 flex items-center justify-center px-2 text-center {{ $emptyTextClass }} leading-snug text-taupe"
        >{{ $empty }}</div>

        <div
            x-show="{{ $previewExpr }}"
            x-cloak
            class="pointer-events-none absolute inset-0 z-[1] flex items-center justify-center bg-[color-mix(in_srgb,var(--color-charcoal)_42%,transparent)] opacity-0 transition group-hover:opacity-100 group-focus-within:opacity-100"
        >
            <span class="px-2 text-center text-xs text-[var(--color-white)]">{{ $changeText }}</span>
        </div>
    </label>
@if($selfManaged)
</div>
@endif
