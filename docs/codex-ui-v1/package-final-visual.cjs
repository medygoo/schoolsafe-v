const fs=require('node:fs'),path=require('node:path'),crypto=require('node:crypto');
const root=path.resolve(__dirname,'../..');
const catalog=require('../../app/modules/codex-ui-v1/feature-catalog.js');
const read=f=>JSON.parse(fs.readFileSync(path.join(__dirname,f),'utf8'));
const menu=read('LOT1_BUTTON_SCREEN_MAPPING.json'),specs=read('LOT2_SCREEN_SPECS.json');
const hash=p=>crypto.createHash('sha256').update(fs.readFileSync(p)).digest('hex');
const entry=file=>({file,bytes:fs.statSync(path.join(root,file)).size,sha256:hash(path.join(root,file))});
const previous=read('SCHOOL_WORKFLOWS_MANIFEST.json'),old=new Map(previous.appFiles.map(e=>[e.file,e.sha256]));
const schoolDirect=['rosario-152','rosario-012','rosario-155','rosario-151'];
const schoolEntries=['rosario-149','rosario-150','rosario-007','rosario-008','rosario-153'];
const coverage=menu.actions.map(a=>{
 const r=catalog.routes[a.id],s=specs.specs[a.id],d=r?catalog.definitions[r.key]:null;
 const presentation=d?(r.entry?s.definition+' + '+d.key:d.key):schoolDirect.includes(a.id)?'school-workflow':s.definition;
 const surface=d&&!r.entry?(a.type==='unavailable'?'EXPLICIT_UNAVAILABLE_SCREEN':'NEW_DEDICATED_PREVIEW'):schoolDirect.includes(a.id)?'SCHOOL_WORKFLOW_PREVIEW':a.type==='read'?'PRESERVED_READ_PREVIEW':d||schoolEntries.includes(a.id)?'ENHANCED_SPECIALIZED_PREVIEW':'PRESERVED_SPECIALIZED_PREVIEW';
 return {id:a.id,branch:a.branch,group:a.group,label:a.label,presentation,surface,roles:Object.entries(menu.roles).filter(([,r])=>r.actions.includes(a.id)).map(([id])=>id),permission:a.permission||null,permissionHint:s.permission,layout:d?.layout||null,fields:d?.fields.map(f=>({key:f.key,label:f.label,type:f.type}))||[],localDraftFields:d?.form||[],source:d?.source||s.contractSource,candidateContracts:s.contracts||[],contractValidation:'NOT_VERIFIED_LIVE',businessOperational:false};
});
if(coverage.length!==211||new Set(coverage.map(r=>r.id)).size!==211)throw new Error('Unexpected catalogue');
if(coverage.some(r=>r.presentation==='legacy'))throw new Error('Uncovered legacy destination');
const original=fs.readFileSync(path.join(root,'../TREE.sha256'),'utf8').trim().split(/\r?\n/).map(line=>line.split('\t'));
function verify(base){let checked=0;const modified=[],missing=[];for(const [file,expected]of original){const target=path.join(base,file);if(!fs.existsSync(target)){missing.push(file);continue;}checked++;if(hash(target)!==expected)modified.push(file);}if(modified.length||missing.length)throw new Error(JSON.stringify({base,modified,missing}));return {checked,modified,missing};}
const reports=['feature-tests/results.json','school-workflows-tests/results.json','record-tests/results.json','lot2-tests/results.json','pro-max-tests/results.json','overview-composition-tests/results.json'];
const tests=reports.map(name=>{const data=read(name);const passed=data.status==='PASS'||(!data.status&&Array.isArray(data.results)&&data.results.length>0&&data.results.every(r=>r.status==='PASS'));if(!passed||data.errors?.length||data.apiRequests?.length||!data.date)throw new Error('Failed or incomplete test '+name);return {...entry('docs/codex-ui-v1/'+name),date:data.date,status:'PASS'};});
const sources=[...new Set(Object.values(catalog.definitions).map(d=>d.source))].map(entry);
const safe=v=>String(v??'').replaceAll('|',' / ').replace(/\s+/g,' ');
fs.writeFileSync(path.join(__dirname,'FINAL_VISUAL_COVERAGE.json'),JSON.stringify(coverage,null,2)+'\n');
fs.writeFileSync(path.join(__dirname,'FINAL_VISUAL_COVERAGE.csv'),'id,branch,label,presentation,surface,permission,contractValidation\n'+coverage.map(r=>[r.id,r.branch,r.label,r.presentation,r.surface,r.permission,r.contractValidation].map(v=>'"'+String(v??'').replaceAll('"','""')+'"').join(',')).join('\n')+'\n');
fs.writeFileSync(path.join(__dirname,'FINAL_VISUAL_COVERAGE.md'),['# Couverture visuelle des 211 destinations','','Etat de la preview, pas validation metier. Voir FINAL_VISUAL_HANDOFF.md. Les roles sont des postes de presentation, pas des grants IAM.','','| ID | Branche | Fonction | Presentation | Etat visuel |','| --- | --- | --- | --- | --- |',...coverage.map(r=>'| '+[r.id,r.branch,r.label,r.presentation,r.surface].map(safe).join(' | ')+' |'),''].join('\n'));
function files(dir){return fs.readdirSync(path.join(root,dir)).filter(n=>fs.statSync(path.join(root,dir,n)).isFile()).map(n=>dir+'/'+n);}
const appFiles=['app/modules/codex-ui-v1','app/styles/codex-ui-v1'].flatMap(files).filter(f=>/\.(html|js|css)$/.test(f)).map(entry).map(e=>({...e,change:old.has(e.file)?old.get(e.file)===e.sha256?'UNCHANGED':'MODIFIED_CODEX_ONLY':'NEW'}));
const docs=['FINAL_VISUAL_HANDOFF.md','FINAL_VISUAL_COVERAGE.json','FINAL_VISUAL_COVERAGE.csv','FINAL_VISUAL_COVERAGE.md','feature-screens-tests.cjs','school-workflows-tests.cjs','record-presentation-tests.cjs','lot2-browser-tests.cjs','ui-ux-pro-max-tests.cjs','overview-composition-tests.cjs','package-final-visual.cjs','build-cursor-visual-package.ps1'].map(f=>'docs/codex-ui-v1/'+f).concat(files('docs/codex-ui-v1/feature-tests'));
const diagnostic=entry('docs/codex-ui-v1/SCHOOLSAFE_NON_RACCORDEMENT_CURSOR_V1.zip');
if(diagnostic.sha256!==previous.historicalAudit.sha256)throw new Error('Historical diagnostic archive changed');
const counts={catalogueDestinations:coverage.length,branches:menu.branches.length,roles:Object.keys(menu.roles).length,newDedicatedDestinations:Object.values(catalog.routes).filter(r=>!r.entry).length,enhancedDestinations:Object.values(catalog.routes).filter(r=>r.entry).length,presentations:Object.keys(catalog.definitions).length,layouts:new Set(Object.values(catalog.definitions).map(d=>d.layout)).size,localFormTypes:Object.values(catalog.definitions).filter(d=>d.form.length).length,responsiveVariants:read('feature-tests/results.json').responsiveVariants,uncoveredLegacyDestinations:0};
const manifest={reference:'CODEX_SCHOOLSAFE_VISUAL_COVERAGE_V1',date:new Date().toISOString(),sourceHead:previous.sourceHead,originalUncommittedChangesPreserved:true,scope:'ISOLATED_CODEX_PREVIEW_ONLY',sourceIntegrity:verify(root),baselineIntegrity:verify(path.join(root,'../baseline')),sourceControls:['../MANIFEST.json','../TREE.sha256'].map(entry),appFiles,documentation:docs.map(entry),sources,tests,counts,historicalDiagnostic:diagnostic,statuses:{visualCatalogue:'READY_FOR_OWNER_REVIEW',productionDesignAcceptance:'PENDING',businessOperational:false,rosarioNewChanges:'NONE',cursorIntegration:'NOT_STARTED',liveE2E:'NOT_EXECUTED'}};
fs.writeFileSync(path.join(__dirname,'FINAL_VISUAL_MANIFEST.json'),JSON.stringify(manifest,null,2)+'\n');console.log(JSON.stringify({counts,sourceIntegrity:manifest.sourceIntegrity,baselineIntegrity:manifest.baselineIntegrity,changes:appFiles.filter(e=>e.change!=='UNCHANGED').map(e=>({file:e.file,change:e.change})),tests},null,2));
