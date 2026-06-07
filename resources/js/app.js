document.querySelectorAll('[data-section-toggle]').forEach((button) => {
    button.addEventListener('click', () => {
        const section = document.querySelector(`[data-section-key="${button.dataset.sectionToggle}"]`);
        const details = Array.from(section?.querySelectorAll('.service-details') ?? []);
        const shouldOpen = details.some((detail) => !detail.open);

        details.forEach((detail) => {
            detail.open = shouldOpen;
        });

        button.textContent = shouldOpen ? 'Hide all' : 'Show all';
    });
});

document.querySelectorAll('[data-next-refresh-at]').forEach((element) => {
    const output = element.querySelector('[data-countdown]');
    const nextRefreshAtValue = element.dataset.nextRefreshAt;
    const nextRefreshAt = new Date(nextRefreshAtValue).getTime();

    if (! output || Number.isNaN(nextRefreshAt)) {
        return;
    }

    const reloadKey = `statuspage-refresh-attempts:${nextRefreshAtValue}`;
    const refreshGraceMilliseconds = 15000;
    let reloadTimer = null;

    const updateCountdown = () => {
        const now = Date.now();
        const remainingSeconds = Math.max(0, Math.ceil((nextRefreshAt - now) / 1000));

        if (remainingSeconds === 0) {
            const overdueMilliseconds = now - nextRefreshAt;

            if (overdueMilliseconds < refreshGraceMilliseconds) {
                output.textContent = 'updating...';

                reloadTimer ??= window.setTimeout(() => {
                    window.location.reload();
                }, refreshGraceMilliseconds - overdueMilliseconds);

                return;
            }

            const attempts = Number.parseInt(window.sessionStorage.getItem(reloadKey) ?? '0', 10);

            if (attempts >= 6) {
                output.textContent = 'poll overdue';

                return;
            }

            output.textContent = attempts === 0
                ? 'refreshing...'
                : 'poll overdue; retrying...';
            window.sessionStorage.setItem(reloadKey, String(attempts + 1));

            reloadTimer ??= window.setTimeout(() => {
                window.location.reload();
            }, attempts === 0 ? 2500 : 10000);

            return;
        }

        const minutes = Math.floor(remainingSeconds / 60);
        const seconds = remainingSeconds % 60;

        output.textContent = minutes > 0
            ? `${minutes}m ${seconds.toString().padStart(2, '0')}s`
            : `${seconds}s`;
    };

    updateCountdown();
    window.setInterval(updateCountdown, 1000);
});
