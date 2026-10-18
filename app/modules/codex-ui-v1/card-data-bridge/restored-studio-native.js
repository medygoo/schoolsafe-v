'use strict';
window.installOfficialQrGuard(window);
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
let loadedStudentId = '';
document.getElementById('native-student').addEventListener('input', () => {
  loadedStudentId = ''; issueButton.disabled = true;
});
form.addEventListener('submit', async event => {
  event.preventDefault();
  if (!form.reportValidity()) return;
  const button = form.querySelector('button'); button.disabled = true;
  loadedStudentId = ''; issueButton.disabled = true;
  window.SchoolSafeStudio.setData({settings:{school:{},year:''},students:[],classes:[],users:[],aps:[]});
  window.toast('Chargement Rosario...');
  try {
    const result = await window.SchoolSafeStudioNativeBridge.loadNativeStudioCard(document.getElementById('native-student').value);
    if (result.nativeReference.schoolId !== window.SchoolSafeNativeSchool) throw new Error('NATIVE_SCOPE_CHANGED');
    window.SchoolSafeStudio.setData(window.toRestoredStudioData(result));
    loadedStudentId = result.nativeReference.studentId;
    issueButton.disabled = result.missing.some(key => key !== 'card.official_qr')
      || Boolean(result.input.credential?.qr_payload);
    window.toast('Donnees Rosario chargees. ' + (result.missing.length ? 'Informations manquantes : ' + result.missing.join(', ') : 'Consultation disponible.') + ' Aucune carte emise.');
  } catch {
    window.toast('Dossier inaccessible ou contexte modifie. Aucune operation effectuee.');
  } finally { button.disabled = false; }
});
issueButton.addEventListener('click', async () => {
  if (!loadedStudentId || issueButton.disabled) return;
  issueButton.disabled = true;
  try {
    const response = await fetch('/card-bridge-issue.php', { method: 'POST', credentials: 'same-origin',
      cache: 'no-store', headers: { 'Content-Type':'application/x-www-form-urlencoded', Accept:'application/json' },
      body: new URLSearchParams({ student_id: loadedStudentId,
        csrf: document.getElementById('native-issue-csrf').value }) });
    const result = await response.json();
    if (!response.ok || result.operationPerformed !== true) throw new Error('ISSUE_REJECTED');
    const fresh = await window.SchoolSafeStudioNativeBridge.loadNativeStudioCard(loadedStudentId);
    if (fresh.nativeReference.schoolId !== window.SchoolSafeNativeSchool
        || !fresh.input.credential?.qr_payload) throw new Error('ISSUE_RELOAD_REQUIRED');
    window.SchoolSafeStudio.setData(window.toRestoredStudioData(fresh));
    window.toast('Carte officielle relue depuis le moteur. Impression non raccordee.');
  } catch {
    window.toast('Emission non confirmee. Relisez le dossier avant toute nouvelle tentative.');
  }
});
