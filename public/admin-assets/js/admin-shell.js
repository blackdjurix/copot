(() => {
    'use strict';

    document.documentElement.classList.add('admin-shell-js');

    const sidebar = document.querySelector('[data-admin-sidebar]');
    const mainShell = document.querySelector('[data-admin-main-shell]');
    const openButton = document.querySelector('[data-admin-nav-open]');
    const closeButton = document.querySelector('[data-admin-nav-close]');
    const overlay = document.querySelector('[data-admin-nav-overlay]');
    const popovers = Array.from(document.querySelectorAll('[data-admin-popover]'));

    if (!sidebar || !mainShell || !openButton || !closeButton || !overlay) {
        return;
    }

    const mobileQuery = window.matchMedia('(max-width: 900px)');
    const focusableSelector = [
        'a[href]',
        'button:not([disabled])',
        'input:not([disabled])',
        'select:not([disabled])',
        'textarea:not([disabled])',
        '[tabindex]:not([tabindex="-1"])',
    ].join(',');

    let navigationOpen = false;
    let lockedScrollY = 0;
    let previousMainAriaHidden = null;

    const visibleFocusableItems = () => Array.from(sidebar.querySelectorAll(focusableSelector))
        .filter((element) => !element.hasAttribute('hidden') && element.getClientRects().length > 0);

    const closePopovers = (except = null) => {
        popovers.forEach((details) => {
            if (details !== except) {
                details.open = false;
            }
        });
    };

    const setMainInactive = (inactive) => {
        if ('inert' in mainShell) {
            mainShell.inert = inactive;
            return;
        }

        if (inactive) {
            previousMainAriaHidden = mainShell.getAttribute('aria-hidden');
            mainShell.setAttribute('aria-hidden', 'true');
        } else if (previousMainAriaHidden === null) {
            mainShell.removeAttribute('aria-hidden');
        } else {
            mainShell.setAttribute('aria-hidden', previousMainAriaHidden);
        }
    };

    const lockDocumentScroll = () => {
        lockedScrollY = window.scrollY;
        document.body.style.position = 'fixed';
        document.body.style.top = `-${lockedScrollY}px`;
        document.body.style.width = '100%';
        document.documentElement.classList.add('admin-nav-open');
    };

    const unlockDocumentScroll = () => {
        document.body.style.position = '';
        document.body.style.top = '';
        document.body.style.width = '';
        document.documentElement.classList.remove('admin-nav-open');
        window.scrollTo(0, lockedScrollY);
    };

    const openNavigation = () => {
        if (!mobileQuery.matches || navigationOpen) {
            return;
        }

        navigationOpen = true;
        closePopovers();
        sidebar.classList.add('is-open');
        sidebar.setAttribute('role', 'dialog');
        sidebar.setAttribute('aria-modal', 'true');
        openButton.setAttribute('aria-expanded', 'true');
        overlay.hidden = false;
        setMainInactive(true);
        lockDocumentScroll();

        window.requestAnimationFrame(() => closeButton.focus());
    };

    const closeNavigation = (restoreFocus = true) => {
        if (!navigationOpen) {
            overlay.hidden = true;
            openButton.setAttribute('aria-expanded', 'false');
            return;
        }

        navigationOpen = false;
        sidebar.classList.remove('is-open');
        sidebar.removeAttribute('role');
        sidebar.removeAttribute('aria-modal');
        openButton.setAttribute('aria-expanded', 'false');
        overlay.hidden = true;
        setMainInactive(false);
        unlockDocumentScroll();

        if (restoreFocus) {
            window.requestAnimationFrame(() => openButton.focus());
        }
    };

    const trapNavigationFocus = (event) => {
        if (!navigationOpen || event.key !== 'Tab') {
            return;
        }

        const items = visibleFocusableItems();

        if (items.length === 0) {
            event.preventDefault();
            sidebar.focus();
            return;
        }

        const first = items[0];
        const last = items[items.length - 1];

        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    };

    openButton.addEventListener('click', openNavigation);
    closeButton.addEventListener('click', () => closeNavigation(true));
    overlay.addEventListener('click', () => closeNavigation(true));

    sidebar.addEventListener('click', (event) => {
        const link = event.target.closest('a[href]');

        if (link && mobileQuery.matches) {
            closeNavigation(false);
        }
    });

    popovers.forEach((details) => {
        details.addEventListener('toggle', () => {
            if (details.open) {
                closePopovers(details);
            }
        });
    });

    document.addEventListener('click', (event) => {
        if (!event.target.closest('[data-admin-popover]')) {
            closePopovers();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            const hadOpenPopover = popovers.some((details) => details.open);
            closePopovers();

            if (navigationOpen) {
                event.preventDefault();
                closeNavigation(true);
            } else if (hadOpenPopover) {
                event.preventDefault();
            }

            return;
        }

        trapNavigationFocus(event);
    });

    const handleViewportChange = (event) => {
        if (!event.matches) {
            closeNavigation(false);
        }
    };

    if (typeof mobileQuery.addEventListener === 'function') {
        mobileQuery.addEventListener('change', handleViewportChange);
    } else {
        mobileQuery.addListener(handleViewportChange);
    }

    closeNavigation(false);
})();
