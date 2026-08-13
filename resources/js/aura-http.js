/**
 * Lightweight fetch helper for in-place UI actions (no full page reload).
 */
export async function auraFetch(url, { method = 'POST', body = null, headers = {} } = {}) {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const init = {
        method,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...headers,
        },
    };

    if (method !== 'GET' && method !== 'HEAD') {
        init.headers['X-CSRF-TOKEN'] = csrf;
    }

    if (body instanceof FormData) {
        init.body = body;
    } else if (body && typeof body === 'object') {
        init.headers['Content-Type'] = 'application/json';
        init.body = JSON.stringify(body);
    } else if (body != null) {
        init.body = body;
    } else if (method !== 'GET' && method !== 'HEAD') {
        init.headers['Content-Type'] = 'application/json';
        init.body = '{}';
    }

    const response = await fetch(url, init);
    let payload = null;
    try {
        payload = await response.json();
    } catch (e) {
        payload = null;
    }

    if (! response.ok) {
        const message = payload?.message
            || (payload?.errors && Object.values(payload.errors).flat()[0])
            || 'Something went wrong. Please try again.';
        throw new Error(message);
    }

    return payload || { ok: true };
}

export function notifyNotificationsChanged(detail = {}) {
    window.dispatchEvent(new CustomEvent('aura:notifications-changed', { detail }));
}

export function flashToast(message, type = 'success') {
    window.dispatchEvent(new CustomEvent('aura:toast', {
        detail: { message, type },
    }));
}
