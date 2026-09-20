document.addEventListener('DOMContentLoaded', () => {
    const wrapper = document.getElementById('wrapper');
    const sidebar = document.getElementById('sidebar-wrapper');
    const content = document.getElementById('page-content-wrapper');
    const toggle = document.getElementById('sidebarToggle');
    const close = document.getElementById('sidebarClose');
    const backdrop = document.getElementById('sidebarBackdrop');
    if (!wrapper || !sidebar || !toggle) return;
    const desktop = window.matchMedia('(min-width: 992px)');
    let open = desktop.matches;
    const apply = (restoreFocus = false) => {
        const toggled = desktop.matches ? !open : open;
        wrapper.classList.toggle('sb-sidenav-toggled', toggled);
        document.body.classList.toggle('sb-sidenav-toggled', toggled);
        toggle.setAttribute('aria-expanded', String(open));
        toggle.setAttribute('aria-label', open ? 'ปิดเมนูหลัก' : 'เปิดเมนูหลัก');
        sidebar.inert = !open;
        sidebar.setAttribute('aria-hidden', String(!open));
        content.inert = open && !desktop.matches;
        backdrop.hidden = !open || desktop.matches;
        if (restoreFocus) toggle.focus();
    };
    toggle.addEventListener('click', () => { open = !open; apply(); if (open && !desktop.matches) close.focus(); });
    const dismiss = () => { open = false; apply(true); };
    close.addEventListener('click', dismiss);
    backdrop.addEventListener('click', dismiss);
    sidebar.addEventListener('keydown', event => {
        if (event.key === 'Escape' && open) { event.preventDefault(); dismiss(); }
        if (event.key !== 'Tab' || desktop.matches || !open) return;
        const items = [...sidebar.querySelectorAll('a[href],button:not([disabled])')].filter(item => item.getClientRects().length);
        const first = items[0], last = items[items.length - 1];
        if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
        else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    });
    desktop.addEventListener('change', () => { const hadFocus = sidebar.contains(document.activeElement); open = desktop.matches; apply(!open && hadFocus); });
    apply();
});
