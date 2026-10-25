(() => {
  'use strict';
  const key = 'schoolsafe-cursor-studio-models-v1';
  const catalogue = window._ssCARDS;
  const additions = [
    ['M','Editorial','#145c53','#e1efea','#093c34'],
    ['N','Lignes graphiques','#ba2853','#fce9ee','#701632'],
    ['O','Cadre institutionnel','#2660a5','#e7effa','#14386b'],
    ['P','Mosaïque','#6d4590','#f0e9f6','#412455'],
    ['Q','Atelier','#a24e20','#faeee6','#633011'],
    ['R','Signature','#24777b','#e3f3f3','#144448'],
    ['S','Contraste','#303c42','#edf0f2','#111d23'],
    ['T','Rubans','#ac3054','#f9e8ee','#641c33'],
    ['U','Architecture','#477027','#edf4e6','#2c4519'],
    ['V','Clarté','#316aaf','#e9f2fc','#183e70']
  ];
  additions.forEach(([id,name,cc,soft,dark]) => {
    catalogue.FAMS.push([id,name]);
    catalogue.FGRAD[id] = `linear-gradient(110deg,${dark},${cc})`;
    catalogue.FVARS[id] = [
      {cc,soft,dark,l:name},
      {...catalogue.CARDPAL[2],l:'Lagon'},
      {...catalogue.CARDPAL[4],l:'Ambre'},
      {...catalogue.CARDPAL[5],l:'Rubis'}
    ];
  });
  let state = {school:null,classes:{},students:{}};
  try {
    const saved = JSON.parse(localStorage.getItem(key) || 'null');
    if (saved && typeof saved === 'object') {
      state.school = saved.school || null;
      for (const scope of ['classes','students']) {
        if (saved[scope] && typeof saved[scope] === 'object' && !Array.isArray(saved[scope])) state[scope] = saved[scope];
      }
    }
  } catch (_) {}
  let scope = 'school', target = '', selected = new Set(), activeStudent = null;
  const escape = value => esc(String(value ?? ''));
  function valid(model) {
    if (!model || typeof model !== 'object') return {};
    const result = {};
    if (catalogue.FAMS.some(([id]) => id === model.family)) result.family = model.family;
    if (Number.isInteger(model.variant) && model.variant >= 0 && model.variant < 4) result.variant = model.variant;
    if (['auto','badge','carte'].includes(model.format)) result.format = model.format;
    if (['catalogue','school'].includes(model.palette)) result.palette = model.palette;
    if (model.heritage === 'auto' || catalogue.PATS.some(p => p.v === model.heritage)) result.heritage = model.heritage;
    for (const flag of ['background','stripe','landscape']) if (typeof model[flag] === 'boolean') result[flag] = model[flag];
    return result;
  }
  function resolve(student, cl) {
    return Object.assign({},valid(state.school),valid(state.classes[cl?.id]),valid(state.students[student?.id]));
  }
  function context(model, callback) {
    const family = window._ssCardFam, variant = window._ssCardVar;
    if (model.family) window._ssCardFam = model.family;
    if (model.variant !== undefined) window._ssCardVar = model.variant;
    try { return window.ssLocalDesignBridge.run(model,callback); }
    finally { window._ssCardFam = family; window._ssCardVar = variant; }
  }
  const originalType = window.ssClassType;
  window.ssClassType = cl => {
    const original = originalType(cl), model = resolve(activeStudent,cl);
    return {...original,type:model.format && model.format !== 'auto' ? model.format : original.type};
  };
  const rules = `
    .art[data-design="M"] .head{border-bottom:3px solid var(--ss-cc)}
    .art[data-design="M"] .rw,.art[data-design="M"] .frw{border-radius:0;border-bottom:1px solid var(--ss-cc-soft);box-shadow:none}
    .art[data-design="N"] .bodyz{background:repeating-linear-gradient(135deg,transparent 0 22px,var(--ss-cc-soft) 22px 24px)}
    .art[data-design="N"] .photo{border-radius:4px!important}
    .art[data-design="O"]{border:3px double var(--ss-cc-dark)!important;border-radius:6px!important}
    .art[data-design="O"] .head{background:var(--ss-navy)!important}
    .art[data-design="P"] .bodyz{background:conic-gradient(from 90deg at 1px 1px,var(--ss-cc-soft) 90deg,transparent 0) 0 0/24px 24px}
    .art[data-design="P"] .photo{border-radius:6px!important;outline-width:4px!important}
    .art[data-design="Q"] .rw,.art[data-design="Q"] .frw{border-left:4px solid var(--ss-cc);border-radius:2px;box-shadow:none}
    .art[data-design="Q"] .bodyz{background:linear-gradient(90deg,var(--ss-cc-soft),white)}
    .art[data-design="R"] .head{background:linear-gradient(120deg,var(--ss-navy) 60%,var(--ss-cc))!important}
    .art[data-design="R"] .photo{border-radius:40% 40% 6px 6px!important}
    .art[data-design="S"]{border-radius:0!important;border:2px solid var(--ss-cc-dark)!important}
    .art[data-design="S"] .rw,.art[data-design="S"] .frw{border-radius:0;border:1px solid var(--ss-cc-dark)}
    .art[data-design="T"] .head{border-left:8px solid var(--ss-cc);border-right:8px solid var(--ss-gold)}
    .art[data-design="T"] .rw,.art[data-design="T"] .frw{border-bottom:2px solid var(--ss-cc-soft);border-radius:4px}
    .art[data-design="U"] .bodyz{background:repeating-linear-gradient(0deg,transparent 0 19px,var(--ss-cc-soft) 19px 20px),repeating-linear-gradient(90deg,white 0 19px,var(--ss-cc-soft) 19px 20px)}
    .art[data-design="U"] .photo{border-radius:0!important;outline-style:double!important}
    .art[data-design="V"] .bodyz{background:white}
    .art[data-design="V"] .rw,.art[data-design="V"] .frw{box-shadow:none;border-radius:2px;border-bottom:1px solid var(--ss-cc-soft)}
    .art[data-design="V"] .head{background:linear-gradient(90deg,var(--ss-navy),var(--ss-navy2))!important}
  `;
  ['ssBuildBadge','ssBuildCarte'].forEach(name => {
    const original = window[name];
    window[name] = function(student,cl,teacher,year,pat,...rest) {
      const model = resolve(student,cl);
      const family = model.family || window._ssCardFam || 'L';
      const variant = model.variant ?? window._ssCardVar ?? 0;
      const heritage = catalogue.PATS.find(p => p.v === model.heritage);
      const built = context(model,() => original(student,cl,teacher,year,heritage || pat,...rest));
      for (const face of ['recto','verso']) {
        const template = document.createElement('template');
        template.innerHTML = built[face];
        const card = template.content.querySelector('.art');
        card.dataset.design = family;
        const schoolColors = card.classList.contains('ss-school-palette');
        const colors = catalogue.FVARS[family]?.[variant] || catalogue.FVARS.L[0];
        if (model.palette === 'school') {
          Object.entries(window.ssLocalDesignBridge.palette(family,variant)).forEach(([k,v]) => card.style.setProperty(k,v));
        } else if (model.family && !schoolColors) {
          card.style.setProperty('--ss-cc',colors.cc);
          card.style.setProperty('--ss-cc-soft',colors.soft);
          card.style.setProperty('--ss-cc-dark',colors.dark);
        }
        if (additions.some(([id]) => id === family)) {
          if (!schoolColors) {
            card.style.setProperty('--ss-navy',colors.dark);
            card.style.setProperty('--ss-navy2',colors.cc);
          }
          const style = document.createElement('style'); style.textContent = rules; card.prepend(style);
        }
        // Scope the legacy selectors to each card rather than the global preview.
        const host = document.createElement('div'); host.className = 'ss-card-studio'; host.dataset.fam = family;
        host.append(template.content); built[face] = host.outerHTML;
      }
      return built;
    };
  });
  const preview = window.ssRenderPreview;
  window.ssRenderPreview = function(...args) {
    activeStudent = DB.students.find(s => s.id === window._ssPreviewSid) || null;
    const cl = DB.classes.find(c => c.id === activeStudent?.cid);
    try { return context(resolve(activeStudent,cl),() => preview.apply(this,args)); }
    finally { activeStudent = null; }
  };
  function persist() {
    try { localStorage.setItem(key,JSON.stringify(state)); return true; }
    catch (_) { toast('Enregistrement local impossible','error'); return false; }
  }
  function destination() {
    if (scope === 'school') return {container:state,key:'school'};
    return {container:state[scope === 'class' ? 'classes' : 'students'],key:target};
  }
  window.ssModelScope = value => {scope=value; target=''; render();};
  window.ssModelTarget = value => {target=value; render();};
  window.ssModelSelect = (id,on) => {if(on)selected.add(id);else selected.delete(id);};
  window.ssModelSave = () => {
    const panel = document.getElementById('ss-model-manager');
    const model = {};
    for (const field of ['family','variant','format','palette','heritage']) {
      const value = panel.querySelector('[data-model="'+field+'"]').value;
      if (value !== '') model[field] = field === 'variant' ? Number(value) : value;
    }
    for (const flag of ['background','stripe','landscape']) {
      const value = panel.querySelector('[data-model="'+flag+'"]').value;
      if (value !== '') model[flag] = value === 'on';
    }
    const previous = JSON.parse(JSON.stringify(state));
    if (scope === 'selection') {
      if (!selected.size) return toast('Sélectionnez les élèves','warning');
      selected.forEach(id => {state.students[id] = valid(model);});
    } else {
      if (scope !== 'school' && !target) return toast('Choisissez une destination','warning');
      const dest = destination(); dest.container[dest.key] = valid(model);
    }
    if (!persist()) {state=previous;return;}
    toast('Modèle enregistré localement','success'); render();
  };
  window.ssModelReset = () => {
    const previous = JSON.parse(JSON.stringify(state));
    if(scope === 'selection')selected.forEach(id=>delete state.students[id]);
    else {const dest=destination();if(!dest.key)return;dest.container[dest.key]=scope==='school'?null:undefined;}
    if(!persist()){state=previous;return;} render();
  };
  function select(field,label,entries,value) {
    return `<label>${label}<select class="form-input" data-model="${field}"><option value="">Hériter / choix courant</option>${entries.map(([id,name])=>`<option value="${escape(id)}"${String(value)===String(id)?' selected':''}>${escape(name)}</option>`).join('')}</select></label>`;
  }
  function printStudents(students) {
    if (!students.length) return toast('Aucun élève sélectionné','warning');
    const sheets = [];
    students.forEach(student => {
      const cl = DB.classes.find(c => c.id === student.cid);
      const teacher = DB.users.find(u => u.id === (cl?.titulaire_id || cl?.teacher_id));
      const model = resolve(student,cl);
      activeStudent = student;
      try {
        const built = context(model,() => {
          const format = ssClassType(cl).type;
          const pat = ssGetClassPat(cl,format);
          return (format === 'badge' ? ssBuildBadge : ssBuildCarte)(student,cl,teacher,DB.settings.year || 'Année à renseigner',pat,'none');
        });
        const template = document.createElement('template');
        template.innerHTML = built.recto + built.verso;
        template.content.querySelectorAll('[id]').forEach(el => {
          if (el.id.startsWith('ss-qr-')) {el.dataset.printQr=built.qr;el.removeAttribute('id');}
        });
        sheets.push(`<section class="ss-print-sheet">${template.innerHTML}</section>`);
      } finally { activeStudent = null; }
    });
    const dialog = document.createElement('dialog');
    dialog.style.cssText = 'width:min(1100px,94vw);height:90vh;padding:12px;border:1px solid #ccd3d8;border-radius:6px';
    const close = document.createElement('button');
    close.textContent = 'Fermer la préparation'; close.className = 'btn';
    close.addEventListener('click',()=>{dialog.close();dialog.remove();});
    dialog.addEventListener('cancel',()=>dialog.remove());
    const frame = document.createElement('iframe');
    frame.title = 'Préparation des cartes'; frame.style.cssText = 'width:100%;height:calc(100% - 44px);border:0;display:block;margin-top:8px';
    dialog.append(close,frame); document.body.append(dialog); dialog.showModal();
    const popup = frame.contentWindow;
    const css = Array.from(document.styleSheets).map(sheet => {
      try {return Array.from(sheet.cssRules).map(rule=>rule.cssText).join('\n');} catch (_) {return '';}
    }).join('\n');
    popup.document.write(`<!doctype html><html lang="fr"><head><meta charset="utf-8"><base href="${escape(new URL('.',location.href).href)}"><title>Atelier local - cartes de démonstration</title><style>${css}
      body{margin:16px;background:white}.ss-print-sheet{display:flex;gap:20px;flex-wrap:wrap;justify-content:center;break-inside:avoid;margin:20px 0}.ss-print-sheet .ss-card-studio{flex:none}.ss-print-tools{font:14px sans-serif;margin-bottom:20px}@media print{.ss-print-tools{display:none}.ss-print-sheet{break-after:page}@page{margin:8mm}}</style></head><body><div class="ss-print-tools">ATELIER LOCAL — cartes fictives, non officielles <button onclick="window.print()">Imprimer / PDF</button></div>${sheets.join('')}</body></html>`);
    popup.document.close();
    popup.document.querySelectorAll('[data-print-qr]').forEach(el => {
      if (window.QRCode) new window.QRCode(el,{text:el.dataset.printQr,width:76,height:76});
    });
  }
  window.ssModelPrint = () => {
    const students = DB.students.filter(s => !s.access_blocked);
    printStudents(scope === 'school' ? students : scope === 'class' ? students.filter(s=>s.cid===target) : scope === 'student' ? students.filter(s=>s.id===target) : students.filter(s=>selected.has(s.id)));
  };
  window.ssPrintCard = () => printStudents(DB.students.filter(s=>s.id===window._ssPreviewSid));
  window.printClassCards = id => printStudents(DB.students.filter(s=>s.cid===id&&!s.access_blocked));
  window.exportSSAllCardsZip = () => printStudents(DB.students.filter(s=>!s.access_blocked&&s.name));
  const renderer = R.cards;
  R.cards = function(...args) {
    const html = renderer.apply(this,args);
    if (!html.includes('ss-card-studio')) return html;
    const current = scope === 'school' ? state.school : scope === 'class' ? state.classes[target] : state.students[target];
    const model = valid(current);
    const destinations = scope === 'class' ? DB.classes : scope === 'student' ? DB.students : [];
    const assignments = DB.classes.map(cl=>`<tr><td>${escape(cl.name)}</td><td>${escape(catalogue.FAMS.find(([id])=>id===resolve(null,cl).family)?.[1] || 'Choix courant')}</td><td>${escape(resolve(null,cl).format || 'Automatique')}</td><td>${DB.students.filter(s=>s.cid===cl.id).length}</td></tr>`).join('');
    const panel = `<section id="ss-model-manager" style="padding:18px 0;border-bottom:1px solid var(--border-light);margin-bottom:18px">
      <h3 style="font-size:18px;margin:0 0 12px">Modèles de cartes</h3>
      <div class="ss-model-fields">${select('scope','Destination',[['school','Toute l’école'],['class','Une classe'],['student','Un élève'],['selection','Plusieurs élèves']],scope).replace('data-model="scope"','id="ss-model-scope" onchange="ssModelScope(this.value)"').replace('<option value="">Hériter / choix courant</option>','')}
      ${destinations.length?`<label>${scope==='class'?'Classe':'Élève'}<select class="form-input" id="ss-model-target" onchange="ssModelTarget(this.value)"><option value="">Choisir</option>${destinations.map(d=>`<option value="${escape(d.id)}"${target===d.id?' selected':''}>${escape(d.name)}</option>`).join('')}</select></label>`:''}
      ${select('family','Famille',catalogue.FAMS,model.family)}
      ${select('variant','Variante',[[0,'1'],[1,'2'],[2,'3'],[3,'4']],model.variant)}
      ${select('format','Format',[['auto','Automatique'],['badge','Vertical'],['carte','Horizontal']],model.format)}
      ${select('palette','Palette',[['catalogue','Catalogue'],['school','Trois couleurs de l’école']],model.palette)}
      ${select('heritage','Patrimoine',[['auto','Choix de la classe'],...catalogue.PATS.map(p=>[p.v,p.n])],model.heritage)}
      ${[['background','Fond patrimonial'],['stripe','Bandeau culturel'],['landscape','Photo patrimoniale']].map(([flag,label])=>select(flag,label,[['on','Afficher'],['off','Masquer']],model[flag]===undefined?'':model[flag]?'on':'off')).join('')}</div>
      ${scope==='selection'?`<div style="display:flex;gap:14px;flex-wrap:wrap;margin-top:12px">${DB.students.map(s=>`<label><input type="checkbox"${selected.has(s.id)?' checked':''} onchange="ssModelSelect('${escape(s.id)}',this.checked)"> ${escape(s.name)}</label>`).join('')}</div>`:''}
      <div style="display:flex;gap:10px;flex-wrap:wrap;margin:14px 0"><button class="btn btn-primary" onclick="ssModelSave()">Enregistrer le modèle</button><button class="btn" onclick="ssModelReset()">Rétablir l’héritage</button><button class="btn" onclick="ssModelPrint()">Imprimer la destination</button></div>
      <table style="width:100%;font-size:12px;text-align:left"><thead><tr><th>Classe</th><th>Famille</th><th>Format</th><th>Élèves</th></tr></thead><tbody>${assignments}</tbody></table>
      <style>.ss-model-fields{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:10px}.ss-model-fields label{min-width:0;font-size:12px}.ss-model-fields select{width:100%;margin-top:4px}.ss-model-fields option{white-space:normal}</style>
    </section>`;
    return panel + html.replace('10 familles & patrimoine RDC','21 familles & patrimoine').replace('Maternelle → 4ᵉ Primaire) · 💳 Carte PVC (5ᵉ, 6ᵉ & Humanités)','vertical) · 💳 Carte horizontale');
  };
})();
