(() => {
    const objectUrls = new Set();
    let sampleKey = 0;

    const createObjectUrl = (file) => {
        const url = URL.createObjectURL(file);
        objectUrls.add(url);

        return url;
    };

    const revokeObjectUrl = (url) => {
        if (!objectUrls.has(url)) {
            return;
        }

        URL.revokeObjectURL(url);
        objectUrls.delete(url);
    };

    const updateSamplePositions = (list) => {
        list.querySelectorAll('[data-image-editor-card]').forEach((card, index) => {
            const position = card.querySelector('[data-image-editor-position]');
            if (position) {
                position.textContent = String(index + 1);
            }
        });
    };

    const initializeCover = (editor) => {
        const input = editor.querySelector('[data-image-editor-cover-input]');
        if (!input) {
            return;
        }

        const removeState = editor.querySelector('[data-image-editor-cover-remove]');
        const picker = editor.querySelector('[data-image-editor-cover-picker]');
        const preview = editor.querySelector('[data-image-editor-cover-preview]');
        const image = editor.querySelector('[data-image-editor-cover-image]');
        const trigger = editor.querySelector('[data-product-image-editor-viewer]');
        const remove = editor.querySelector('[data-image-editor-cover-remove-button]');
        let objectUrl = null;

        input.addEventListener('change', () => {
            const file = input.files?.[0];
            if (!file) {
                return;
            }

            revokeObjectUrl(objectUrl);
            objectUrl = createObjectUrl(file);
            image.src = objectUrl;
            trigger.dataset.indexImageViewerSource = objectUrl;
            if (removeState) {
                removeState.value = '0';
            }
            picker?.classList.remove('image-editor-native-picker--empty');
            preview.hidden = false;
        });

        remove?.addEventListener('click', () => {
            revokeObjectUrl(objectUrl);
            objectUrl = null;
            input.value = '';
            image.removeAttribute('src');
            trigger.dataset.indexImageViewerSource = '';
            if (removeState) {
                removeState.value = '1';
            }
            picker?.classList.add('image-editor-native-picker--empty');
            preview.hidden = true;
        });
    };

    const initializeSamples = (editor) => {
        const list = editor.querySelector('[data-image-editor-sample-list]');
        const template = editor.querySelector('[data-image-editor-sample-template]');
        const addCard = editor.querySelector('[data-image-editor-add-card]');
        if (!list || !template || !addCard) {
            return;
        }

        const singleFileInput = (picker, file, key) => {
            const input = picker.cloneNode();
            const transfer = new DataTransfer();
            transfer.items.add(file);

            input.removeAttribute('id');
            input.removeAttribute('multiple');
            input.removeAttribute('class');
            input.removeAttribute('data-image-editor-sample-input');
            input.name = `sample_images[${key}]`;
            input.files = transfer.files;
            input.hidden = true;

            return input;
        };

        const addFileCard = (picker, file) => {
            const key = String(++sampleKey);
            const input = singleFileInput(picker, file, key);
            const fragment = template.content.cloneNode(true);
            const card = fragment.querySelector('[data-image-editor-card]');
            const order = card.querySelector('[data-image-editor-order]');
            const slot = card.querySelector('[data-image-editor-file-slot]');
            const trigger = card.querySelector('[data-product-image-editor-viewer]');
            const image = card.querySelector('img');
            const name = card.querySelector('.image-editor-card__name');
            const objectUrl = createObjectUrl(file);

            order.value = `new:${key}`;
            slot.replaceWith(input);
            trigger.dataset.indexImageViewerSource = objectUrl;
            image.src = objectUrl;
            name.textContent = file.name;
            name.title = file.name;
            card.dataset.imageEditorObjectUrl = objectUrl;

            list.insertBefore(fragment, addCard);
        };

        const handleFiles = (input) => {
            const files = Array.from(input.files ?? []);
            if (files.length === 0) {
                return;
            }

            files.forEach((file) => addFileCard(input, file));
            input.value = '';
            updateSamplePositions(list);
        };

        addCard.addEventListener('change', (event) => {
            if (event.target.matches('[data-image-editor-sample-input]')) {
                handleFiles(event.target);
            }
        });

        list.addEventListener('click', (event) => {
            const remove = event.target.closest('[data-image-editor-remove-sample]');
            if (!remove) {
                return;
            }

            const card = remove.closest('[data-image-editor-card]');
            revokeObjectUrl(card?.dataset.imageEditorObjectUrl);
            card?.remove();
            updateSamplePositions(list);
        });

        let dragged = null;

        list.addEventListener('dragstart', (event) => {
            const handle = event.target.closest('[data-image-editor-drag-handle]');
            if (!handle) {
                event.preventDefault();
                return;
            }

            dragged = handle.closest('[data-image-editor-card]');
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', 'image');
            dragged?.classList.add('is-dragging');
        });

        list.addEventListener('dragover', (event) => {
            if (!dragged) {
                return;
            }

            event.preventDefault();
            const target = event.target.closest('[data-image-editor-card]');
            if (!target || target === dragged) {
                return;
            }

            const rect = target.getBoundingClientRect();
            const after = event.clientX > rect.left + rect.width / 2;
            list.insertBefore(dragged, after ? target.nextSibling : target);
            updateSamplePositions(list);
        });

        list.addEventListener('dragend', () => {
            dragged?.classList.remove('is-dragging');
            dragged = null;
            updateSamplePositions(list);
        });
    };

    document.querySelectorAll('[data-product-image-editor]').forEach((editor) => {
        if (editor.dataset.productImageEditorKind === 'cover') {
            initializeCover(editor);
        } else if (editor.dataset.productImageEditorKind === 'samples') {
            initializeSamples(editor);
        }
    });

    window.addEventListener('pagehide', () => {
        objectUrls.forEach((url) => URL.revokeObjectURL(url));
        objectUrls.clear();
    });
})();
