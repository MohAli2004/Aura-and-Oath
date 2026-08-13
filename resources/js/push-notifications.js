import { auraFetch, flashToast } from './aura-http';

function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const rawData = window.atob(base64);
    const outputArray = new Uint8Array(rawData.length);
    for (let i = 0; i < rawData.length; i += 1) {
        outputArray[i] = rawData.charCodeAt(i);
    }
    return outputArray;
}

function isIosDevice() {
    const ua = navigator.userAgent || '';
    if (/iphone|ipad|ipod/i.test(ua)) {
        return true;
    }
    // iPadOS 13+ can report as Mac
    return navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1;
}

function isStandaloneDisplay() {
    return window.matchMedia('(display-mode: standalone)').matches
        || window.navigator.standalone === true;
}

export default function pushNotifications(config = {}) {
    return {
        supported: false,
        configured: false,
        permission: typeof Notification !== 'undefined' ? Notification.permission : 'denied',
        subscribed: false,
        busy: false,
        dismissed: false,
        publicKey: null,
        ios: false,
        standalone: false,
        statusUrl: config.statusUrl,
        storeUrl: config.storeUrl,
        destroyUrl: config.destroyUrl,

        get canSubscribe() {
            return this.supported && this.configured && this.permission !== 'denied';
        },

        get showPrompt() {
            if (this.dismissed || this.busy || this.subscribed || ! this.configured) {
                return false;
            }

            // Android / desktop Chrome-like browsers
            if (this.canSubscribe) {
                return true;
            }

            // iPhone/iPad Safari in a normal tab: push only works after Add to Home Screen
            if (this.ios && ! this.standalone) {
                return true;
            }

            return false;
        },

        get isIosHint() {
            return this.ios && ! this.standalone && ! this.supported;
        },

        async init() {
            this.dismissed = window.localStorage.getItem('aura.push.dismissed') === '1';
            this.ios = isIosDevice();
            this.standalone = isStandaloneDisplay();
            this.supported = 'serviceWorker' in navigator
                && 'PushManager' in window
                && 'Notification' in window;

            if (! this.statusUrl) {
                return;
            }

            // Still fetch config even on iOS so the tip can show when server is ready.
            try {
                const status = await auraFetch(this.statusUrl, { method: 'GET' });
                this.configured = !! status.enabled;
                this.publicKey = status.publicKey || null;
                this.subscribed = !! status.subscribed;
                if (typeof Notification !== 'undefined') {
                    this.permission = Notification.permission;
                }

                if (this.configured && this.supported && this.permission === 'granted' && ! this.subscribed) {
                    await this.enable({ silent: true });
                }
            } catch (e) {
                this.configured = false;
            }
        },

        dismiss() {
            this.dismissed = true;
            window.localStorage.setItem('aura.push.dismissed', '1');
        },

        async enable({ silent = false } = {}) {
            if (! this.configured || this.busy) {
                return;
            }

            if (! this.supported) {
                if (! silent && this.isIosHint) {
                    flashToast('On iPhone: Share → Add to Home Screen, then open the app and allow notifications.', 'error');
                } else if (! silent) {
                    flashToast('This browser does not support background notifications.', 'error');
                }
                return;
            }

            this.busy = true;
            try {
                const permission = await Notification.requestPermission();
                this.permission = permission;

                if (permission !== 'granted') {
                    if (! silent) {
                        flashToast('Notifications were blocked. Enable them in your phone site settings, then try again.', 'error');
                    }
                    return;
                }

                const registration = await navigator.serviceWorker.register('/sw.js', { scope: '/' });
                await navigator.serviceWorker.ready;

                let subscription = await registration.pushManager.getSubscription();
                if (! subscription) {
                    subscription = await registration.pushManager.subscribe({
                        userVisibleOnly: true,
                        applicationServerKey: urlBase64ToUint8Array(this.publicKey),
                    });
                }

                const payload = subscription.toJSON();
                await auraFetch(this.storeUrl, {
                    method: 'POST',
                    body: {
                        endpoint: payload.endpoint,
                        keys: payload.keys,
                        contentEncoding: (PushManager.supportedContentEncodings || ['aesgcm'])[0],
                    },
                });

                this.subscribed = true;
                window.localStorage.removeItem('aura.push.dismissed');

                if (! silent) {
                    flashToast('You will get alerts even when you leave the site.', 'success');
                }
            } catch (error) {
                if (! silent) {
                    flashToast(error.message || 'Could not enable notifications.', 'error');
                }
            } finally {
                this.busy = false;
            }
        },
    };
}
