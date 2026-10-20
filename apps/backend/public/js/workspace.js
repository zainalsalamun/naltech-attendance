(() => {
    const root = document.documentElement;
    const choices = document.querySelectorAll('[data-theme-choice]');
    const update = theme => {
        root.dataset.theme = theme;
        choices.forEach(button => button.setAttribute('aria-pressed', String(button.dataset.themeChoice === theme)));
    };
    update(root.dataset.theme);
    choices.forEach(button => button.addEventListener('click', () => {
        update(button.dataset.themeChoice);
        try { localStorage.setItem('naltech.theme', root.dataset.theme); } catch (_) {}
    }));
    window.addEventListener('storage', event => {
        if (event.key === 'naltech.theme') update(event.newValue === 'dark' ? 'dark' : 'light');
    });
    document.querySelectorAll('[data-attendance-dialog]').forEach(button => {
        button.addEventListener('click', () => document.getElementById(button.dataset.attendanceDialog).showModal());
    });
    document.querySelectorAll('.account-menu').forEach(account => {
        account.addEventListener('keydown', event => {
            if (event.key !== 'Escape' || !account.open) return;
            event.stopPropagation();
            account.open = false;
            account.querySelector('summary').focus();
        });
    });
    const menu = document.querySelector('.menu-toggle');
    const sidebar = document.querySelector('.sidebar');
    const header = document.querySelector('.workspace-header');
    if (!menu || !sidebar || !header) return;
    const syncHeaderHeight = () => root.style.setProperty('--workspace-header-height', `${header.getBoundingClientRect().height}px`);
    syncHeaderHeight();
    new ResizeObserver(syncHeaderHeight).observe(header);
    const closeMenu = () => {
        const restoreFocus = sidebar.contains(document.activeElement);
        menu.setAttribute('aria-expanded', 'false');
        menu.setAttribute('aria-label', 'Buka navigasi');
        sidebar.classList.remove('is-open');
        if (restoreFocus) menu.focus();
    };
    menu.addEventListener('click', () => {
        const open = menu.getAttribute('aria-expanded') !== 'true';
        menu.setAttribute('aria-expanded', String(open));
        menu.setAttribute('aria-label', open ? 'Tutup navigasi' : 'Buka navigasi');
        sidebar.classList.toggle('is-open', open);
    });
    document.addEventListener('keydown', event => { if (event.key === 'Escape') { closeMenu(); document.querySelectorAll('details[open]').forEach(d => d.open = false); } });
    document.addEventListener('click', event => {
        if (!sidebar.contains(event.target) && !menu.contains(event.target)) closeMenu();
        document.querySelectorAll('details[open]').forEach(d => { if (!d.contains(event.target)) d.open = false; });
    });
})();
