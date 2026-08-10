@extends('layouts.auth')
@section('title', 'Sign in')
@section('content')
    <h1 class="font-display text-3xl sm:text-4xl mb-5 sm:mb-6">Welcome back</h1>

    @if(filled(config('services.google.client_id')) && filled(config('services.google.client_secret')))
        <x-google-auth-button />

        <div class="auth-divider my-5 sm:my-6 flex items-center gap-3 text-xs uppercase tracking-wide text-taupe">
            <span class="h-px flex-1 bg-beige"></span>
            <span class="shrink-0">or email</span>
            <span class="h-px flex-1 bg-beige"></span>
        </div>
    @endif

    <form method="POST" action="{{ route('login') }}" class="auth-form space-y-4">
        @csrf
        <x-input label="Email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required />
        <x-input label="Password" name="password" type="password" autocomplete="current-password" required />
        <label class="flex items-center gap-2 text-sm text-charcoal">
            <input type="checkbox" name="remember" class="size-4 shrink-0">
            <span>Remember me</span>
        </label>
        <x-button class="w-full">Sign in</x-button>
    </form>
    <div class="mt-6 text-sm text-taupe flex flex-wrap items-center justify-between gap-3">
        <a href="{{ route('password.request') }}" class="underline-offset-2 hover:underline">Forgot password?</a>
        <a href="{{ route('register') }}" class="underline-offset-2 hover:underline">Create account</a>
    </div>
@endsection
