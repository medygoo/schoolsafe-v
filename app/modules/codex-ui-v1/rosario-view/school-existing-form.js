(() => {
    'use strict';
    const frame = document.querySelector('.native-school-frame');
    if (!frame) return;
    const root = new URL('.', location.href);
    const fields = {
        'school-name': 'TITLE', 'short-name': 'SHORT_NAME', principal: 'PRINCIPAL',
        address: 'ADDRESS', city: 'CITY', state: 'STATE', zipcode: 'ZIPCODE',
        phone: 'PHONE', website: 'WWW_ADDRESS', number: 'SCHOOL_NUMBER',
        scale: 'REPORTING_GP_SCALE'
    };
    const templates = new Map();
    const observed = new WeakSet();
    const pending = new WeakSet();
    async function present() {
        try {
            const url = new URL(frame.contentWindow.location.href);
            const module = url.searchParams.get('modname');
            if (url.origin !== location.origin || url.pathname !== '/Modules.php' ||
                !['School_Setup/Schools.php', 'School_Setup/MarkingPeriods.php'].includes(module) ||
                url.searchParams.has('modfunc')) return;
            const doc = frame.contentDocument;
            if (!observed.has(doc) && doc.getElementById('body')) {
                observed.add(doc);
                const observer = new MutationObserver(() => present());
                observer.observe(doc.getElementById('body'), {childList: true, subtree: true});
            }
            const form = [...doc.forms].find(item => {
                const action = new URL(item.action);
                return action.origin === location.origin && action.pathname === '/Modules.php' &&
                    action.searchParams.get('modname') === module && item.method === 'post' &&
                    (module.endsWith('/MarkingPeriods.php') || action.searchParams.get('modfunc') === 'update');
            });
            if (!form || form.dataset.schoolSafeLayout || pending.has(form)) return;
            // Keep the actual form, controls, templates and event listeners owned by Rosario.
            const marking = module.endsWith('/MarkingPeriods.php');
            const periodId = new URL(form.action).searchParams.get('marking_period_id');
            if (marking && !/^(new|\d+)$/.test(periodId || '')) return;
            const bindings = marking ? {
                'period-title': 'TITLE', 'period-short': 'SHORT_NAME', 'period-order': 'SORT_ORDER',
                'period-start': 'START_DATE', 'period-end': 'END_DATE', graded: 'DOES_GRADES',
                comments: 'DOES_COMMENTS', 'posting-start': 'POST_START_DATE', 'posting-end': 'POST_END_DATE'
            } : fields;
            const prefix = marking ? 'tables' + periodId : 'values';
            const cells = Object.entries(bindings).map(([id, column]) => {
                const control = form.querySelector('#' + prefix + column + ', #html' + prefix + column +
                    ', [name$="[' + column + ']"]');
                return [id, control?.closest('td')];
            });
            if (!marking && cells.some(([, cell]) => !cell)) return;
            if (marking && cells.slice(0, 2).some(([, cell]) => !cell)) return;
            const filename = marking ? 'marking-periods.html' : 'school-form.html';
            if (!templates.has(filename)) templates.set(filename,
                fetch(new URL(filename, root), {cache: 'no-store'}).then(response => {
                    if (!response.ok) throw Error('Template unavailable'); return response.text();
                }).catch(error => { templates.delete(filename); throw error; }));
            pending.add(form);
            let html;
            try { html = await templates.get(filename); }
            finally { pending.delete(form); }
            const source = new DOMParser().parseFromString(html, 'text/html');
            if (frame.contentDocument !== doc || !form.isConnected) return;
            const layout = doc.createElement('section');
            layout.className = 'ss-existing-school-form';
            for (const fieldset of source.querySelectorAll('main > fieldset')) {
                layout.append(doc.importNode(fieldset, true));
            }
            if (cells.some(([id]) => !layout.querySelector('#' + id)?.closest('label'))) return;
            if (marking) layout.querySelector('#year')?.closest('fieldset')?.remove();
            for (const [id, cell] of cells) {
                const slot = layout.querySelector('#' + id)?.closest('label');
                if (!cell) { slot.remove(); continue; }
                const group = doc.createElement('div');
                group.className = slot.className;
                group.append(...cell.childNodes);
                slot.replaceWith(group);
            }
            layout.querySelector('#logo')?.closest('label')?.remove();
            if (!doc.getElementById('ss-existing-form-style')) {
                const sheet = doc.createElement('link');
                sheet.id = 'ss-existing-form-style'; sheet.rel = 'stylesheet';
                sheet.href = new URL('school-existing-form.css?v=4', root).href;
                doc.head.append(sheet);
            }
            form.prepend(layout);
            form.dataset.schoolSafeLayout = filename;
        } catch (_) {
            // Fail open to the complete native form, never to an empty or fabricated form.
        }
    }
    frame.addEventListener('load', present);
    present();
})();
