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
    async function speak(text) {
        stop();
        if (!text) return;
        var current = generation;
        var spoken = text.replace(/\bRosario(?:SIS)?(?: Student Information System)?\b/gi, 'SchoolSafe').replace(/[*#`]/g, '');
        var chunks = spoken.match(/.{1,180}(?:\s|$)|.{1,180}/gs) || [];
        controller = new AbortController();
        var signal = controller.signal;
        var headers = {'X-Jaspe-Token': document.getElementById('jaspe-token').value};
        var index = 0;
        try {
            var health = await voiceRequest({headers:headers,credentials:'same-origin',cache:'no-store',signal:signal}, 6000, 'json');
            if (!health.ok || !health.data.enabled) throw new Error('VOICE_NOT_READY');
            for (; index < chunks.length; index++) {
                if (current !== generation) return;
                state('THINKING');
                status.textContent = 'Preparation de la voix de JASPE...';
                var response;
                for (var attempt = 0; attempt < 3; attempt++) {
                    response = await voiceRequest({method:'POST',credentials:'same-origin',cache:'no-store',signal:signal,
                        headers:Object.assign({'Content-Type':'application/json'},headers),body:JSON.stringify({text:chunks[index].trim()})}, 90000, 'blob');
                    if (response.status !== 429 || attempt === 2) break;
                    status.textContent = 'La voix de JASPE est occupee, un instant...';
                    await waitRetry(1500 * (attempt + 1), signal);
                }
                if (!response.ok) throw new Error('VOICE_UNAVAILABLE');
                var blob = response.data;
                if (current !== generation) return;
                audioUrl = URL.createObjectURL(blob);audio = new Audio(audioUrl);
                audio.playbackRate = 0.85;
                audio.preservesPitch = true;
                await new Promise(function (resolve,reject) {
                    finishAudio = resolve;
                    audio.onplaying = function () { if(current===generation){status.textContent='';state('SPEAKING');} };
                    audio.onended = function () { resolve(true); };
                    audio.onerror = function () { reject(new Error('AUDIO_UNAVAILABLE')); };
                    audio.play().catch(reject);
                });
                if (current !== generation) return;
                finishAudio = null;audio = null;URL.revokeObjectURL(audioUrl);audioUrl = null;
            }
            if(current===generation){controller=null;state('IDLE');}
        } catch (error) {
            if (current !== generation || error.name === 'AbortError') return;
            if(audio){audio.pause();audio=null;}if(audioUrl){URL.revokeObjectURL(audioUrl);audioUrl=null;}
            finishAudio=null;controller=null;
            state('IDLE');
            status.textContent='Voix de reference indisponible : voix du navigateur utilisee.';
            speakSystem(chunks.slice(index).join(' '),current);
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
    if (!Recognition) {
        mic.disabled = true;
        mic.title = 'Reconnaissance vocale indisponible dans ce navigateur';
    }
    mic.addEventListener('click', function () {
        if (recognition) { stop(); status.textContent = ''; return; }
        stop();
        var current = generation;
        var session = new Recognition();
        recognition = session;
        session.lang = 'fr-FR'; session.continuous = false; session.interimResults = false;
        session.onstart = function () {
            if (current !== generation) return;
            mic.setAttribute('aria-pressed', 'true');
            status.textContent = "Je vous ecoute..."; state('LISTENING');
        };
        session.onresult = function (event) {
            if (current !== generation) return;
            var text = event.results[0][0].transcript.trim();
            recognition = null; ++generation; mic.setAttribute('aria-pressed', 'false');
            if (text) { input.value = text.slice(0, 2000); form.requestSubmit(); }
        };
        session.onerror = function (event) {
            if (current !== generation) return;
            recognition = null; mic.setAttribute('aria-pressed', 'false');
            status.textContent = event.error === 'not-allowed'
                ? 'Microphone non autorise. Vous pouvez ecrire votre demande.'
                : 'Ecoute indisponible. Vous pouvez ecrire votre demande.';
            state('IDLE');
        };
        session.onend = function () {
            if (current !== generation) return;
            recognition = null; mic.setAttribute('aria-pressed', 'false');
            if (status.textContent === 'Je vous ecoute...') { status.textContent = ''; state('IDLE'); }
        };
        try { session.start(); } catch (_) {
            recognition = null; mic.setAttribute('aria-pressed', 'false'); state('IDLE'); status.textContent = 'Microphone indisponible.';
        }
    });
    function reset() { stop(); lastReply = ''; host.dispatchEvent(new Event('jaspe-reset')); }
    document.addEventListener('visibilitychange', function () { if (document.hidden) reset(); });
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
