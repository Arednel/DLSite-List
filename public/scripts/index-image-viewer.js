(() => {
    if (window.indexImageViewerInitialized) {
        return;
    }

    const dialog = document.getElementById('index-image-viewer-dialog');

    if (!dialog) {
        return;
    }

    window.indexImageViewerInitialized = true;

    const title = dialog.querySelector('[data-index-image-viewer-title]');
    const image = dialog.querySelector('[data-index-image-viewer-image]');
    const placeholder = dialog.querySelector('[data-index-image-viewer-placeholder]');
    const loading = dialog.querySelector('[data-index-image-viewer-loading]');
    const counter = dialog.querySelector('[data-index-image-viewer-counter]');
    const previous = dialog.querySelector('[data-index-image-viewer-previous]');
    const next = dialog.querySelector('[data-index-image-viewer-next]');
    const viewFull = dialog.querySelector('[data-index-image-viewer-full]');
    const imageLabel = dialog.dataset.imageLabel;

    let images = [];
    let currentIndex = 0;
    let opener = null;
    let requestSequence = 0;

    const close = () => {
        if (dialog.open) {
            dialog.close();
        }
    };

    const setNavigationVisibility = () => {
        const hidden = images.length <= 1;

        previous.hidden = hidden;
        next.hidden = hidden;
    };

    const hideFullView = () => {
        viewFull.hidden = true;
        viewFull.removeAttribute('href');
    };

    const showMissingImage = () => {
        loading.hidden = true;
        image.hidden = true;
        placeholder.hidden = false;
        hideFullView();
    };

    const showUnavailableGallery = () => {
        images = [];
        currentIndex = 0;
        counter.textContent = '';
        image.alt = '';
        image.removeAttribute('src');
        previous.hidden = true;
        next.hidden = true;
        showMissingImage();
    };

    const renderCurrentImage = () => {
        const source = images[currentIndex];
        const current = currentIndex + 1;
        const total = images.length;

        counter.textContent = `${current} / ${total}`;
        setNavigationVisibility();
        placeholder.hidden = true;
        image.hidden = true;
        hideFullView();

        image.alt = imageLabel
            .replace(':current', current)
            .replace(':total', total)
            .replace(':title', title.textContent);

        loading.hidden = false;
        image.src = source;
    };

    const showImage = (offset) => {
        currentIndex = (currentIndex + offset + images.length) % images.length;
        renderCurrentImage();
    };

    const normalizeImages = (candidates) => {
        if (!Array.isArray(candidates)) {
            return [];
        }

        return candidates.filter((item) => typeof item === 'string' && item !== '');
    };

    const open = async (trigger) => {
        const request = ++requestSequence;
        opener = trigger;
        images = [];
        currentIndex = 0;

        title.textContent = trigger.dataset.indexImageViewerTitle;
        image.removeAttribute('src');
        image.hidden = true;
        image.alt = '';
        placeholder.hidden = true;
        loading.hidden = false;
        counter.textContent = '';
        previous.hidden = true;
        next.hidden = true;
        hideFullView();

        if (!dialog.open) {
            dialog.showModal();
        }

        document.body.classList.add('image-viewer-open');

        try {
            const componentId = trigger.closest('[wire\\:id]')?.getAttribute('wire:id');
            const component = componentId ? window.Livewire?.find(componentId) : null;

            if (!component) {
                throw new Error('Image viewer Livewire component was not found.');
            }

            const candidates = await component.workImages(trigger.dataset.indexImageViewerProduct);

            if (request !== requestSequence) {
                return;
            }

            images = normalizeImages(candidates);

            if (images.length === 0) {
                showUnavailableGallery();

                return;
            }

            renderCurrentImage();
        } catch {
            if (request !== requestSequence) {
                return;
            }

            showUnavailableGallery();
        }
    };

    image.addEventListener('load', () => {
        loading.hidden = true;
        placeholder.hidden = true;
        image.hidden = false;
        viewFull.href = images[currentIndex];
        viewFull.hidden = false;
    });

    image.addEventListener('error', showMissingImage);

    dialog.addEventListener('close', () => {
        requestSequence++;
        document.body.classList.remove('image-viewer-open');

        const focusTarget = opener;
        opener = null;
        queueMicrotask(() => focusTarget?.focus());
    });

    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) {
            close();
        }
    });

    document.addEventListener('click', (event) => {
        if (!(event.target instanceof Element)) {
            return;
        }

        const trigger = event.target.closest('[data-index-image-viewer-product]');

        if (trigger) {
            open(trigger);

            return;
        }

        if (event.target.closest('[data-index-image-viewer-close]')) {
            close();

            return;
        }

        if (event.target.closest('[data-index-image-viewer-previous]')) {
            showImage(-1);

            return;
        }

        if (event.target.closest('[data-index-image-viewer-next]')) {
            showImage(1);
        }
    });

    document.addEventListener('keydown', (event) => {
        if (!dialog.open || images.length <= 1) {
            return;
        }

        if (event.key === 'ArrowLeft') {
            event.preventDefault();
            showImage(-1);
        }

        if (event.key === 'ArrowRight') {
            event.preventDefault();
            showImage(1);
        }
    });
})();
