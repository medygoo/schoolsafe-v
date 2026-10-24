'use strict';
window.installOfficialQrGuard(window);
// Native issuance must not fall through to the restored demo printer.
const unavailableProduction = () => window.toast('Impression officielle non raccordee. Aucune operation effectuee.');
for (const name of ['ssModelPrint', 'ssPrintCard', 'printClassCards', 'exportSSAllCardsZip']) {
  window[name] = unavailableProduction;
}
window.ssSetLocalSchoolText = () => window.toast('Coordonnees gerees dans Rosario.');
const panel = document.getElementById('PC');
function protect() {
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
    window.SchoolSafeStudio.setData(window.toRestoredStudioData(result));
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
    }
  }
});
