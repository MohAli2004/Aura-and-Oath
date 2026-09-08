@extends('layouts.auth')
@section('title', __('storefront.page_title_forgot_password'))
@section('content')
    <h1 class="font-display text-3xl mb-6">{{ __('storefront.reset_password') }}</h1>
    <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
        @csrf
        <x-input :label="__('storefront.email')" name="email" type="email" value="{{ old('email') }}" required />
        <x-button class="w-full">{{ __('storefront.send_reset_link') }}</x-button>
    </form>
@endsection
