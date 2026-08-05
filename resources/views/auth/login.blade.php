@extends('layouts.auth')
@section('title', 'Sign in')
@section('content')
    <h1 class="font-display text-3xl mb-6">Welcome back</h1>
    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf
        <x-input label="Email" name="email" type="email" value="{{ old('email') }}" required />
        <x-input label="Password" name="password" type="password" required />
        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="remember"> Remember me</label>
        <x-button class="w-full">Sign in</x-button>
    </form>
    <div class="mt-6 text-sm text-taupe flex justify-between">
        <a href="{{ route('password.request') }}">Forgot password?</a>
        <a href="{{ route('register') }}">Create account</a>
    </div>
@endsection
