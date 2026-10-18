const fs=require('node:fs');
const path=require('node:path');
const assert=require('node:assert/strict');
const {pathToFileURL}=require('node:url');
const {chromium}=require('C:/Users/PC/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const root=path.resolve(__dirname,'../..');
const output=path.join(__dirname,'lot1-tests');
fs.mkdirSync(output,{recursive:true});
const results=[];
async function check(name,run){await run();results.push({name,status:'PASS'});console.log('PASS '+name);}
async function overflow(page){assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false,'Page overflow');}
async function main(){
 const browser=await chromium.launch({headless:true});
 try{
 const page=await browser.newPage({viewport:{width:1440,height:1000}});
 const errors=[];page.on('pageerror',error=>errors.push(error.message));
 await page.goto(pathToFileURL(path.join(root,'app/modules/codex-ui-v1/preview.html')).href);
 await page.evaluate(()=>codexPreview.ready);
 await check('13 branches, 15 postes, aucun Palmares',async()=>{
  assert.equal(await page.locator('.sscx-primary-list [data-branch]').count(),13);
  assert.equal(await page.locator('#previewRole option').count(),15);
  assert.equal(await page.evaluate(()=>SchoolSafeCodexCatalog.actions.some(a=>/palmar[eè]s/i.test(a.label))),false);
 });
 await check('Toutes les destinations sans faux succes',async()=>{
  const actions=await page.evaluate(()=>SchoolSafeCodexCatalog.actions.map(a=>({id:a.id,type:a.type,reader:a.reader})));
  for(const action of actions){
   await page.evaluate(id=>codexPreview.open(id),action.id);
   assert.equal(await page.locator('.sscx-content h1').count(),1,action.id);
   if(action.type==='existing'){
    await page.locator('[data-command="open-existing"]').click();
    await page.waitForFunction(()=>document.querySelector('.sscx-state')?.textContent.includes('non raccordee'));
    assert.match(await page.locator('.sscx-state').textContent(),/Aucune operation effectuee/);
   }
  }
 });
 await check('6 lectures, filtres, pagination et detail',async()=>{
  for(const id of ['presence-teacher','presence-school','presence-school-count']){
   await page.evaluate(id=>codexPreview.open(id),id);
   assert.ok(await page.locator('.sscx-metric').count()>0);
  }
  for(const id of ['presence-student','presence-class','scan-history']){
   await page.evaluate(id=>codexPreview.open(id),id);
   await page.locator('[data-read-form] select').selectOption({index:1});
   await page.locator('[data-read-form] button').click();
   await page.waitForFunction(()=>document.querySelector('.sscx-state')?.textContent.includes('Resultat'));
  }
  assert.equal(await page.locator('tbody tr').count(),10);
  await page.locator('[data-page="1"]').click();
  assert.match(await page.locator('.sscx-pagination').textContent(),/Page 2/);
  await page.locator('[data-filter]').fill('denied');
  assert.equal(await page.locator('tbody tr').count(),8);
  await page.locator('[data-audit-index]').first().click();
  assert.ok(await page.locator('dialog[open] dl').textContent());
  await page.keyboard.press('Escape');
  assert.equal(await page.locator('dialog[open]').count(),0);
  assert.equal(await page.locator('[data-audit-index]').first().evaluate(el=>el===document.activeElement),true);
 });
 await check('Navigation fixe desktop et sous-menu independant',async()=>{
  const before=await page.locator('.sscx-primary').boundingBox();
  await page.locator('.sscx-primary [data-branch="school"]').click();
  const after=await page.locator('.sscx-primary').boundingBox();
  assert.deepEqual(after,before);
  assert.ok(await page.locator('.sscx-context').isVisible());
 });
 await check('15 postes x 6 viewports x 2 themes',async()=>{
  const roles=await page.locator('#previewRole option').evaluateAll(nodes=>nodes.map(n=>n.value));
  for(const width of [320,390,700,768,1024,1440]){
   await page.setViewportSize({width,height:1000});
   for(const role of roles){
    await page.selectOption('#previewRole',role);
    await page.waitForFunction(role=>document.querySelector('.sscx-eyebrow')?.textContent===SchoolSafeCodexCatalog.roles[role].label,role);
    await page.waitForFunction(()=>document.querySelectorAll('.sscx-module').length>0);
    for(const theme of ['light','dark']){
     await page.locator('[data-theme-choice="'+theme+'"]').click();await overflow(page);
    }
   }
  }
 });
 await check('Menus et tableaux mobiles',async()=>{
  await page.setViewportSize({width:390,height:844});await page.selectOption('#previewRole','admin');
  await page.locator('.sscx-mobile [data-command="menu"]').click();
  await page.locator('.sscx-primary [data-branch="security"]').click();
  assert.ok(await page.locator('.sscx-context').isVisible());
  await page.locator('.sscx-context [data-action-id="scan-history"]').click();
  await page.locator('[data-read-form] select').selectOption({index:1});await page.locator('[data-read-form] button').click();
  await page.waitForFunction(()=>document.querySelector('tbody tr'));
  await overflow(page);
  assert.ok(await page.locator('.sscx-table-scroll').evaluate(el=>el.scrollWidth>el.clientWidth));
  await page.screenshot({path:path.join(output,'mobile-history.png'),fullPage:true});
 });
 await check('JASPE image, modes indisponibles et focus',async()=>{
  await page.locator('.sscx-mobile [data-command="jaspe"]').click();
  assert.ok(await page.locator('dialog[open] img').evaluate(img=>img.complete&&img.naturalWidth>0));
  await page.locator('[data-command="jaspe-audio"]').click();
  assert.match(await page.locator('.sscx-jaspe-status').textContent(),/indisponible/);
  await page.locator('[data-jaspe-demo-form] input').fill('Message de demonstration');
  await page.locator('[data-jaspe-demo-form] button').click();
  assert.match(await page.locator('.sscx-jaspe-status').textContent(),/Aucun message transmis/);
  await page.screenshot({path:path.join(output,'mobile-jaspe.png')});
  await page.keyboard.press('Escape');
  assert.equal(await page.locator('.sscx-mobile [data-command="jaspe"]').evaluate(el=>el===document.activeElement),true);
 });
 await page.setViewportSize({width:1440,height:1000});await page.selectOption('#previewRole','school_head');
 await page.locator('[data-theme-choice="light"]').click();
 await page.screenshot({path:path.join(output,'desktop-direction.png'),fullPage:true});
 await page.evaluate(()=>codexPreview.open('presence-school'));
 await page.screenshot({path:path.join(output,'desktop-presence.png'),fullPage:true});
 await page.setViewportSize({width:1024,height:1000});
 await page.screenshot({path:path.join(output,'tablet-presence.png'),fullPage:true});
 await check('Mode reel: refus ferme sans adaptateur, y compris admin',async()=>{
  await page.evaluate(async()=>{
   codexPreview.destroy();
   window.liveSession={identityKey:'test-person',schoolId:'test-school',revision:1,role:'admin'};
   window.readCalls=0;
   window.testUi=SchoolSafeCodexUiV1.mountApplication('#codexDashboardPreview',{getSession:()=>liveSession,read:()=>{readCalls++;return {total:1};}});
   await testUi.ready;await testUi.open('presence-school');
  });
  assert.equal(await page.evaluate(()=>readCalls),0);
  assert.match(await page.locator('.sscx-state').textContent(),/refuse/);
 });
 await check('Contrat GET, revision session, erreurs et reprise',async()=>{
  await page.evaluate(async()=>{
   testUi.destroy();window.failRead=true;
   window.testUi=SchoolSafeCodexUiV1.mountApplication('#codexDashboardPreview',{getSession:()=>liveSession,authorize:async a=>a.id==='presence-school',read:async()=>{readCalls++;if(failRead)throw new Error('test');return {total:7,currently_on_site:4};}});
   await testUi.ready;await testUi.open('presence-school');
  });
  assert.match(await page.locator('.sscx-state').textContent(),/impossible/);
  await page.evaluate(()=>{failRead=false;});await page.locator('[data-command="refresh"]').click();
  await page.waitForFunction(()=>document.querySelectorAll('.sscx-metric').length===2);
  await page.evaluate(async()=>{liveSession={...liveSession,revision:2,schoolId:'other-school'};await testUi.refreshSession();});
  assert.equal(await page.locator('.sscx-metric').count(),0);
 });
 await check('Reponses tardives, revocation, projection et echappement',async()=>{
  await page.evaluate(async()=>{
   testUi.destroy();window.allowRead=true;window.releaseRead=null;
   window.testUi=SchoolSafeCodexUiV1.mountApplication('#codexDashboardPreview',{getSession:()=>liveSession,authorize:async()=>allowRead,read:()=>new Promise(resolve=>{releaseRead=resolve;})});
   await testUi.ready;window.pending=testUi.open('presence-school');
  });
  await page.waitForFunction(()=>releaseRead!==null);
  await page.evaluate(async()=>{allowRead=false;releaseRead({total:777});await pending;});
  assert.equal(await page.locator('.sscx-metric').count(),0);
  assert.match(await page.locator('.sscx-state').textContent(),/refuse|retire/);
  await page.evaluate(async()=>{
   allowRead=true;releaseRead=null;window.pending=testUi.open('presence-school');
  });await page.waitForFunction(()=>releaseRead!==null);
  await page.evaluate(async()=>{liveSession={...liveSession,revision:3};await testUi.refreshSession();releaseRead({total:888});await pending;});
  assert.equal(await page.locator('.sscx-metric').count(),0);
  await page.evaluate(async()=>{
   testUi.destroy();window.xss=false;
   window.testUi=SchoolSafeCodexUiV1.mountApplication('#codexDashboardPreview',{getSession:()=>liveSession,authorize:async()=>true,listAuthorized:async()=>[{id:'00000000-0000-4000-8000-000000000001',label:'Exemple'}],read:async()=>[{mode:'<img src=x onerror="window.xss=true">',decision:'allowed',secret:'DO_NOT_RENDER'}]});
   await testUi.ready;await testUi.open('scan-history');
  });
  await page.locator('[data-read-form] select').selectOption({index:1});await page.locator('[data-read-form] button').click();await page.waitForFunction(()=>document.querySelector('tbody'));
  assert.equal(await page.evaluate(()=>xss),false);
  assert.equal((await page.locator('.sscx-content').textContent()).includes('DO_NOT_RENDER'),false);
 });
 await check('Read adapter: GET allowliste et validation des parametres',async()=>{
  const result=await page.evaluate(async()=>{
   const calls=[];const reader=SchoolSafeCodexReads.create(async(url,opts)=>{calls.push({url,method:opts.method});return {data:{total:2}};});
   await reader('teacherBoard',{},{});await reader('onSiteStudent',{student_id:'00000000-0000-4000-8000-000000000001'},{});
   let rejected=false;try{await reader('onSiteStudent',{student_id:'bad'},{});}catch{rejected=true;}
   return {calls,rejected};
  });assert.ok(result.rejected);assert.equal(result.calls.length,2);assert.ok(result.calls.every(c=>c.method==='GET'));
 });
 await check('Staff financier: permission sans dependance au role demo',async()=>{
  await page.evaluate(async()=>{
   testUi.destroy();liveSession={...liveSession,role:'staff',revision:4,title:'Controleur financier'};window.openedRoute=null;
   window.testUi=SchoolSafeCodexUiV1.mountApplication('#codexDashboardPreview',{getSession:()=>liveSession,authorize:async a=>a.id==='scan-fees',openExisting:async route=>{openedRoute=route;return {opened:true};}});
   await testUi.ready;await testUi.open('scan-fees');
  });
  await page.locator('[data-command="open-existing"]').click();
  await page.waitForFunction(()=>openedRoute!==null);
  assert.equal(await page.evaluate(()=>openedRoute.mode),'fees');
  assert.equal(await page.locator('.sscx-primary [data-branch="feeControl"]').isEnabled(),true);
 });
 await check('Schema exact SQL, compteurs invalides et legacy sans faux succes',async()=>{
  const result=await page.evaluate(()=>{
   const valid=SchoolSafeCodexUiV1.validateRead('schoolBoard',{total_students:180,present_today:140,absent_today:40,currently_on_site:126,exited:14,pre_exit:9,pickups:10,secret:'hidden'});
   let rejected=false;try{SchoolSafeCodexUiV1.validateRead('schoolBoard',{total_students:-1});}catch{rejected=true;}
   return {valid,rejected};
  });assert.ok(result.rejected);assert.equal(Object.keys(result.valid).length,7);assert.equal(result.valid.exited,14);
  await page.evaluate(()=>{
   const host=document.createElement('div');host.id='legacy-test';document.body.appendChild(host);
   SchoolSafeCodexUiV1.mount(host,SchoolSafeCodexUiV1.dashboard({actions:[{label:'Example'}]}));
  });await page.locator('#legacy-test button').click();assert.match(await page.locator('#legacy-test [data-codex-status]').textContent(),/non raccordee/);
  await page.evaluate(()=>document.getElementById('legacy-test').remove());
 });
 await check('Presentation opt-in sur le vrai markup JASPE (moteur non execute)',async()=>{
  const html=fs.readFileSync(path.join(root,'app/index.html'),'utf8');
  const styles=['app/styles/modules/jaspe-dashboard.css','app/styles/codex-ui-v1/jaspe-overlay.css'].map(file=>pathToFileURL(path.join(root,file)).href);
  await page.evaluate(({html,styles})=>{
   testUi.destroy();const parsed=new DOMParser().parseFromString(html,'text/html');
   const dialog=parsed.getElementById('jaspePanelOverlay');dialog.setAttribute('data-codex-ui-v1','');
   document.body.appendChild(document.importNode(dialog,true));
   styles.forEach(href=>{const link=document.createElement('link');link.rel='stylesheet';link.href=href;document.head.appendChild(link);});
  },{html,styles});
  await page.waitForFunction(()=>getComputedStyle(document.getElementById('jaspePanelOverlay')).maxWidth==='760px');
  await page.evaluate(()=>document.getElementById('jaspePanelOverlay').showModal());
  await page.evaluate(()=>Promise.all(document.getElementById('jaspePanelOverlay').getAnimations({subtree:true}).map(animation=>animation.finished)));
  for(const width of [390,1024,1440]){
   await page.setViewportSize({width,height:1000});
   assert.ok(await page.locator('#jaspePanelOverlay').evaluate(el=>el.getBoundingClientRect().width<=innerWidth));
   await page.screenshot({path:path.join(output,'jaspe-existing-'+width+'.png')});
  }
  await page.evaluate(()=>document.getElementById('jaspePanelOverlay').remove());
 });
 assert.deepEqual(errors,[]);
 fs.writeFileSync(path.join(output,'results.json'),JSON.stringify({date:new Date().toISOString(),mode:'file preview + explicit substituted adapters, no real backend',results,browserErrors:errors},null,2));
 }finally{await browser.close();}
}
main().catch(error=>{fs.writeFileSync(path.join(output,'failure.txt'),error.stack);console.error(error);process.exitCode=1;});
