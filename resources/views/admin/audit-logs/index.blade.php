@extends('layouts.admin')
@section('heading', 'Audit log')
@section('content')
<div class="border border-beige bg-[#FFFCFA]">
@foreach($logs as $log)
    <div class="border-b border-beige p-3 text-sm">
        <div class="flex justify-between"><strong>{{ $log->action }}</strong><span class="text-taupe">{{ $log->created_at }}</span></div>
        <div class="text-taupe">{{ $log->user?->name ?? 'System' }} · {{ $log->description }}</div>
    </div>
@endforeach
</div>
<x-admin.pagination :paginator="$logs" noun="log" />
@endsection
