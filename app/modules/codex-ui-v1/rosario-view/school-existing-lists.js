(() => {
    'use strict';
    const frame = document.querySelector('.native-school-frame');
    if (!frame) return;
    const root = new URL('.', location.href);
    const supported = new Set(['GradeLevels', 'Periods', 'MarkingPeriods', 'Calendar',
        'PortalNotes', 'PortalPolls', 'SchoolFields', 'Configuration']);
    const observed = new WeakSet();
    function present() {
        try {
            const url = new URL(frame.contentWindow.location.href);
            const module = (url.searchParams.get('modname') || '').match(/^School_Setup\/([A-Za-z]+)\.php$/)?.[1];
            if (url.origin !== location.origin || url.pathname !== '/Modules.php' || !supported.has(module)) return;
            const doc = frame.contentDocument;
            const body = doc.getElementById('body');
            if (!body) return;
            if (!observed.has(doc)) {
                observed.add(doc);
                const observer = new MutationObserver(present);
                observer.observe(body, {childList: true, subtree: true});
            }
            if (!doc.getElementById('ss-existing-form-style')) {
                const sheet = doc.createElement('link');
                sheet.id = 'ss-existing-form-style'; sheet.rel = 'stylesheet';
                sheet.href = new URL('school-existing-form.css?v=4', root).href;
                doc.head.append(sheet);
            }
            for (const table of body.querySelectorAll('table.list')) {
                if (table.closest('.ss-existing-list')) continue;
                // Move the real table, not a copy: native inputs, links and listeners remain attached.
                const section = doc.createElement('fieldset');
                section.className = 'ss-existing-list';
                const legend = doc.createElement('legend');
                legend.textContent = table.querySelector('caption')?.textContent.trim() ||
                    body.querySelector('h2')?.textContent.trim() || 'Liste';
                const scroll = doc.createElement('div');
                scroll.className = 'ss-existing-list-scroll';
                scroll.tabIndex = 0; scroll.setAttribute('role', 'region');
                scroll.setAttribute('aria-label', legend.textContent);
                table.before(section); section.append(legend, scroll); scroll.append(table);
            }
        } catch (_) {
            // Keep login, other branches and sensitive native operations unmodified.
        }
    }
    frame.addEventListener('load', present);
    present();
})();
