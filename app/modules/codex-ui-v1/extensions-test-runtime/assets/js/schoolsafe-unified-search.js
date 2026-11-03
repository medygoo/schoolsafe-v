'use strict';
// Barre de recherche unique : remplace visuellement le formulaire natif
// « Trouver un élève / un utilisateur » (form#search) et enrichit la barre du
// tableau de bord. Le formulaire natif reste intact sous « Recherche avancée ».
(() => {
 if (window.SchoolSafeUnifiedSearch) return;
 window.SchoolSafeUnifiedSearch = true;

 const css = document.createElement('link');
 css.rel = 'stylesheet';
 css.href = 'assets/css/schoolsafe-unified-search.css?v=r2';
 document.head.append(css);

 function currentModname(form) {
  try { return new URL(form.getAttribute('action') || location.href, location.href).searchParams.get('modname'); }
  catch (_) { return null; }
 }

 function typeOf(form) {
  if (form.querySelector('[name=usrid],[name=username],select[name=profile]')) return 'staff';
  if (form.querySelector('[name=last],[name=stuid]')) return 'student';
  return null;
 }

 function attach(input, type, openRecord, onEnter) {
  const list = document.createElement('ul');
  list.className = 'ssus-list';
  list.setAttribute('role', 'listbox');
  list.hidden = true;
  input.insertAdjacentElement('afterend', list);
  input.setAttribute('autocomplete', 'off');
  input.setAttribute('role', 'combobox');
  input.setAttribute('aria-expanded', 'false');
  let timer = 0, items = [], active = -1, seq = 0;

  function render() {
   list.replaceChildren();
   items.forEach((item, i) => {
    const li = document.createElement('li');
    li.setAttribute('role', 'option');
    li.className = 'ssus-item' + (i === active ? ' ssus-active' : '');
    const name = document.createElement('span'); name.className = 'ssus-name'; name.textContent = item.label;
    const detail = document.createElement('span'); detail.className = 'ssus-detail';
    detail.textContent = [item.detail, '#' + item.id].filter(Boolean).join(' · ');
    li.append(name, detail);
    li.addEventListener('mousedown', e => { e.preventDefault(); openRecord(item); });
    list.append(li);
   });
   if (!items.length && input.value.trim().length >= 2) {
    const li = document.createElement('li'); li.className = 'ssus-empty'; li.textContent = 'Aucun résultat';
    list.append(li);
   }
   list.hidden = !list.childElementCount;
   input.setAttribute('aria-expanded', String(!list.hidden));
  }

  input.addEventListener('input', () => {
   clearTimeout(timer);
   const q = input.value.trim();
   if (q.length < 2) { items = []; active = -1; list.hidden = true; return; }
   timer = setTimeout(async () => {
    const mine = ++seq;
    try {
     const r = await fetch('schoolsafe-search.php?type=' + type + '&q=' + encodeURIComponent(q), {credentials: 'same-origin', cache: 'no-store'});
     const body = r.ok ? await r.json() : {data: []};
     if (mine !== seq) return;
     items = Array.isArray(body.data) ? body.data : []; active = items.length ? 0 : -1; render();
    } catch (_) { /* recherche native toujours disponible */ }
   }, 180);
  });
  input.addEventListener('keydown', e => {
   if (e.key === 'ArrowDown' && items.length) { active = (active + 1) % items.length; render(); e.preventDefault(); }
   else if (e.key === 'ArrowUp' && items.length) { active = (active - 1 + items.length) % items.length; render(); e.preventDefault(); }
   else if (e.key === 'Escape') { list.hidden = true; }
   else if (e.key === 'Enter') {
    e.preventDefault();
    if (active >= 0 && items[active]) openRecord(items[active]); else onEnter(input.value.trim());
   }
  });
  input.addEventListener('blur', () => setTimeout(() => { list.hidden = true; }, 150));
 }

 function enhanceNative(form) {
  if (form.dataset.ssus) return;
  const type = typeOf(form);
  const modname = currentModname(form);
  if (!type || !modname) return;
  form.dataset.ssus = '1';
  const box = document.createElement('div');
  box.className = 'ssus-box';
  const input = document.createElement('input');
  input.type = 'search';
  input.className = 'ssus-input';
  input.placeholder = type === 'student' ? 'Rechercher un élève : nom, prénom ou numéro' : 'Rechercher une personne : nom, prénom, identifiant ou numéro';
  input.setAttribute('aria-label', input.placeholder);
  const tools = document.createElement('p');
  tools.className = 'ssus-tools';
  const all = document.createElement('button');
  all.type = 'button'; all.className = 'ssus-link';
  all.textContent = type === 'student' ? 'Afficher tous les élèves' : 'Afficher toutes les personnes';
  const advanced = document.createElement('button');
  advanced.type = 'button'; advanced.className = 'ssus-link';
  advanced.textContent = 'Recherche avancée';
  tools.append(all, advanced);
  box.append(input, tools);
  const holder = form;
  holder.insertAdjacentElement('beforebegin', box);
  holder.classList.add('ssus-hidden');

  const key = type === 'student' ? 'student_id' : 'staff_id';
  const nativeField = form.querySelector('[name=last]');
  const submitNative = value => {
   for (const el of form.querySelectorAll('input[type=text],input:not([type]),input[type=search]')) if (el !== input) el.value = '';
   if (nativeField && value) nativeField.value = value;
   form.requestSubmit ? form.requestSubmit() : form.submit();
  };
  attach(input, type, item => { location.href = 'Modules.php?modname=' + encodeURIComponent(modname) + '&' + key + '=' + item.id; }, submitNative);
  all.addEventListener('click', () => submitNative(''));
  advanced.addEventListener('click', () => {
   const hidden = holder.classList.toggle('ssus-hidden');
   advanced.textContent = hidden ? 'Recherche avancée' : 'Masquer la recherche avancée';
  });
  if (!matchMedia('(max-width: 700px)').matches) input.focus({preventScroll: true});
 }

 function enhanceDashboard(form) {
  if (form.dataset.ssus) return;
  const input = form.querySelector('input[name=last]');
  if (!input) return;
  form.dataset.ssus = '1';
  form.classList.add('ssus-dashboard');
  input.placeholder = 'Rechercher un élève : nom, prénom ou numéro';
  attach(input, 'student', item => { location.href = 'Modules.php?modname=Students%2FStudent.php&student_id=' + item.id; },
   () => form.requestSubmit ? form.requestSubmit() : form.submit());
 }

 function scan() {
  document.querySelectorAll('form#search').forEach(enhanceNative);
  document.querySelectorAll('form.ss-dashboard-search').forEach(enhanceDashboard);
 }

 scan();
 new MutationObserver(() => scan()).observe(document.documentElement, {childList: true, subtree: true});
})();
