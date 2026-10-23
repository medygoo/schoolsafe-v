(function () {
    'use strict';
    function mount() {
        var root = document.getElementById('ss-photo-dashboard');
        if (!root || root.dataset.photoReady) return;
        if (window.schoolSafePhotoDashboard) window.schoolSafePhotoDashboard.destroy();
        root.dataset.photoReady = 'true';
        var events = new AbortController();
        var placements = [];
        var disposed = false;
        var observer;
        var themeObserver;
        function on(target, type, listener, capture) {
            target.addEventListener(type, listener, {signal: events.signal, capture: Boolean(capture)});
        }
        // Keep the server-rendered composition intact: no hidden parking area,
        // cloned cards or fixed/paginated viewport. Native chrome owns navigation.
        var original = document.querySelector('#body > .ss-dashboard-context');
        function move(node, slot) {
            if (!node || !slot) return;
            placements.push({node: node, parent: node.parentNode, next: node.nextSibling});
            slot.appendChild(node);
        }
        if (original) {
            move(original.querySelector('.ss-dashboard-search'), root.querySelector('[data-photo-search]'));
            ['school', 'syear'].forEach(function (key) {
                var slot = original.querySelector('[data-native-dashboard-context="' + key + '"]');
                if (slot) move(slot.parentElement, root.querySelector(key === 'school' ? '[data-photo-school]' : '[data-photo-year]'));
            });
        }
        var campus = root.querySelector('.ss-photo-campus');
        var theme = root.querySelector('.ss-photo-theme');
        function updateTheme() {
            var dark = document.documentElement.dataset.ssBackground === 'night';
            if (theme) {
                theme.setAttribute('aria-pressed', String(dark));
                theme.setAttribute('aria-label', dark ? 'Activer le mode clair' : 'Activer le mode sombre');
            }
            if (campus) {
                var source = dark ? campus.dataset.campusNight : campus.dataset.campusDay;
                if (source && campus.getAttribute('src') !== source) campus.src = source;
            }
            document.querySelectorAll('#ss-jaspe-dialog iframe').forEach(function (frame) {
                try { frame.contentDocument.documentElement.dataset.ssConversationTheme = document.documentElement.dataset.ssBackground; } catch (_) {}
            });
        }
        if (theme) on(theme, 'click', function () {
            var next = document.documentElement.dataset.ssBackground === 'night' ? 'white' : 'night';
            document.documentElement.dataset.ssBackground = next;
            try { localStorage.setItem('schoolsafe-background', next); } catch (_) {}
        });
        themeObserver = new MutationObserver(updateTheme);
        themeObserver.observe(document.documentElement, {attributes: true, attributeFilter: ['data-ss-background']});
        updateTheme();
        root.querySelectorAll('[data-open-native-branch]').forEach(function (link) {
            var branch = link.dataset.openNativeBranch;
            if (branch === 'pilotage') return;
            on(link, 'click', function (event) {
                if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
                // The validated theme owns menu state, breakpoints, inert and focus.
                // The authorized href remains a fallback and supports modified clicks.
                if (!window.schoolSafeNavigation?.openBranch(branch)) return;
                event.preventDefault();
                event.stopPropagation();
            });
        });
        // Mobile uses the same controls and listeners, not copies or new permissions.
        var phone = matchMedia('(max-width:700px)');
        var mobileState = null;
        var secondary = document.createElement('section');
        secondary.className = 'ss-photo-secondary-metrics';
        secondary.setAttribute('aria-label', 'Autres indicateurs de l’école');
        var lower = root.querySelector('.ss-closing-finance,.ss-dashboard-details,.ss-photo-footer');
        root.insertBefore(secondary, lower);
        function restoreMobile() {
            if (!mobileState) return;
            mobileState.placements.slice().reverse().forEach(function (place) {
                place.marker.replaceWith(place.node);
            });
            mobileState.button.remove();
            mobileState.panel.remove();
            mobileState = null;
            delete root.dataset.phoneActions;
        }
        function syncMobile() {
            if (disposed) return;
            if (!phone.matches) { restoreMobile(); return; }
            if (mobileState) return;
            var header = document.getElementById('footerwrap');
            var tools = header?.querySelector('.ss-header-tools');
            var notifications = tools?.querySelector('[aria-label="Notifications"]');
            if (!tools || !notifications) return; // Native async authorization must finish first.
            var button = document.createElement('button');
            button.id = 'ss-photo-actions-button'; button.type = 'button';
            button.textContent = 'Actions'; button.setAttribute('aria-expanded', 'false');
            button.setAttribute('aria-controls', 'ss-photo-actions-panel');
            var panel = document.createElement('section');
            panel.id = 'ss-photo-actions-panel'; panel.hidden = true;
            panel.setAttribute('aria-label', 'Actions du tableau de bord');
            mobileState = {button: button, panel: panel, placements: []};
            function mobileMove(node, parent) {
                if (!node) return;
                var marker = document.createComment('dashboard-mobile-original-position');
                node.before(marker); mobileState.placements.push({node: node, marker: marker});
                parent.appendChild(node);
            }
            Array.from(tools.children).forEach(function (node) {
                if (node !== notifications) mobileMove(node, panel);
            });
            header.querySelectorAll(':scope > .BottomButton,:scope > .ss-header-profile').forEach(function (node) { mobileMove(node, panel); });
            var nativeMenu = document.getElementById('BottomButtonMenu');
            if (nativeMenu && !panel.contains(nativeMenu)) mobileMove(nativeMenu, panel);
            mobileMove(root.querySelector('.ss-photo-mobile-brand'), header);
            mobileMove(root.querySelector('.ss-photo-theme'), panel);
            mobileMove(root.querySelector('.ss-photo-hero-actions'), panel);
            mobileMove(document.querySelector('#body > .ss-report-actions'), panel);
            var metrics = root.querySelector('.ss-photo-metrics');
            metrics?.querySelectorAll('[data-photo-metric="subjects"],[data-photo-metric="receipts"],[data-photo-metric="alerts"]').forEach(function (node) { mobileMove(node, secondary); });
            tools.append(button, panel);
            root.dataset.phoneActions = 'true';
        }
        function closeActions(refocus) {
            if (!mobileState || mobileState.panel.hidden) return;
            mobileState.panel.hidden = true;
            mobileState.button.setAttribute('aria-expanded', 'false');
            if (refocus) mobileState.button.focus();
        }
        on(document, 'click', function (event) {
            if (!mobileState) return;
            if (mobileState.button.contains(event.target)) {
                var opening = mobileState.panel.hidden;
                mobileState.panel.hidden = !opening;
                mobileState.button.setAttribute('aria-expanded', String(opening));
                return;
            }
            if (!mobileState.panel.contains(event.target)) closeActions(false);
            else if (event.target.closest('#ss-header-jaspe,#ss-header-watch,#BottomButtonMenu,[aria-haspopup="dialog"],a[href]:not([aria-disabled="true"])')) closeActions(false);
        });
        on(document, 'keydown', function (event) {
            if (event.key === 'Escape' && mobileState && !mobileState.panel.hidden) {
                event.preventDefault(); event.stopImmediatePropagation();
                closeActions(true);
            }
        }, true);
        on(phone, 'change', syncMobile);
        syncMobile();
        // Old bookmarked views are section shortcuts, never filters that hide data.
        var legacyTargets = {
            'accueil': '.ss-photo-hero',
            'indicateurs-1': '.ss-photo-metrics', 'indicateurs-2': '.ss-photo-metrics',
            'encaissements': '.ss-closing-finance',
            'modules-1': '.ss-photo-modules', 'modules-2': '.ss-photo-modules',
            'frequentation-scolaire': '.ss-photo-charts', 'synthese-financiere': '.ss-photo-charts',
            'top-alertes': '.ss-photo-charts',
            'vie-scolaire-1': '.ss-photo-school-life', 'vie-scolaire-2': '.ss-photo-school-life',
            'alertes-importantes': '.ss-photo-alerts', 'dossiers-detailles': '.ss-dashboard-details'
        };
        var url = new URL(location.href);
        var target = !url.hash && legacyTargets[url.searchParams.get('dashboard_view')];
        if (target) requestAnimationFrame(function () {
            if (!disposed) root.querySelector(target)?.scrollIntoView({block: 'start'});
        });
        function destroy() {
            if (disposed) return;
            disposed = true;
            events.abort();
            observer?.disconnect();
            restoreMobile();
            secondary.remove();
            themeObserver.disconnect();
            placements.slice().reverse().forEach(function (place) {
                place.parent.insertBefore(place.node, place.next?.parentNode === place.parent ? place.next : null);
            });
            delete root.dataset.photoReady;
        }
        observer = new MutationObserver(function () {
            if (!root.isConnected) destroy(); else syncMobile();
        });
        observer.observe(document.body, {childList: true, subtree: true});
        on(window, 'pagehide', destroy);
        window.schoolSafePhotoDashboard = {destroy: destroy};
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', mount, {once: true});
    else mount();
})();
