(() => {
    'use strict';
    const frame = document.querySelector('.native-school-frame');
    if (!frame) return;
    const supported = new Set(['Schools', 'Calendar', 'MarkingPeriods', 'Periods',
        'GradeLevels', 'PortalNotes', 'PortalPolls', 'SchoolFields', 'Configuration']);
    const stylesheet = new URL('school-native-presentation.css?v=3', window.location.href).href;
    function applyPresentation() {
        try {
            const url = new URL(frame.contentWindow.location.href);
            const module = url.searchParams.get('modname') || '';
            if (url.origin !== window.location.origin || url.pathname !== '/Modules.php' ||
                !/^School_Setup\/[^/]+\.php$/.test(module) ||
                !supported.has(module.slice('School_Setup/'.length, -4))) return;
            const doc = frame.contentDocument;
            if (!doc || !doc.head || !doc.body) return;
            doc.documentElement.classList.add('ss-native-school');
            if (!doc.getElementById('ss-native-presentation')) {
                const link = doc.createElement('link');
                link.id = 'ss-native-presentation';
                link.rel = 'stylesheet';
                link.href = stylesheet;
                doc.head.appendChild(link);
            }
        } catch (_) {
            // A login redirect or another origin must retain its native presentation.
        }
    }
    frame.addEventListener('load', applyPresentation);
    applyPresentation();
})();
