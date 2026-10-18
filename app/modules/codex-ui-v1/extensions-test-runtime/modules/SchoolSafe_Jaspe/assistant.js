(function () {
    'use strict';
    var host = document.getElementById('jaspe-native');
    if (!host || host.dataset.ready) return;
    host.dataset.ready = 'true';
    var presenter = window.SchoolSafeJaspe2d;
    if (presenter) {
        var mounted = presenter.mount(document.getElementById('jaspe-stage'), {label: 'JASPE, assistant Rosario'});
        if (mounted && mounted.img) mounted.img.addEventListener('load', function () {
            document.getElementById('jaspe-fallback').hidden = true;
        });
        presenter.init();
    }
    var status = document.getElementById('jaspe-status');
    var nav = document.getElementById('jaspe-destinations');
    var requestNumber = 0;
    var activeRequest = null;
    var log = document.getElementById('jaspe-log');
    function message(speaker, text) {
        if (!log) return;
        var bubble = document.createElement('p');
        bubble.dataset.speaker = speaker; bubble.textContent = text;
        if (speaker === 'user') log.replaceChildren(bubble);
        else {
            Array.from(log.children).forEach(function (item) {
                if (item.dataset.speaker === 'jaspe') log.removeChild(item);
            });
            log.appendChild(bubble);
        }
        log.scrollTop = log.scrollHeight;
    }
    function show(items) {
        nav.replaceChildren();
        nav.hidden = items.length === 0;
        items.forEach(function (action) {
            var link = document.createElement('a');
            link.textContent = action.title;
            link.href = action.url;
            if (window.parent !== window) link.target = '_parent';
            nav.appendChild(link);
        });
    }
    show([]);
    host.addEventListener('jaspe-reset', function () {
        ++requestNumber;
        if (activeRequest) activeRequest.abort();
        activeRequest = null;
        document.getElementById('jaspe-question').value = '';
        status.textContent = '';
        if (log) log.replaceChildren();
        message('jaspe', 'Bonjour, je suis JASPE, votre assistante personnalisée SchoolSafe.');
        show([]);
        if (presenter) presenter.setState('IDLE');
    });
    document.getElementById('jaspe-search').addEventListener('submit', async function (event) {
        event.preventDefault();
        if (event.stopPropagation) event.stopPropagation();
        var question = document.getElementById('jaspe-question').value.trim();
        if (!question) return;
        if (window.SchoolSafeJaspeVoice) window.SchoolSafeJaspeVoice.stop();
        var currentRequest = ++requestNumber;
        if (activeRequest) activeRequest.abort();
        var controller = new AbortController();
        activeRequest = controller;
        var timeout = setTimeout(function () { controller.abort(); }, 45000);
        message('user', question);
        document.getElementById('jaspe-question').value = '';
        status.textContent = 'Jaspe prepare sa reponse...';
        if (presenter) presenter.setState('THINKING');
        show([]);
        try {
            var response = await fetch('jaspe-chat.php', {
                method: 'POST', credentials: 'same-origin', cache: 'no-store', signal: controller.signal,
                headers: {'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': document.getElementById('jaspe-rosario-token').value,
                    'X-Jaspe-Token': document.getElementById('jaspe-token').value},
                body: JSON.stringify({message: question})
            });
            if (!response.ok) throw new Error('ACCESS_UNAVAILABLE');
            var result = await response.json();
            if (currentRequest !== requestNumber) return;
            if (!Array.isArray(result.actions) || typeof result.reply !== 'string') throw new Error('INVALID_RESPONSE');
            show(result.mode === 'local' && typeof result.navigate === 'string'
                ? result.actions.filter(function (action) {
                    return action.id === result.navigate && action.kind === 'navigation' &&
                        /^Modules\.php\?modname=[A-Za-z0-9_]+\/[A-Za-z0-9_]+\.php(?:&staff_id=new|&include=General_Info&student_id=new)?$/.test(action.url);
                }).slice(0, 1) : []);
            status.textContent = result.reply;
            message('jaspe', result.reply);
            if (log) status.textContent = '';
            if (presenter) presenter.setState(result.state);
            // Emotion or gesture chosen by the conversation rules, played by the v12 engine.
            var motion = typeof result.gesture === 'string' && result.gesture ? result.gesture
                : (typeof result.emotion === 'string' ? result.emotion : '');
            if (motion) host.dispatchEvent(new CustomEvent('jaspe-motion', {detail:{kind: motion}}));
            if (window.SchoolSafeJaspeVoice) window.SchoolSafeJaspeVoice.reply(result.reply);
            if (result.mode === 'local' && typeof result.navigate === 'string') {
                var destination = result.actions.find(function (action) {
                    return action.id === result.navigate && action.kind === 'navigation' &&
                        /^Modules\.php\?modname=[A-Za-z0-9_]+\/[A-Za-z0-9_]+\.php(?:&staff_id=new|&include=General_Info&student_id=new)?$/.test(action.url);
                });
                if (destination) (window.parent !== window ? window.parent : window).location.assign(destination.url);
            }
        } catch (_) {
            if (currentRequest !== requestNumber) return;
            show([]);
            status.textContent = controller.signal.aborted
                ? 'La demande a pris trop de temps. Vous pouvez reessayer.'
                : 'Acces indisponible. Rechargez la page.';
            message('jaspe', status.textContent);
            if (presenter) presenter.setState('ERROR');
        } finally {
            clearTimeout(timeout);
            if (currentRequest === requestNumber) activeRequest = null;
        }
    });
    var title = document.getElementById('jaspe-title');
    if (!title) return;
    var content = document.getElementById('jaspe-content');
    function preview() {
        var draft = window.SchoolSafeJaspeDraft.compose(title.value, content.value);
        document.getElementById('jaspe-preview-title').textContent = draft.title;
        document.getElementById('jaspe-preview-content').textContent = draft.content;
    }
    title.addEventListener('input', preview);
    content.addEventListener('input', preview);
    document.getElementById('jaspe-copy').addEventListener('click', async function () {
        try {
            var draft = window.SchoolSafeJaspeDraft.compose(title.value, content.value);
            if (!draft.text) { status.textContent = 'Le brouillon est vide.'; return; }
            await navigator.clipboard.writeText(draft.text);
            status.textContent = 'Brouillon copie.';
        } catch (_) { status.textContent = 'Copie indisponible.'; }
    });
    var picture = document.getElementById('jaspe-picture');
    var image = document.getElementById('jaspe-preview-picture');
    var remove = document.getElementById('jaspe-remove-picture');
    var objectUrl = null;
    function clearImage() {
        if (objectUrl) URL.revokeObjectURL(objectUrl);
        objectUrl = null; image.removeAttribute('src'); image.hidden = true;
        remove.hidden = true; picture.value = '';
    }
    remove.addEventListener('click', clearImage);
    picture.addEventListener('change', function () {
        var file = picture.files[0];
        clearImage();
        if (!file) return;
        if (['image/png','image/jpeg','image/webp'].indexOf(file.type) < 0 || file.size > 5 * 1024 * 1024) {
            status.textContent = 'Image PNG, JPEG ou WebP requise, 5 Mo maximum.'; return;
        }
        objectUrl = URL.createObjectURL(file); image.src = objectUrl;
        image.hidden = false; remove.hidden = false;
    });
    image.addEventListener('error', function () {clearImage(); status.textContent = 'Image illisible.';});
})();
