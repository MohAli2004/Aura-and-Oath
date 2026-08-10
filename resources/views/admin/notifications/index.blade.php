@extends('layouts.admin')
@section('heading', 'Notifications')
@section('title', 'Notifications')
@section('content')
<div class="mb-6 flex flex-wrap items-center justify-between gap-3">
    <p class="text-sm text-taupe">Customer requests and store alerts that need your attention.</p>
    @if(auth()->user()->unreadNotifications()->exists())
        <form method="POST" action="{{ route('admin.notifications.read-all') }}">
            @csrf
            <button type="submit" class="btn btn-secondary">Mark all as read</button>
        </form>
    @endif
</div>

<div class="border border-beige bg-[#FFFCFA]">
    @forelse($notifications as $row)
        @php
            $item = $row['presented'];
            $raw = $row['raw'];
            $data = $raw->data;
            $isContact = $raw->type === \App\Notifications\ContactMessageNotification::class;
        @endphp
        <div class="border-b border-beige last:border-b-0 {{ $item['unread'] ? 'bg-ivory/70' : '' }}">
            <div class="flex flex-col gap-2 p-4 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2">
                        <h2 class="font-display text-xl">{{ $item['title'] }}</h2>
                        @if($item['unread'])
                            <span class="inline-block h-2 w-2 rounded-full bg-gold" title="Unread"></span>
                        @endif
                    </div>
                    <p class="mt-1 text-sm text-charcoal/90">{{ $item['message'] }}</p>
                    @if($isContact && ! empty($data['contact_message']))
                        <div class="mt-3 border border-beige bg-white/50 p-3 text-sm">
                            <p class="mb-1 text-xs uppercase tracking-[0.12em] text-taupe">Full message</p>
                            <p class="whitespace-pre-wrap">{{ $data['contact_message'] }}</p>
                            @if(! empty($data['sender_email']))
                                <p class="mt-2 text-xs text-taupe">
                                    Reply:
                                    <a class="underline" href="mailto:{{ $data['sender_email'] }}">{{ $data['sender_email'] }}</a>
                                </p>
                            @endif
                        </div>
                    @endif
                    <p class="mt-2 text-xs text-taupe">{{ $item['created_at'] }}</p>
                </div>
                <div class="flex shrink-0 flex-wrap gap-2">
                    @if($item['unread'])
                        <form method="POST" action="{{ route('admin.notifications.read', $item['id']) }}">
                            @csrf
                            <button type="submit" class="btn btn-secondary btn-sm">Mark read</button>
                        </form>
                    @endif
                    @if($isContact && ! empty($data['sender_email']))
                        <a href="mailto:{{ $data['sender_email'] }}" class="btn btn-primary btn-sm">Reply by email</a>
                    @elseif($item['url'])
                        <form method="POST" action="{{ route('admin.notifications.read', $item['id']) }}">
                            @csrf
                            <button type="submit" class="btn btn-primary btn-sm">Open</button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    @empty
        <div class="px-4 py-12 text-center text-sm text-taupe">No notifications</div>
    @endforelse
</div>

<div class="mt-6">{{ $notifications->links() }}</div>
@endsection
