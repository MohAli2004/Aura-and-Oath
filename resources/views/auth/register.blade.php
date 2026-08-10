@extends('layouts.auth')
@section('title', 'Register')
@section('content')
    <h1 class="font-display text-3xl sm:text-4xl mb-5 sm:mb-6">Create account</h1>

    @if(filled(config('services.google.client_id')) && filled(config('services.google.client_secret')))
        <x-google-auth-button />

        <div class="auth-divider my-5 sm:my-6 flex items-center gap-3 text-xs uppercase tracking-wide text-taupe">
            <span class="h-px flex-1 bg-beige"></span>
            <span class="shrink-0">or email</span>
            <span class="h-px flex-1 bg-beige"></span>
        </div>
    @endif

    <form method="POST" action="{{ route('register') }}" class="auth-form space-y-4">
        @csrf
        <x-input label="Name" name="name" value="{{ old('name') }}" autocomplete="name" required />
        <x-input label="Email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required />
        <x-input label="Phone" name="phone" value="{{ old('phone') }}" autocomplete="tel" />
        <x-input label="Password" name="password" type="password" autocomplete="new-password" required />
        <x-input label="Confirm password" name="password_confirmation" type="password" autocomplete="new-password" required />
        <x-button class="w-full">Register</x-button>
    </form>
    <p class="mt-6 text-sm text-taupe text-center">
        <a href="{{ route('login') }}" class="underline-offset-2 hover:underline">Already have an account?</a>
    </p>
@endsection
