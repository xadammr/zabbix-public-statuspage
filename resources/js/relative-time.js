const numberWords = new Map([
    [1, 'one'], [2, 'two'], [3, 'three'], [4, 'four'], [5, 'five'],
    [6, 'six'], [7, 'seven'], [8, 'eight'], [9, 'nine'],
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

    return elapsedHours < 24
        ? pluralize(elapsedHours, 'hour')
        : pluralize(Math.floor(elapsedHours / 24), 'day');
};

export const updateLastUpdatedAges = ({ polling = false } = {}) => {
    document.querySelectorAll('[data-last-updated-at]').forEach((element) => {
        if (polling) {
            element.textContent = 'Polling...';
            return;
        }

        const updatedAt = new Date(element.dataset.lastUpdatedAt);

        if (! Number.isNaN(updatedAt.getTime())) {
            element.textContent = `${element.dataset.lastUpdatedPrefix ?? 'Last updated: '}${relativeTime(updatedAt)}`;
        }
    });
};

const formatAlertDuration = (elapsedSeconds) => {
    const days = Math.floor(elapsedSeconds / 86400);
    const hours = Math.floor((elapsedSeconds % 86400) / 3600);
    const minutes = Math.floor((elapsedSeconds % 3600) / 60);
    const seconds = elapsedSeconds % 60;

    if (days > 0) return `${days}d ${hours}h`;
    if (hours > 0) return `${hours}h ${minutes}m`;
    if (minutes > 0) return `${minutes}m ${seconds}s`;

    return `${seconds}s`;
};

export const updateAlertDurations = () => {
    document.querySelectorAll('[data-alert-started-at]').forEach((element) => {
        const startedAt = Number(element.dataset.alertStartedAt) * 1000;
        const output = element.querySelector('[data-alert-duration]');

        if (! output || ! Number.isFinite(startedAt) || startedAt <= 0) {
            return;
        }

        output.textContent = formatAlertDuration(Math.max(0, Math.floor((Date.now() - startedAt) / 1000)));
    });
};
