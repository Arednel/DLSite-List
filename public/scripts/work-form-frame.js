(function () {
    'use strict';

    document.addEventListener('click', function (event) {
        if (!(event.target instanceof Element) || !event.target.closest('[data-work-form-modal-cancel]')) {
            return;
        }

        window.parent.postMessage({ type: 'work-form-cancelled' }, window.location.origin);
    });
})();
