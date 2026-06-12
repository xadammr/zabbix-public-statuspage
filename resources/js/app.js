const serviceDetailsAnimationDuration = 180;
const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

const clearServiceDetailsBodyStyles = (body) => {
    body.style.height = '';
    body.style.opacity = '';
    body.style.transform = '';
    body.style.transition = '';
};

const setServiceDetailsOpen = (details, shouldOpen) => {
    const body = details.querySelector('[data-details-body]');

    window.clearTimeout(details._serviceDetailsAnimation);

    if (! body || prefersReducedMotion.matches) {
        details.open = shouldOpen;
        return;
    }

    if (shouldOpen) {
        details.open = true;
        body.style.height = '0px';
        body.style.opacity = '0';
        body.style.transform = 'translateY(-4px)';
        body.offsetHeight;
        body.style.transition = `height ${serviceDetailsAnimationDuration}ms ease, opacity 140ms ease, transform ${serviceDetailsAnimationDuration}ms ease`;
        body.style.height = `${body.scrollHeight}px`;
        body.style.opacity = '1';
        body.style.transform = 'translateY(0)';

        details._serviceDetailsAnimation = window.setTimeout(() => {
            clearServiceDetailsBodyStyles(body);
        }, serviceDetailsAnimationDuration);

        return;
    }

    body.style.height = `${body.scrollHeight}px`;
    body.style.opacity = '1';
    body.style.transform = 'translateY(0)';
    body.offsetHeight;
    body.style.transition = `height ${serviceDetailsAnimationDuration}ms ease, opacity 120ms ease, transform ${serviceDetailsAnimationDuration}ms ease`;
    body.style.height = '0px';
    body.style.opacity = '0';
    body.style.transform = 'translateY(-4px)';

    details._serviceDetailsAnimation = window.setTimeout(() => {
        details.open = false;
        clearServiceDetailsBodyStyles(body);
    }, serviceDetailsAnimationDuration);
};

const statusRefreshIntervalMilliseconds = 15000;
let statusRefreshStartedAt = Date.now();
let statusRefreshPaused = false;
let statusRefreshFetching = false;
let pushSubscription = null;
let pushSupported = false;
let pushReady = false;
let pushBusy = false;
let pushRegistration = null;
let pushPublicKey = null;

const syncSectionToggleLabel = (button) => {
    const section = document.querySelector(`[data-section-key="${button.dataset.sectionToggle}"]`);
    const details = Array.from(section?.querySelectorAll('.service-details') ?? []);

    button.textContent = details.length > 0 && details.every((detail) => detail.open)
        ? 'Hide all'
        : 'Show all';
};

const bindServiceDetails = () => {
    document.querySelectorAll('.service-details > summary').forEach((summary) => {
        if (summary.dataset.bound === 'true') {
            return;
        }

        summary.dataset.bound = 'true';
        summary.addEventListener('click', (event) => {
            if (event.target.closest('a')) {
                return;
            }

            event.preventDefault();
            setServiceDetailsOpen(summary.parentElement, ! summary.parentElement.open);
        });
    });
};

const bindSectionToggles = () => {
    document.querySelectorAll('[data-section-toggle]').forEach((button) => {
        if (button.dataset.bound === 'true') {
            syncSectionToggleLabel(button);
            return;
        }

        button.dataset.bound = 'true';
        button.addEventListener('click', () => {
            const section = document.querySelector(`[data-section-key="${button.dataset.sectionToggle}"]`);
            const details = Array.from(section?.querySelectorAll('.service-details') ?? []);
            const shouldOpen = details.some((detail) => !detail.open);

            details.forEach((detail) => {
                setServiceDetailsOpen(detail, shouldOpen);
            });

            button.textContent = shouldOpen ? 'Hide all' : 'Show all';
        });

        syncSectionToggleLabel(button);
    });
};

const bindStatusPageControls = () => {
    bindServiceDetails();
    bindSectionToggles();
    bindRefreshToggle();
    bindPushToggle();
};

const numberWords = new Map([
    [1, 'one'],
    [2, 'two'],
    [3, 'three'],
    [4, 'four'],
    [5, 'five'],
    [6, 'six'],
    [7, 'seven'],
    [8, 'eight'],
    [9, 'nine'],
]);

const formatCount = (count) => numberWords.get(count) ?? String(count);

const pluralize = (count, singular) => `${formatCount(count)} ${singular}${count === 1 ? '' : 's'} ago`;

const relativeTime = (date) => {
    const elapsedSeconds = Math.max(0, Math.floor((Date.now() - date.getTime()) / 1000));

    if (elapsedSeconds < 60) {
        return elapsedSeconds < 1 ? 'just now' : pluralize(elapsedSeconds, 'second');
    }

    const elapsedMinutes = Math.floor(elapsedSeconds / 60);

    if (elapsedMinutes < 60) {
        return pluralize(elapsedMinutes, 'minute');
    }

    const elapsedHours = Math.floor(elapsedMinutes / 60);

    if (elapsedHours < 24) {
        return pluralize(elapsedHours, 'hour');
    }

    return pluralize(Math.floor(elapsedHours / 24), 'day');
};

const updateLastUpdatedAges = () => {
    document.querySelectorAll('[data-last-updated-at]').forEach((element) => {
        const updatedAt = new Date(element.dataset.lastUpdatedAt);

        if (Number.isNaN(updatedAt.getTime())) {
            return;
        }

        element.textContent = `Last updated: ${relativeTime(updatedAt)}`;
    });
};

const highlightLastUpdated = () => {
    document.querySelectorAll('[data-refresh-highlight]').forEach((element) => {
        element.classList.remove('is-fresh');
        element.offsetHeight;
        element.classList.add('is-fresh');
    });
};

const updatePageRefreshProgress = () => {
    if (statusRefreshPaused) {
        document.querySelectorAll('[data-page-refresh-progress]').forEach((element) => {
            element.style.setProperty('--reload-progress', '0deg');
            element.setAttribute('aria-valuenow', '0');
        });
        return;
    }

    const elapsedMilliseconds = Date.now() - statusRefreshStartedAt;
    const progress = Math.min(1, elapsedMilliseconds / statusRefreshIntervalMilliseconds);
    const elapsedSeconds = Math.min(15, Math.floor(elapsedMilliseconds / 1000));

    document.querySelectorAll('[data-page-refresh-progress]').forEach((element) => {
        element.style.setProperty('--reload-progress', `${progress * 360}deg`);
        element.setAttribute('aria-valuenow', String(elapsedSeconds));
    });
};

const syncRefreshToggle = () => {
    document.querySelectorAll('[data-refresh-toggle]').forEach((button) => {
        const icon = button.querySelector('[data-refresh-toggle-icon]');

        button.hidden = statusRefreshFetching;
        button.disabled = statusRefreshFetching;
        button.setAttribute('aria-label', statusRefreshPaused ? 'Resume automatic refresh' : 'Pause automatic refresh');
        button.title = statusRefreshPaused ? 'Resume automatic refresh' : 'Pause automatic refresh';

        if (icon) {
            icon.textContent = statusRefreshPaused ? '>' : '||';
        }
    });
};

const bindRefreshToggle = () => {
    document.querySelectorAll('[data-refresh-toggle]').forEach((button) => {
        if (button.dataset.bound === 'true') {
            syncRefreshToggle();
            return;
        }

        button.dataset.bound = 'true';
        button.addEventListener('click', () => {
            statusRefreshPaused = !statusRefreshPaused;
            statusRefreshStartedAt = Date.now();
            syncRefreshToggle();
            updatePageRefreshProgress();
        });
    });

    syncRefreshToggle();
};

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

const urlBase64ToUint8Array = (base64String) => {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = `${base64String}${padding}`.replace(/-/g, '+').replace(/_/g, '/');
    const rawData = window.atob(base64);

    return Uint8Array.from([...rawData].map((character) => character.charCodeAt(0)));
};

const pushJsonRequest = async (url, options = {}) => fetch(url, {
    ...options,
    credentials: 'same-origin',
    headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken(),
        'X-Requested-With': 'XMLHttpRequest',
        ...(options.headers ?? {}),
    },
});

const syncPushToggle = () => {
    document.querySelectorAll('[data-push-toggle]').forEach((button) => {
        const permission = pushSupported ? Notification.permission : 'default';

        button.hidden = ! pushSupported;
        button.disabled = ! pushReady || pushBusy || permission === 'denied';

        const subscribed = pushSubscription !== null;
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

const subscriptionPayload = (subscription) => {
    const json = subscription.toJSON();

    return {
        endpoint: json.endpoint,
        keys: json.keys,
        contentEncoding: PushManager.supportedContentEncodings?.includes('aes128gcm') ? 'aes128gcm' : 'aesgcm',
    };
};

const savePushSubscription = async (subscription) => {
    const response = await pushJsonRequest('/push/subscriptions', {
        method: 'POST',
        body: JSON.stringify(subscriptionPayload(subscription)),
    });

    if (! response.ok) {
        throw new Error(`Could not save push subscription: ${response.status}`);
    }
};

const deletePushSubscription = async (subscription) => {
    const response = await pushJsonRequest('/push/subscriptions', {
        method: 'DELETE',
        body: JSON.stringify({ endpoint: subscription.endpoint }),
    });

    if (! response.ok && response.status !== 404) {
        throw new Error(`Could not delete push subscription: ${response.status}`);
    }
};

const subscribeToPushNotifications = async () => {
    const permission = await Notification.requestPermission();

    if (permission !== 'granted') {
        syncPushToggle();
        return;
    }

    const subscription = await pushRegistration.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: urlBase64ToUint8Array(pushPublicKey),
    });

    await savePushSubscription(subscription);
    pushSubscription = subscription;
};

const unsubscribeFromPushNotifications = async () => {
    const subscription = pushSubscription;

    if (! subscription) {
        return;
    }

    await deletePushSubscription(subscription);
    await subscription.unsubscribe();
    pushSubscription = null;
};

const togglePushSubscription = async () => {
    if (! pushReady || pushBusy) {
        return;
    }

    pushBusy = true;
    syncPushToggle();

    try {
        if (pushSubscription) {
            await unsubscribeFromPushNotifications();
        } else {
            await subscribeToPushNotifications();
        }
    } catch (error) {
        console.error('[statuspage] Could not update push notification subscription.', error);
    } finally {
        pushBusy = false;
        syncPushToggle();
    }
};

const bindPushToggle = () => {
    document.querySelectorAll('[data-push-toggle]').forEach((button) => {
        if (button.dataset.bound === 'true') {
            syncPushToggle();
            return;
        }

        button.dataset.bound = 'true';
        button.addEventListener('click', togglePushSubscription);
    });

    syncPushToggle();
};

const initializePushNotifications = async () => {
    pushSupported = 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
    syncPushToggle();

    if (! pushSupported) {
        return;
    }

    try {
        const keyResponse = await fetch('/push/vapid-public-key', {
            cache: 'no-store',
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (! keyResponse.ok) {
            pushSupported = false;
            syncPushToggle();
            return;
        }

        pushPublicKey = (await keyResponse.json()).publicKey;
        pushRegistration = await navigator.serviceWorker.register('/sw.js');
        pushSubscription = await pushRegistration.pushManager.getSubscription();

        if (pushSubscription) {
            await savePushSubscription(pushSubscription);
        }

        pushReady = true;
    } catch (error) {
        pushSupported = false;
        console.error('[statuspage] Could not initialize push notifications.', error);
    } finally {
        syncPushToggle();
    }
};

const openServiceIds = () => Array.from(document.querySelectorAll('.service-details[open][data-service-id]'))
    .map((detail) => detail.dataset.serviceId);

const restoreOpenServices = (serviceIds) => {
    serviceIds.forEach((serviceId) => {
        const detail = document.querySelector(`.service-details[data-service-id="${CSS.escape(serviceId)}"]`);

        if (detail) {
            detail.open = true;
        }
    });
};

const refreshStatusFragment = async () => {
    if (statusRefreshPaused || statusRefreshFetching) {
        return;
    }

    const container = document.querySelector('[data-status-page-content]');

    if (! container) {
        return;
    }

    const openedServiceIds = openServiceIds();
    statusRefreshFetching = true;
    syncRefreshToggle();
    console.info('[statuspage] Fetching updated status fragment.', new Date().toISOString());

    try {
        const response = await fetch('/status-fragment', {
            cache: 'no-store',
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        if (! response.ok) {
            console.warn('[statuspage] Status fragment refresh returned an error.', response.status, response.statusText);
            return;
        }

        container.innerHTML = await response.text();
        restoreOpenServices(openedServiceIds);
        bindStatusPageControls();
        statusRefreshStartedAt = Date.now();
        updatePageRefreshProgress();
        highlightLastUpdated();
        console.info('[statuspage] Status fragment updated.', new Date().toISOString());
    } catch (error) {
        console.error('[statuspage] Could not refresh status page fragment.', error);
    } finally {
        statusRefreshFetching = false;
        syncRefreshToggle();
    }
};

bindStatusPageControls();
initializePushNotifications();
updateLastUpdatedAges();
updatePageRefreshProgress();
window.setInterval(() => {
    updateLastUpdatedAges();
    updatePageRefreshProgress();
}, 1000);
window.setInterval(refreshStatusFragment, statusRefreshIntervalMilliseconds);
