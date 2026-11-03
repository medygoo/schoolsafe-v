// JASPE en bulles (03/11, concept valide par le proprietaire) — fenetre compagnon uniquement.
// Interface seulement : le moteur (jaspe-chat.php), la memoire de session, la voix et les droits
// restent ceux d'assistant.js / voice.js. Les bulles apparaissent et disparaissent ; la memoire
// conversationnelle cote serveur n'est jamais effacee par l'interface.
(function () {
    'use strict';
    var host = document.getElementById('jaspe-native');
    var form = document.getElementById('jaspe-search');
    var input = document.getElementById('jaspe-question');
    var log = document.getElementById('jaspe-log');
    var stage = document.getElementById('jaspe-stage');
    if (!host || !form || !input || !log || !stage || host.dataset.bulles) return;
    host.dataset.bulles = 'true';
    document.documentElement.classList.add('jaspe-bulles');

    // La reponse ne se lit plus d'elle-meme : l'utilisateur choisit « Ecouter ».
    var auto = document.getElementById('jaspe-auto-voice');
    if (auto && auto.checked) { auto.checked = false; auto.dispatchEvent(new Event('change')); }

    function el(tag, cls, text) {
        var node = document.createElement(tag);
        if (cls) node.className = cls;
        if (text) node.textContent = text;
        return node;
    }
    function icon(name) {
        var img = el('img');
        img.src = 'modules/SchoolSafe_Jaspe/icons/' + name + '.svg'; img.alt = ''; img.width = 18; img.height = 18;
        return img;
    }
    function proxy(id) { var target = document.getElementById(id); if (target && !target.disabled) target.click(); }

    // Etat de JASPE (parle, reflechit...) suivi sans modifier le moteur.
    var current = 'IDLE';
    var presenter = window.SchoolSafeJaspe2d;
    if (presenter && typeof presenter.setState === 'function') {
        var setState = presenter.setState.bind(presenter);
        presenter.setState = function (value) { current = String(value || 'IDLE'); onState(); return setState(value); };
    }
    function speaking() { return current === 'SPEAKING' || (window.speechSynthesis && window.speechSynthesis.speaking); }

    // 1. Au repos : zone de JASPE (appui) + trois petits boutons sur le torse.
    var touch = el('button', 'jaspe-bulles-touch');
    touch.type = 'button'; touch.setAttribute('aria-label', 'Parler avec JASPE');
    var torso = el('div', 'jaspe-bulles-torse');
    torso.setAttribute('role', 'toolbar'); torso.setAttribute('aria-label', 'Commandes de JASPE');
    var bMic = el('button'); bMic.type = 'button'; bMic.title = 'Parler au micro'; bMic.setAttribute('aria-label', 'Parler au micro'); bMic.appendChild(icon('mic'));
    var bListen = el('button'); bListen.type = 'button'; bListen.title = 'Écouter la réponse'; bListen.setAttribute('aria-label', 'Écouter la réponse'); bListen.appendChild(icon('volume-2'));
    var bTalk = el('button'); bTalk.type = 'button'; bTalk.title = 'Conversation'; bTalk.setAttribute('aria-label', 'Conversation'); bTalk.appendChild(icon('message-circle'));
    torso.append(bMic, bListen, bTalk);

    // 2. Bulle d'ecriture : le formulaire existant y est deplace (memes ecouteurs, meme envoi).
    var write = el('div', 'jaspe-bulle jaspe-bulle-ecrire');
    write.hidden = true; write.setAttribute('role', 'dialog'); write.setAttribute('aria-label', 'Écrire à JASPE');
    var writeMic = el('button', 'jaspe-bulle-micro'); writeMic.type = 'button'; writeMic.title = 'Parler au micro';
    writeMic.setAttribute('aria-label', 'Parler au micro'); writeMic.appendChild(icon('mic'));
    var hint = el('p', 'jaspe-bulle-etat');
    input.placeholder = 'Écrivez à JASPE…';
    write.append(form, writeMic, hint);

    // 3-4. Bulle d'attente puis bulle de reponse.
    var reply = el('div', 'jaspe-bulle jaspe-bulle-reponse');
    reply.hidden = true; reply.setAttribute('aria-live', 'polite');
    var replyText = el('p', 'jaspe-bulle-texte');
    var replyTools = el('div', 'jaspe-bulle-outils');
    var rListen = el('button', 'jaspe-bulle-ecouter'); rListen.type = 'button'; rListen.appendChild(icon('volume-2'));
    rListen.appendChild(document.createTextNode(' Écouter'));
    var rClose = el('button', 'jaspe-bulle-fermer', '×'); rClose.type = 'button'; rClose.setAttribute('aria-label', 'Fermer la bulle');
    replyTools.append(rListen, rClose);
    var nav = document.getElementById('jaspe-destinations');
    reply.append(replyText);
    if (nav) reply.append(nav);
    reply.append(replyTools);

    document.body.append(touch, torso, write, reply);

    // Placement : suit la partie dessinee du canvas (image « contenue » dans sa boite).
    function figure() {
        var canvas = stage.querySelector('canvas') || stage.querySelector('img');
        if (!canvas) return null;
        var box = canvas.getBoundingClientRect();
        if (!box.width || !box.height) return null;
        var natW = canvas.width || canvas.naturalWidth || box.width, natH = canvas.height || canvas.naturalHeight || box.height;
        var scale = Math.min(box.width / natW, box.height / natH);
        var w = natW * scale, h = natH * scale;
        return {x: box.left + (box.width - w) / 2, y: box.top + (box.height - h) / 2, w: w, h: h};
    }
    // Fenetre ajustee a JASPE et placee au milieu de l'ecran (03/11) : le reste de la page reste
    // cliquable. Ordinateur : la bulle s'ouvre a droite, JASPE ne bouge pas. Telephone : au-dessus.
    var dialog = null;
    try { dialog = window.frameElement && window.frameElement.closest('dialog'); } catch (_) { dialog = null; }
    var narrow = false, inner = {w: 0, h: 0};
    function important(node, styles) { Object.keys(styles).forEach(function (k) { node.style.setProperty(k, styles[k], 'important'); }); }
    function layout() {
        if (!dialog || !dialog.open) return;
        var pw = window.parent.innerWidth, ph = window.parent.innerHeight;
        var nav = window.parent.document.getElementById('ss-mobile-navigation');
        var navH = nav && nav.offsetHeight && window.parent.getComputedStyle(nav).display !== 'none' ? nav.offsetHeight : 0;
        var avail = Math.max(260, ph - navH - 16);
        var h = Math.round(Math.min(380, avail * 0.86)), jw = Math.round(h * 1023 / 1537);
        var jx = Math.round((pw - jw) / 2), jy = Math.round(Math.max(8, (ph - navH - h) / 2));
        var open = !write.hidden || !reply.hidden;
        narrow = pw < 640;
        var left = jx, top = jy, w = jw, hh = h, sx = 0, sy = 0;
        if (open && !narrow) {
            w = Math.round(jw * 0.7) + 320;
            left = Math.max(8, Math.min(jx, pw - w - 8)); sx = jx - left;
        } else if (open) {
            w = Math.min(pw - 16, 380); left = Math.round((pw - w) / 2);
            top = Math.max(8, jy - 210); hh = h + (jy - top);
            sx = Math.round((w - jw) / 2); sy = jy - top;
        }
        inner = {w: w, h: hh};
        important(dialog, {position: 'fixed', inset: 'auto', left: left + 'px', top: top + 'px', width: w + 'px', height: hh + 'px',
            'max-width': 'none', 'max-height': 'none', margin: '0', padding: '0', transform: 'none', overflow: 'visible'});
        important(stage, {position: 'fixed', left: sx + 'px', top: sy + 'px', bottom: 'auto', right: 'auto', width: jw + 'px', height: h + 'px', 'max-height': 'none'});
    }
    function place() {
        layout();
        var f = figure();
        if (!f) return;
        var cx = f.x + f.w * 0.46, vw = document.documentElement.clientWidth, vh = document.documentElement.clientHeight;
        if (narrow) {
            [write, reply].forEach(function (bubble) {
                bubble.style.left = '8px';
                bubble.style.top = '8px';
                bubble.style.width = Math.max(200, vw - 16) + 'px';
                bubble.style.maxHeight = Math.max(160, Math.round(f.y + f.h * 0.35)) + 'px';
            });
        }
        touch.style.left = Math.round(f.x + f.w * 0.22) + 'px';
        touch.style.top = Math.round(f.y + f.h * 0.08) + 'px';
        touch.style.width = Math.round(f.w * 0.5) + 'px';
        touch.style.height = Math.round(f.h * 0.86) + 'px';
        torso.style.left = Math.round(cx) + 'px';
        torso.style.top = Math.round(f.y + f.h * 0.43) + 'px';
        if (narrow) return;
        var left = Math.round(Math.max(8, Math.min(f.x + f.w * 0.66, vw - 240)));
        var top = Math.round(Math.max(8, f.y + f.h * 0.06));
        [write, reply].forEach(function (bubble) {
            bubble.style.left = left + 'px';
            bubble.style.top = top + 'px';
            bubble.style.width = Math.max(200, Math.min(300, vw - left - 10)) + 'px';
            bubble.style.maxHeight = Math.max(160, vh - top - 16) + 'px';
        });
    }
    var raf = 0;
    function schedule() { if (!raf) raf = requestAnimationFrame(function () { raf = 0; place(); }); }
    window.addEventListener('resize', schedule);
    if (window.ResizeObserver) new ResizeObserver(schedule).observe(stage);
    try { window.parent.addEventListener('resize', schedule); } catch (_) { /* fenetre autonome */ }
    if (dialog) new MutationObserver(function () { if (dialog.open) place(); })
        .observe(dialog, {attributes: true, attributeFilter: ['open']});
    setInterval(place, 700);
    place();

    // Ouverture / fermeture des bulles.
    var hideTimer = 0, lastReply = '';
    function openWrite() {
        clearTimeout(hideTimer); reply.hidden = true;
        write.hidden = false; hint.textContent = '';
        place();
        setTimeout(function () { input.focus(); }, 30);
    }
    function closeWrite() { write.hidden = true; schedule(); }
    function closeReply() { clearTimeout(hideTimer); reply.hidden = true; reply.classList.remove('jaspe-bulle-attente'); schedule(); }
    touch.addEventListener('click', function () { if (write.hidden) openWrite(); else closeWrite(); });
    bTalk.addEventListener('click', function () { if (write.hidden) openWrite(); else closeWrite(); });
    bMic.addEventListener('click', function () { openWrite(); proxy('jaspe-microphone'); });
    writeMic.addEventListener('click', function () { proxy('jaspe-microphone'); });
    function listen() { if (lastReply) { proxy('jaspe-speak'); schedule(); } }
    bListen.addEventListener('click', listen);
    rListen.addEventListener('click', function () { clearTimeout(hideTimer); listen(); });
    rClose.addEventListener('click', closeReply);
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') { closeWrite(); closeReply(); }
    });

    // 3. Envoi : la bulle de l'utilisateur disparait, JASPE reflechit.
    form.addEventListener('submit', function () {
        if (!input.value.trim()) return;
        closeWrite();
        clearTimeout(hideTimer);
        replyText.textContent = 'JASPE réfléchit…';
        reply.classList.add('jaspe-bulle-attente');
        rListen.hidden = true;
        reply.hidden = false;
        place();
    }, true);

    // 4. Reponse : nouvelle bulle, a ecouter si on le souhaite, puis elle disparait.
    function readingTime(text) { return Math.min(30000, Math.max(7000, text.length * 65)); }
    function scheduleHide(delay) {
        clearTimeout(hideTimer);
        hideTimer = setTimeout(function check() {
            if (speaking() || reply.matches(':hover') || reply.contains(document.activeElement)) { hideTimer = setTimeout(check, 1500); return; }
            closeReply();
        }, delay);
    }
    function onState() {
        if (reply.hidden || reply.classList.contains('jaspe-bulle-attente')) return;
        if (current === 'SPEAKING') clearTimeout(hideTimer);
        else if (current === 'IDLE') scheduleHide(2500);
    }
    new MutationObserver(function (records) {
        records.forEach(function (record) {
            record.addedNodes.forEach(function (node) {
                if (node.nodeType !== 1 || node.dataset.speaker !== 'jaspe' || !reply.classList.contains('jaspe-bulle-attente')) return;
                lastReply = node.textContent;
                replyText.textContent = lastReply;
                reply.classList.remove('jaspe-bulle-attente');
                rListen.hidden = false;
                reply.hidden = false;
                place();
                scheduleHide(readingTime(lastReply));
            });
        });
    }).observe(log, {childList: true});

    // Messages d'etat de la voix (ecoute, micro refuse...) dans la bulle d'ecriture.
    var status = document.getElementById('jaspe-status');
    if (status) new MutationObserver(function () {
        var text = status.textContent.trim();
        if (!text || /navigateur utilis/i.test(text)) return;
        hint.textContent = text;
        if (write.hidden && /ecoute|écoute|micro/i.test(text)) openWrite();
    }).observe(status, {childList: true, characterData: true, subtree: true});

    // Fermeture de la fenetre / reinitialisation : seules les bulles se ferment.
    host.addEventListener('jaspe-reset', function () { closeWrite(); closeReply(); lastReply = ''; });
})();
