window.DB={settings:{school:{name:'École de démonstration',motto:'Savoir · Respect · Avenir'},year:'2026-2027'},students:[{id:'demo-1',name:'Élève Exemple Un',mat:'DEMO-001',dob:'2017-01-01',cid:'demo-class-1'},{id:'demo-2',name:'Élève Exemple Deux',mat:'DEMO-002',dob:'2014-01-01',cid:'demo-class-2'}],classes:[{id:'demo-class-1',name:'3e Primaire',cycle:'primaire',card_pat:'rdc-okapi'},{id:'demo-class-2',name:'6e Primaire',cycle:'primaire',card_pat:'min-or'}],users:[],aps:[]};
window.esc=value=>String(value??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
window._schoolInfo=()=>({...DB.settings.school,slogan:DB.settings.school.motto||DB.settings.school.slogan||''});
window.toast=message=>{document.getElementById('status').textContent=message;};
window.R={};
R.cards=()=>{
 const c=window._ssCARDS,fam=window._ssCardFam||'L',variant=window._ssCardVar||0;
 const opts='<option value="auto">Automatique</option>'+c.PATS.map(p=>`<option value="${esc(p.v)}">${esc(p.n)}</option>`).join('');
 return `<div class="controls">${c.FAMS.map(([id,name])=>`<button class="ss-fam-btn ${id===fam?'active':''}" onclick="window._ssCardFam='${id}';window._ssCardVar=0;render()">${id} · ${esc(name)}</button>`).join('')}</div>
 <div class="controls">${c.FVARS[fam].map((v,i)=>`<button class="ss-var-btn" title="${esc(v.l)}" data-idx="${i}" data-cc="${v.cc}" style="width:32px;height:32px;background:${v.cc};border:3px solid ${i===variant?'#202c36':'white'}" onclick="window._ssCardVar=${i};ssRenderPreview()" aria-label="${esc(v.l)}"></button>`).join('')}</div>
 <select id="ss-pat-style"><option value="fond">Fond</option></select>
 <div class="controls"><label>Patrimoine vertical <select id="ss-pat-badge" onchange="ssRenderPreview()">${opts}</select></label><label>Patrimoine horizontal <select id="ss-pat-carte" onchange="ssRenderPreview()">${opts}</select></label><label>Élève <select id="ss-student-sel" onchange="window._ssPreviewSid=this.value;ssRenderPreview()">${DB.students.map(s=>`<option value="${esc(s.id)}" ${s.id===window._ssPreviewSid?'selected':''}>${esc(s.name)}</option>`).join('')}</select></label></div>
 <div id="ss-type-label"></div><div class="ss-card-studio" id="ss-card-studio" data-fam="${fam}"></div><div class="controls ss-production"><button onclick="ssPrintCard()">Préparer cet élève</button><label>Salle de classe <select id="ss-prepare-class">${DB.classes.map(cl=>`<option value="${esc(cl.id)}">${esc(cl.name)}</option>`).join('')}</select></label><button onclick="printClassCards(document.getElementById('ss-prepare-class').value)" ${DB.classes.length?'':'disabled'}>Préparer cette classe</button><button onclick="exportSSAllCardsZip()">Préparer toute l’école</button></div>`;
};
window.render=()=>{document.getElementById('PC').innerHTML=R.cards();ssRenderPreview();};
window.SchoolSafeStudio={setData(data){
 if(!data||!Array.isArray(data.students)||!Array.isArray(data.classes))throw new Error('students et classes doivent être des listes');
 for(const list of [data.students,data.classes]){
  if(list.some(x=>!x||typeof x.id!=='string'||typeof x.name!=='string')||new Set(list.map(x=>x.id)).size!==list.length)throw new Error('Identifiants uniques et noms requis');
 }
 if(data.students.some(s=>!data.classes.some(c=>c.id===s.cid)))throw new Error('Classe inconnue pour un élève');
 const imageSafe=value=>typeof value==='string'&&(value.startsWith('data:image/')||!/[<>"'\s]/.test(value)&&!value.startsWith('javascript:'));
 if(data.students.some(s=>s.photo&&!imageSafe(s.photo)))throw new Error('Photo invalide');
 if(data.settings?.school?.logo&&!imageSafe(data.settings.school.logo))throw new Error('Logo invalide');
 DB.students=data.students;DB.classes=data.classes;DB.users=Array.isArray(data.users)?data.users:[];DB.aps=Array.isArray(data.aps)?data.aps:[];
 DB.settings=data.settings||{school:{}};DB.settings.school ||= {};window.SCHOOL_LOGO=DB.settings.school.logo||null;
 window._ssPreviewSid=DB.students[0]?.id||null;render();
}};
