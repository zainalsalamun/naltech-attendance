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

(() => {
    const selects = document.querySelectorAll('select[data-searchable-select="true"]');
    if (!selects.length) return;

    selects.forEach((select) => {
        if (select.dataset.searchableInitialized === '1') return;
        select.dataset.searchableInitialized = '1';

        const placeholder = select.dataset.searchPlaceholder || 'Cari...';
        const wrapper = document.createElement('div');
        wrapper.className = 'searchable-select';
        select.parentNode.insertBefore(wrapper, select);
        wrapper.appendChild(select);
        select.classList.add('searchable-select-native');
        select.tabIndex = -1;

        const trigger = document.createElement('button');
        trigger.type = 'button';
        trigger.className = 'searchable-select-trigger';
        trigger.setAttribute('aria-haspopup', 'listbox');
        trigger.setAttribute('aria-expanded', 'false');

        const labelSpan = document.createElement('span');
        labelSpan.className = 'searchable-select-label';
        const chevron = document.createElement('span');
        chevron.className = 'searchable-select-chevron';
        chevron.setAttribute('aria-hidden', 'true');
        chevron.textContent = '▾';
        trigger.append(labelSpan, chevron);

        const dropdown = document.createElement('div');
        dropdown.className = 'searchable-select-dropdown';
        dropdown.hidden = true;

        const searchInput = document.createElement('input');
        searchInput.type = 'search';
        searchInput.className = 'searchable-select-search';
        searchInput.placeholder = placeholder;
        searchInput.setAttribute('aria-label', placeholder);
        searchInput.autocomplete = 'off';

        const list = document.createElement('ul');
        list.className = 'searchable-select-list';
        list.setAttribute('role', 'listbox');

        const emptyMsg = document.createElement('div');
        emptyMsg.className = 'searchable-select-empty';
        emptyMsg.textContent = 'Tidak ada data yang cocok.';
        emptyMsg.hidden = true;

        dropdown.append(searchInput, list, emptyMsg);
        wrapper.append(trigger, dropdown);

        let activeIndex = -1;
        let visibleOptions = [];

        const syncLabel = () => {
            const selected = select.options[select.selectedIndex];
            labelSpan.textContent = selected ? selected.textContent.trim() : placeholder;
        };

        const renderOptions = (query = '') => {
            const q = query.trim().toLowerCase();
            list.innerHTML = '';
            visibleOptions = [];
            activeIndex = -1;

            Array.from(select.options).forEach((opt) => {
                const text = opt.textContent.trim();
                if (q && !text.toLowerCase().includes(q)) return;

                const li = document.createElement('li');
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'searchable-select-option';
                btn.setAttribute('role', 'option');
                btn.setAttribute('aria-selected', String(opt.value === select.value));
                btn.textContent = text;
                btn.dataset.value = opt.value;

                btn.addEventListener('click', () => {
                    select.value = opt.value;
                    select.dispatchEvent(new Event('change', { bubbles: true }));
                    syncLabel();
                    close();
                    trigger.focus();
                });

                li.appendChild(btn);
                list.appendChild(li);
                visibleOptions.push(btn);
            });

            emptyMsg.hidden = visibleOptions.length > 0;
        };

        const open = () => {
            dropdown.hidden = false;
            trigger.setAttribute('aria-expanded', 'true');
            searchInput.value = '';
            renderOptions('');
            searchInput.focus();
        };

        const close = () => {
            dropdown.hidden = true;
            trigger.setAttribute('aria-expanded', 'false');
            activeIndex = -1;
        };

        trigger.addEventListener('click', () => {
            if (dropdown.hidden) open();
            else close();
        });

        searchInput.addEventListener('input', () => {
            renderOptions(searchInput.value);
        });

        searchInput.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                e.preventDefault();
                close();
                trigger.focus();
            } else if (e.key === 'ArrowDown') {
                e.preventDefault();
                if (!visibleOptions.length) return;
                activeIndex = Math.min(activeIndex + 1, visibleOptions.length - 1);
                visibleOptions.forEach((b, i) => b.classList.toggle('is-active', i === activeIndex));
                visibleOptions[activeIndex]?.scrollIntoView({ block: 'nearest' });
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                if (!visibleOptions.length) return;
                activeIndex = Math.max(activeIndex - 1, 0);
                visibleOptions.forEach((b, i) => b.classList.toggle('is-active', i === activeIndex));
                visibleOptions[activeIndex]?.scrollIntoView({ block: 'nearest' });
            } else if (e.key === 'Enter' && activeIndex >= 0 && visibleOptions[activeIndex]) {
                e.preventDefault();
                visibleOptions[activeIndex].click();
            }
        });

        document.addEventListener('click', (e) => {
            if (!wrapper.contains(e.target) && !dropdown.hidden) {
                close();
            }
        });

        select.addEventListener('change', syncLabel);
        syncLabel();
    });
})();
