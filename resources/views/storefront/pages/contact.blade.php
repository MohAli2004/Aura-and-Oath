@extends('layouts.storefront')
@section('title', 'Contact')
@section('content')
<div class="max-w-xl mx-auto px-4 sm:px-6 py-16">
    <h1 class="font-display text-5xl mb-4">Contact</h1>
    <p class="text-taupe mb-8">{{ config('aura.contact.email') }} · {{ config('aura.contact.phone') }}</p>
    <form method="POST" action="{{ route('pages.contact.submit') }}" class="space-y-4">
        @csrf
        <x-input label="Name" name="name" required />
        <x-input label="Email" name="email" type="email" required />
        <div><label class="label">Message</label><textarea name="message" class="input" rows="5" required></textarea></div>
        <button class="btn btn-primary" type="submit">Send</button>
    </form>
</div>
@endsection
