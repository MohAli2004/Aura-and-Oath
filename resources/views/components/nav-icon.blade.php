@props(['name'])

@php
    $src = '/images/nav/'.preg_replace('/[^a-z0-9\-]/', '', strtolower((string) $name)).'.svg';
@endphp

<span {{ $attributes->class(['inline-flex h-8 w-8 shrink-0 items-center justify-center']) }}>
    <img
        src="{{ $src }}"
        alt=""
        width="20"
        height="20"
        class="h-5 w-5 object-contain"
        loading="eager"
    >
</span>
