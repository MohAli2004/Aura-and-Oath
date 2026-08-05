@extends('layouts.auth')
@section('title', 'Forgot password')
@section('content')
    <h1 class="font-display text-3xl mb-6">Reset password</h1>
    <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
        @csrf
        <x-input label="Email" name="email" type="email" value="{{ old('email') }}" required />
        <x-button class="w-full">Send reset link</x-button>
    </form>
@endsection
