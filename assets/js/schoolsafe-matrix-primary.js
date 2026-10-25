'use strict';
(() => {
  const closing = document.querySelector('#ss-closing-matrices');
  const dashboard = document.querySelector('#ss-photo-dashboard');
  if (closing && dashboard && dashboard.parentElement.id === 'body') {
    const extras = document.createElement('section');
    extras.className = 'ss-closing-native-information';
    extras.setAttribute('aria-label','Autres informations scolaires');
    let sibling = dashboard.nextSibling;
    while (sibling) {
      const next = sibling.nextSibling;
      if (sibling.nodeType === 1 && !['SCRIPT','STYLE','LINK','DIALOG'].includes(sibling.tagName)) {
        extras.append(sibling);
      }
      sibling = next;
    }
    if (extras.childElementCount) closing.before(extras);
  }
  const cells = Array.from(document.querySelectorAll('[data-matrix-primary]'));
  if (!cells.length) return;
  const cache = new Map();
  let session;
  const queue = [];
  let active = 0;
  const ACCESS_STATUS = [401, 403];
  function request(url, options, ms) {
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), ms || 8000);
    return fetch(url, Object.assign({}, options, { signal: controller.signal }))
      .finally(() => clearTimeout(timer));
  }
  function load(id) {
    if (cache.has(id)) return cache.get(id);
    const promise = (async () => {
      session ||= request('scanner-relay.php?operation=capabilities', {credentials:'same-origin',cache:'no-store'})
        .then(async response => {
          if (!response.ok) throw new Error(ACCESS_STATUS.includes(response.status) ? 'ACCESS' : 'SERVICE');
          const data = await response.json();
          if (typeof data.csrfToken !== 'string') throw new Error('SERVICE');
          return data.csrfToken;
        })
        .catch(error => {
          session = undefined;
          throw new Error(error && error.name === 'AbortError' ? 'SERVICE' : (error.message === 'ACCESS' ? 'ACCESS' : 'SERVICE'));
        });
      const csrf = await session;
      const response = await request('scanner-relay.php?operation=management', {
        method:'POST', credentials:'same-origin', cache:'no-store',
        headers:{'Content-Type':'application/json','X-SchoolSafe-Csrf':csrf},
        body:JSON.stringify({action:'school-list',nativeStudentId:id})
      });
      if (!response.ok) throw new Error(ACCESS_STATUS.includes(response.status) ? 'ACCESS' : 'SERVICE');
      const data = await response.json();
      if (!Array.isArray(data.family)) throw new Error('SERVICE');
      const primary = data.family.filter(person => person.is_primary === true);
      if (primary.length > 1) return 'Responsables principaux : attribution a verifier';
      if (!primary.length) return 'Aucun responsable principal designe';
      return typeof primary[0].name === 'string' && primary[0].name.trim()
        ? primary[0].name : 'Nom non renseigne';
    })().catch(error => {
      if (error && error.name === 'AbortError') return 'Responsable indisponible';
      return error && error.message === 'ACCESS' ? 'Lecture familiale non autorisee' : 'Responsable indisponible';
    });
    cache.set(id,promise);
    return promise;
  }
  function drain() {
    while (active < 2 && queue.length) {
      const cell = queue.shift();
      active++;
      load(cell.dataset.matrixPrimary).then(label => { cell.textContent = label; })
        .finally(() => { active--; drain(); });
    }
  }
  if ('IntersectionObserver' in window) {
    const pending = new Set(cells);
    const observer = new IntersectionObserver(entries => {
      for (const entry of entries) if (entry.isIntersecting) {
        observer.unobserve(entry.target);
        pending.delete(entry.target);
        queue.push(entry.target);
      }
      drain();
    });
    cells.forEach(cell => observer.observe(cell));
    setTimeout(() => {
      let added = false;
      pending.forEach(cell => { pending.delete(cell); queue.push(cell); added = true; });
      if (added) drain();
    }, 1500);
  } else { queue.push(...cells); drain(); }
})();
