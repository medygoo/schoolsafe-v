'use strict';
window.installOfficialQrGuard(window);
// Native issuance must not fall through to the restored demo printer.
// Printing stays unavailable until the official school roster is loaded.
const officialModelPrint = window.ssModelPrint;
let rosterReady = false;
const unavailableProduction = () => window.toast('Cartes officielles en cours de chargement. Aucune operation effectuee.');
for (const name of ['ssModelPrint', 'ssPrintCard', 'printClassCards', 'exportSSAllCardsZip']) {
  window[name] = unavailableProduction;
}
window.ssSetLocalSchoolText = () => window.toast('Coordonnees gerees dans Rosario.');
const panel = document.getElementById('PC');
function protect() {
  if (rosterReady) return;
  panel.querySelectorAll('.ss-production button').forEach(button => { button.disabled = true; });
  panel.querySelectorAll('button').forEach(button => {
    if (button.textContent.includes('Imprimer')) button.disabled = true;
  });
  panel.querySelectorAll('#ss-school-name,#ss-school-motto,input[oninput*="ssSetLocalSchoolText"]').forEach(input => { input.disabled = true; });
}
const originalRender = window.render;
window.render = function () { originalRender(); protect(); };
window.render();
const form = document.getElementById('native-lookup');
const issueButton = document.getElementById('native-issue');
const printButton = document.getElementById('native-print');
const previewButton = document.getElementById('native-print-preview');
const credentialStatus = document.createElement('p');
credentialStatus.id = 'native-credential-status';
credentialStatus.setAttribute('aria-live', 'polite');
document.getElementById('native-lookup').appendChild(credentialStatus);
function showNativeCredential(result) {
  credentialStatus.replaceChildren();
  if (result.backend !== 'native-registry') return;
  const qr = result.input?.credential?.status === 'active' ? result.view?.student?.qr : '';
  if (!/^schoolsafe:\/\/card\/[^/\s]+\/[^/\s]+$/.test(qr || '')) return;
  const code = document.createElement('code');
  code.textContent = qr;
  credentialStatus.append('QR scolaire enregistre — verification possible. Ce QR ne vaut pas autorisation d’impression. ', code);
  if (typeof window.ssGenQR === 'function') {
    const preview = document.createElement('span');
    preview.id = 'native-credential-qr';
    credentialStatus.appendChild(preview);
    window.ssGenQR(preview.id, qr);
  }
}
let loadedQr = '';
function rememberOfficialCard(result) {
  const credential = result.input?.credential;
  loadedQr = credential?.status === 'active' && result.missing.length === 0
    && result.view?.student?.qr === credential.qr_payload
    && /^schoolsafe:\/\/card\/[^/\s]+\/[^/\s]+$/.test(credential.qr_payload || '')
    ? credential.qr_payload : '';
  if (printButton) printButton.disabled = !loadedQr;
  if (previewButton) previewButton.disabled = !loadedQr;
}
let loadedStudentId = '';
const studentInput = document.getElementById('native-student');
const loadButton = form.querySelector('button');
let selectionVersion = 0;
let pendingController = null;
function invalidateSelection() {
  selectionVersion += 1;
  pendingController?.abort();
  pendingController = null;
  loadedStudentId = ''; issueButton.disabled = true;
  credentialStatus.replaceChildren();
  loadedQr = '';
  if (printButton) printButton.disabled = true;
  if (previewButton) previewButton.disabled = true;
  window.SchoolSafeOfficialPrint?.close();
  loadButton.disabled = false;
  window.SchoolSafeStudio.setData({settings:{school:{},year:''},students:[],classes:[],users:[],aps:[]});
}
function isCurrent(version, studentId) {
  return version === selectionVersion && studentInput.value === studentId;
}
studentInput.addEventListener('input', invalidateSelection);
form.addEventListener('submit', async event => {
  event.preventDefault();
  if (!form.reportValidity()) return;
  invalidateSelection();
  const version = selectionVersion;
  const studentId = studentInput.value;
  const controller = new AbortController();
  pendingController = controller;
  const button = form.querySelector('button'); button.disabled = true;
  loadedStudentId = ''; issueButton.disabled = true;
  window.SchoolSafeStudio.setData({settings:{school:{},year:''},students:[],classes:[],users:[],aps:[]});
  window.toast('Chargement Rosario...');
  try {
    const result = await window.SchoolSafeStudioNativeBridge.loadNativeStudioCard(studentId, { signal: controller.signal });
    if (!isCurrent(version, studentId)) return;
    if (result.nativeReference.schoolId !== window.SchoolSafeNativeSchool
        || result.nativeReference.year !== window.SchoolSafeNativeYear) throw new Error('NATIVE_SCOPE_CHANGED');
    const nativeRegistry = result.backend === 'native-registry';
    const unmapped = result.missing.includes('class.mapping') || result.missing.includes('class.cycle');
    if (unmapped && !nativeRegistry) {
      window.toast('Dossier Rosario lu. Classe ou cycle non raccorde : ' + result.missing.join(', ')
        + '. Emission et impression bloquees.');
      return;
    }
    if (!unmapped) window.SchoolSafeStudio.setData(window.toRestoredStudioData(result));
    loadedStudentId = result.nativeReference.studentId;
    rememberOfficialCard(result);
    showNativeCredential(result);
    issueButton.textContent = nativeRegistry ? 'Enregistrer le QR officiel' : 'Emettre la carte officielle';
    issueButton.disabled = (nativeRegistry ? result.nativeCredentialIssuanceAllowed !== true
      : result.missing.some(key => key !== 'card.official_qr')) || Boolean(result.input.credential?.qr_payload);
    window.toast('Donnees Rosario chargees. ' + (result.missing.length ? 'Informations manquantes : ' + result.missing.join(', ') : 'Consultation disponible.') + ' Aucune carte emise.');
  } catch {
    if (!isCurrent(version, studentId)) return;
    window.toast('Dossier inaccessible ou contexte modifie. Aucune operation effectuee.');
  } finally {
    if (isCurrent(version, studentId)) {
      button.disabled = false;
      pendingController = null;
    }
  }
});
issueButton.addEventListener('click', async () => {
  if (!loadedStudentId || issueButton.disabled) return;
  const studentId = loadedStudentId;
  const version = selectionVersion;
  if (!isCurrent(version, studentId)) return;
  const controller = new AbortController();
  pendingController = controller;
  issueButton.disabled = true;
  loadedQr = '';
  if (printButton) printButton.disabled = true;
  if (previewButton) previewButton.disabled = true;
  loadButton.disabled = true;
  try {
    const response = await fetch('/card-bridge-issue.php', { method: 'POST', credentials: 'same-origin',
      cache: 'no-store', headers: { 'Content-Type':'application/x-www-form-urlencoded', Accept:'application/json' },
      signal: controller.signal,
      body: new URLSearchParams({ student_id: studentId, expected_school: window.SchoolSafeNativeSchool,
        expected_year: window.SchoolSafeNativeYear, csrf: document.getElementById('native-issue-csrf').value }) });
    const result = await response.json();
    if (!isCurrent(version, studentId)) return;
    if (!response.ok || (result.operationPerformed !== true
        && !(result.operationPerformed === false && result.result === 'ALREADY_ISSUED'))) throw new Error('ISSUE_REJECTED');
    const fresh = await window.SchoolSafeStudioNativeBridge.loadNativeStudioCard(studentId, { signal: controller.signal });
    if (!isCurrent(version, studentId)) return;
    if (fresh.nativeReference.schoolId !== window.SchoolSafeNativeSchool
        || fresh.nativeReference.year !== window.SchoolSafeNativeYear || !fresh.input.credential?.qr_payload) throw new Error('ISSUE_RELOAD_REQUIRED');
    if (!(fresh.backend === 'native-registry'
        && (fresh.missing.includes('class.mapping') || fresh.missing.includes('class.cycle')))) {
      window.SchoolSafeStudio.setData(window.toRestoredStudioData(fresh));
    }
    rememberOfficialCard(fresh);
    showNativeCredential(fresh);
    window.toast(fresh.backend === 'native-registry'
      ? 'QR officiel relu depuis le registre scolaire. Impression distincte soumise aux controles serveur.'
      : 'Carte officielle relue depuis le moteur.');
  } catch {
    if (!isCurrent(version, studentId)) return;
    window.toast('Emission non confirmee. Relisez le dossier avant toute nouvelle tentative.');
  } finally {
    if (isCurrent(version, studentId)) {
      pendingController = null;
      loadButton.disabled = false;
    }
  }
});
if (printButton) printButton.addEventListener('click', async () => {
  if (printButton.disabled || !loadedStudentId || !loadedQr) return;
  const studentId = loadedStudentId;
  const qr = loadedQr;
  const version = selectionVersion;
  if (!isCurrent(version, studentId)) return;
  const controller = new AbortController();
  pendingController = controller;
  printButton.disabled = true;
  if (previewButton) previewButton.disabled = true;
  issueButton.disabled = true;
  loadButton.disabled = true;
  try {
    const fresh = await window.SchoolSafeStudioNativeBridge.loadNativeStudioCard(studentId, { signal: controller.signal });
    if (!isCurrent(version, studentId)) return;
    if (fresh.nativeReference.schoolId !== window.SchoolSafeNativeSchool
        || fresh.input?.credential?.status !== 'active' || fresh.missing.length
        || fresh.input.credential.qr_payload !== qr || fresh.view?.student?.qr !== qr) {
      throw new Error('ACTIVE_CARD_CHANGED');
    }
    const data = window.toRestoredStudioData(fresh);
    const format = window.ssClassType(data.classes[0]).type;
    if (!['badge', 'carte'].includes(format)) throw new Error('INVALID_PRINT_FORMAT');
    const response = await fetch('/card-bridge-print.php', { method: 'POST', credentials: 'same-origin',
      cache: 'no-store', headers: { 'Content-Type':'application/x-www-form-urlencoded', Accept:'application/json' },
      signal: controller.signal, body: new URLSearchParams({ student_id: studentId, qr_payload: qr, format,
        csrf: document.getElementById('native-issue-csrf').value }) });
    const result = await response.json();
    if (!isCurrent(version, studentId)) return;
    if (!response.ok || result.operationPerformed !== true || !result.data?.id
        || result.data.status !== 'pending') throw new Error('PRINT_REQUEST_UNCONFIRMED');
    window.SchoolSafeStudio.setData(data);
    window.toast('Demande officielle en attente de traitement. Aucun document imprime.');
  } catch {
    if (!isCurrent(version, studentId)) return;
    window.toast('Demande non confirmee ou carte modifiee. Relisez le dossier avant toute nouvelle tentative.');
  } finally {
    if (isCurrent(version, studentId)) {
      pendingController = null;
      loadedQr = '';
      loadButton.disabled = false;
      // A reload is required after every attempt, including an uncertain response.
      printButton.disabled = true;
    }
  }
});
if (previewButton) previewButton.addEventListener('click', async () => {
  if (previewButton.disabled || !loadedStudentId || !loadedQr) return;
  const studentId = loadedStudentId, qr = loadedQr, version = selectionVersion;
  const controller = new AbortController();
  pendingController = controller;
  previewButton.disabled = true;
  if (printButton) printButton.disabled = true;
  issueButton.disabled = true; loadButton.disabled = true;
  const read = async () => {
    const response = await fetch('/card-bridge-print-read.php', { method:'POST', credentials:'same-origin',
      cache:'no-store', headers:{'Content-Type':'application/x-www-form-urlencoded',Accept:'application/json'},
      signal:controller.signal, body:new URLSearchParams({student_id:studentId,qr_payload:qr,
        csrf:document.getElementById('native-issue-csrf').value}) });
    const body = await response.json();
    if (!response.ok || body.operationPerformed !== false || !isCurrent(version, studentId)) throw new Error('PRINT_READ_REJECTED');
    const result = {...body.data,view:window.SchoolSafeCardAdapter.adaptRosarioCard(body.data.input)};
    return window.SchoolSafeOfficialPrint.credentialReady(result,studentId,window.SchoolSafeNativeSchool,qr);
  };
  try {
    const result = await read();
    if (!isCurrent(version,studentId)) return;
    if (!rosterReady) window.SchoolSafeStudio.setData(window.toRestoredStudioData(result));
    await window.SchoolSafeOfficialPrint.open({result,qr,read,current:()=>isCurrent(version,studentId)});
  } catch {
    if (!isCurrent(version,studentId)) return;
    window.SchoolSafeOfficialPrint?.close();
    window.toast('Rendu bloque : carte modifiee, dossier incomplet ou asset inaccessible.');
  } finally {
    if (isCurrent(version,studentId)) {
      pendingController = null;
      loadedQr = ''; loadButton.disabled = false;
      previewButton.disabled = true;
      if (rosterReady) selectRoster(studentId);
    }
  }
});

// ---------------------------------------------------------------------------
// Studio automatique (03/11) : cartes de toute l'ecole depuis les fiches Rosario,
// QR officiel emis automatiquement pour les fiches completes, dossier PNG recto/verso.
const MISSING_LABELS = {
  'student.last_name': 'nom', 'student.first_name': 'prénom', 'student.matricule': 'matricule',
  'student.photo': 'photo', 'student.address': 'adresse', 'class.mapping': 'classe (emploi du temps)',
  'class.cycle': 'cycle de la classe', 'class.tutor': 'titulaire de la classe',
  'family.primary_contact': 'responsable principal (onglet Famille)', 'card.official_qr': 'QR officiel',
  'card.signing_configuration': 'clé de signature des cartes (ProDéli)', 'school.name': "nom de l'école",
  'school.logo': "logo de l'école", 'school.address': "adresse de l'école", 'school.phone': "téléphone de l'école",
  'school.email': "e-mail de l'école", 'school.website': "site de l'école", 'school.year': 'année scolaire',
  'student.unreadable': 'fiche illisible', 'card.render': 'carte non affichable',
};
const rosterById = new Map();
const rosterStatus = document.createElement('section');
rosterStatus.id = 'ss-roster-status';
rosterStatus.setAttribute('aria-live', 'polite');
form.after(rosterStatus);

function selectRoster(id) {
  const result = rosterById.get(String(id || ''));
  if (!result) return;
  studentInput.value = result.nativeReference.studentId;
  loadedStudentId = result.nativeReference.studentId;
  rememberOfficialCard(result);
  showNativeCredential(result);
  issueButton.disabled = true;
  const exportButton = document.getElementById('export-png');
  if (exportButton) exportButton.disabled = false;
}
document.addEventListener('change', event => {
  if (event.target && event.target.id === 'ss-student-sel' && rosterReady) selectRoster(event.target.value);
});

function renderRosterStatus(readyCount, incomplete) {
  rosterStatus.replaceChildren();
  const title = document.createElement('p');
  title.innerHTML = '<b>' + readyCount + '</b> carte(s) prête(s) · <b>' + incomplete.length + '</b> fiche(s) à compléter';
  rosterStatus.appendChild(title);
  if (!incomplete.length) return;
  const details = document.createElement('details');
  const summary = document.createElement('summary');
  summary.textContent = 'Fiches à compléter dans Rosario';
  details.appendChild(summary);
  const list = document.createElement('ul');
  for (const card of incomplete) {
    const id = card.nativeReference?.studentId || '';
    const record = card.input?.record || {};
    const name = [record.last_name, record.first_name].filter(Boolean).join(' ') || ('Élève n° ' + id);
    const item = document.createElement('li');
    const link = document.createElement('a');
    link.href = '/Modules.php?modname=Students/Student.php&student_id=' + encodeURIComponent(id);
    link.target = '_top';
    link.textContent = name;
    item.append(link, ' — manque : ' + (card.missing || []).map(key => MISSING_LABELS[key] || key).join(', '));
    list.appendChild(item);
  }
  details.appendChild(list);
  rosterStatus.appendChild(details);
}

async function loadRoster() {
  window.toast('Préparation automatique des cartes de l’école…');
  try {
    // Droit d'emission : QR officiel relie automatiquement aux fiches completes (refus silencieux sinon).
    await fetch('/card-bridge-issue.php', { method: 'POST', credentials: 'same-origin', cache: 'no-store',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', Accept: 'application/json' },
      body: new URLSearchParams({ scope: 'school', expected_school: window.SchoolSafeNativeSchool,
        expected_year: window.SchoolSafeNativeYear, csrf: document.getElementById('native-issue-csrf').value }) });
  } catch {}
  try {
    const response = await fetch('/card-bridge-prepare.php?resource=roster', { credentials: 'same-origin',
      cache: 'no-store', headers: { Accept: 'application/json' } });
    const body = await response.json();
    if (!response.ok || body.data?.schoolId !== window.SchoolSafeNativeSchool
        || body.data?.year !== window.SchoolSafeNativeYear || !Array.isArray(body.data.cards)) throw new Error('ROSTER_UNAVAILABLE');
    const ready = [], incomplete = [];
    for (const card of body.data.cards) {
      if (Array.isArray(card.missing) && card.missing.length === 0 && card.input && card.issuanceAllowed === false) {
        try {
          const result = { ...card, view: window.SchoolSafeCardAdapter.adaptRosarioCard(card.input) };
          ready.push(window.toRestoredStudioData(result));
          rosterById.set(String(result.nativeReference.studentId), result);
        } catch { incomplete.push({ ...card, missing: ['card.render'] }); }
      } else incomplete.push(card);
    }
    if (ready.length) {
      rosterReady = true;
      window.SchoolSafeStudio.setData(window.mergeRestoredStudioData(ready));
      selectRoster(window._ssPreviewSid);
    }
    renderRosterStatus(ready.length, incomplete);
    window.toast(ready.length ? 'Cartes prêtes. Choisissez un élève ou préparez une classe.'
      : 'Aucune carte prête : complétez les fiches indiquées.');
  } catch {
    window.toast('Liste des cartes indisponible. La recherche par identifiant reste possible.');
  }
}

// Archive ZIP sans compression (PNG deja compresses) : un dossier par eleve, recto.png et verso.png.
const CRC_TABLE = (() => { const t = new Uint32Array(256); for (let n = 0; n < 256; n++) { let c = n;
  for (let k = 0; k < 8; k++) c = c & 1 ? 0xEDB88320 ^ (c >>> 1) : c >>> 1; t[n] = c >>> 0; } return t; })();
function crc32(bytes) { let c = 0xFFFFFFFF; for (let i = 0; i < bytes.length; i++) c = CRC_TABLE[(c ^ bytes[i]) & 255] ^ (c >>> 8); return (c ^ 0xFFFFFFFF) >>> 0; }
function zipStore(files) {
  const encoder = new TextEncoder(), parts = [], central = []; let offset = 0;
  for (const file of files) {
    const name = encoder.encode(file.name), crc = crc32(file.data), size = file.data.length;
    const local = new DataView(new ArrayBuffer(30));
    local.setUint32(0, 0x04034b50, true); local.setUint16(4, 20, true); local.setUint16(6, 0x0800, true);
    local.setUint16(12, 0x21, true); local.setUint32(14, crc, true); local.setUint32(18, size, true);
    local.setUint32(22, size, true); local.setUint16(26, name.length, true);
    parts.push(new Uint8Array(local.buffer), name, file.data);
    const entry = new DataView(new ArrayBuffer(46));
    entry.setUint32(0, 0x02014b50, true); entry.setUint16(4, 20, true); entry.setUint16(6, 20, true);
    entry.setUint16(8, 0x0800, true); entry.setUint16(14, 0x21, true); entry.setUint32(16, crc, true);
    entry.setUint32(20, size, true); entry.setUint32(24, size, true); entry.setUint16(28, name.length, true);
    entry.setUint32(42, offset, true);
    central.push(new Uint8Array(entry.buffer), name);
    offset += 30 + name.length + size;
  }
  const centralSize = central.reduce((n, part) => n + part.length, 0);
  const end = new DataView(new ArrayBuffer(22));
  end.setUint32(0, 0x06054b50, true); end.setUint16(8, files.length, true); end.setUint16(10, files.length, true);
  end.setUint32(12, centralSize, true); end.setUint32(16, offset, true);
  return new Blob([...parts, ...central, new Uint8Array(end.buffer)], { type: 'application/zip' });
}
const safeName = value => String(value || '').normalize('NFC').replace(/[\\/:*?"<>|]+/g, '-').replace(/\s+/g, ' ').trim().slice(0, 80) || 'sans-nom';
function download(blob, name) {
  const link = document.createElement('a');
  link.href = URL.createObjectURL(blob); link.download = name;
  document.body.appendChild(link); link.click(); link.remove();
  setTimeout(() => URL.revokeObjectURL(link.href), 30000);
}
// Capture exactement l'apercu (modele de la classe, QR officiel) : recto puis verso.
async function captureStudent(id) {
  window._ssPreviewSid = id;
  const select = document.getElementById('ss-student-sel'); if (select) select.value = id;
  window.ssRenderPreview();
  await new Promise(resolve => setTimeout(resolve, 300));
  const faces = Array.from(document.querySelectorAll('#ss-card-studio .art')).slice(0, 2);
  if (faces.length !== 2 || faces.some(face => !face.querySelector('[id^="ss-qr-"] canvas, [id^="ss-qr-"] img'))) throw new Error('RENDER_INCOMPLETE');
  const images = [];
  for (const face of faces) {
    const canvas = await window.html2canvas(face, { scale: 3, useCORS: true, backgroundColor: null,
      onclone: doc => { doc.querySelectorAll('.ss-layout-handle,.face-tag').forEach(el => el.remove());
        doc.querySelectorAll('.ss-layout-selected').forEach(el => el.classList.remove('ss-layout-selected')); } });
    const blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/png'));
    images.push(new Uint8Array(await blob.arrayBuffer()));
  }
  return images;
}
let preparing = false;
async function prepareCards(students, label) {
  if (!rosterReady || preparing) return;
  if (!students.length) { window.toast('Aucune carte prête pour cette sélection.'); return; }
  if (typeof window.html2canvas !== 'function') { window.toast('Export indisponible : bibliothèque d’image absente.'); return; }
  preparing = true;
  const current = window._ssPreviewSid, files = [], failed = [];
  try {
    for (const [index, student] of students.entries()) {
      window.toast('Préparation des cartes : ' + (index + 1) + ' / ' + students.length + '…');
      const cl = (DB.classes || []).find(c => c.id === student.cid);
      const folder = safeName(cl ? cl.name : 'Classe') + '/' + safeName(student.name + (student.mat ? ' - ' + student.mat : ''));
      try {
        const [recto, verso] = await captureStudent(student.id);
        files.push({ name: folder + '/recto.png', data: recto }, { name: folder + '/verso.png', data: verso });
      } catch { failed.push(student.name); }
    }
    if (files.length) download(zipStore(files), 'Cartes-' + safeName(label) + '.zip');
    window.toast((files.length / 2) + ' carte(s) téléchargée(s)' + (failed.length ? ' · non préparées : ' + failed.join(', ') : '.'));
  } finally {
    preparing = false;
    window._ssPreviewSid = current; window.ssRenderPreview();
    const select = document.getElementById('ss-student-sel'); if (select) select.value = current;
  }
}
// Boutons existants raccordes aux cartes officielles.
window.ssPrintCard = () => prepareCards(DB.students.filter(s => s.id === window._ssPreviewSid),
  (DB.students.find(s => s.id === window._ssPreviewSid) || {}).name || 'eleve');
window.printClassCards = id => prepareCards(DB.students.filter(s => s.cid === id),
  (DB.classes.find(c => c.id === id) || {}).name || 'classe');
window.exportSSAllCardsZip = () => prepareCards(DB.students.slice(), 'ecole');
window.ssModelPrint = () => { if (rosterReady) officialModelPrint(); };
const exportButton = document.getElementById('export-png');
if (exportButton) exportButton.addEventListener('click', async () => {
  if (!rosterReady || preparing || !window._ssPreviewSid) return;
  try {
    const [recto] = await captureStudent(window._ssPreviewSid);
    const student = DB.students.find(s => s.id === window._ssPreviewSid) || {};
    download(new Blob([recto], { type: 'image/png' }), safeName(student.name) + ' - recto.png');
  } catch { window.toast('Export PNG indisponible pour cet élève.'); }
});
// « Imprimer la carte officielle » : recto/verso officiels relus au serveur puis impression du navigateur.
if (printButton) printButton.addEventListener('click', event => {
  if (!rosterReady) return;
  event.stopImmediatePropagation();
  if (!previewButton || previewButton.disabled) return;
  previewButton.click();
  const started = Date.now();
  const wait = setInterval(() => {
    const print = Array.from(document.querySelectorAll('dialog[open] button')).find(b => b.textContent.includes('Imprimer'));
    if (print) { clearInterval(wait); print.click(); }
    else if (Date.now() - started > 8000) clearInterval(wait);
  }, 200);
}, true);
loadRoster();
