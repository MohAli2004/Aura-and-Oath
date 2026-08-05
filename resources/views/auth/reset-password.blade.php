@extends('layouts.auth')
@section('title', 'Reset password')
@section('content')
    <h1 class="font-display text-3xl mb-6">Choose a new password</h1>
    <form method="POST" action="{{ route('password.update') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <x-input label="Email" name="email" type="email" value="{{ old('email', $email) }}" required />
        <x-input label="Password" name="password" type="password" required />
        <x-input label="Confirm password" name="password_confirmation" type="password" required />
        <x-button class="w-full">Reset password</x-button>
    </form>
@endsection
