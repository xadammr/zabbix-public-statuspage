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

document.querySelectorAll('.service-details > summary').forEach((summary) => {
    summary.addEventListener('click', (event) => {
        event.preventDefault();
        setServiceDetailsOpen(summary.parentElement, ! summary.parentElement.open);
    });
});

document.querySelectorAll('[data-section-toggle]').forEach((button) => {
    button.addEventListener('click', () => {
        const section = document.querySelector(`[data-section-key="${button.dataset.sectionToggle}"]`);
        const details = Array.from(section?.querySelectorAll('.service-details') ?? []);
        const shouldOpen = details.some((detail) => !detail.open);

        details.forEach((detail) => {
            setServiceDetailsOpen(detail, shouldOpen);
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
