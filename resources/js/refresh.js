import { bindDetailsControls, openServiceIds, restoreOpenServices } from './details';
import { bindPushToggle } from './push';
import { updateAlertDurations, updateLastUpdatedAges } from './relative-time';

const intervalMilliseconds = 15000;
const pollingLabelMinimumMilliseconds = 1000;
let startedAt = Date.now();
let paused = false;
let fetching = false;
let showPollingLabel = false;
let pollingLabelShownAt = 0;
let pollingLabelTimer = null;

const waitForPollingLabelMinimum = () => new Promise((resolve) => {
    const remaining = Math.max(0, pollingLabelMinimumMilliseconds - (Date.now() - pollingLabelShownAt));

    if (! showPollingLabel || remaining <= 0) {
        resolve();
        return;
    }

    window.clearTimeout(pollingLabelTimer);
    pollingLabelTimer = window.setTimeout(resolve, remaining);
});

const syncRefreshToggle = () => {
    document.querySelectorAll('[data-refresh-toggle]').forEach((button) => {
        const icon = button.querySelector('[data-refresh-toggle-icon]');

        button.hidden = fetching;
        button.disabled = fetching;
        button.setAttribute('aria-label', paused ? 'Resume automatic refresh' : 'Pause automatic refresh');
        button.title = paused ? 'Resume automatic refresh' : 'Pause automatic refresh';

        if (icon) {
            icon.textContent = paused ? '>' : '||';
        }
    });
};

const setFetching = (isFetching) => {
    window.clearTimeout(pollingLabelTimer);
    pollingLabelTimer = null;

    if (isFetching) {
        fetching = true;
        showPollingLabel = true;
        pollingLabelShownAt = Date.now();
    } else {
        fetching = false;
        showPollingLabel = false;
    }

    syncRefreshToggle();
    updateLastUpdatedAges({ polling: fetching && showPollingLabel });
};

const highlightLastUpdated = () => {
    document.querySelectorAll('[data-refresh-highlight]').forEach((element) => {
        element.classList.remove('is-fresh');
        element.offsetHeight;
        element.classList.add('is-fresh');
    });
};

const updateProgress = () => {
    if (paused) {
        document.querySelectorAll('[data-page-refresh-progress]').forEach((element) => {
            element.style.setProperty('--reload-progress', '0deg');
            element.setAttribute('aria-valuenow', '0');
        });
        return;
    }

    const elapsedMilliseconds = Date.now() - startedAt;
    const progress = Math.min(1, elapsedMilliseconds / intervalMilliseconds);
    const elapsedSeconds = Math.min(15, Math.floor(elapsedMilliseconds / 1000));

    document.querySelectorAll('[data-page-refresh-progress]').forEach((element) => {
        element.style.setProperty('--reload-progress', `${progress * 360}deg`);
        element.setAttribute('aria-valuenow', String(elapsedSeconds));
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
            paused = ! paused;
            startedAt = Date.now();
            syncRefreshToggle();
            updateProgress();
        });
    });

    syncRefreshToggle();
};

const bindPageReload = () => {
    document.querySelectorAll('[data-page-reload]').forEach((button) => {
        if (button.dataset.bound === 'true') {
            return;
        }

        button.dataset.bound = 'true';
        button.addEventListener('click', () => {
            const reloadUrl = new URL(window.location.href);

            reloadUrl.searchParams.set('_reload', Date.now().toString());
            window.location.replace(reloadUrl);
        });
    });
};

export const bindRefreshControls = () => {
    bindRefreshToggle();
    bindPageReload();
};

const refreshStatusFragment = async () => {
    if (paused || fetching) {
        return;
    }

    const container = document.querySelector('[data-status-page-content]');

    if (! container) {
        return;
    }

    const openedServiceIds = openServiceIds();
    setFetching(true);
    console.info('[statuspage] Fetching updated status fragment.', new Date().toISOString());

    try {
        const response = await fetch(container.dataset.statusFragmentUrl || '/status-fragment', {
            cache: 'no-store',
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });

        if (! response.ok) {
            console.warn('[statuspage] Status fragment refresh returned an error.', response.status, response.statusText);
            return;
        }

        const html = await response.text();
        await waitForPollingLabelMinimum();
        container.innerHTML = html;
        restoreOpenServices(openedServiceIds);
        bindDetailsControls();
        bindRefreshControls();
        bindPushToggle();
        updateAlertDurations();
        startedAt = Date.now();
        updateProgress();
        highlightLastUpdated();
        console.info('[statuspage] Status fragment updated.', new Date().toISOString());
    } catch (error) {
        console.error('[statuspage] Could not refresh status page fragment.', error);
    } finally {
        setFetching(false);
    }
};

export const initializeRefresh = () => {
    updateLastUpdatedAges();
    updateAlertDurations();
    updateProgress();

    window.setInterval(() => {
        updateLastUpdatedAges({ polling: fetching && showPollingLabel });
        updateAlertDurations();
        updateProgress();
    }, 1000);
    window.setInterval(refreshStatusFragment, intervalMilliseconds);
};
