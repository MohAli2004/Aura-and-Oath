<div
    class="pointer-events-none fixed inset-x-0 bottom-20 z-[99990] flex justify-center px-4 sm:bottom-6 sm:justify-end sm:px-6"
    x-data="pushNotifications({
        statusUrl: @js(route('push.status')),
        storeUrl: @js(route('push.store')),
        destroyUrl: @js(route('push.destroy')),
    })"
    x-init="init()"
>
    <div
        x-show="showPrompt"
        x-cloak
        x-transition.opacity
        class="pointer-events-auto w-full max-w-sm border border-beige bg-[#FFFCFA] p-4"
        role="dialog"
        aria-label="Enable notifications"
    >
        <p class="font-display text-lg text-charcoal">Stay updated</p>
        <p class="mt-1 text-sm text-taupe">
            Allow notifications so we can alert you about orders and account updates even when you leave this site.
        </p>
        <div class="mt-3 flex flex-wrap gap-2">
            <button
                type="button"
                class="btn btn-primary"
                :disabled="busy"
                @click="enable()"
            >
                <span x-show="!busy">Allow notifications</span>
                <span x-show="busy" x-cloak>Enabling…</span>
            </button>
            <button type="button" class="btn btn-secondary" :disabled="busy" @click="dismiss()">Not now</button>
        </div>
    </div>
</div>
