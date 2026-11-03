(() => {
    const root = document.documentElement;

    // Theme switch
    const choices = document.querySelectorAll('[data-theme-choice]');
    const updateTheme = theme => {
        root.dataset.theme = theme;
        choices.forEach(button => button.setAttribute('aria-pressed', String(button.dataset.themeChoice === theme)));
    };
    updateTheme(root.dataset.theme);
    choices.forEach(button => button.addEventListener('click', () => {
        updateTheme(button.dataset.themeChoice);
        try { localStorage.setItem('naltech.theme', root.dataset.theme); } catch (_) {}
    }));
    window.addEventListener('storage', event => {
        if (event.key === 'naltech.theme') updateTheme(event.newValue === 'dark' ? 'dark' : 'light');
    });

    // Attendance dialog modal trigger
    document.querySelectorAll('[data-attendance-dialog]').forEach(button => {
        button.addEventListener('click', () => {
            const dialog = document.getElementById(button.dataset.attendanceDialog);
            if (dialog) dialog.showModal();
        });
    });

    // Header account details menu
    document.querySelectorAll('.account-menu').forEach(account => {
        account.addEventListener('keydown', event => {
            if (event.key !== 'Escape' || !account.open) return;
            event.stopPropagation();
            account.open = false;
            account.querySelector('summary')?.focus();
        });
    });

    // Navigation, Sidebar Minimize, and Mobile Drawer
    const layout = document.querySelector('.workspace-layout');
    const menu = document.querySelector('.menu-toggle');
    const sidebar = document.querySelector('.sidebar');
    const header = document.querySelector('.workspace-header');
    const backdrop = document.getElementById('sidebar-backdrop');
    const toggleBtn = document.getElementById('sidebar-toggle-btn') || document.querySelector('.sidebar-toggle-btn');

    if (!sidebar) return;

    const storageKey = sidebar.dataset.storagePrefix || 'naltech.sidebar.groups';
    const desktopMinKey = storageKey.replace('groups', 'minimized');

    if (header) {
        const syncHeaderHeight = () => root.style.setProperty('--workspace-header-height', `${header.getBoundingClientRect().height}px`);
        syncHeaderHeight();
        new ResizeObserver(syncHeaderHeight).observe(header);
    }

    const updateDesktopMinimize = isMinimized => {
        if (!layout) return;
        layout.classList.toggle('sidebar-minimized', isMinimized);
        if (menu) {
            menu.setAttribute('aria-expanded', String(!isMinimized));
            menu.setAttribute('aria-label', isMinimized ? 'Buka navigasi' : 'Minimize sidebar');
            menu.setAttribute('title', isMinimized ? 'Buka navigasi' : 'Minimize sidebar');
        }
        if (toggleBtn) {
            toggleBtn.setAttribute('aria-expanded', String(!isMinimized));
            toggleBtn.setAttribute('aria-label', isMinimized ? 'Buka navigasi' : 'Minimize sidebar');
            toggleBtn.setAttribute('title', isMinimized ? 'Buka navigasi' : 'Minimize sidebar');
        }
        try { localStorage.setItem(desktopMinKey, String(isMinimized)); } catch (_) {}
    };

    // Inisialisasi preferensi desktop minimize
    if (layout && window.innerWidth > 800) {
        try {
            const savedMin = localStorage.getItem(desktopMinKey) === 'true';
            if (savedMin) updateDesktopMinimize(true);
        } catch (_) {}
    }

    const closeMenu = () => {
        if (!sidebar) return;
        const restoreFocus = sidebar.contains(document.activeElement);
        if (menu) {
            menu.setAttribute('aria-expanded', 'false');
            menu.setAttribute('aria-label', 'Buka navigasi');
        }
        sidebar.classList.remove('is-open');
        if (backdrop) backdrop.classList.remove('is-visible');
        if (restoreFocus && menu) menu.focus();
    };

    const openMenu = () => {
        if (!sidebar) return;
        if (menu) {
            menu.setAttribute('aria-expanded', 'true');
            menu.setAttribute('aria-label', 'Tutup navigasi');
        }
        sidebar.classList.add('is-open');
        if (backdrop) backdrop.classList.add('is-visible');
    };

    if (menu) {
        menu.addEventListener('click', () => {
            if (window.innerWidth <= 800) {
                const isOpen = sidebar.classList.contains('is-open');
                if (isOpen) closeMenu(); else openMenu();
            } else {
                const willMinimize = !layout?.classList.contains('sidebar-minimized');
                updateDesktopMinimize(willMinimize);
            }
        });
    }

    if (toggleBtn) {
        toggleBtn.addEventListener('click', () => {
            if (window.innerWidth <= 800) {
                closeMenu();
            } else {
                updateDesktopMinimize(true);
                menu?.focus();
            }
        });
    }

    if (backdrop) {
        backdrop.addEventListener('click', closeMenu);
    }

    // Navigasi ke halaman menutup drawer mobile
    sidebar.querySelectorAll('a').forEach(link => {
        link.addEventListener('click', () => {
            if (sidebar.classList.contains('is-open') || window.innerWidth <= 800) {
                closeMenu();
            }
        });
    });

    document.addEventListener('keydown', event => {
        if (event.key === 'Escape') {
            closeMenu();
            document.querySelectorAll('details[open]').forEach(d => d.open = false);
        }
    });

    document.addEventListener('click', event => {
        if (!sidebar.contains(event.target) && (!menu || !menu.contains(event.target)) && (!backdrop || !backdrop.contains(event.target))) {
            if (window.innerWidth <= 800) closeMenu();
        }
        document.querySelectorAll('details[open]:not([data-persistent-details])').forEach(d => {
            if (!d.contains(event.target)) d.open = false;
        });
    });

    // Sidebar Accordion Groups with Isolation & Persistence
    let groupPrefs = {};
    try {
        const stored = localStorage.getItem(storageKey);
        if (stored) {
            const parsed = JSON.parse(stored);
            if (parsed && typeof parsed === 'object' && !Array.isArray(parsed)) {
                groupPrefs = parsed;
            }
        }
    } catch (_) {}

    const groups = sidebar.querySelectorAll('.sidebar-group');
    groups.forEach(group => {
        const button = group.querySelector('.sidebar-group-toggle');
        const submenu = group.querySelector('.sidebar-submenu');
        if (!button || !submenu) return;

        const groupId = button.getAttribute('aria-controls');
        const hasActiveChild = button.dataset.hasActiveChild === 'true';

        // Aturan: Grup yang berisi halaman aktif terbuka otomatis
        if (hasActiveChild) {
            button.setAttribute('aria-expanded', 'true');
            submenu.removeAttribute('hidden');
            group.classList.add('is-open');
        } else if (groupPrefs && typeof groupPrefs[groupId] === 'boolean') {
            const isOpen = groupPrefs[groupId];
            button.setAttribute('aria-expanded', String(isOpen));
            if (isOpen) {
                submenu.removeAttribute('hidden');
                group.classList.add('is-open');
            } else {
                submenu.setAttribute('hidden', '');
                group.classList.remove('is-open');
            }
        }

        button.addEventListener('click', () => {
            const isCurrentlyOpen = button.getAttribute('aria-expanded') === 'true' && group.classList.contains('is-open');
            const willOpen = !isCurrentlyOpen;

            button.setAttribute('aria-expanded', String(willOpen));
            if (willOpen) {
                submenu.removeAttribute('hidden');
                group.classList.add('is-open');
            } else {
                submenu.setAttribute('hidden', '');
                group.classList.remove('is-open');
            }

            groupPrefs[groupId] = willOpen;
            try {
                localStorage.setItem(storageKey, JSON.stringify(groupPrefs));
            } catch (_) {}
        });
    });
})();
