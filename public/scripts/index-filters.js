document.addEventListener('DOMContentLoaded', () => {
    const modal = document.querySelector('[data-index-filter-modal]');

    if (!modal) {
        return;
    }

    const openButtons = document.querySelectorAll('[data-index-filter-open]');
    const closeButtons = modal.querySelectorAll('[data-index-filter-close]');
    const firstField = modal.querySelector('input:not([type="hidden"]), select, textarea, button');
    const primarySortField = modal.querySelector('[data-sort-field="primary"]');
    const secondarySortField = modal.querySelector('[data-sort-field="secondary"]');
    const primarySortDirections = modal.querySelectorAll('[data-sort-direction="primary"]');
    const secondarySortDirections = modal.querySelectorAll('[data-sort-direction="secondary"]');
    let lastOpenButton = null;

    const setOpen = (isOpen) => {
        modal.hidden = !isOpen;
        modal.setAttribute('aria-hidden', isOpen ? 'false' : 'true');
        document.body.classList.toggle('filter-modal-open', isOpen);

        openButtons.forEach((button) => {
            button.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });

        if (isOpen) {
            firstField?.focus();
            return;
        }

        lastOpenButton?.focus();
    };

    openButtons.forEach((button) => {
        button.addEventListener('click', () => {
            lastOpenButton = button;
            setOpen(true);
        });
    });

    closeButtons.forEach((button) => {
        button.addEventListener('click', () => setOpen(false));
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !modal.hidden) {
            setOpen(false);
        }
    });

    const setDisabled = (elements, disabled) => {
        elements.forEach((element) => {
            element.disabled = disabled;
        });
    };

    const updateSortState = () => {
        const hasPrimary = primarySortField?.value !== '';
        const hasSecondary = hasPrimary && secondarySortField?.value !== '';

        if (secondarySortField) {
            secondarySortField.disabled = !hasPrimary;
        }

        setDisabled(primarySortDirections, !hasPrimary);
        setDisabled(secondarySortDirections, !hasSecondary);
    };

    primarySortField?.addEventListener('change', () => {
        if (primarySortField.value === '' && secondarySortField) {
            secondarySortField.value = '';
        }

        updateSortState();
    });

    secondarySortField?.addEventListener('change', updateSortState);

    updateSortState();
});
