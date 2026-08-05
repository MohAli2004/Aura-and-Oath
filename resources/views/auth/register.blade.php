@extends('layouts.auth')
@section('title', 'Register')
@section('content')
    <h1 class="font-display text-3xl mb-6">Create account</h1>
    <form method="POST" action="{{ route('register') }}" class="space-y-4">
        @csrf
        <x-input label="Name" name="name" value="{{ old('name') }}" required />
        <x-input label="Email" name="email" type="email" value="{{ old('email') }}" required />
        <x-input label="Phone" name="phone" value="{{ old('phone') }}" />
        <x-input label="Password" name="password" type="password" required />
        <x-input label="Confirm password" name="password_confirmation" type="password" required />
        <x-button class="w-full">Register</x-button>
    </form>
    <p class="mt-6 text-sm text-taupe text-center"><a href="{{ route('login') }}">Already have an account?</a></p>
@endsection
