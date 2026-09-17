(() => {
    'use strict';

    const updateControls = root => {
        const disabled = root.uploading || root.accepting === false;
        const button = root.querySelector('[data-upload]');
        const input = root.querySelector('[data-files]');
        if (button) button.disabled = disabled;
        if (input) input.disabled = disabled;
    };

    const updateProgress = (root, scope, value) => {
        const percent = Math.min(100, Math.max(0, Number(value) || 0));
        const progress = root.querySelector(`[data-${scope}-progress]`);
        if (!progress) return;
        progress.setAttribute('aria-valuenow', String(Math.round(percent)));
        progress.querySelector('.progress-fill').style.width = `${percent}%`;
        root.querySelector(`[data-${scope}-progress-value]`).textContent = `${Math.round(percent)}%`;
    };

    const showFiles = (root, files) => {
        const list = root.querySelector('[data-file-status]');
        const results = root.fileResults ??= new WeakMap();
        list.replaceChildren(...files.map(file => {
            const row = document.createElement('li');
            const result = results.get(file);
            const marker = result?.state === 'success' ? '✓' : (result?.state === 'error' ? '✕' : '•');
            row.textContent = `${marker} ${file.name} - ${result?.message ?? root.dataset.waiting}`;
            return row;
        }));
    };

    const startImport = async (root, fileCount) => {
        if (root.dataset.run) return true;

        const componentElement = root.closest('[wire\\:id]');
        const component = componentElement ? window.Livewire?.find(componentElement.getAttribute('wire:id')) : null;
        if (!component) throw new Error(root.dataset.startFailed);

        root.querySelector('[data-status]').textContent = root.dataset.starting;
        const run = await component.startImport(fileCount);
        if (!run) return false;

        root.dataset.run = String(run.runId);
        root.dataset.url = run.uploadUrl;
        root.dataset.show = run.showUrl;

        return true;
    };

    const uploadFile = (root, file, sent, total, files) => new Promise((resolve, reject) => {
        const request = new XMLHttpRequest();
        const results = root.fileResults ??= new WeakMap();
        root.request = request;
        let watchdog;
        let settled = false;
        const finish = error => {
            if (settled) return;
            settled = true;
            clearTimeout(watchdog);
            root.request = null;
            results.set(file, {
                state: error ? 'error' : 'success',
                message: error ? error.message : root.dataset.uploaded,
            });
            showFiles(root, files);
            error ? reject(error) : resolve();
        };
        // This is an inactivity timeout, not a total-duration cap on a large upload.
        const activity = () => {
            clearTimeout(watchdog);
            watchdog = setTimeout(() => request.abort(), 300000);
        };
        request.open('POST', root.dataset.url);
        request.setRequestHeader('X-CSRF-TOKEN', root.dataset.token);
        request.setRequestHeader('Accept', 'application/json');
        request.upload.onprogress = progress => {
            activity();
            const bytes = progress.lengthComputable ? Math.min(file.size, file.size * progress.loaded / progress.total) : 0;
            updateProgress(root, 'file', file.size ? bytes / file.size * 100 : 0);
            updateProgress(root, 'total', total ? (sent + bytes) / total * 100 : 0);
        };
        request.upload.onload = () => {
            activity();
            updateProgress(root, 'file', 100);
            root.querySelector('[data-status]').textContent = file.name + ': ' + root.dataset.processing;
        };
        request.onprogress = activity;
        request.onload = () => {
            if (request.status >= 200 && request.status < 300) return finish();
            let message = 'HTTP ' + request.status;
            try { message = JSON.parse(request.responseText).message ?? message; } catch (_) { /* Proxy responses can be HTML. */ }
            finish(new Error(message));
        };
        request.onerror = request.onabort = () => finish(new Error(root.dataset.interrupted));
        const data = new FormData();
        data.append('archive', file);
        activity();
        request.send(data);
    });

    document.addEventListener('transfer-status', event => {
        const root = document.querySelector('[data-transfer-upload][data-run="' + event.detail.runId + '"]');
        if (!root) return;
        root.accepting = event.detail.accepting;
        if (event.detail.status === 'cancelled') {
            root.cancelled = true;
            root.request?.abort();
        }
        updateControls(root);
    });
    document.addEventListener('transfer-cancelled', event => {
        const root = document.querySelector('[data-transfer-upload][data-run="' + event.detail.runId + '"]');
        if (!root) return;
        root.cancelled = true;
        root.accepting = false;
        root.request?.abort();
        updateControls(root);
    });
    document.addEventListener('click', async event => {
        const downloadAll = event.target.closest('[data-transfer-downloads] [data-download-all]');
        if (downloadAll && !downloadAll.disabled) {
            const root = downloadAll.closest('[data-transfer-downloads]');
            const links = Array.from(root.querySelectorAll('[data-download-part]'));
            const status = root.querySelector('[data-download-status]');
            downloadAll.disabled = true;
            status.textContent = root.dataset.started;
            try {
                const directory = await window.showDirectoryPicker({ mode: 'readwrite' });
                for (const link of links) {
                    const response = await fetch(link.href);
                    if (!response.ok || !response.body) throw new Error(root.dataset.failed);

                    const file = await directory.getFileHandle(link.textContent.trim(), { create: true });
                    await response.body.pipeTo(await file.createWritable());
                }
                status.textContent = root.dataset.complete;
            } catch (error) {
                status.textContent = error.name === 'AbortError' ? root.dataset.cancelled : root.dataset.failed;
            } finally {
                downloadAll.disabled = false;
            }
            return;
        }

        const button = event.target.closest('[data-transfer-upload] [data-upload]');
        if (!button || button.disabled) return;
        const root = button.closest('[data-transfer-upload]');
        const input = root.querySelector('[data-files]');
        const files = Array.from(input.files);
        const status = root.querySelector('[data-status]');
        if (!files.length) {
            status.textContent = root.dataset.noFiles;
            return;
        }

        const uploadLimit = Number(root.dataset.uploadMaxBytes) || 0;
        const oversized = uploadLimit > 0 ? files.filter(file => file.size > uploadLimit) : [];
        const uploadable = files.filter(file => !oversized.includes(file));
        if (oversized.length) {
            const message = root.dataset.fileTooLarge
                .replace('__SIZE__', Math.floor(uploadLimit / 1048576).toLocaleString());
            const results = root.fileResults ??= new WeakMap();
            for (const file of oversized) {
                results.set(file, { state: 'error', message });
            }
            showFiles(root, files);
            if (!uploadable.length) {
                status.textContent = root.dataset.failed;
                return;
            }
        }

        const completed = root.completedFiles ??= new WeakSet();
        const total = uploadable.reduce((sum, file) => sum + file.size, 0);
        let sent = uploadable.filter(file => completed.has(file)).reduce((sum, file) => sum + file.size, 0);
        let failures = oversized.length;
        root.uploading = true;
        updateControls(root);
        const progress = root.querySelector('[data-progress]');
        if (progress) progress.hidden = false;
        updateProgress(root, 'file', 0);
        updateProgress(root, 'total', total ? sent / total * 100 : 0);
        showFiles(root, files);
        try {
            if (root.dataset.startImport !== undefined && !await startImport(root, uploadable.length)) {
                status.textContent = root.dataset.startFailed;
                return;
            }

            for (const file of uploadable) {
                if (completed.has(file)) continue;
                if (root.cancelled || root.accepting === false) break;
                status.textContent = file.name;
                updateProgress(root, 'file', 0);
                try {
                    await uploadFile(root, file, sent, total, files);
                    completed.add(file);
                    sent += file.size;
                } catch (_) {
                    failures++;
                }
            }
            updateProgress(root, 'total', total ? sent / total * 100 : 0);
            status.textContent = root.cancelled
                ? root.dataset.cancelled
                : (failures ? root.dataset.failed : root.dataset.complete);

            const componentElement = root.closest('[wire\\:id]');
            const component = componentElement ? window.Livewire?.find(componentElement.getAttribute('wire:id')) : null;
            if (root.dataset.startImport === undefined && component && !root.cancelled) {
                await component.$refresh();
            }
            if (root.dataset.startImport !== undefined && root.dataset.show && !root.cancelled) {
                status.textContent = root.dataset.opening;
                window.location.assign(root.dataset.show);
            }
        } catch (error) {
            status.textContent = root.cancelled ? root.dataset.cancelled : (error.message || root.dataset.startFailed);
        } finally {
            root.uploading = false;
            updateControls(root);
        }
    });
})();
