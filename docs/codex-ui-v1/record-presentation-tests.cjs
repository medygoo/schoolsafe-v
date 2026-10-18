const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const {chromium}=require('C:/Users/PC/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const specs=JSON.parse(fs.readFileSync(path.join(__dirname,'LOT2_SCREEN_SPECS.json'),'utf8'));
const kinds=['students','classes','staff','attendance','family','subjects','assignments','grades','lessons','documents','years','alerts','overview','access'];
const variants=kinds.map(kind=>({kind,def:specs.definitions[kind],id:Object.keys(specs.specs).find(id=>specs.specs[id].definition===kind)}));
const out=path.join(__dirname,'record-tests');fs.mkdirSync(out,{recursive:true});
(async()=>{const browser=await chromium.launch({headless:true});try{
 const page=await browser.newPage({viewport:{width:1440,height:1000}}),errors=[],apiRequests=[],checks=[];
 page.on('pageerror',e=>errors.push(e.message));page.on('request',r=>{if(/\/native\/|\/documents\//.test(r.url()))apiRequests.push(r.url());});
 await page.goto('http://127.0.0.1:5187/modules/codex-ui-v1/preview.html');await page.evaluate(()=>codexPreview.ready);
 const settled=()=>page.waitForFunction(()=>document.querySelector('[data-lot2="refresh"]')?.disabled===false);
 const open=async id=>{await page.evaluate(id=>codexPreview.open(id),id);await settled();};
 const fits=async()=>assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false,'Page overflow');
 await page.evaluate(()=>{window.recordCalls=[];const consult=SchoolSafeCodexDemo.consult;SchoolSafeCodexDemo.consult=async(a,c)=>{recordCalls.push(c.params);return consult(a,c);};});
 for(const {kind,def,id} of variants){
  await open(id);await page.locator('[data-lot2-search]').fill('NoSuchRecord');assert.equal(await page.locator('[data-lot2-record]').count(),0);
  await page.locator('[data-lot2="clear-filters"]').click();assert.equal(await page.locator('[data-lot2-search]').inputValue(),'');
  const record=await page.locator('[data-lot2-record]').first().getAttribute('data-lot2-record');
  await page.locator('[data-lot2-record]').first().click();await settled();
  for(const tab of def.tabs){
   await page.locator('[data-lot2-tab="'+tab+'"]').click();await settled();
   const expected=await page.evaluate(async({id,kind,tab,record})=>{const def=SchoolSafeCodexScreenSpecs.definitions[kind];const raw=await SchoolSafeCodexDemo.consult({id},{definition:kind,session:{role:'admin'},params:{record_id:record,tab}});return SchoolSafeCodexLot2.validateData(def,raw,tab).fields;},{id,kind,tab,record});
   assert.deepEqual((await page.locator('[data-detail-field]').evaluateAll(nodes=>nodes.map(n=>n.dataset.detailField))).sort(),Object.keys(expected).sort(),kind+' '+tab+' exact projection');
   for(const [key,value] of Object.entries(expected))assert.equal(await page.locator('[data-detail-field="'+key+'"] dd').textContent(),value===''?'Non renseigné':String(value));
   if(kind==='grades'&&tab==='Evaluation')assert.equal(await page.locator('[data-detail-field="subject"] dt').textContent(),'Évaluation');
   if(kind==='staff'&&tab==='Profil')assert.equal(await page.locator('[data-detail-field="scope"] dt').textContent(),'Affectation');
   assert.equal(await page.evaluate(()=>recordCalls.at(-1).record_id),record);
  }
  await page.locator('[data-lot2="list"]').click();await settled();assert.ok(await page.locator('[data-lot2-record]').count()>0);
 }
 checks.push('14 schemas, every tab: exact projected fields and identifiers, reset filters and list return');
 for(const theme of ['light','dark']){
  await page.locator('[data-theme-choice="'+theme+'"]').click();
  for(const width of [320,390,768,1280,1920]){
   await page.setViewportSize({width,height:1000});
   for(const {kind,id} of variants){
    await open(id);await page.locator('[data-lot2-record]').first().click();await settled();await fits();
    assert.equal(await page.locator('.sscx-detail-sheet').count(),1);
    assert.deepEqual(await page.locator('.sscx-detail-sheet').evaluateAll(nodes=>nodes.filter(n=>n.scrollWidth>n.clientWidth+1).map(n=>n.textContent)),[]);
    if([390,1280].includes(width)&&['students','family','assignments'].includes(kind))await page.screenshot({path:path.join(out,kind+'-'+theme+'-'+width+'.png'),fullPage:true});
   }
  }
 }
 checks.push('140 detail layouts: 14 schemas, five widths, both themes, assets and overflow');
 for(const {kind,id} of variants.filter(v=>['assignments','grades','lessons'].includes(v.kind))){
  await page.setViewportSize({width:1280,height:1000});await open(id);await page.locator('[data-lot2="draft"]').click();
  await page.locator('[data-lot2-draft] button[type="submit"]').click();assert.equal(await page.locator('.sscx-draft-errors').evaluate(n=>n===document.activeElement),true);
  assert.equal(await page.locator('[data-lot2-draft] [aria-invalid="true"]').count(),3);
  await page.locator('[data-lot2-field="name"]').click();assert.equal(await page.locator('[name="name"]').evaluate(n=>n===document.activeElement),true);
  for(const input of await page.locator('[data-lot2-draft] input, [data-lot2-draft] textarea').all()){
   const type=await input.getAttribute('type');await input.fill(type==='date'?'2026-10-15':type==='number'?'0':'Texte autorise\nDeuxieme ligne <img src=x>');
  }
  const before=await page.locator('[data-lot2-draft]').evaluate(f=>Object.fromEntries(new FormData(f)));
  assert.equal(await page.locator('[aria-invalid="true"]').count(),0);assert.equal(await page.locator('.sscx-draft-errors').count(),0);
  assert.equal(await page.locator('[data-lot2-draft] textarea').last().evaluate(n=>n===document.activeElement),true);
  assert.deepEqual(await page.locator('[data-lot2-draft]').evaluate(form=>Array.from(form.querySelectorAll('input,textarea,button')).flatMap(n=>{const r=n.getBoundingClientRect(),issues=[];if(r.height<44)issues.push('Small control');if(n.matches('input,textarea')&&!n.labels.length)issues.push('Missing label');return issues;})),[]);
  for(const theme of ['light','dark']){
   await page.locator('[data-theme-choice="'+theme+'"]').click();assert.deepEqual(await page.locator('[data-lot2-draft]').evaluate(f=>Object.fromEntries(new FormData(f))),before);
   for(const width of [390,1280]){await page.setViewportSize({width,height:1000});await fits();await page.screenshot({path:path.join(out,'draft-'+kind+'-'+theme+'-'+width+'.png'),fullPage:true});}
  }
  await page.locator('[data-lot2-draft] button[type="submit"]').click();assert.match(await page.locator('.sscx-view-status').textContent(),/Non sauvegarde et non transmis/);
  assert.equal(await page.locator('[aria-invalid="true"]').count(),0);assert.equal(await page.locator('[data-lot2-draft] img').count(),0);
  await page.locator('[data-lot2-draft] textarea').first().fill('Texte modifie');assert.equal(await page.locator('.sscx-view-status').count(),0);
  if(kind==='grades'){
   await page.locator('[name="grade"]').fill('-1');await page.locator('[data-lot2-draft] button[type="submit"]').click();assert.equal(await page.locator('[name="grade"]').getAttribute('aria-invalid'),'true');assert.equal(await page.locator('[name="comment"]').inputValue(),'Texte modifie');
  }
  await page.locator('[data-lot2="draft-close"]').click();await page.locator('[data-lot2="draft"]').click();assert.equal(await page.locator('[name="name"]').inputValue(),'');assert.equal(await page.locator('.sscx-draft-errors').count(),0);
  const largeText=await page.addStyleTag({content:'html {font-size:200% !important;}'});
  for(const [width,height] of [[375,812],[844,390]]){await page.setViewportSize({width,height});await fits();}
  await largeText.evaluate(n=>n.remove());
 }
 checks.push('Three local drafts: multiline inputs, inline errors, summary focus and field links, preserved values on theme changes, zero grade, close resets');
 await page.setViewportSize({width:390,height:844});await open(variants[0].id);
 await page.locator('[data-lot2-filter="class"]').selectOption('Classe exemple A');await page.locator('[data-lot2-search]').fill('Amira');
 await page.locator('[data-lot2-record]').first().click();await settled();await page.locator('[data-lot2="list"]').click();await settled();
 assert.equal(await page.locator('[data-lot2-search]').inputValue(),'Amira');assert.equal(await page.locator('[data-lot2-filter="class"]').inputValue(),'Classe exemple A');
 await page.addStyleTag({content:'html {font-size:200% !important;}'});await page.locator('[data-lot2-record]').first().click();await settled();await fits();
 await page.emulateMedia({forcedColors:'active',reducedMotion:'reduce'});await fits();
 checks.push('List filters retained after detail, 200% text, forced colors and reduced motion');
 await page.evaluate(async()=>{codexPreview.destroy();window.recordUi=SchoolSafeCodexUiV1.mountApplication('#codexDashboardPreview',{getSession:()=>({identityKey:'record-test',schoolId:'test-school',role:'admin',revision:1}),authorize:async(a,p,params,purpose)=>a.id==='rosario-006'&&params.tab!=='Famille',consult:async(a,c)=>({rows:[{id:'opaque-1',name:'LIST_ONLY',class:'CLASS_ONLY'}],fields:{name:'<img src=x onerror=alert(1)>',reference:0,status:'Value unchanged',guardian:'NEVER_SHOW'}})});await recordUi.ready;await recordUi.open('rosario-006');});
 await page.locator('[data-lot2-record]').click();await settled();
 assert.equal(await page.locator('[data-detail-field="reference"] dd').textContent(),'0');assert.equal(await page.locator('.sscx-detail-sheet img').count(),0);
 assert.doesNotMatch(await page.locator('.sscx-detail-sheet').textContent(),/LIST_ONLY|CLASS_ONLY|NEVER_SHOW/);
 await page.locator('[data-lot2-tab="Famille"]').click();await settled();assert.equal(await page.locator('[data-detail-kind]').count(),0);assert.doesNotMatch(await page.locator('.sscx-content').textContent(),/LIST_ONLY|CLASS_ONLY|NEVER_SHOW|onerror/);
 checks.push('No row fallback, no cross-tab leak, denial clears identity, zero and escaped data preserved');
 assert.deepEqual(errors,[]);assert.deepEqual(apiRequests,[]);
 const result={status:'PASS',date:new Date().toISOString(),schemas:kinds.length,responsiveVariants:kinds.length*10,checks,errors,apiRequests,realBusinessIntegration:false};
 fs.writeFileSync(path.join(out,'results.json'),JSON.stringify(result,null,2)+'\n');console.log(JSON.stringify(result,null,2));
 }finally{await browser.close();}})().catch(e=>{console.error(e);process.exitCode=1;});
