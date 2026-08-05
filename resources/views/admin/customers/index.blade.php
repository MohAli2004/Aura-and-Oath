@extends('layouts.admin')
@section('heading', 'Customers')
@section('content')
<form method="GET" class="mb-6"><input class="input max-w-sm" name="q" value="{{ request('q') }}" placeholder="Search customers"></form>
<div class="border border-beige bg-[#FFFCFA]">
@foreach($customers as $customer)
    <a href="{{ route('admin.customers.show', $customer) }}" class="flex justify-between border-b border-beige p-3 text-sm">
        <span>{{ $customer->name }}</span><span>{{ $customer->email }}</span><span>{{ $customer->phone }}</span>
    </a>
@endforeach
</div>
<div class="mt-6">{{ $customers->links() }}</div>
@endsection
