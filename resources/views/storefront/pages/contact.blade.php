@extends('layouts.storefront')
@section('title', __('storefront.page_title_contact'))
@section('meta_description', __('storefront.contact_meta_description'))
@section('canonical', route('pages.contact'))
@section('content')
<div class="max-w-xl mx-auto px-4 sm:px-6 py-16">
    <h1 class="font-display text-5xl mb-4">{{ __('storefront.contact') }}</h1>
    <p class="text-taupe mb-4 leading-relaxed">{{ __('storefront.contact_intro') }}</p>
    <x-store-contact class="text-taupe mb-8" />
    <form method="POST" action="{{ route('pages.contact.submit') }}" class="space-y-4">
        @csrf
        <x-input :label="__('storefront.name')" name="name" required value="{{ old('name') }}" />
        <x-input :label="__('storefront.email')" name="email" type="email" required value="{{ old('email') }}" />
        <div>
            <label class="label" for="message">{{ __('storefront.message') }} <span class="normal-case tracking-wide text-[10px] font-normal text-blush">{{ __('storefront.required') }}</span></label>
            <textarea id="message" name="message" class="input" rows="5" required>{{ old('message') }}</textarea>
            @error('message')
                <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
            @enderror
        </div>
        <button class="btn btn-primary" type="submit">{{ __('storefront.send') }}</button>
    </form>
</div>
@endsection
