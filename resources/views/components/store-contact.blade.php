@props([
    'class' => 'text-taupe text-sm',
])
@php
    $email = store_public_email();
    $phone = store_public_phone();
    $whatsapp = store_public_whatsapp();
    $whatsappUrl = store_whatsapp_url();
    $address = store_public_address();
    $hours = store_public_hours();
    $hasAny = $email || $phone || $whatsapp || $address || $hours;
@endphp
@if($hasAny)
    <div {{ $attributes->merge(['class' => $class]) }}>
        @if($email || $phone || $whatsapp)
            <p class="break-words">
                @if($email)
                    <a class="underline decoration-beige underline-offset-2 hover:text-charcoal" href="mailto:{{ $email }}">{{ $email }}</a>
                @endif
                @if($email && ($phone || $whatsapp))
                    <span class="text-taupe/70"> · </span>
                @endif
                @if($phone)
                    <a class="underline decoration-beige underline-offset-2 hover:text-charcoal" href="tel:{{ preg_replace('/\s+/', '', $phone) }}">{{ $phone }}</a>
                @endif
                @if($phone && $whatsapp)
                    <span class="text-taupe/70"> · </span>
                @endif
                @if($whatsapp)
                    @if($whatsappUrl)
                        <a class="underline decoration-beige underline-offset-2 hover:text-charcoal" href="{{ $whatsappUrl }}" target="_blank" rel="noopener noreferrer">WhatsApp {{ $whatsapp }}</a>
                    @else
                        WhatsApp {{ $whatsapp }}
                    @endif
                @endif
            </p>
        @endif
        @if($address)
            <p class="mt-1">{{ $address }}</p>
        @endif
        @if($hours)
            <p class="mt-1">{{ $hours }}</p>
        @endif
    </div>
@endif
