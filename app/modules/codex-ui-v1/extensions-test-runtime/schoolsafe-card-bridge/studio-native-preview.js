import { renderCardPreview } from './studio-original/card-renderer.js';

const form = document.getElementById('lookup');
const student = document.getElementById('student');
const button = document.getElementById('load');
const status = document.getElementById('status');
const details = document.getElementById('details');
const preview = document.getElementById('preview');
const readiness = document.getElementById('readiness');
const missing = document.getElementById('missing');
const schooling = document.getElementById('schooling');
const checkIssuance = document.getElementById('check-issuance');
const issuanceStatus = document.getElementById('issuance-status');
let loadedStudentId = null;
const fieldLabels = {
  'student.last_name': "Nom de l'eleve", 'student.first_name': "Prenom de l'eleve",
  'student.matricule': 'Matricule SchoolSafe', 'student.photo': "Photo de l'eleve",
  'school.name': "Nom de l'ecole", 'school.logo': "Logo de l'ecole",
  'school.address': "Adresse de l'ecole", 'school.phone': "Telephone de l'ecole",
  'school.email': "Adresse e-mail de l'ecole", 'school.website': "Site de l'ecole",
  'school.year': 'Annee scolaire', 'class.mapping': 'Correspondance classe Rosario / Cartes',
  'class.cycle': 'Cycle scolaire confirme',
  'class.tutor': 'Titulaire de classe confirme', 'family.primary_contact': 'Coordonnees du parent principal',
  'card.official_qr': 'Carte officielle non emise',
};
const messages = {
  NATIVE_SESSION_REQUIRED: 'Connectez-vous a Rosario pour consulter les cartes.',
  CARD_BRIDGE_ACCESS_DENIED: 'Votre profil ne peut pas consulter cette carte.',
  NATIVE_ACCESS_DENIED: 'Acces refuse par Rosario.',
  CARD_BRIDGE_UNAVAILABLE: 'Le service Cartes est indisponible. Aucune operation effectuee.',
};

form.addEventListener('submit', async (event) => {
  event.preventDefault();
  if (!form.reportValidity()) return;
  button.disabled = true;
  checkIssuance.disabled = true;
  loadedStudentId = null;
  issuanceStatus.textContent = '';
  preview.replaceChildren();
  details.hidden = true;
  readiness.hidden = true;
  missing.replaceChildren();
  status.textContent = 'Chargement...';
  try {
    const result = await window.SchoolSafeStudioNativeBridge.loadNativeStudioCard(student.value);
    const view = result.view;
    // No class/cycle is fabricated to satisfy the existing renderer.
    if (result.missing.includes('class.mapping') || result.missing.includes('class.cycle')) {
      status.textContent = 'Donnees recues. Classe et cycle requis avant le rendu de la carte.';
    } else {
      renderCardPreview(preview, view.student, view.classData, view.teacher,
        view.year, view.school, view.school.logo);
      status.textContent = view.student.qr ? 'Carte existante - consultation.' : 'Carte non emise - aucun QR actif.';
    }
    details.textContent = [view.student.first_name, view.student.last_name].filter(Boolean).join(' ')
      + ' | Matricule : ' + (view.student.mat || 'Non attribue')
      + ' | Annee : ' + (view.year || 'Non renseignee');
    details.hidden = false;
    for (const key of result.missing) {
      const item = document.createElement('li');
      item.textContent = fieldLabels[key] || 'Information non renseignee';
      missing.append(item);
    }
    const source = result.schoolingSource;
    schooling.textContent = source
      ? 'Niveau Rosario : ' + (source.grade?.title || 'Non renseigne') + '. '
        + (source.scheduleAccess
          ? 'Sections de cours actives : ' + source.courseSections.length + '. Aucun titulaire deduit automatiquement.'
          : 'Consultation des affectations non autorisee pour ce profil.')
      : 'Affectations Rosario non verifiees.';
    readiness.hidden = false;
    loadedStudentId = result.nativeReference.studentId;
    checkIssuance.disabled = false;
  } catch (error) {
    status.textContent = messages[error.message] || 'Impossible de charger la carte. Aucune operation effectuee.';
  } finally {
    button.disabled = false;
  }
});

checkIssuance.addEventListener('click', async () => {
  if (!loadedStudentId) return;
  checkIssuance.disabled = true;
  button.disabled = true;
  issuanceStatus.textContent = 'Verification...';
  try {
    const response = await fetch('/card-bridge-prepare.php?resource=issuance-check&student_id='
      + encodeURIComponent(loadedStudentId), { credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' } });
    const result = await response.json();
    const data = result.data;
    if (!response.ok || !data || data.nativeReference?.studentId !== loadedStudentId
        || data.operationPerformed !== false || data.issuanceAllowed !== false || !Array.isArray(data.blockers)) {
      throw new Error('PREFLIGHT_FAILED');
    }
    issuanceStatus.textContent = (data.blockers.length ? data.blockers.length + ' informations a completer. ' : 'Donnees completes. ')
      + (data.issuerConnected === false ? "Service d'emission non raccorde. " : '')
      + 'Aucune carte emise.';
  } catch {
    issuanceStatus.textContent = 'Verification indisponible. Aucune carte emise.';
  } finally {
    checkIssuance.disabled = false;
    button.disabled = false;
  }
});
