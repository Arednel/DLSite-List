document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-list-menu-shell]').forEach((shell) => {
        const menu = shell.querySelector('[data-list-menu]');
        const toggle = shell.querySelector('[data-list-menu-toggle]');
        const overlay = shell.querySelector('[data-list-menu-overlay]');

        if (!menu || !toggle || !overlay) {
            return;
        }

        const desktopQuery = window.matchMedia('(min-width: 993px)');

        const closeMenu = () => {
            menu.classList.remove('is-open');
            menu.setAttribute('aria-hidden', 'true');
            overlay.classList.remove('is-visible');
            toggle.setAttribute('aria-expanded', 'false');
            document.body.classList.remove('list-menu-mobile-open');
        };

        const openMenu = () => {
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
