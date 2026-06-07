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

const pageReloadIntervalMilliseconds = 60000;
const pageLoadedAt = Date.now();

const updatePageRefreshProgress = () => {
    const elapsedMilliseconds = Date.now() - pageLoadedAt;
    const progress = Math.min(1, elapsedMilliseconds / pageReloadIntervalMilliseconds);
    const elapsedSeconds = Math.min(60, Math.floor(elapsedMilliseconds / 1000));

    document.querySelectorAll('[data-page-refresh-progress]').forEach((element) => {
        element.style.setProperty('--reload-progress', `${progress * 360}deg`);
        element.setAttribute('aria-valuenow', String(elapsedSeconds));
    });
};

updatePageRefreshProgress();
window.setInterval(updatePageRefreshProgress, 1000);

window.setTimeout(() => {
    window.location.reload();
}, pageReloadIntervalMilliseconds);
