/* Read-only source inventory; outputs are confined to this documentation folder. */
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const crypto = require('node:crypto');
const root = path.resolve(__dirname, '../..');
const walk = dir => fs.readdirSync(dir, {withFileTypes:true}).flatMap(e => e.isDirectory() ? walk(path.join(dir,e.name)) : [path.join(dir,e.name)]);
const rel = p => path.relative(root,p).replaceAll('\\','/');
const read = p => fs.readFileSync(path.join(root,p),'utf8');
const line = (s,i) => s.slice(0,i).split('\n').length;
const files = walk(path.join(root,'app')).filter(p => /\.(js|html)$/.test(p) && !/vendor|lucide.min|jspdf|qa-|codex-ui-v1/.test(rel(p)));
const sources = files.map(p=>({file:rel(p),text:fs.readFileSync(p,'utf8')}));
const app = read('app/app.js');
function literal(source, marker) {
  const start = source.indexOf(marker);
  if(start < 0) return null;
  const end = source.indexOf('\n  };',start);
  if(end < 0) return null;
  return vm.runInNewContext('('+source.slice(start+marker.length,end+4).trim()+')',{group:(label,actions)=>({label,actions}),branch:(key,description,groups)=>({key,description,groups})}, {timeout:1000});
}
const roles = literal(app,'var roleCatalog = ');
const branches = literal(app,'var branchDefinitions = ');
const permissions = literal(read('app/modules/core/access.js'),'var BRANCH_PERMISSIONS = ');
const layers = ['visual-output-p1','visual-output-p2a','visual-output-p2b','visual-output-p2c','visual-output-closure-a','visual-output-closure-b1','visual-wiring'];
const bindings=[];
for(const name of layers){
  const file='app/modules/core/'+name+'.js', text=read(file);
  for(const m of text.matchAll(/"([^"\n]+\|[^"\n]+)"\s*:\s*"([^"\n]+)"/g)) bindings.push({key:m[1].replaceAll('\\u2019','\u2019'),target:m[2],file,line:line(text,m.index),layer:name});
  for(const m of text.matchAll(/key === "([^"\n]+\|[^"\n]+)"/g)) bindings.push({key:m[1],target:text.slice(m.index, text.indexOf('\n',m.index)).slice(0,240),file,line:line(text,m.index),layer:name});
}
const domain = file => file==='app/app.js'||file==='app/index.html' ? 'workspace' : (file.split('/')[2]||'workspace');
const buttons=[];
for(const {file,text} of sources){
  for(const m of text.matchAll(/<button\b[^>]*>[\s\S]*?<\/button>/g)){
    const raw=m[0], head=raw.slice(0,raw.indexOf('>')+1);
    const attrs=[...head.matchAll(/(id|data-[\w-]+|aria-label|type)\s*=\s*["']([^"']*)["']/g)].map(a=>({name:a[1],value:a[2]}));
    const identifiers=attrs.filter(a=>a.name==='id'||a.name.startsWith('data-'));
    const candidates=identifiers.flatMap(a=>sources.flatMap(s=>s.text.split('\n').flatMap((t,i)=> t.includes(a.value)&&a.value.length>2&& /addEventListener|querySelector|getElementById|closest|dataset|matches/.test(t) ? [{file:s.file,line:i+1,source:t.trim().slice(0,260)}] : []))).slice(0,12);
    buttons.push({id:'B'+String(buttons.length+1).padStart(4,'0'),kind:file.endsWith('.html')?'HTML':'modele dynamique HTML',file,line:line(text,m.index),domain:domain(file),label:raw.replace(/<[^>]+>/g,' ').replace(/\s+/g,' ').trim().slice(0,200),attributes:attrs,disabled:/\bdisabled\b/.test(head),handlerEvidence:candidates,status:'non verifie',screen:'Conteneur de rendu du fichier; cible a confirmer par gestionnaire',role:'Session Access Law; voir matrice roles et filtrage',permission:'Non etablie pour ce bouton; permissions de domaine seulement',business:'Gestionnaire candidat joint; ne prouve pas une action metier',api:'Voir catalogue API; correspondance individuelle non prouvee',recommended:domain(file)+' > sous-fonction correspondante',source:raw.slice(0,700)});
  }
  for(const m of text.matchAll(/\b(?:ssButton|ssIconButton|createElement)\s*\(\s*(?:\{[\s\S]{0,1800}?\}|["']button["'])\s*\)/g)){
    if(m[0].startsWith('createElement')&&!/button/.test(m[0]))continue;
    buttons.push({id:'B'+String(buttons.length+1).padStart(4,'0'),kind:'generateur/helper',file,line:line(text,m.index),domain:domain(file),label:m[0].slice(0,250),status:'non verifie',role:'Session Access Law',permission:'A confirmer dans les parametres et le gestionnaire',screen:'Rendu du module '+domain(file),business:'Helper de presentation; action du consommateur a tracer',api:'Non etablie',recommended:domain(file)+' > sous-fonction correspondante',source:m[0].slice(0,700)});
  }
}
const menu=[];
for(const [role,profile] of Object.entries(roles)) for(const b of profile.branches) for(const g of b.groups) for(const a of g.actions){
  const key=b.key+'|'+a[0], hits=bindings.filter(x=>x.key===key);
  const first=hits[0];
  const target=first?.target || 'openActionByBranch: route de domaine, details non verifies';
  const report=/Bulletins/.test(a[0]);
  menu.push({id:'M'+String(menu.length+1).padStart(4,'0'),role,roleLabel:profile.label,scope:profile.scope,branch:b.key,group:g.label,label:a[0],current:'workspaceNav > workspaceContextNav; raccourcis dashboard',screen:target,permissionCandidates:permissions[b.key]||[],permissionStatus:'Visibilite de branche seulement; droit exact et DENY a verifier',business:'Voir preuves de routage et catalogue API/RPC; contrat individuel non etabli',api:'Catalogue API du domaine, aucun appariement implicite',status:report?'non raccorde (BACKEND_GAP bulletin)':'non verifie',recommended:(branches[b.key]?.label||b.key)+' > '+g.label+' > '+a[0],evidence:hits,source:'app/app.js:'+line(app,app.indexOf('"'+a[0]+'"',app.indexOf('var roleCatalog')))});
}
const routes=[];
const rpc=[];
for(const p of walk(path.join(root,'server/src')).filter(p=>p.endsWith('.ts'))){
  const text=fs.readFileSync(p,'utf8'),file=rel(p);
  for(const m of text.matchAll(/\b(?:app|server|fastify)\.(get|post|patch|delete|put)\s*(?:<[^>]*>)?\s*\(\s*["']([^"']+)["']/g)) routes.push({method:m[1].toUpperCase(),path:m[2],file,line:line(text,m.index),status:'contrat present; execution non verifiee'});
  for(const m of text.matchAll(/\bapi\.([a-z_][a-z_0-9]*)\s*\(/g)) rpc.push({name:'api.'+m[1],file,line:line(text,m.index)});
}
const sql=[];
for(const p of walk(path.join(root,'database')).filter(p=>p.endsWith('.sql'))){
  const text=fs.readFileSync(p,'utf8'),file=rel(p);
  for(const m of text.matchAll(/create\s+(?:or\s+replace\s+)?function\s+(?:"?api"?\.)"?([a-z_][a-z_0-9]*)"?\s*\(/gi))sql.push({name:'api.'+m[1],file,line:line(text,m.index)});
}
const uniqueSql=[...new Set(sql.map(x=>x.name))];
const noServerReference=uniqueSql.filter(n=>!rpc.some(x=>x.name===n)).map(name=>({name,declarations:sql.filter(x=>x.name===name),status:'candidat sans facade serveur detectee; pas preuve de fonction utilisateur sans ecran'}));
const incomplete=[
 ['studio cartes','app/index.html:881','Production toute ecole desactivee; impression et ZIP bloques selon handoff'],
 ['scanner financier staff','docs/CURRENT_HANDOFF.md:1','Parcours navigateur bloque a ouverture du scanner'],
 ['bulletins','app/modules/core/visual-output-p2c.js:13','Surface report; BACKEND_GAP, pas de bulletin officiel'],
 ['creation comptes personnel','docs/CURRENT_HANDOFF.md:6','503 DEPENDENCY_UNAVAILABLE identite externe'],
 ['recadrage cartes et portraits','docs/CURRENT_HANDOFF.md:14','Parcours non execute'],
 ['RH et paie','app/modules/hr/hr-demo.js','Surface demo; operations reelles non prouvees'],
 ['comptabilite','app/modules/accounting/accounting-treasury-demo.js','Projection demo; comptabilite legale non prouvee'],
 ['inventaire','app/modules/inventory/inventory-demo.js','Surface demo; raccordement reel non prouve'],
 ['communication hors email/push','app/modules/communication/communication-demo.js','Brouillons/publication locale; examiner chaque canal'],
 ['rapports generaux','app/modules/reports/reports-demo.js','Surface demo; ne pas confondre avec routes rapports financiers'],
 ['epreuves certificatives','app/app.js:1406','Preparation seulement; pas de resultats nationaux'],
 ['apercu Codex','app/modules/codex-ui-v1/preview.js:7','Seulement admin teacher parent; actions sans gestionnaire']
];
const summary={date:'2026-10-11',buttonsSource:buttons.length,buttonsHtml:buttons.filter(x=>x.kind==='HTML').length,buttonsDynamic:buttons.filter(x=>x.kind!=='HTML').length,menuRoleInstances:menu.length,menuUniqueActions:new Set(menu.map(x=>x.branch+'|'+x.label)).size,misplacedConfirmed:1,functionsWithoutDirectScreen:6,functionsWithoutScreenCandidates:noServerReference.length,incompleteScreenFamilies:incomplete.length,buttonsWithoutActionConfirmedPreview:28,buttonsWithoutActionApplication:'indetermine: absence de gestionnaire non prouvable par recherche seule',unverifiedMenuFunctions:new Set(menu.filter(x=>x.status==='non verifie').map(x=>x.branch+'|'+x.label)).size,routes:routes.length,sqlFunctions:uniqueSql.length};
const mdCell = x=>String(x??'').replaceAll('|',' / ').replace(/\s+/g,' ').slice(0,500);
const table=(headers,rows)=>'| '+headers.join(' | ')+' |\n| '+headers.map(()=>'---').join(' | ')+' |\n'+rows.map(r=>'| '+r.map(mdCell).join(' | ')+' |').join('\n')+'\n';
const output=(name,data)=>fs.writeFileSync(path.join(__dirname,name), typeof data==='string'?data:JSON.stringify(data,null,2)+'\n');
output('LOT0_BUTTONS.json',buttons);output('LOT0_MENUS.json',menu);output('LOT0_API_RPC.json',{routes,rpc,sql,noServerReference});output('LOT0_COUNTS.json',summary);
output('LOT0_MATRICE_BOUTONS.md','# LOT 0 - Registre des modeles de boutons\n\nExtraction statique de tous les fichiers applicatifs JS/HTML hors vendors, QA et Codex. Une ligne = occurrence source, pas instance DOM. Les helpers generiques peuvent recouvrir les memes boutons: ne pas additionner aux menus. Les fichiers laboratoire et apercus historiques sont inclus; leur chargement est repertorie dans LOT0_SOURCES.json. Les colonnes non etablies sont explicites dans le JSON. Une recherche de gestionnaire est une piste, pas une preuve de cablage.\n\n'+table(['ID','Fichier:ligne / emplacement','Type','Libelle / modele','Etat','Ecran / fonction / API / permission','Recommandation'],buttons.map(b=>[b.id,b.file+':'+b.line,b.kind,b.label,b.status,b.screen+'; '+b.business+'; '+b.api+'; '+b.permission,b.recommended])));
output('LOT0_ORGANISATION_ROLES.md','# LOT 0 - Menu principal > sous-menu > ecran > fonction\n\nProposition: conserver les 13 branches et le panneau independant. Les postes internes restent combinables selon la session; ce catalogue demo ne vaut pas attribution reelle. Aucun droit ne doit etre derive du seul nom du role. Un ecran sans source officielle affiche indisponible.\n\n'+table(['ID / role','Menu','Sous-menu','Bouton','Ecran / routage source','Permission de branche (pas droit exact)','Emplacement recommande'],menu.map(m=>[m.id+' / '+m.roleLabel,m.branch,m.group,m.label,m.screen,m.permissionCandidates.join(', '),m.recommended]))+'\n## Priorites de tableau de bord\n\n'+table(['Profil','Priorite','Perimetre'],Object.entries(roles).map(([k,v])=>[v.label,k==='guard'?'Scanner, presence, retraits, anomalies':k==='parent'?'Mes enfants, presence, devoirs, frais, personnes autorisees':k==='teacher'?'Classes affectees, appel, devoirs, notes, preparation':/finance|cashier|accountant/.test(k)?'Caisse ou supervision selon permission; controle des frais distinct':'Resume des branches autorisees; alertes et decisions accessibles',v.scope])));
output('LOT0_SOURCES.json',sources.map(s=>({file:s.file,lines:s.text.split('\n').length,sha256:crypto.createHash('sha256').update(fs.readFileSync(path.join(root,s.file))).digest('hex'),loadedByIndex:read('app/index.html').includes(s.file.replace(/^app\//,'./'))})));
output('LOT0_ECRANS_INCOMPLETS.md','# LOT 0 - Familles d ecrans incomplets\n\nUnites de comptage: familles, pas nombre de routes ni formulaires. Les preuves historiques ne sont pas des tests executes par cet audit.\n\n'+table(['Famille','Preuve','Limite'],incomplete));
const packageFiles=fs.readdirSync(__dirname).filter(n=>n.startsWith('LOT0_')||n==='lot0-audit.cjs'||n==='CURSOR_CODEX_PRE_INTEGRATION_AUDIT.md');
output('LOT0_MANIFEST.json',{reference:'CODEX_ROSARIO_UI_AUDIT_CLOSURE_V1',status:'READY_FOR_OWNER_REVIEW',lot1:'NOT_AUTHORIZED',integration:'NON_COMMENCEE',newAuditFiles:[...new Set([...packageFiles,'LOT0_MANIFEST.json'])].map(n=>'docs/codex-ui-v1/'+n),preservedVisualFiles:['app/modules/codex-ui-v1/codex-ui-v1.js','app/modules/codex-ui-v1/preview.js','app/modules/codex-ui-v1/preview.html','app/styles/codex-ui-v1/codex-ui-v1.css'].map(file=>({file,sha256:crypto.createHash('sha256').update(fs.readFileSync(path.join(root,file))).digest('hex')})),limitations:['Audit statique; correspondances individuelles non toutes etablies','Aucun test navigateur authentifie ou PostgreSQL','Extraction par motifs: pas preuve de completude DOM']});
console.log(JSON.stringify(summary,null,2));
