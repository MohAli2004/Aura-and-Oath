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

export default function pushNotifications(config = {}) {
    return {
        supported: false,
        configured: false,
        permission: typeof Notification !== 'undefined' ? Notification.permission : 'denied',
        subscribed: false,
        busy: false,
        dismissed: false,
        publicKey: null,
        statusUrl: config.statusUrl,
        storeUrl: config.storeUrl,
        destroyUrl: config.destroyUrl,

        get showPrompt() {
            return this.supported
                && this.configured
                && ! this.dismissed
                && ! this.busy
                && this.permission !== 'denied'
                && ! this.subscribed;
        },

        async init() {
            this.dismissed = window.localStorage.getItem('aura.push.dismissed') === '1';
            this.supported = 'serviceWorker' in navigator
                && 'PushManager' in window
                && 'Notification' in window;

            if (! this.supported || ! this.statusUrl) {
                return;
            }

            try {
                const status = await auraFetch(this.statusUrl, { method: 'GET' });
                this.configured = !! status.enabled;
                this.publicKey = status.publicKey || null;
                this.subscribed = !! status.subscribed;
                this.permission = Notification.permission;

                if (this.configured && this.permission === 'granted' && ! this.subscribed) {
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
            if (! this.supported || ! this.configured || this.busy) {
                return;
            }

            this.busy = true;
            try {
                const permission = await Notification.requestPermission();
                this.permission = permission;

                if (permission !== 'granted') {
                    if (! silent) {
                        flashToast('Notifications were blocked. You can enable them in browser settings.', 'error');
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
