'use strict';
// Onglet « Famille et récupération » : actions du guichet famille.
// Écritures via l'API existante scanner-relay.php?operation=management
// (CSRF de session) ; photo via pickup-photo-upload.php. Aucune règle ici.
(() => {
 const root = document.getElementById('ssft');
 if (!root) return;
 const panel = document.getElementById('ssft-panel');
 const status = document.getElementById('ssft-status');
 const student = root.dataset.student;
 const hasPrimary = root.dataset.hasPrimary === '1';
 let csrf = '';
 let busy = false;

 const forms = {
  'register-family': {title: 'Enregistrer un responsable familial', fields: [
   ['fullName', 'Nom complet (tel que sur la pièce d’identité)', 'text', true],
   ['phone', 'Téléphone (notifications)', 'tel', false],
   ['isPrimary', 'Responsable principal de l’enfant', 'checkbox', false]]},
  'register-external': {title: 'Enregistrer une personne accréditée', fields: [
   ['fullName', 'Nom complet (tel que sur la pièce d’identité)', 'text', true],
   ['phone', 'Téléphone', 'tel', false],
   ['reason', 'Lien avec l’enfant et motif', 'text', true],
   ['consent', 'Accord du responsable principal (nom, date)', 'text', true]]},
  'photo-upload': {title: 'Photo prise à l’école', fields: [
   ['photo', 'Photo du visage et du haut du corps', 'file', true]]},
  'photo-review': {title: 'Valider la photo', fields: [
   ['decision', 'Décision', 'decision', true],
   ['reason', 'Vérification effectuée (pièce présentée, ressemblance)', 'text', true]]},
  'school-family-approve': {title: 'Autoriser la récupération par ce responsable', fields: [
   ['reason', 'Pièce d’identité vérifiée (type et numéro)', 'text', true],
   ['consent', 'Signature de la fiche d’autorisation (date)', 'text', true]]},
  'validate-authorization': {title: 'Valider l’accréditation', fields: []},
  'school-retire': {title: 'Retirer cette personne', fields: [
   ['reason', 'Motif du retrait', 'text', true],
   ['consent', 'Demande reçue de (nom, date)', 'text', true]]}
 };

 const errors = {
  PRIMARY_SELECTION_REQUIRED: 'Aucun responsable principal : cochez « Responsable principal » pour ce premier responsable.',
  PRIMARY_PARENT_REQUIRED: 'Le responsable principal doit d’abord avoir un compte parent lié pour désigner un accrédité.',
  PRIMARY_TRANSFER_REQUIRED: 'Le responsable principal ne peut pas être retiré : faites d’abord un transfert de principal à l’école.',
  CONFLICT: 'Action refusée : place déjà occupée, limite atteinte ou personne déjà enregistrée à ce rôle.',
  NOT_FOUND: 'Élément introuvable : rechargez la page.',
  '400': 'Information manquante ou invalide.',
  '403': 'Vous n’avez pas le droit de faire cette action.'
 };

 function say(text, tone) {
  status.textContent = text;
  status.className = 'ssft-status' + (tone ? ' ssft-' + tone : '');
 }

 function field([name, label, type, required]) {
  const wrap = document.createElement('label');
  wrap.className = 'ssft-field';
  const caption = document.createElement('span');
  caption.textContent = label + (required ? ' *' : '');
  let input;
  if (type === 'decision') {
   input = document.createElement('select');
   for (const [value, text] of [['accepted', 'Accepter la photo'], ['rejected', 'Refuser la photo']]) {
    const option = document.createElement('option');
    option.value = value; option.textContent = text; input.append(option);
   }
  } else {
   input = document.createElement('input');
   input.type = type;
   if (type === 'file') { input.accept = 'image/jpeg,image/png,image/webp'; input.setAttribute('capture', 'user'); }
   if (type === 'text' || type === 'tel') input.maxLength = name === 'fullName' ? 200 : name === 'phone' ? 64 : 240;
   if (name === 'isPrimary' && !hasPrimary) { input.checked = true; input.disabled = true; }
  }
  input.name = name;
  input.required = required;
  if (type === 'checkbox') { wrap.classList.add('ssft-check'); wrap.append(input, caption); } else wrap.append(caption, input);
  return wrap;
 }

 function open(button) {
  const action = button.dataset.action;
  const spec = forms[action];
  if (!spec) return;
  panel.replaceChildren();
  const heading = document.createElement('h4');
  heading.textContent = spec.title;
  panel.append(heading);
  spec.fields.forEach(f => panel.append(field(f)));
  const bar = document.createElement('p');
  bar.className = 'ssft-actions';
  const ok = document.createElement('button');
  ok.type = 'button'; ok.className = 'ssft-action ssft-confirm'; ok.textContent = 'Confirmer';
  const cancel = document.createElement('button');
  cancel.type = 'button'; cancel.className = 'ssft-action'; cancel.textContent = 'Annuler';
  cancel.addEventListener('click', () => { panel.hidden = true; say(''); });
  ok.addEventListener('click', () => submit(action, button.dataset, ok));
  bar.append(ok, cancel);
  panel.append(bar);
  panel.hidden = false;
  panel.scrollIntoView({behavior: 'smooth', block: 'center'});
  const first = panel.querySelector('input:not([disabled]),select');
  if (first) first.focus();
 }

 function values() {
  const out = {};
  for (const input of panel.querySelectorAll('[name]')) {
   if (input.type === 'checkbox') out[input.name] = input.checked;
   else if (input.type !== 'file' && input.value.trim()) out[input.name] = input.value.trim();
   if (input.required && input.type !== 'checkbox' && !(input.type === 'file' ? input.files.length : input.value.trim())) return null;
  }
  return out;
 }

 async function submit(action, data, ok) {
  if (busy) return;
  const input = values();
  if (!input) { say('Complétez les champs marqués *.', 'warn'); return; }
  if (!csrf) { say('Session SchoolSafe indisponible : rechargez la page.', 'warn'); return; }
  busy = true; ok.disabled = true; say('Enregistrement…');
  try {
   let response;
   if (action === 'photo-upload') {
    const body = new FormData();
    body.append('nativeStudentId', student);
    body.append('guardianId', data.guardian);
    body.append('photo', panel.querySelector('input[type=file]').files[0]);
    response = await fetch('pickup-photo-upload.php', {method: 'POST', credentials: 'same-origin', cache: 'no-store',
     headers: {'X-SchoolSafe-Csrf': csrf}, body});
   } else {
    const payload = Object.assign({action, nativeStudentId: student}, input);
    if (action === 'register-family') { payload.guardianType = data.type; if (!hasPrimary) payload.isPrimary = true; }
    if (data.guardian) payload.guardianId = data.guardian;
    if (data.authorization) payload.authorizationId = data.authorization;
    response = await fetch('scanner-relay.php?operation=management', {method: 'POST', credentials: 'same-origin', cache: 'no-store',
     headers: {'Content-Type': 'application/json', 'X-SchoolSafe-Csrf': csrf}, body: JSON.stringify(payload)});
   }
   let result = {};
   try { result = await response.json(); } catch (_) { /* réponse non JSON */ }
   if (!response.ok) throw new Error(result.error && errors[result.error] ? result.error : String(response.status));
   say('Enregistré.', 'ok');
   location.reload();
  } catch (error) {
   say(errors[error.message] || 'Action impossible pour le moment (' + error.message + ').', 'warn');
  } finally {
   busy = false; ok.disabled = false;
  }
 }

 root.addEventListener('click', event => {
  const button = event.target.closest('button.ssft-action[data-action]');
  if (button && root.contains(button) && !panel.contains(button)) open(button);
 });

 fetch('scanner-relay.php?operation=capabilities', {credentials: 'same-origin', cache: 'no-store'})
  .then(r => r.ok ? r.json() : Promise.reject())
  .then(d => { if (typeof d.csrfToken === 'string') csrf = d.csrfToken; })
  .catch(() => say('Session SchoolSafe indisponible : les actions sont désactivées.', 'warn'));
})();
