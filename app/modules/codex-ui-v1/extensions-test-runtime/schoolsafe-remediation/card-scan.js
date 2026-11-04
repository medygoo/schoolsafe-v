// Rattrapage (04/11) : lecture de la carte de l'eleve par la camera pour la presence d'une seance.
// Remplit seulement le champ « card_qr » : la carte est verifiee par le serveur (signature, eleve du dossier).
(function () {
    'use strict';
    function stop(stream, box) {
        if (stream) stream.getTracks().forEach(function (track) { track.stop(); });
        if (box) box.remove();
    }
    function fill(form, value) {
        var field = form.querySelector('input[name="card_qr"]');
        if (field) field.value = value;
        var status = form.querySelector('select[name="status"]');
        if (status) status.value = 'held';
        var attendance = form.querySelector('select[name="attendance"]');
        if (attendance) attendance.value = 'present';
        var note = form.querySelector('.ss-card-note');
        if (note) note.textContent = 'Carte lue : la présence sera vérifiée à l’enregistrement.';
    }
    async function scan(button) {
        var form = button.closest('form');
        var note = form.querySelector('.ss-card-note');
        if (!('BarcodeDetector' in window) || !navigator.mediaDevices) {
            if (note) note.textContent = 'Caméra non prise en charge ici : utilisez un lecteur de cartes ou saisissez le code.';
            return;
        }
        var stream = null;
        var box = document.createElement('div');
        box.className = 'ss-card-camera';
        box.innerHTML = '<video playsinline muted></video><button type="button">Fermer</button>';
        button.after(box);
        box.querySelector('button').addEventListener('click', function () { stop(stream, box); });
        try {
            stream = await navigator.mediaDevices.getUserMedia({video: {facingMode: 'environment'}});
            var video = box.querySelector('video');
            video.srcObject = stream;
            await video.play();
            var detector = new window.BarcodeDetector({formats: ['qr_code']});
            var tries = 0;
            var tick = async function () {
                if (!box.isConnected) return;
                var codes = await detector.detect(video).catch(function () { return []; });
                var card = codes.map(function (code) { return code.rawValue; }).find(function (value) { return /^schoolsafe:\/\/card\//.test(value); });
                if (card) { fill(form, card); stop(stream, box); return; }
                if (++tries > 200) { stop(stream, box); if (note) note.textContent = 'Aucune carte lue : réessayez.'; return; }
                setTimeout(tick, 300);
            };
            tick();
        } catch (error) {
            stop(stream, box);
            if (note) note.textContent = 'Caméra indisponible : autorisez la caméra ou utilisez un lecteur de cartes.';
        }
    }
    document.addEventListener('click', function (event) {
        var button = event.target.closest('.ss-card-scan');
        if (button) scan(button);
    });
})();
