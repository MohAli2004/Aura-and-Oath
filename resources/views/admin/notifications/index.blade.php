@extends('layouts.admin')
@section('heading', 'Notifications')
@section('title', 'Notifications')
@section('content')
<div class="mb-6 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
    <p class="text-sm text-taupe">Customer requests and store alerts that need your attention.</p>
    @if(auth()->user()->unreadNotifications()->exists())
        <form method="POST" action="{{ route('admin.notifications.read-all') }}" class="shrink-0">
            @csrf
            <button type="submit" class="btn btn-secondary w-full sm:w-auto">Mark all as read</button>
        </form>
    @endif
</div>

<div class="overflow-hidden border border-beige bg-[#FFFCFA]">
    @forelse($notifications as $row)
        @php
            $item = $row['presented'];
            $raw = $row['raw'];
            $data = $raw->data;
            $isContact = $raw->type === \App\Notifications\ContactMessageNotification::class;
        @endphp
        <div class="border-b border-beige last:border-b-0 {{ $item['unread'] ? 'bg-ivory/70' : '' }}">
            <div class="flex flex-col gap-3 p-3 sm:gap-2 sm:p-4 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0 flex-1">
                    <div class="flex items-start gap-2">
                        <h2 class="min-w-0 break-words font-display text-lg sm:text-xl">{{ $item['title'] }}</h2>
                        @if($item['unread'])
                            <span class="mt-2 inline-block h-2 w-2 shrink-0 rounded-full bg-[#B85C5C]" title="Unread"></span>
                        @endif
                    </div>
                    <p class="mt-1 break-words text-sm text-charcoal/90">{{ $item['message'] }}</p>
                    @if($isContact && ! empty($data['contact_message']))
                        <div class="mt-3 border border-beige bg-white/50 p-3 text-sm">
                            <p class="mb-1 text-xs uppercase tracking-[0.12em] text-taupe">Full message</p>
                            <p class="break-words whitespace-pre-wrap">{{ $data['contact_message'] }}</p>
                            @if(! empty($data['sender_email']))
                                <p class="mt-2 break-all text-xs text-taupe">
                                    Reply:
                                    <a class="underline" href="mailto:{{ $data['sender_email'] }}">{{ $data['sender_email'] }}</a>
                                </p>
                            @endif
                        </div>
                    @endif
                    <p class="mt-2 text-xs text-taupe">{{ $item['created_at'] }}</p>
                </div>
                <div class="flex w-full shrink-0 flex-col gap-2 sm:w-auto sm:flex-row sm:flex-wrap">
                    @if($item['unread'])
                        <form method="POST" action="{{ route('admin.notifications.read', $item['id']) }}" class="w-full sm:w-auto">
                            @csrf
                            <button type="submit" class="btn btn-secondary btn-sm w-full sm:w-auto">Mark read</button>
                        </form>
                    @endif
                    @if($isContact && ! empty($data['sender_email']))
                        <a href="mailto:{{ $data['sender_email'] }}" class="btn btn-primary btn-sm w-full text-center sm:w-auto">Reply by email</a>
                    @elseif($item['url'])
                        <form method="POST" action="{{ route('admin.notifications.read', $item['id']) }}" class="w-full sm:w-auto">
                            @csrf
                            <button type="submit" class="btn btn-primary btn-sm w-full sm:w-auto">Open</button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    @empty
        <div class="px-4 py-12 text-center text-sm text-taupe">No notifications</div>
    @endforelse
</div>

<div class="mt-6 overflow-x-auto">{{ $notifications->links() }}</div>
@endsection
