document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-list-menu-shell]').forEach((shell) => {
        const menu = shell.querySelector('[data-list-menu]');
        const toggle = shell.querySelector('[data-list-menu-toggle]');
        const overlay = shell.querySelector('[data-list-menu-overlay]');

        if (!menu || !toggle || !overlay) {
            return;
        }

        const desktopQuery = window.matchMedia('(min-width: 993px)');
        const closeTransitionFallbackMs = 340;
        let closeTimer = null;

        const finishClosing = () => {
            window.clearTimeout(closeTimer);
            closeTimer = null;
            menu.classList.remove('is-closing');
            overlay.classList.remove('is-visible');
            document.body.classList.remove('list-menu-mobile-open');
        };

        const closeMenu = () => {
            if (!menu.classList.contains('is-open') && !menu.classList.contains('is-closing')) {
                return;
            }

            window.clearTimeout(closeTimer);
            menu.classList.add('is-closing');
            menu.classList.remove('is-open');
            menu.setAttribute('aria-hidden', 'true');
            toggle.setAttribute('aria-expanded', 'false');

            // Keep the body locked until the slide-out finishes so mobile browsers do not resize mid-animation.
            closeTimer = window.setTimeout(finishClosing, closeTransitionFallbackMs);
        };

        const openMenu = () => {
            window.clearTimeout(closeTimer);
            closeTimer = null;
            menu.classList.remove('is-closing');
            menu.classList.add('is-open');
            menu.setAttribute('aria-hidden', 'false');
            overlay.classList.add('is-visible');
            toggle.setAttribute('aria-expanded', 'true');
            document.body.classList.add('list-menu-mobile-open');
        };

        toggle.addEventListener('click', () => {
            if (menu.classList.contains('is-open')) {
                closeMenu();
                return;
            }

            openMenu();
        });

        overlay.addEventListener('click', closeMenu);

        menu.addEventListener('transitionend', (event) => {
            if (event.target === menu && event.propertyName === 'transform' && menu.classList.contains('is-closing')) {
                finishClosing();
            }
        });

        menu.querySelectorAll('a').forEach((link) => {
            link.addEventListener('click', closeMenu);
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closeMenu();
            }
        });

        desktopQuery.addEventListener('change', (event) => {
            if (event.matches) {
                closeMenu();
            }
        });
    });
});
