@extends('layouts.storefront')
@section('title', 'Notifications')
@section('content')
<div
    class="mx-auto max-w-3xl px-4 py-8 sm:px-6 sm:py-10"
    x-data="{
        unread: @js(auth()->user()->unreadNotifications()->count()),
        markingAll: false,
        async markRead(id, row) {
            try {
                const data = await window.auraHttp(@js(route('account.notifications.read', ['id' => '__ID__'])).replace('__ID__', id), {
                    method: 'POST',
                    body: {},
                });
                this.unread = data.unread_count ?? Math.max(0, this.unread - 1);
                row?.classList.remove('bg-ivory/70', 'border-s-2', 'border-s-[#B85C5C]');
                row?.querySelectorAll('[data-unread-only]').forEach((el) => el.remove());
                window.dispatchEvent(new CustomEvent('aura:notifications-changed', {
                    detail: { unread_count: this.unread },
                }));
            } catch (error) {
                window.dispatchEvent(new CustomEvent('aura:toast', {
                    detail: { message: error.message || 'Could not mark as read.', type: 'error' },
                }));
            }
        },
        async markAll() {
            if (this.unread < 1 || this.markingAll) return;
            this.markingAll = true;
            try {
                await window.auraHttp(@js(route('account.notifications.read-all')), { method: 'POST', body: {} });
                this.unread = 0;
                document.querySelectorAll('[data-notification-row]').forEach((row) => {
                    row.classList.remove('bg-ivory/70', 'border-s-2', 'border-s-[#B85C5C]');
                    row.querySelectorAll('[data-unread-only]').forEach((el) => el.remove());
                });
                window.dispatchEvent(new CustomEvent('aura:notifications-changed', {
                    detail: { unread_count: 0 },
                }));
                window.dispatchEvent(new CustomEvent('aura:toast', {
                    detail: { message: 'All notifications marked as read.', type: 'success' },
                }));
            } catch (error) {
                window.dispatchEvent(new CustomEvent('aura:toast', {
                    detail: { message: error.message || 'Could not mark all as read.', type: 'error' },
                }));
            } finally {
                this.markingAll = false;
            }
        },
        async openItem(id, url, row) {
            await this.markRead(id, row);
            if (url) window.location.href = url;
        },
    }"
>
    <div class="mb-6 flex flex-col gap-3 sm:mb-8 sm:flex-row sm:flex-wrap sm:items-end sm:justify-between">
        <div class="min-w-0">
            <h1 class="font-display text-4xl sm:text-5xl">Notifications</h1>
            <p class="mt-2 text-sm text-taupe">Updates about your orders and account.</p>
        </div>
        <button
            type="button"
            class="btn btn-secondary w-full shrink-0 sm:w-auto"
            x-show="unread > 0"
            x-cloak
            :disabled="markingAll"
            @click="markAll()"
        >
            <span x-text="markingAll ? 'Marking…' : 'Mark all as read'"></span>
        </button>
    </div>

    <div class="overflow-hidden border border-beige bg-[#FFFCFA]">
        @forelse($notifications as $row)
            @php $item = $row['presented']; @endphp
            <div
                data-notification-row
                class="border-b border-beige last:border-b-0 {{ $item['unread'] ? 'bg-ivory/70 border-s-2 border-s-[#B85C5C]' : '' }}"
            >
                <div class="px-3 py-3.5 sm:px-4 sm:py-4">
                    <div class="flex items-start gap-2">
                        <h2 class="min-w-0 break-words font-display text-lg sm:text-xl">{{ $item['title'] }}</h2>
                        @if($item['unread'])
                            <span data-unread-only class="mt-2 inline-block h-2 w-2 shrink-0 rounded-full bg-[#B85C5C]" title="Unread"></span>
                        @endif
                    </div>
                    <p class="mt-1 break-words text-sm text-taupe">{{ $item['message'] }}</p>
                    <p class="mt-2 text-xs text-taupe/80">{{ $item['created_at'] }}</p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        @if($item['unread'])
                            <button
                                type="button"
                                data-unread-only
                                class="btn btn-secondary btn-sm"
                                @click="markRead(@js($item['id']), $event.currentTarget.closest('[data-notification-row]'))"
                            >Mark as read</button>
                        @endif
                        @if($item['url'])
                            <button
                                type="button"
                                class="btn btn-primary btn-sm"
                                @click="openItem(@js($item['id']), @js($item['url']), $event.currentTarget.closest('[data-notification-row]'))"
                            >Open</button>
                        @endif
                    </div>
                </div>
            </div>
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
