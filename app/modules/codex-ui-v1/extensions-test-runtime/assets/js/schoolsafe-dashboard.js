(() => {
    const root = document.querySelector('.ss-connected-dashboard');
    if (!root) return;
    const tabs = Array.from(root.querySelectorAll('[data-dashboard-tab]'));
    const activate = tab => {
        tabs.forEach(item => {
            const active = item === tab;
            item.setAttribute('aria-selected', String(active));
            item.tabIndex = active ? 0 : -1;
            const panel = root.querySelector('#' + item.getAttribute('aria-controls'));
            if (panel) panel.hidden = !active;
        });
    };
    tabs.forEach((tab, index) => {
        tab.addEventListener('click', () => activate(tab));
        tab.addEventListener('keydown', event => {
            let next;
            if (event.key === 'ArrowRight') next = (index + 1) % tabs.length;
            if (event.key === 'ArrowLeft') next = (index + tabs.length - 1) % tabs.length;
            if (event.key === 'Home') next = 0;
            if (event.key === 'End') next = tabs.length - 1;
            if (next === undefined) return;
            event.preventDefault(); activate(tabs[next]); tabs[next].focus();
        });
    });
})();
