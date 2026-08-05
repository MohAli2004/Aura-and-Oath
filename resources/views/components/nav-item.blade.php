@props([
    'href',
    'icon',
    'label' => null,
])

<a
    href="{{ $href }}"
    {{ $attributes->class(['flex items-center gap-2.5 hover:bg-beige/40']) }}
>
    <x-nav-icon :name="$icon" />
    <span>{{ $label ?? $slot }}</span>
</a>
