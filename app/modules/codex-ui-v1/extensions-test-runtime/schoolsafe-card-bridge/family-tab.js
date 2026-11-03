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
 let accounts = [];
 try { accounts = JSON.parse(root.dataset.accounts || '[]'); } catch (_) { accounts = []; }
 let csrf = '';
 let busy = false;
 let cropper = null;

 const forms = {
  'register-family': {title: 'Enregistrer un responsable familial', fields: [
   ['fullName', 'Nom complet (tel que sur la pièce d’identité)', 'text', true],
   ['phone', 'Téléphone (notifications)', 'tel', false],
   ['isPrimary', 'Responsable principal de l’enfant', 'checkbox', false]]},
  'register-external': {title: 'Enregistrer une personne accréditée (désignée par le principal, présent)', fields: [
   ['fullName', 'Nom complet (tel que sur la pièce d’identité)', 'text', true],
   ['phone', 'Téléphone', 'tel', false],
   ['reason', 'Lien avec l’enfant et motif', 'text', true],
   ['consent', 'Accord du responsable principal (nom, date)', 'text', true]]},
  'photo-upload': {title: 'Photo prise à l’école', fields: [
   ['photo', 'Photo : visage, épaules et bras visibles jusqu’à la taille', 'file', true]]},
  'photo-review': {title: 'Valider la photo', fields: [
   ['decision', 'Décision', 'decision', true],
   ['reason', 'Vérification effectuée (pièce présentée, ressemblance)', 'text', true]]},
  'school-family-approve': {title: 'Autoriser la récupération par ce responsable', fields: [
   ['reason', 'Pièce d’identité vérifiée (type et numéro)', 'text', true],
   ['consent', 'Signature de la fiche d’autorisation (date)', 'text', true]]},
  'link-account': {title: 'Lier le compte parent de ce responsable', fields: [
   ['parentStaffId', 'Compte parent (comptes déjà rattachés à cet enfant)', 'account', true],
   ['reason', 'Pièce d’identité vérifiée (type et numéro)', 'text', true],
   ['consent', 'Demande du responsable (date)', 'text', true]]},
  'validate-authorization': {title: 'Valider l’accréditation', fields: []},
  'school-retire': {title: 'Retirer cette personne', fields: [
   ['reason', 'Motif du retrait', 'text', true],
   ['consent', 'Demande reçue de (nom, date)', 'text', true]]}
 };

 const errors = {
  PRIMARY_SELECTION_REQUIRED: 'Aucun responsable principal : enregistrez d’abord le responsable principal.',
  PRIMARY_PARENT_REQUIRED: 'Le responsable principal doit d’abord avoir un compte parent lié.',
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

 // ---- Recadrage photo : cadre 3:4, guide tête/épaules/bras, déplacement + zoom.
 function makeCropper(file) {
  const view = {w: 300, h: 400};
  const wrap = document.createElement('div');
  wrap.className = 'ssft-crop';
  const canvas = document.createElement('canvas');
  canvas.width = view.w; canvas.height = view.h;
  canvas.className = 'ssft-crop-canvas';
  canvas.setAttribute('aria-label', 'Recadrage de la photo');
  const hint = document.createElement('p');
  hint.className = 'ssft-crop-hint';
  hint.textContent = 'Placez la tête dans l’ovale, les épaules et les bras visibles jusqu’à la taille. Glissez pour déplacer, zoomez avec le curseur.';
  const zoom = document.createElement('input');
  zoom.type = 'range'; zoom.min = '1'; zoom.max = '3'; zoom.step = '0.01'; zoom.value = '1';
  zoom.className = 'ssft-crop-zoom';
  zoom.setAttribute('aria-label', 'Zoom');
  wrap.append(canvas, zoom, hint);
  const ctx = canvas.getContext('2d');
  const img = new Image();
  const state = {base: 1, zoom: 1, x: 0, y: 0, ready: false};

  function clamp() {
   const s = state.base * state.zoom;
   const w = img.naturalWidth * s, h = img.naturalHeight * s;
   state.x = Math.min(0, Math.max(view.w - w, state.x));
   state.y = Math.min(0, Math.max(view.h - h, state.y));
  }
  function draw() {
   if (!state.ready) return;
   const s = state.base * state.zoom;
   ctx.fillStyle = '#e5e7eb'; ctx.fillRect(0, 0, view.w, view.h);
   ctx.drawImage(img, state.x, state.y, img.naturalWidth * s, img.naturalHeight * s);
   ctx.save();
   ctx.strokeStyle = 'rgba(255,255,255,.95)'; ctx.lineWidth = 2; ctx.setLineDash([6, 5]);
   ctx.beginPath(); ctx.ellipse(view.w / 2, 110, 52, 66, 0, 0, Math.PI * 2); ctx.stroke();
   ctx.beginPath(); ctx.moveTo(40, 250); ctx.quadraticCurveTo(view.w / 2, 190, view.w - 40, 250); ctx.stroke();
   ctx.beginPath(); ctx.moveTo(40, 250); ctx.lineTo(28, 392); ctx.moveTo(view.w - 40, 250); ctx.lineTo(view.w - 28, 392); ctx.stroke();
   ctx.setLineDash([]); ctx.fillStyle = 'rgba(15,23,42,.55)'; ctx.fillRect(0, view.h - 22, view.w, 22);
   ctx.fillStyle = '#fff'; ctx.font = '12px sans-serif'; ctx.textAlign = 'center';
   ctx.fillText('jusqu’à la taille', view.w / 2, view.h - 7);
   ctx.restore();
  }
  function zoomTo(value) {
   const cx = view.w / 2, cy = view.h / 2, before = state.base * state.zoom;
   state.zoom = value;
   const after = state.base * state.zoom;
   state.x = cx - (cx - state.x) * after / before;
   state.y = cy - (cy - state.y) * after / before;
   clamp(); draw();
  }
  img.onload = () => {
   state.base = Math.max(view.w / img.naturalWidth, view.h / img.naturalHeight);
   state.x = (view.w - img.naturalWidth * state.base) / 2;
   state.y = (view.h - img.naturalHeight * state.base) / 2;
   state.ready = true; clamp(); draw();
  };
  img.src = URL.createObjectURL(file);
  let drag = null;
  canvas.addEventListener('pointerdown', e => { drag = {x: e.clientX, y: e.clientY}; canvas.setPointerCapture(e.pointerId); });
  canvas.addEventListener('pointermove', e => {
   if (!drag) return;
   const r = canvas.getBoundingClientRect(), k = view.w / r.width;
   state.x += (e.clientX - drag.x) * k; state.y += (e.clientY - drag.y) * k;
   drag = {x: e.clientX, y: e.clientY}; clamp(); draw();
  });
  canvas.addEventListener('pointerup', () => { drag = null; });
  canvas.addEventListener('pointercancel', () => { drag = null; });
  zoom.addEventListener('input', () => zoomTo(Number(zoom.value)));

  return {
   element: wrap,
   async export() {
    if (!state.ready) throw new Error('400');
    const out = document.createElement('canvas');
    out.width = 600; out.height = 800;
    const k = out.width / view.w, s = state.base * state.zoom * k;
    const octx = out.getContext('2d');
    octx.fillStyle = '#fff'; octx.fillRect(0, 0, out.width, out.height);
    octx.drawImage(img, state.x * k, state.y * k, img.naturalWidth * s, img.naturalHeight * s);
    const blob = await new Promise(r => out.toBlob(r, 'image/jpeg', 0.88));
    if (!blob) throw new Error('400');
    return new File([blob], 'photo.jpg', {type: 'image/jpeg'});
   }
  };
 }

 function field([name, label, type, required]) {
  const wrap = document.createElement('label');
  wrap.className = 'ssft-field';
  const caption = document.createElement('span');
  caption.textContent = label + (required ? ' *' : '');
  let input;
  if (type === 'decision' || type === 'account') {
   input = document.createElement('select');
   const choices = type === 'decision'
    ? [['accepted', 'Accepter la photo'], ['rejected', 'Refuser la photo']]
    : accounts.map(a => [String(a.id), a.name + ' (#' + a.id + ')']);
   for (const [value, text] of choices) {
    const option = document.createElement('option');
    option.value = value; option.textContent = text; input.append(option);
   }
  } else {
   input = document.createElement('input');
   input.type = type;
   if (type === 'file') {
    input.accept = 'image/jpeg,image/png,image/webp';
    input.setAttribute('capture', 'user');
    input.addEventListener('change', () => {
     const old = panel.querySelector('.ssft-crop');
     if (old) old.remove();
     cropper = input.files.length ? makeCropper(input.files[0]) : null;
     if (cropper) wrap.after(cropper.element);
    });
   }
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
  cropper = null;
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
  cancel.addEventListener('click', () => { panel.hidden = true; cropper = null; say(''); });
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
    body.append('photo', cropper ? await cropper.export() : panel.querySelector('input[type=file]').files[0]);
    response = await fetch('pickup-photo-upload.php', {method: 'POST', credentials: 'same-origin', cache: 'no-store',
     headers: {'X-SchoolSafe-Csrf': csrf}, body});
   } else {
    const api = action === 'link-account' ? 'school-family-approve' : action;
    const payload = Object.assign({action: api, nativeStudentId: student}, input);
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
