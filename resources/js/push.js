let subscription = null;
let supported = false;
let ready = false;
let busy = false;
let registration = null;
let publicKey = null;
const publicKeyStorageKey = 'statuspage.pushPublicKey';

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

const urlBase64ToUint8Array = (base64String) => {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = `${base64String}${padding}`.replace(/-/g, '+').replace(/_/g, '/');
    const rawData = window.atob(base64);

    return Uint8Array.from([...rawData].map((character) => character.charCodeAt(0)));
};

const jsonRequest = async (url, options = {}) => fetch(url, {
    ...options,
    credentials: 'same-origin',
    headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken(),
        'X-Requested-With': 'XMLHttpRequest',
        ...(options.headers ?? {}),
    },
});

const syncToggle = () => {
    document.querySelectorAll('[data-push-toggle]').forEach((button) => {
        const permission = supported ? Notification.permission : 'default';

        button.hidden = ! supported;
        button.disabled = ! ready || busy || permission === 'denied';

        const subscribed = subscription !== null;
        const label = permission === 'denied'
            ? 'Notifications blocked by browser'
            : subscribed
                ? 'Unsubscribe from status notifications'
                : 'Subscribe to status notifications';

        button.setAttribute('aria-label', label);
        button.title = label;
        button.dataset.subscribed = subscribed ? 'true' : 'false';
    });
};

const subscriptionPayload = (currentSubscription) => {
    const json = currentSubscription.toJSON();

    return {
        endpoint: json.endpoint,
        keys: json.keys,
        contentEncoding: PushManager.supportedContentEncodings?.includes('aes128gcm') ? 'aes128gcm' : 'aesgcm',
    };
};

const saveSubscription = async (currentSubscription) => {
    const response = await jsonRequest('/push/subscriptions', {
        method: 'POST',
        body: JSON.stringify(subscriptionPayload(currentSubscription)),
    });

    if (! response.ok) {
        throw new Error(`Could not save push subscription: ${response.status}`);
    }
};

const deleteSubscription = async (currentSubscription) => {
    const response = await jsonRequest('/push/subscriptions', {
        method: 'DELETE',
        body: JSON.stringify({ endpoint: currentSubscription.endpoint }),
    });

    if (! response.ok && response.status !== 404) {
        throw new Error(`Could not delete push subscription: ${response.status}`);
    }
};

const forgetSubscription = async (currentSubscription) => {
    await deleteSubscription(currentSubscription);
    await currentSubscription.unsubscribe();
    subscription = null;
    window.localStorage.removeItem(publicKeyStorageKey);
};

const subscribe = async () => {
    const permission = await Notification.requestPermission();

    if (permission !== 'granted') {
        syncToggle();
        return;
    }

    const currentSubscription = await registration.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: urlBase64ToUint8Array(publicKey),
    });

    await saveSubscription(currentSubscription);
    window.localStorage.setItem(publicKeyStorageKey, publicKey);
    subscription = currentSubscription;
};

const unsubscribe = async () => {
    if (subscription) {
        await forgetSubscription(subscription);
    }
};

const toggleSubscription = async () => {
    if (! ready || busy) {
        return;
    }

    busy = true;
    syncToggle();

    try {
        if (subscription) {
            await unsubscribe();
        } else {
            await subscribe();
        }
    } catch (error) {
        console.error('[statuspage] Could not update push notification subscription.', error);
    } finally {
        busy = false;
        syncToggle();
    }
};

export const bindPushToggle = () => {
    document.querySelectorAll('[data-push-toggle]').forEach((button) => {
        if (button.dataset.bound === 'true') {
            syncToggle();
            return;
        }

        button.dataset.bound = 'true';
        button.addEventListener('click', toggleSubscription);
    });

    syncToggle();
};

export const initializePushNotifications = async () => {
    supported = 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
    syncToggle();

    if (! supported) {
        return;
    }

    try {
        const keyResponse = await fetch('/push/vapid-public-key', {
            cache: 'no-store',
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });

        if (! keyResponse.ok) {
            supported = false;
            syncToggle();
            return;
        }

        publicKey = (await keyResponse.json()).publicKey;
        registration = await navigator.serviceWorker.register('/sw.js');
        subscription = await registration.pushManager.getSubscription();

        if (subscription) {
            if (window.localStorage.getItem(publicKeyStorageKey) !== publicKey) {
                await forgetSubscription(subscription);
            } else {
                await saveSubscription(subscription);
            }
        }

        ready = true;
    } catch (error) {
        supported = false;
        console.error('[statuspage] Could not initialize push notifications.', error);
    } finally {
        syncToggle();
    }
};
