@extends('layouts.storefront')
@section('title', 'Notifications')
@section('content')
<div class="mx-auto max-w-3xl px-4 py-8 sm:px-6 sm:py-10">
    <div class="mb-6 flex flex-col gap-3 sm:mb-8 sm:flex-row sm:flex-wrap sm:items-end sm:justify-between">
        <div class="min-w-0">
            <h1 class="font-display text-4xl sm:text-5xl">Notifications</h1>
            <p class="mt-2 text-sm text-taupe">Updates about your orders and account.</p>
        </div>
        @if(auth()->user()->unreadNotifications()->exists())
            <form method="POST" action="{{ route('account.notifications.read-all') }}" class="shrink-0">
                @csrf
                <button type="submit" class="btn btn-secondary w-full sm:w-auto">Mark all as read</button>
            </form>
        @endif
    </div>

    <div class="overflow-hidden border border-beige bg-[#FFFCFA]">
        @forelse($notifications as $row)
            @php $item = $row['presented']; @endphp
            <form method="POST" action="{{ route('account.notifications.read', $item['id']) }}" class="border-b border-beige last:border-b-0">
                @csrf
                <button
                    type="submit"
                    class="block w-full px-3 py-3.5 text-start transition-colors hover:bg-beige/30 sm:px-4 sm:py-4 {{ $item['unread'] ? 'bg-ivory/70 border-s-2 border-s-[#B85C5C]' : '' }}"
                >
                    <div class="flex items-start gap-2">
                        <h2 class="min-w-0 break-words font-display text-lg sm:text-xl">{{ $item['title'] }}</h2>
                        @if($item['unread'])
                            <span class="mt-2 inline-block h-2 w-2 shrink-0 rounded-full bg-[#B85C5C]" title="Unread"></span>
                        @endif
                    </div>
                    <p class="mt-1 break-words text-sm text-taupe">{{ $item['message'] }}</p>
                    <p class="mt-2 text-xs text-taupe/80">{{ $item['created_at'] }}</p>
                </button>
            </form>
        @empty
            <div class="px-4 py-12 text-center text-sm text-taupe">No notifications</div>
        @endforelse
    </div>

    <div class="mt-6 overflow-x-auto">{{ $notifications->links() }}</div>

    <div class="mt-8">
        <a href="{{ route('account.index') }}" class="text-sm text-taupe underline hover:text-charcoal">Back to account</a>
    </div>
</div>
@endsection
