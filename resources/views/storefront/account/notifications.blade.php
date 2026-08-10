@extends('layouts.storefront')
@section('title', 'Notifications')
@section('content')
<div class="max-w-3xl mx-auto px-4 sm:px-6 py-10">
    <div class="mb-8 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="font-display text-5xl">Notifications</h1>
            <p class="mt-2 text-sm text-taupe">Updates about your orders and account.</p>
        </div>
        @if(auth()->user()->unreadNotifications()->exists())
            <form method="POST" action="{{ route('account.notifications.read-all') }}">
                @csrf
                <button type="submit" class="btn btn-secondary">Mark all as read</button>
            </form>
        @endif
    </div>

    <div class="border border-beige bg-[#FFFCFA]">
        @forelse($notifications as $row)
            @php $item = $row['presented']; @endphp
            <form method="POST" action="{{ route('account.notifications.read', $item['id']) }}" class="border-b border-beige last:border-b-0">
                @csrf
                <button
                    type="submit"
                    class="block w-full px-4 py-4 text-start transition-colors hover:bg-beige/30 {{ $item['unread'] ? 'bg-ivory/70' : '' }}"
                >
                    <div class="flex items-center gap-2">
                        <h2 class="font-display text-xl">{{ $item['title'] }}</h2>
                        @if($item['unread'])
                            <span class="inline-block h-2 w-2 rounded-full bg-gold" title="Unread"></span>
                        @endif
                    </div>
                    <p class="mt-1 text-sm text-taupe">{{ $item['message'] }}</p>
                    <p class="mt-2 text-xs text-taupe/80">{{ $item['created_at'] }}</p>
                </button>
            </form>
        @empty
            <div class="px-4 py-12 text-center text-sm text-taupe">No notifications</div>
        @endforelse
    </div>

    <div class="mt-6">{{ $notifications->links() }}</div>

    <div class="mt-8">
        <a href="{{ route('account.index') }}" class="text-sm text-taupe underline hover:text-charcoal">Back to account</a>
    </div>
</div>
@endsection
