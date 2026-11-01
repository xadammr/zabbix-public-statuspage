const animationDuration = 180;
const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

const clearBodyStyles = (body) => {
    body.style.height = '';
    body.style.opacity = '';
    body.style.transform = '';
    body.style.transition = '';
};

const setOpen = (details, shouldOpen) => {
    if (details.dataset.expandable !== 'true') {
        return;
    }

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
        body.style.transition = `height ${animationDuration}ms ease, opacity 140ms ease, transform ${animationDuration}ms ease`;
        body.style.height = `${body.scrollHeight}px`;
        body.style.opacity = '1';
        body.style.transform = 'translateY(0)';

        details._serviceDetailsAnimation = window.setTimeout(() => clearBodyStyles(body), animationDuration);
        return;
    }

    body.style.height = `${body.scrollHeight}px`;
    body.style.opacity = '1';
    body.style.transform = 'translateY(0)';
    body.offsetHeight;
    body.style.transition = `height ${animationDuration}ms ease, opacity 120ms ease, transform ${animationDuration}ms ease`;
    body.style.height = '0px';
    body.style.opacity = '0';
    body.style.transform = 'translateY(-4px)';

    details._serviceDetailsAnimation = window.setTimeout(() => {
        details.open = false;
        clearBodyStyles(body);
    }, animationDuration);
};

const syncSectionToggleLabel = (button) => {
    const section = document.querySelector(`[data-section-key="${button.dataset.sectionToggle}"]`);
    const details = Array.from(section?.querySelectorAll('.service-details[data-expandable="true"]') ?? []);

    button.textContent = details.length > 0 && details.every((detail) => detail.open) ? 'Hide all' : 'Show all';
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

            if (summary.parentElement.dataset.expandable === 'true') {
                setOpen(summary.parentElement, ! summary.parentElement.open);
            }
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
            const details = Array.from(section?.querySelectorAll('.service-details[data-expandable="true"]') ?? []);
            const shouldOpen = details.some((detail) => ! detail.open);

            details.forEach((detail) => setOpen(detail, shouldOpen));
            button.textContent = shouldOpen ? 'Hide all' : 'Show all';
        });

        syncSectionToggleLabel(button);
    });
};

export const bindDetailsControls = () => {
    bindServiceDetails();
    bindSectionToggles();
};

export const openServiceIds = () => Array.from(document.querySelectorAll('.service-details[open][data-service-id]'))
    .map((detail) => detail.dataset.serviceId);

export const restoreOpenServices = (serviceIds) => {
    serviceIds.forEach((serviceId) => {
        const detail = document.querySelector(`.service-details[data-service-id="${CSS.escape(serviceId)}"]`);

        if (detail) {
            detail.open = true;
        }
    });
};
