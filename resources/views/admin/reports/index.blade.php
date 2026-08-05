@extends('layouts.admin')
@section('heading', 'Reports')
@section('content')
<div class="flex justify-between mb-6">
    <p class="text-taupe text-sm">Live metrics from the database.</p>
    <a href="{{ route('admin.reports.orders.csv') }}" class="btn btn-secondary">Export orders CSV</a>
</div>
<div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
    @foreach([['Pending',$stats['pending_approval']],['Customers',$stats['customers']],['Month revenue', money($stats['revenue_month'])],['Low stock',$stats['low_stock']]] as [$l,$v])
        <div class="border border-beige p-4 bg-[#FFFCFA]"><div class="text-xs uppercase tracking-widest text-taupe">{{ $l }}</div><div class="font-display text-3xl mt-1">{{ $v }}</div></div>
    @endforeach
</div>
<div class="grid lg:grid-cols-2 gap-8">
    <div>
        <h2 class="font-display text-2xl mb-3">Sales (30 days)</h2>
        @forelse($sales as $row)
            <div class="flex justify-between border-b border-beige py-2 text-sm"><span>{{ $row->day }}</span><span>{{ $row->orders }} orders</span><span>{{ money($row->revenue) }}</span></div>
        @empty
            <p class="text-taupe">No sales yet.</p>
        @endforelse
    </div>
    <div>
        <h2 class="font-display text-2xl mb-3">Top products</h2>
        @foreach($topProducts as $row)
            <div class="flex justify-between border-b border-beige py-2 text-sm"><span>{{ $row->product_name }}</span><span>{{ $row->qty }} sold</span><span>{{ money($row->revenue) }}</span></div>
        @endforeach
    </div>
</div>
@endsection
