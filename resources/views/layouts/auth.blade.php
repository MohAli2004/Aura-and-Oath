<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Auth') — {{ setting('store_name', config('aura.name')) }}</title>
    @if(store_favicon_url())
        <link rel="icon" href="{{ store_favicon_url() }}">
    @endif
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-ivory text-charcoal flex items-center justify-center px-4"
      style="background: linear-gradient(145deg, #F7F3EE 0%, #E8DFD4 55%, #F3E8E4 100%);">
    <div class="w-full max-w-md">
        <div class="flex justify-center mb-8">
            <x-brand-logo size="xl" class="justify-center" />
        </div>
        <div class="bg-[#FFFCFA] border border-beige p-8 shadow-sm">
            @if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif
            @if($errors->any())
                <div class="alert alert-error"><ul class="list-disc ms-4">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
            @endif
            @yield('content')
        </div>
    </div>
</body>
</html>
