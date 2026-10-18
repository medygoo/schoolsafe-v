const fs=require('node:fs');
const path=require('node:path');
const vm=require('node:vm');
const crypto=require('node:crypto');
const root=path.resolve(__dirname,'../..');
const read=file=>fs.readFileSync(path.join(root,file),'utf8');
const context={window:{}};
vm.runInNewContext(read('app/modules/codex-ui-v1/catalog.js'),context);
const catalog=context.window.SchoolSafeCodexCatalog;
const endpoints={teacherBoard:'/native/scan-control/board/teacher',schoolBoard:'/native/scan-control/board/school',onSiteStudent:'/native/scan-control/on-site?student_id',onSiteClass:'/native/scan-control/on-site?class_id',onSiteSchool:'/native/scan-control/on-site',scanAudit:'/native/scan-control/audit?student_id'};
const safe=x=>String(x||'').replaceAll('|',' / ').replace(/\s+/g,' ');
const matrix=['# LOT 1 - Bouton > ecran > contrat','',
 '211 destinations uniques. Chaque entree principale/sous-menu utilise un identifiant du catalogue; cet identifiant est la cle de raccordement. Les modeles historiques internes aux modules (LOT 0) restent proprietes des moteurs existants. Permissions null = a resoudre par Cursor, jamais un droit accorde par defaut. Les profils ne sont que des metadonnees de demonstration.','',
 '| ID | Branche / bouton | Ecran central | Contrat | Permission precise / capacite | Etat |','| --- | --- | --- | --- | --- | --- |',
 ...catalog.actions.map(a=>'| '+[a.id,a.branch+' / '+a.label,a.type==='read'?'Lecture interactive '+a.reader:a.type==='unavailable'?'Indisponible':'Destination existante / ouverture explicite',a.type==='read'?'GET '+endpoints[a.reader]:a.legacy?JSON.stringify(a.legacy):a.reason,a.permission||'Non etablie; port authorize requis',a.type==='read'?'Teste avec substitut; API reelle non testee':a.type==='unavailable'?'Moteur absent signale':'Navigation testee; raccordement Cursor en attente'].map(safe).join(' | ')+' |')
].join('\n')+'\n';
fs.writeFileSync(path.join(__dirname,'LOT1_MATRICE_RACCORDEMENTS.md'),matrix);
const appFiles=['codex-ui-v1.js','preview.js','preview.html','catalog.js','read-adapter.js','unified-interface.js'].map(n=>'app/modules/codex-ui-v1/'+n).concat(['app/styles/codex-ui-v1/codex-ui-v1.css','app/styles/codex-ui-v1/jaspe-overlay.css']);
const docs=fs.readdirSync(__dirname).filter(n=>n.startsWith('LOT1_')&&n!=='LOT1_MANIFEST.json').map(n=>'docs/codex-ui-v1/'+n).concat(['docs/codex-ui-v1/build-lot1-catalog.cjs','docs/codex-ui-v1/lot1-browser-tests.cjs','docs/codex-ui-v1/package-lot1.cjs']);
const testdir=path.join(__dirname,'lot1-tests');
const tests=fs.readdirSync(testdir).filter(n=>n.endsWith('.png')||n==='results.json').map(n=>'docs/codex-ui-v1/lot1-tests/'+n);
function entry(file){return {file,bytes:fs.statSync(path.join(root,file)).size,sha256:crypto.createHash('sha256').update(fs.readFileSync(path.join(root,file))).digest('hex')};}
const records=read('../TREE.sha256').trim().split(/\r?\n/).map(s=>s.split('\t'));
function verify(base){let count=0,modified=[],missing=[];for(const [file,hash] of records){const p=path.join(base,file);if(!fs.existsSync(p)){missing.push(file);continue;}count++;if(crypto.createHash('sha256').update(fs.readFileSync(p)).digest('hex')!==hash)modified.push(file);}return {checked:count,modified,missing};}
const source=verify(root),baseline=verify(path.join(root,'../baseline'));
if(source.modified.length||source.missing.length||baseline.modified.length||baseline.missing.length)throw new Error('Instantane ou baseline modifie');
const manifest={reference:'CODEX_SCHOOLSAFE_UNIFIED_INTERFACES_LOT1_V1',scope:'isolated_frontend_only',sourceHead:'657aaef34c067173bd44a022d6b83a90010aea89',integrationOwner:'Cursor Desktop',integration:'PENDING',authorization:'LOT 1 uniquement; integration finale non autorisee',sourceIntegrity:source,baselineIntegrity:baseline,appFiles:appFiles.map(entry),documentation:docs.map(entry),tests:tests.map(entry),counts:{branches:catalog.branches.length,roles:Object.keys(catalog.roles).length,destinations:catalog.actions.length,reads:6,unavailable:2,existingScreenPorts:203},realBackendTested:false,changesRosario:'NONE',excluded:catalog.excluded};
fs.writeFileSync(path.join(__dirname,'LOT1_MANIFEST.json'),JSON.stringify(manifest,null,2)+'\n');
console.log(JSON.stringify({appFiles:appFiles.length,documents:docs.length,captures:tests.filter(n=>n.endsWith('.png')).length,sourceIntegrity:source,baselineIntegrity:baseline},null,2));
