@extends('layouts.admin')
@section('heading', 'Customers')
@section('content')
<form method="GET" class="mb-6 flex flex-wrap gap-2">
    <input class="input max-w-sm" name="q" value="{{ request('q') }}" placeholder="Search customers">
    <button class="btn btn-secondary" type="submit">Search</button>
    @if(request()->filled('q'))
        <a href="{{ url()->current() }}" class="btn btn-secondary">Clear</a>
    @endif
</form>
<div class="border border-beige bg-[#FFFCFA] overflow-x-auto">
    <div class="hidden sm:grid grid-cols-[1.2fr_1.4fr_1fr_auto] gap-3 border-b border-beige bg-beige/40 px-3 py-2 text-xs uppercase tracking-wide text-taupe">
        <span>Name</span><span>Email</span><span>Phone</span><span>Sign-in</span>
    </div>
@foreach($customers as $customer)
    <a href="{{ route('admin.customers.show', $customer) }}" class="grid grid-cols-1 sm:grid-cols-[1.2fr_1.4fr_1fr_auto] gap-1 sm:gap-3 border-b border-beige p-3 text-sm items-center">
        <span class="font-medium">{{ $customer->name }}</span>
        <span class="text-taupe sm:text-charcoal break-all">{{ $customer->email }}</span>
        <span class="text-taupe">{{ $customer->phone ?: '—' }}</span>
        <span>
            @if($customer->google_id)
                <span class="badge">Google</span>
            @else
                <span class="badge">Email</span>
            @endif
        </span>
    </a>
@endforeach
</div>
<x-admin.pagination :paginator="$customers" noun="customer" />
@endsection
