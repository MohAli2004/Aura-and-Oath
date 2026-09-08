@props([
    'class' => 'text-sm text-taupe leading-snug',
])
@php
    $lines = \App\Support\TrustMessaging::reassuranceLines();
@endphp
@if($lines !== [])
    <p {{ $attributes->merge(['class' => $class]) }}>
        {{ implode(' · ', $lines) }}
    </p>
@endif
