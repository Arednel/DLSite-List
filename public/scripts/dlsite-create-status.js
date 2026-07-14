(function () {
    'use strict';

    const form = document.querySelector('[data-dlsite-fetch-form]');
    const status = document.querySelector('[data-dlsite-fetch-status]');

    if (!form || !status) {
        return;
    }

    form.addEventListener('submit', function () {
        status.hidden = false;
    });

    window.addEventListener('pageshow', function () {
        status.hidden = true;
    });
})();
