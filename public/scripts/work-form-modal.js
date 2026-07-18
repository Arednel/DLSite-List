(function () {
    'use strict';

    var activeTrigger = null;
    var pendingRedirectStorageKey = 'work-form-modal.pending-redirect-url';

    function rememberPendingRedirect(redirectUrl) {
        try {
            window.sessionStorage.setItem(pendingRedirectStorageKey, redirectUrl.href);
        } catch (error) {
            return;
        }
    }

    function takePendingRedirect() {
        try {
            var pendingRedirectUrl = window.sessionStorage.getItem(pendingRedirectStorageKey);

            window.sessionStorage.removeItem(pendingRedirectStorageKey);

            return pendingRedirectUrl;
        } catch (error) {
            return null;
        }
    }

    function scrollToPendingRedirect() {
        var pendingRedirectUrl = takePendingRedirect();

        if (!pendingRedirectUrl || pendingRedirectUrl !== window.location.href) {
            return;
        }

        var pendingRedirect;
        var targetId;

        try {
            pendingRedirect = new URL(pendingRedirectUrl);
            targetId = decodeURIComponent(pendingRedirect.hash.slice(1));
        } catch (error) {
            return;
        }

        if (!targetId) {
            return;
        }

        var target = document.getElementById(targetId);

        if (target) {
            // Let the browser finish its reload scroll restoration before moving to the new row.
            window.requestAnimationFrame(function () {
                target.scrollIntoView();
            });
        }
    }

    function modalHost() {
        return document.querySelector('[data-work-form-modal]');
    }

    function closeModal(dialog) {
        if (dialog && dialog.open) {
            dialog.close();
        }
    }

    function isUnmodifiedPrimaryClick(event) {
        return event.button === 0
            && !event.altKey
            && !event.ctrlKey
            && !event.metaKey
            && !event.shiftKey;
    }

    function canOpenInModal(dialog, link, event) {
        if (!dialog || dialog.dataset.enabled !== 'true' || typeof dialog.showModal !== 'function') {
            return false;
        }

        if (!isUnmodifiedPrimaryClick(event) || event.defaultPrevented || link.hasAttribute('download')) {
            return false;
        }

        var target = link.getAttribute('target');

        return target === null || target === '' || target === '_self';
    }

    document.addEventListener('click', function (event) {
        if (!(event.target instanceof Element)) {
            return;
        }

        var closeButton = event.target.closest('[data-work-form-modal-close]');
        var dialog = modalHost();

        if (closeButton) {
            closeModal(dialog);
            return;
        }

        var link = event.target.closest('[data-work-form-modal-link]');

        if (!link || !canOpenInModal(dialog, link, event)) {
            return;
        }

        var url = new URL(link.href, window.location.href);

        if (url.origin !== window.location.origin) {
            return;
        }

        var frame = dialog.querySelector('[data-work-form-modal-frame]');
        var title = dialog.querySelector('[data-work-form-modal-title]');
        var modalTitle = link.dataset.workFormModalTitle
            || link.textContent.trim()
            || dialog.dataset.workFormDefaultTitle;

        if (!frame) {
            return;
        }

        event.preventDefault();
        url.searchParams.set('modal', '1');
        frame.title = modalTitle;
        frame.src = url.href;
        dialog.setAttribute('aria-label', modalTitle);

        if (title) {
            title.textContent = modalTitle;
        }

        activeTrigger = link;
        dialog.showModal();
    });

    document.addEventListener('work-form-modal-settings-updated', function (event) {
        var dialog = modalHost();

        if (!dialog || !event.detail) {
            return;
        }

        dialog.dataset.enabled = event.detail.enabled ? 'true' : 'false';
        dialog.dataset.completionAction = event.detail.completionAction || 'redirect';
    });

    document.addEventListener('click', function (event) {
        var dialog = modalHost();

        if (dialog && event.target === dialog) {
            closeModal(dialog);
        }
    });

    document.addEventListener('close', function (event) {
        var dialog = event.target;

        if (!(dialog instanceof Element) || !dialog.matches('[data-work-form-modal]')) {
            return;
        }

        var frame = dialog.querySelector('[data-work-form-modal-frame]');

        if (frame) {
            frame.removeAttribute('src');
        }

        if (activeTrigger && document.contains(activeTrigger)) {
            activeTrigger.focus();
        }

        activeTrigger = null;
    }, true);

    window.addEventListener('pageshow', scrollToPendingRedirect);

    window.addEventListener('message', function (event) {
        if (event.origin !== window.location.origin || !event.data) {
            return;
        }

        var dialog = modalHost();
        var frame = dialog ? dialog.querySelector('[data-work-form-modal-frame]') : null;

        if (!dialog || !frame || event.source !== frame.contentWindow) {
            return;
        }

        if (event.data.type === 'work-form-cancelled') {
            closeModal(dialog);
            return;
        }

        if (event.data.type !== 'work-form-completed') {
            return;
        }

        var action = dialog.dataset.completionAction || 'redirect';

        if (action === 'close') {
            closeModal(dialog);
            return;
        }

        if (action === 'refresh') {
            closeModal(dialog);
            window.location.reload();
            return;
        }

        var redirectUrl = new URL(event.data.redirectUrl, window.location.href);

        if (redirectUrl.origin !== window.location.origin) {
            closeModal(dialog);
            return;
        }

        if (redirectUrl.pathname === window.location.pathname
            && redirectUrl.search === window.location.search) {
            rememberPendingRedirect(redirectUrl);
            window.location.href = redirectUrl.href;
            window.location.reload();
            return;
        }

        window.location.assign(redirectUrl.href);
    });
})();
