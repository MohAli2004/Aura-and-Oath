@extends('layouts.auth')
@section('title', __('storefront.page_title_reset_password'))
@section('content')
    <h1 class="font-display text-3xl mb-6">{{ __('storefront.choose_new_password') }}</h1>
    <form method="POST" action="{{ route('password.update') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <x-input :label="__('storefront.email')" name="email" type="email" value="{{ old('email', $email) }}" required />
        <x-input :label="__('storefront.password')" name="password" type="password" required />
        <x-input :label="__('storefront.confirm_password')" name="password_confirmation" type="password" required />
        <x-button class="w-full">{{ __('storefront.reset_password') }}</x-button>
    </form>
@endsection
