@extends('layouts.storefront')
@section('title', 'Contact')
@section('content')
<div class="max-w-xl mx-auto px-4 sm:px-6 py-16">
    <h1 class="font-display text-5xl mb-4">Contact</h1>
    <p class="text-taupe mb-8">{{ config('aura.contact.email') }} · {{ config('aura.contact.phone') }}</p>
    <form method="POST" action="{{ route('pages.contact.submit') }}" class="space-y-4">
        @csrf
        <x-input label="Name" name="name" required value="{{ old('name') }}" />
        <x-input label="Email" name="email" type="email" required value="{{ old('email') }}" />
        <div>
            <label class="label" for="message">Message <span class="normal-case tracking-wide text-[10px] font-normal text-blush">Required</span></label>
            <textarea id="message" name="message" class="input" rows="5" required>{{ old('message') }}</textarea>
            @error('message')
                <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
            @enderror
        </div>
        <button class="btn btn-primary" type="submit">Send</button>
    </form>
</div>
@endsection
