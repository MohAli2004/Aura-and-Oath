    <div
        class="pointer-events-none fixed inset-x-0 z-[99990] flex justify-center px-3 sm:bottom-6 sm:justify-end sm:px-6"
        style="bottom: max(1rem, env(safe-area-inset-bottom, 0px));"
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
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-y-3"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 translate-y-3"
        class="pointer-events-auto w-full max-w-md border border-beige bg-[#FFFCFA] p-4 sm:max-w-sm"
        role="dialog"
        aria-label="Enable notifications"
    >
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <p class="font-display text-xl text-charcoal sm:text-lg">Stay updated</p>
                <template x-if="!isIosHint">
                    <p class="mt-1 text-sm leading-relaxed text-taupe">
                        Allow notifications so we can alert you about orders and account updates even when you leave this site.
                    </p>
                </template>
                <template x-if="isIosHint">
                    <div class="mt-1 space-y-2 text-sm leading-relaxed text-taupe">
                        <p>On iPhone/iPad, background alerts work after you install the site:</p>
                        <ol class="list-decimal space-y-1 ps-5">
                            <li>Tap <span class="text-charcoal">Share</span></li>
                            <li>Choose <span class="text-charcoal">Add to Home Screen</span></li>
                            <li>Open the app icon, then allow notifications</li>
                        </ol>
                    </div>
                </template>
            </div>
            <button
                type="button"
                class="inline-flex h-10 w-10 shrink-0 items-center justify-center text-taupe hover:text-charcoal"
                @click="dismiss()"
                aria-label="Dismiss notification prompt"
            >
                <x-icon name="close" class="h-4 w-4" />
            </button>
        </div>

        <div class="mt-4 grid grid-cols-1 gap-2 sm:flex sm:flex-wrap">
            <button
                type="button"
                class="btn btn-primary min-h-11 w-full sm:w-auto"
                :disabled="busy"
                @click="enable()"
                x-show="!isIosHint"
            >
                <span x-show="!busy">Allow notifications</span>
                <span x-show="busy" x-cloak>Enabling…</span>
            </button>
            <button
                type="button"
                class="btn btn-secondary min-h-11 w-full sm:w-auto"
                :disabled="busy"
                @click="dismiss()"
            >
                <span x-text="isIosHint ? 'Got it' : 'Not now'"></span>
            </button>
        </div>
    </div>
</div>
