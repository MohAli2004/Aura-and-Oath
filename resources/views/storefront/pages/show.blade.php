@extends('layouts.storefront')
@section('title', $page->title)
@section('content')
<div class="max-w-3xl mx-auto px-4 sm:px-6 py-16">
    <h1 class="font-display text-5xl mb-6">{{ $page->title }}</h1>
    <div class="text-taupe leading-relaxed whitespace-pre-line">{{ $page->content }}</div>
</div>
@endsection
