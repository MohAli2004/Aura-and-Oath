@props([
    'class' => 'text-sm text-taupe leading-relaxed space-y-2',
])
@php
    $payments = \App\Support\TrustMessaging::paymentMethodLabels();
    $returnHours = \App\Support\TrustMessaging::returnWindowLabel();
@endphp
<div {{ $attributes->merge(['class' => $class]) }}>
    <p>Delivery across {{ \App\Support\TrustMessaging::countryName() }}. {{ \App\Support\TrustMessaging::deliveryNote() }}</p>
    @if($payments !== [])
        <p>Pay with {{ implode(' or ', $payments) }} at checkout.</p>
    @endif
    <p>
        Returns within {{ $returnHours }} for eligible problems —
        <a href="{{ route('returns.index') }}" class="underline decoration-beige underline-offset-2 hover:text-charcoal">how returns work</a>.
    </p>
</div>
