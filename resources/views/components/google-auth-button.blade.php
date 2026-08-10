@props([
    'href' => null,
])

@php
    $href = $href ?: url('/auth/google');
@endphp

<a
    href="{{ $href }}"
    {{ $attributes->merge([
        'class' => 'btn btn-secondary auth-google-btn w-full inline-flex items-center justify-center gap-2 no-underline',
    ]) }}
>
    <svg class="auth-google-icon" width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
        <path fill="#EA4335" d="M12 10.2v3.6h5.1c-.2 1.2-1.5 3.6-5.1 3.6-3.1 0-5.6-2.5-5.6-5.6S8.9 6.2 12 6.2c1.8 0 3 .7 3.7 1.4l2.5-2.4C16.8 3.8 14.6 3 12 3 7 3 3 7 3 12s4 9 9 9c5.2 0 8.6-3.6 8.6-8.7 0-.6-.1-1-.2-1.5H12z"/>
        <path fill="#34A853" d="M3.9 7.7l3 2.2C7.7 7.9 9.7 6.2 12 6.2c1.8 0 3 .7 3.7 1.4l2.5-2.4C16.8 3.8 14.6 3 12 3 8.5 3 5.5 5 3.9 7.7z"/>
        <path fill="#4A90E2" d="M12 21c2.5 0 4.6-.8 6.1-2.2l-2.9-2.3c-.8.6-1.9 1-3.2 1-3.5 0-6.4-2.3-7.4-5.5l-3 2.3C3.4 18.9 7.3 21 12 21z"/>
        <path fill="#FBBC05" d="M4.6 14c-.2-.6-.3-1.3-.3-2s.1-1.4.3-2l-3-2.3C1.2 9.1 1 10.5 1 12s.2 2.9.6 4.3l3-2.3z"/>
    </svg>
    <span>Continue with Google</span>
</a>
