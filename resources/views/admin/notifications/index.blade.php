@extends('layouts.admin')
@section('heading', 'Notifications')
@section('title', 'Notifications')
@section('content')
<div
    class="space-y-6"
    x-data="{
        unread: @js(auth()->user()->unreadNotifications()->count()),
        markingAll: false,
        async markRead(id, button) {
            const row = button.closest('[data-notification-row]');
            try {
                const data = await window.auraHttp(@js(route('admin.notifications.read', ['id' => '__ID__'])).replace('__ID__', id), {
                    method: 'POST',
                    body: {},
                });
                this.unread = data.unread_count ?? Math.max(0, this.unread - 1);
                row?.classList.remove('bg-ivory/70');
                row?.querySelectorAll('[data-unread-only]').forEach((el) => el.remove());
                window.dispatchEvent(new CustomEvent('aura:notifications-changed', {
                    detail: { unread_count: this.unread },
                }));
                window.dispatchEvent(new CustomEvent('aura:toast', {
                    detail: { message: 'Marked as read.', type: 'success' },
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
                await window.auraHttp(@js(route('admin.notifications.read-all')), { method: 'POST', body: {} });
                this.unread = 0;
                document.querySelectorAll('[data-notification-row]').forEach((row) => {
                    row.classList.remove('bg-ivory/70');
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
        async openItem(id, url) {
            try {
                await window.auraHttp(@js(route('admin.notifications.read', ['id' => '__ID__'])).replace('__ID__', id), {
                    method: 'POST',
                    body: {},
                });
            } catch (e) {}
            if (url) window.location.href = url;
        },
    }"
>
    <div class="mb-2 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
        <p class="text-sm text-taupe">Customer requests and store alerts that need your attention.</p>
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
            @php
                $item = $row['presented'];
                $raw = $row['raw'];
                $data = $raw->data;
                $isContact = $raw->type === \App\Notifications\ContactMessageNotification::class;
            @endphp
            <div
                data-notification-row
                class="border-b border-beige last:border-b-0 {{ $item['unread'] ? 'bg-ivory/70' : '' }}"
            >
                <div class="flex flex-col gap-3 p-3 sm:gap-2 sm:p-4 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0 flex-1">
                        <div class="flex items-start gap-2">
                            <h2 class="min-w-0 break-words font-display text-lg sm:text-xl">{{ $item['title'] }}</h2>
                            @if($item['unread'])
                                <span data-unread-only class="mt-2 inline-block h-2 w-2 shrink-0 rounded-full bg-[#B85C5C]" title="Unread"></span>
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
                            <button
                                type="button"
                                data-unread-only
                                class="btn btn-secondary min-h-11 w-full sm:min-h-8 sm:w-auto sm:px-3 sm:py-1.5"
                                @click="markRead(@js($item['id']), $event.currentTarget)"
                            >Mark read</button>
                        @endif
                        @if($isContact && ! empty($data['sender_email']))
                            <a href="mailto:{{ $data['sender_email'] }}" class="btn btn-primary min-h-11 w-full text-center sm:min-h-8 sm:w-auto sm:px-3 sm:py-1.5">Reply by email</a>
                        @elseif($item['url'])
                            <button
                                type="button"
                                class="btn btn-primary min-h-11 w-full sm:min-h-8 sm:w-auto sm:px-3 sm:py-1.5"
                                @click="openItem(@js($item['id']), @js($item['url']))"
                            >Open</button>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="px-4 py-12 text-center text-sm text-taupe">No notifications</div>
        @endforelse
    </div>

    <x-admin.pagination :paginator="$notifications" noun="notification" />
</div>
@endsection
