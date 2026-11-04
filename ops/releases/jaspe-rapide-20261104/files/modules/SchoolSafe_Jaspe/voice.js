(function () {
    'use strict';
    var host = document.getElementById('jaspe-native');
    var mic = document.getElementById('jaspe-microphone');
    if (!host || !mic) return;
    var status = document.getElementById('jaspe-status');
    var input = document.getElementById('jaspe-question');
    var form = document.getElementById('jaspe-search');
    var read = document.getElementById('jaspe-speak');
    var auto = document.getElementById('jaspe-auto-voice');
    var synth = window.speechSynthesis;
    var Recognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    var recognition = null;
    var lastReply = '';
    var generation = 0;
    var controller = null;
    var audio = null;
    var audioUrl = null;
    var finishAudio = null;
    // 03/11 : la voix serveur n'est pas installee en production ; apres un echec, la voix du
    // navigateur parle directement pendant toute la session (plus d'attente ni de message).
    var serverVoiceOff = false;
    
    async function voiceRequest(options, timeout, format) {
        var request = new AbortController();
        var parent = options.signal;
        var expired = false;
        var cancel = function () { request.abort(); };
        if (parent.aborted) request.abort();
        parent.addEventListener('abort', cancel, {once:true});
        var timer = setTimeout(function () { expired = true; request.abort(); }, timeout);
        try {
            var response = await fetch('/jaspe-voice.php', Object.assign({}, options, {signal:request.signal}));
            return {ok:response.ok, status:response.status,
                data:response.ok ? await response[format]() : null};
        } catch (error) {
            if (expired && !parent.aborted) throw new Error('VOICE_TIMEOUT');
            throw error;
        } finally {
            clearTimeout(timer);
            parent.removeEventListener('abort', cancel);
        }
    }
    function waitRetry(milliseconds, signal) {
        return new Promise(function (resolve, reject) {
            var cancel = function () {
                clearTimeout(timer);
                signal.removeEventListener('abort', cancel);
                reject(new DOMException('Cancelled', 'AbortError'));
            };
            var timer = setTimeout(function () {
                signal.removeEventListener('abort', cancel);
                resolve();
            }, milliseconds);
            signal.addEventListener('abort', cancel, {once:true});
            if (signal.aborted) cancel();
        });
    }
    function state(value) {
        if (window.SchoolSafeJaspe2d) window.SchoolSafeJaspe2d.setState(value);
    }
    function stop() {
        ++generation;
        if (controller) { controller.abort(); controller = null; }
        if (audio) { audio.pause(); audio.src = ''; audio = null; }
        if (finishAudio) { finishAudio(false); finishAudio = null; }
        if (audioUrl) { URL.revokeObjectURL(audioUrl); audioUrl = null; }
        if (recognition) { recognition.abort(); recognition = null; }
        if (recorder) { var rec = recorder; recorder = null; try { rec.stop(); } catch (_) {} }
        mic.setAttribute('aria-pressed', 'false');
        if (synth) synth.cancel();
        state('IDLE');
    }
    function speakSystem(text, current) {
        if (!synth || !window.SpeechSynthesisUtterance || !text) { state('IDLE'); return; }
        var spokenText = text.replace(/\bRosario(?:SIS)?(?: Student Information System)?\b/gi, 'SchoolSafe').replace(/[*#`]/g, '');
        var utterance = new window.SpeechSynthesisUtterance(spokenText);
        var voices = synth.getVoices().filter(function (voice) { return /^fr(?:-|_)/i.test(voice.lang); });
        var preferred = voices.find(function (voice) { return /female|femme|denise|hortense|julie|audrey|amelie|marie|sylvie/i.test(voice.name); });
        utterance.voice = preferred || voices[0] || null;
        utterance.lang = 'fr-FR'; utterance.pitch = 1.25; utterance.rate = 0.95;
        utterance.onstart = function () { if (current === generation) state('SPEAKING'); };
        utterance.onend = function () { if (current === generation) state('IDLE'); };
        utterance.onerror = function () {
            if (current !== generation) return;
            status.textContent = 'Lecture vocale indisponible. La reponse reste affichee.';
            state('IDLE');
        };
        synth.speak(utterance);
    }
    // 04/11 : voix Piper du serveur (0,5 s par phrase) verifiee une seule fois ; morceaux decoupes par phrase,
    // le suivant prepare pendant que le precedent est lu (plus de silences) ; vitesse normale.
    var voiceHealth = null;
    function serverVoiceReady(headers) {
        if (!voiceHealth) {
            var probe = new AbortController();
            voiceHealth = voiceRequest({headers:headers,credentials:'same-origin',cache:'no-store',signal:probe.signal}, 3000, 'json')
                .then(function (health) { return !!(health.ok && health.data && health.data.enabled); })
                .catch(function () { return false; });
        }
        return voiceHealth;
    }
    function sentences(text) {
        var parts = text.match(/[^.!?;:\n]+[.!?;:]*\s*/g) || [text], out = [], cur = '';
        parts.forEach(function (part) {
            if ((cur + part).length > (out.length ? 200 : 110) && cur) { out.push(cur.trim()); cur = ''; }
            cur += part;
            while (cur.length > 200) { out.push(cur.slice(0, 200).trim()); cur = cur.slice(200); }
        });
        if (cur.trim()) out.push(cur.trim());
        return out.filter(Boolean);
    }
    async function speak(text) {
        stop();
        if (!text) return;
        var current = generation;
        var spoken = text.replace(/\bRosario(?:SIS)?(?: Student Information System)?\b/gi, 'SchoolSafe').replace(/[*#`]/g, '');
        if (serverVoiceOff) { speakSystem(spoken, current); return; }
        controller = new AbortController();
        var signal = controller.signal;
        var headers = {'X-Jaspe-Token': document.getElementById('jaspe-token').value};
        var chunks = sentences(spoken);
        var index = 0;
        function synth(i) {
            return voiceRequest({method:'POST',credentials:'same-origin',cache:'no-store',signal:signal,
                headers:Object.assign({'Content-Type':'application/json'},headers),body:JSON.stringify({text:chunks[i]})}, 20000, 'blob');
        }
        try {
            if (!(await serverVoiceReady(headers))) throw new Error('VOICE_NOT_READY');
            if (current !== generation) return;
            var next = synth(0);
            for (; index < chunks.length; index++) {
                var response = await next;
                if (current !== generation) return;
                if (!response.ok) throw new Error('VOICE_UNAVAILABLE');
                next = index + 1 < chunks.length ? synth(index + 1) : null;
                audioUrl = URL.createObjectURL(response.data); audio = new Audio(audioUrl);
                await new Promise(function (resolve, reject) {
                    finishAudio = resolve;
                    audio.onplaying = function () { if (current === generation) { status.textContent = ''; state('SPEAKING'); } };
                    audio.onended = function () { resolve(true); };
                    audio.onerror = function () { reject(new Error('AUDIO_UNAVAILABLE')); };
                    audio.play().catch(reject);
                });
                if (current !== generation) return;
                finishAudio = null; audio = null; URL.revokeObjectURL(audioUrl); audioUrl = null;
            }
            if (current === generation) { controller = null; state('IDLE'); }
        } catch (error) {
            if (current !== generation || error.name === 'AbortError') return;
            if (audio) { audio.pause(); audio = null; } if (audioUrl) { URL.revokeObjectURL(audioUrl); audioUrl = null; }
            finishAudio = null; controller = null;
            serverVoiceOff = true;
            speakSystem(chunks.slice(index).join(' '), current);
        }
    }
    window.SchoolSafeJaspeVoice = {
        stop: stop,
        reply: function (text) { lastReply = text; read.disabled = !text; if (auto.checked) speak(text); else state('IDLE'); }
    };
    read.disabled = false;
    auto.disabled = false;
    read.addEventListener('click', function () {
        var bubble = document.querySelector('#jaspe-log p[data-speaker="jaspe"]');
        speak(lastReply || (bubble ? bubble.textContent : ''));
    });
    document.getElementById('jaspe-stop-voice').addEventListener('click', stop);
    auto.addEventListener('change', function () { if (!auto.checked) stop(); });
    // 04/11 : ecoute par enregistrement + Whisper (tous navigateurs, s'arrete seule apres un silence ;
    // un second appui arrete aussi). Reconnaissance du navigateur seulement en secours.
    var recorder = null;
    var canRecord = !!(window.MediaRecorder && navigator.mediaDevices && navigator.mediaDevices.getUserMedia);
    if (!canRecord && !Recognition) {
        mic.disabled = true;
        mic.title = 'Écoute indisponible dans ce navigateur';
    }
    function listenWithBrowser(current) {
        var session = new Recognition();
        recognition = session;
        session.lang = 'fr-FR'; session.continuous = false; session.interimResults = false;
        session.onstart = function () { if (current !== generation) return; mic.setAttribute('aria-pressed', 'true'); status.textContent = 'Je vous écoute...'; state('LISTENING'); };
        session.onresult = function (event) {
            if (current !== generation) return;
            var text = event.results[0][0].transcript.trim();
            recognition = null; ++generation; mic.setAttribute('aria-pressed', 'false');
            if (text) { input.value = text.slice(0, 2000); form.requestSubmit(); }
        };
        session.onerror = function (event) {
            if (current !== generation) return;
            recognition = null; mic.setAttribute('aria-pressed', 'false');
            status.textContent = event.error === 'not-allowed' ? 'Microphone non autorisé. Vous pouvez écrire votre demande.' : 'Écoute indisponible. Vous pouvez écrire votre demande.';
            state('IDLE');
        };
        session.onend = function () { if (current !== generation) return; recognition = null; mic.setAttribute('aria-pressed', 'false'); if (status.textContent === 'Je vous écoute...') { status.textContent = ''; state('IDLE'); } };
        try { session.start(); } catch (_) { recognition = null; mic.setAttribute('aria-pressed', 'false'); state('IDLE'); status.textContent = 'Microphone indisponible.'; }
    }
    async function listenWithWhisper(current) {
        var stream;
        try { stream = await navigator.mediaDevices.getUserMedia({audio:{echoCancellation:true,noiseSuppression:true}}); }
        catch (error) {
            if (current !== generation) return;
            if (Recognition) { listenWithBrowser(current); return; }
            status.textContent = 'Microphone non autorisé. Vous pouvez écrire votre demande.'; state('IDLE'); return;
        }
        if (current !== generation) { stream.getTracks().forEach(function (t) { t.stop(); }); return; }
        var types = ['audio/webm;codecs=opus', 'audio/webm', 'audio/ogg;codecs=opus', 'audio/mp4'];
        var type = types.find(function (t) { return MediaRecorder.isTypeSupported && MediaRecorder.isTypeSupported(t); }) || '';
        var rec = new MediaRecorder(stream, type ? {mimeType:type} : undefined), parts = [];
        recorder = rec;
        var ctx = window.AudioContext ? new AudioContext() : null, analyser = null, timer = null, heard = false, quietSince = 0, started = performance.now();
        if (ctx) { analyser = ctx.createAnalyser(); analyser.fftSize = 512; ctx.createMediaStreamSource(stream).connect(analyser); }
        var buffer = analyser ? new Uint8Array(analyser.fftSize) : null;
        function level() { analyser.getByteTimeDomainData(buffer); var sum = 0; for (var i = 0; i < buffer.length; i++) { var v = (buffer[i] - 128) / 128; sum += v * v; } return Math.sqrt(sum / buffer.length); }
        rec.ondataavailable = function (event) { if (event.data && event.data.size) parts.push(event.data); };
        rec.onstop = async function () {
            clearInterval(timer); stream.getTracks().forEach(function (t) { t.stop(); }); if (ctx) ctx.close().catch(function () {});
            recorder = null; mic.setAttribute('aria-pressed', 'false');
            if (current !== generation) return;
            var blob = new Blob(parts, {type:(rec.mimeType || type || 'audio/webm').split(';')[0]});
            if (blob.size < 800) { status.textContent = ''; state('IDLE'); return; }
            status.textContent = 'Je réfléchis à ce que vous avez dit...'; state('THINKING');
            try {
                var response = await fetch('/jaspe-listen.php', {method:'POST', credentials:'same-origin', cache:'no-store',
                    headers:{'Content-Type':blob.type, 'X-Jaspe-Token':document.getElementById('jaspe-token').value}, body:blob});
                var data = await response.json().catch(function () { return {}; });
                if (current !== generation) return;
                if (!response.ok) throw new Error(data.error || 'LISTEN_UNAVAILABLE');
                var text = String(data.text || '').trim();
                status.textContent = '';
                if (text) { input.value = text.slice(0, 2000); form.requestSubmit(); }
                else { status.textContent = 'Je n’ai rien entendu. Réessayez ou écrivez votre demande.'; state('IDLE'); }
            } catch (error) {
                if (current !== generation) return;
                status.textContent = 'Écoute indisponible pour le moment. Vous pouvez écrire votre demande.'; state('IDLE');
            }
        };
        rec.start(250);
        mic.setAttribute('aria-pressed', 'true'); status.textContent = 'Je vous écoute... (parlez, je m’arrête quand vous vous taisez)'; state('LISTENING');
        timer = setInterval(function () {
            var now = performance.now();
            if (now - started > 15000) { rec.stop(); return; }
            if (!analyser) return;
            var loud = level() > 0.035;
            if (loud) { heard = true; quietSince = 0; }
            else if (heard) { quietSince = quietSince || now; if (now - quietSince > 1300) rec.stop(); }
            else if (now - started > 7000) rec.stop();
        }, 100);
    }
    mic.addEventListener('click', function () {
        if (recorder) { recorder.stop(); return; }
        if (recognition) { stop(); status.textContent = ''; return; }
        stop();
        var current = generation;
        if (canRecord) listenWithWhisper(current); else listenWithBrowser(current);
    });
    function reset() { stop(); lastReply = ''; host.dispatchEvent(new Event('jaspe-reset')); }
    // 03/11 : page masquee (changement d'onglet ou d'application) = la voix s'arrete seulement ;
    // la reponse en cours n'est plus perdue. Fermer JASPE reinitialise toujours les bulles.
    document.addEventListener('visibilitychange', function () { if (document.hidden) stop(); });
    window.addEventListener('pagehide', stop);
    // Closing the parent dialog must stop audio and discard the current exchange.
    if (window.parent !== window) {
        try {
            var dialog = window.parent.document.getElementById('ss-jaspe-dialog');
            if (dialog) new MutationObserver(function () { if (!dialog.open) reset(); })
                .observe(dialog, {attributes: true, attributeFilter: ['open']});
        } catch (_) { /* Standalone or cross-origin embedding has no parent dialog. */ }
    }
})();
