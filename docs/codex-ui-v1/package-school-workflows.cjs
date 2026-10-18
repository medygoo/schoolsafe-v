const fs=require('node:fs'),path=require('node:path'),crypto=require('node:crypto');
const root=path.resolve(__dirname,'../..');
const hash=file=>crypto.createHash('sha256').update(fs.readFileSync(file)).digest('hex');
const entry=file=>({file,bytes:fs.statSync(path.join(root,file)).size,sha256:hash(path.join(root,file))});
const original=fs.readFileSync(path.join(root,'../TREE.sha256'),'utf8').trim().split(/\r?\n/).map(line=>line.split('\t'));
function verify(base){
  const modified=[],missing=[];let checked=0;
  for(const [file,expected]of original){const target=path.join(base,file);if(!fs.existsSync(target)){missing.push(file);continue;}checked++;if(hash(target)!==expected)modified.push(file);}
  if(modified.length||missing.length)throw new Error(JSON.stringify({base,modified,missing}));
  return {checked,modified,missing};
}
const previous=JSON.parse(fs.readFileSync(path.join(__dirname,'LOT2_MANIFEST.json'),'utf8'));
const old=new Map(previous.appFiles.map(e=>[e.file,e.sha256]));
const appFiles=['app/modules/codex-ui-v1','app/styles/codex-ui-v1'].flatMap(dir=>fs.readdirSync(path.join(root,dir)).filter(name=>/\.(html|js|css)$/.test(name)).map(name=>dir+'/'+name)).map(entry).map(e=>({...e,change:!old.has(e.file)?'NEW':old.get(e.file)===e.sha256?'UNCHANGED':'MODIFIED_CODEX_ONLY'}));
const reports=['school-workflows-tests/results.json','record-tests/results.json','lot2-tests/results.json'];
const testReports=reports.map(name=>{
  const file='docs/codex-ui-v1/'+name,data=JSON.parse(fs.readFileSync(path.join(root,file),'utf8'));
  if(data.status&&data.status!=='PASS'||data.results?.some(r=>r.status!=='PASS')||data.errors?.length||data.apiRequests?.length)throw new Error('Failed test: '+name);
  return {...entry(file),date:data.date||data.generatedAt||null,status:'PASS'};
});
const artifacts=['SCHOOL_WORKFLOWS_HANDOFF.md','school-workflows-tests.cjs','package-school-workflows.cjs'].map(name=>'docs/codex-ui-v1/'+name).concat(fs.readdirSync(path.join(__dirname,'school-workflows-tests')).map(name=>'docs/codex-ui-v1/school-workflows-tests/'+name));
const manifest={reference:'CODEX_SCHOOL_WORKFLOWS_PREVIEW_V1',date:new Date().toISOString(),scope:'ISOLATED_PREVIEW_ONLY',sourceHead:'657aaef34c067173bd44a022d6b83a90010aea89',originalUncommittedChangesPreserved:true,sourceIntegrity:verify(root),baselineIntegrity:verify(path.join(root,'../baseline')),sourceControls:['../MANIFEST.json','../TREE.sha256'].map(entry),appFiles,artifacts:artifacts.map(entry),testReports,counts:{presentations:5,destinations:9,responsiveVariants:90},statuses:{visualBatch:'READY_FOR_OWNER_REVIEW',designComplete:false,realBusinessIntegration:'NOT_EXECUTED',cursorIntegration:'NOT_STARTED',changesRosario:'NONE'},historicalAudit:entry('docs/codex-ui-v1/SCHOOLSAFE_NON_RACCORDEMENT_CURSOR_V1.zip')};
fs.writeFileSync(path.join(__dirname,'SCHOOL_WORKFLOWS_MANIFEST.json'),JSON.stringify(manifest,null,2)+'\n');
console.log(JSON.stringify({sourceIntegrity:manifest.sourceIntegrity,baselineIntegrity:manifest.baselineIntegrity,changes:appFiles.filter(e=>e.change!=='UNCHANGED').map(e=>({file:e.file,change:e.change})),testReports,counts:manifest.counts},null,2));
