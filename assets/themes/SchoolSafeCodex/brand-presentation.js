(function () {
    // SchoolSafe presentation modified 2026-10-14. Legal attribution is available separately.
    'use strict';
    if (window.schoolSafePresentationInstalled) return;
    window.schoolSafePresentationInstalled = true;
    var brand = /\bRosario(?:SIS)?(?: Student Information System)?\b/gi;
    var premium = /\b(?:non[\s-]+)?premium\b/gi;
    var skip = 'script,style,textarea,input,code,pre,[contenteditable],.ss-legal-credit';

    function legal(element) {
        if (!element) return false;
        if (element.closest('.ss-legal-credit')) return true;
        var block = element.closest('p');
        return Boolean(block && /\u00a9|copyright/i.test(block.textContent));
    }
    function external(anchor) {
        var href = anchor.getAttribute('href');
        if (!href) return false;
        try {
            var url = new URL(href, document.baseURI);
            return /^https?:$/.test(url.protocol) && url.origin !== location.origin;
        } catch (_) { return false; }
    }
    function present(root) {
        if (!root || !root.querySelectorAll) return;
        root.querySelectorAll('img[src]').forEach(function (image) {
            var path;try { path = new URL(image.getAttribute('src'),document.baseURI).pathname; } catch (_) { return; }
            if (path === '/schoolsafe-logo.png' || path === '/schoolsafe-card-bridge/studio-validated-20261008/logo-schoolsafe.png') image.classList.add('ss-animated-brand');
        });
        root.querySelectorAll('.about-rosariosis,.ss-legal-credit,p').forEach(function (element) {
            if (!/(?:Rosario(?:SIS)?)/i.test(element.textContent)) return;
            if (!element.matches('.about-rosariosis,.ss-legal-credit') && !/\u00a9|copyright/i.test(element.textContent)) return;
            if (element.hidden) return;
            element.hidden = true;
            var notice = document.createElement('a');
            notice.href = 'schoolsafe-licenses.php';
            notice.className = 'ss-license-link';
            notice.textContent = 'Licences et mentions l\u00e9gales';
            element.after(notice);
        });
        root.querySelectorAll('a[href]').forEach(function (anchor) {
            if (!external(anchor)) return;
            anchor.removeAttribute('href');
            anchor.removeAttribute('target');
            anchor.removeAttribute('onclick');
            anchor.removeAttribute('download');
            anchor.onclick = null;
            anchor.dataset.ssExternalRemoved = 'true';
            anchor.classList.add('ss-external-label');
            anchor.removeAttribute('tabindex');
            anchor.removeAttribute('role');
            if (/^https?:\/\//i.test(anchor.textContent.trim())) anchor.textContent = 'Ressource externe';
        });
        var walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);
        var node;
        while ((node = walker.nextNode())) {
            var parent = node.parentElement;
            if (!parent || parent.closest(skip) || legal(parent)) continue;
            var value = node.nodeValue.replace(brand, 'School Safe').replace(premium, '');
            if (value !== node.nodeValue) node.nodeValue = value;
        }
        root.querySelectorAll('[title],[aria-label],img[alt],input[placeholder]').forEach(function (element) {
            if (legal(element) || element.closest('code,pre,[contenteditable]')) return;
            ['title', 'aria-label', 'alt', 'placeholder'].forEach(function (attribute) {
                var value = element.getAttribute(attribute);
                if (value === null) return;
                var next = value.replace(brand, 'School Safe').replace(premium, '');
                if (next !== value) element.setAttribute(attribute, next);
            });
        });
        document.title = document.title.replace(brand, 'School Safe');
    }
    function start() {
        var logoStyle = document.querySelector('link[rel="stylesheet"][href*="menu-logo.css"]');
        if (!logoStyle) {
            logoStyle = document.createElement('link');
            logoStyle.rel = 'stylesheet';
            document.head.appendChild(logoStyle);
        }
        logoStyle.href = '/assets/themes/SchoolSafeCodex/menu-logo.css?v=shared-2';
        present(document.body);
        var queued = false;
        new MutationObserver(function () {
            if (queued) return;
            queued = true;
            queueMicrotask(function () { queued = false; present(document.body); });
        }).observe(document.body, {subtree: true, childList: true, characterData: true,
            attributes: true, attributeFilter: ['href', 'title', 'aria-label', 'alt', 'placeholder']});
        document.addEventListener('click', function (event) {
            var anchor = event.target.closest && event.target.closest('a');
            if (anchor && (anchor.dataset.ssExternalRemoved || external(anchor))) {
                event.preventDefault(); event.stopImmediatePropagation();
            }
        }, true);
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start, {once: true});
    else start();
})();
