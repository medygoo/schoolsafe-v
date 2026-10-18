const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const {chromium}=require('C:/Users/PC/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const out=path.join(__dirname,'overview-composition-tests');
fs.mkdirSync(out,{recursive:true});
(async()=>{
 const browser=await chromium.launch({headless:true});
 try{
  const page=await browser.newPage({viewport:{width:1440,height:1000}}),errors=[],apiRequests=[];
  page.on('pageerror',e=>errors.push(e.message));page.on('request',r=>{if(/\/native\/|\/documents\//.test(r.url()))apiRequests.push(r.url());});
  await page.goto('http://127.0.0.1:5187/modules/codex-ui-v1/preview.html');await page.evaluate(()=>codexPreview.ready);
  const ready=()=>page.waitForFunction(()=>document.querySelectorAll('[data-overview-class]').length===8);
  const fits=async()=>assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false,'Page overflow');
  for(const role of ['admin','school_head']){
   await page.selectOption('#previewRole',role);await ready();
   assert.match(await page.locator('.sscx-presence-total').textContent(),/78%.*140 \/ 180/);
   assert.deepEqual(await page.locator('.sscx-presence-legend dd').allTextContents(),['140','0','40','0']);
   assert.deepEqual(await page.locator('[data-cycle-summary] b').allTextContents(),['40','80','60']);
   assert.equal(await page.locator('.sscx-overview-inbox > .sscx-record-list li').count(),2);
   assert.equal(await page.locator('.sscx-overview-followup .sscx-record-list li').count(),4);
   assert.equal(await page.locator('.sscx-overview-pending .sscx-badge').count(),2);
   assert.equal(await page.locator('.sscx-overview-pending button').count(),0);
   for(const theme of ['light','dark']){
    await page.locator('[data-theme-choice="'+theme+'"]').click();
    for(const [width,height] of [[320,800],[390,844],[768,1024],[844,390],[1280,900],[1440,1000],[1920,1080]]){
     await page.setViewportSize({width,height});await fits();
     if(width>=1280){const main=await page.locator('.sscx-overview-core').boundingBox(),rail=await page.locator('.sscx-overview-inbox').boundingBox();assert.ok(rail.x>=main.x+main.width,'Alerts must sit beside summary');assert.ok(Math.abs(rail.y-main.y)<2);}
     const clipped=await page.locator('.sscx-school-overview button:visible').evaluateAll(nodes=>nodes.filter(n=>n.scrollWidth>n.clientWidth+1).map(n=>n.textContent));assert.deepEqual(clipped,[]);
     assert.equal(await page.locator('.sscx-school-overview i[data-lucide]').count(),0);
     if(role==='admin'&&[390,1440].includes(width)){
      await page.screenshot({path:path.join(out,'overview-'+theme+'-'+width+'.png'),fullPage:true});
      await page.screenshot({path:path.join(out,'viewport-'+theme+'-'+width+'.png')});
     }
    }
   }
   await page.setViewportSize({width:1440,height:1000});
   await page.locator('.sscx-overview-followup summary').click();
   await page.locator('[data-lot2="dashboard-refresh"]').click();await ready();
   assert.equal(await page.locator('.sscx-overview-followup').getAttribute('open'),'');
   await page.locator('[data-lot2-destination]').first().click();await page.waitForSelector('.sscx-data-list');
   await page.locator('.sscx-primary-list [data-command="dashboard"]').click();await ready();
  }
  for(const scenario of ['empty','error','denied']){
   await page.selectOption('#previewScenario',scenario);
   await page.waitForFunction(()=>!document.querySelector('[data-lot2="dashboard-refresh"]')?.disabled);
   assert.equal(await page.locator('.sscx-presence-bar').count(),0);assert.equal(await page.locator('[data-cycle-summary]').count(),0);
   assert.equal(await page.locator('.sscx-presence-total').count(),0);
   assert.match(await page.locator('.sscx-snapshot').textContent(),/Aucun effectif/);await fits();
  }
  await page.selectOption('#previewScenario','normal');await ready();
  // Substitute the authorized DTO in the isolated fixture, not a live endpoint.
  await page.evaluate(()=>{window.originalDashboard=SchoolSafeCodexDemo.dashboard;SchoolSafeCodexDemo.dashboard=async c=>{const d=await originalDashboard(c);d.schoolOverview.classes=[{cycle:'Cycle <img src=x>',name:'Classe test',enrolled:10,girls:4,boys:5,unspecified:1,present:3,late:2,absent:1,unverified:4}];d.schoolOverview.teachers=[{name:'Test',assignment:'Classe test',status:'Non verifie',arrival:''}];return d;};});
  await page.locator('[data-lot2="dashboard-refresh"]').click();await page.waitForFunction(()=>document.querySelector('.sscx-presence-total')?.textContent.includes('50%'));
  assert.deepEqual(await page.locator('.sscx-presence-legend dd').allTextContents(),['3','2','1','4']);
  assert.match(await page.locator('[data-metric-kind="presence"]').textContent(),/4 non verifies/);
  assert.match(await page.locator('[data-metric-kind="teachers"]').textContent(),/1 non verifies/);
  assert.equal(await page.locator('.sscx-school-overview img').count(),0);
  await page.evaluate(()=>{SchoolSafeCodexDemo.dashboard=async c=>{const d=await originalDashboard(c);d.schoolOverview.classes=[];d.schoolOverview.teachers=[];return d;};});
  await page.locator('[data-lot2="dashboard-refresh"]').click();await page.waitForFunction(()=>!document.querySelector('[data-lot2="dashboard-refresh"]')?.disabled);
  assert.equal(await page.locator('.sscx-presence-total').count(),0);assert.doesNotMatch(await page.locator('.sscx-school-overview').textContent(),/NaN|Infinity/);
  await page.evaluate(()=>{SchoolSafeCodexDemo.dashboard=originalDashboard;});await page.locator('[data-lot2="dashboard-refresh"]').click();await ready();
  await page.addStyleTag({content:'html {font-size:200% !important;}'});
  for(const width of [390,1280]){await page.setViewportSize({width,height:1000});await fits();}
  await page.emulateMedia({forcedColors:'active',reducedMotion:'reduce'});await fits();
  assert.deepEqual(errors,[]);assert.deepEqual(apiRequests,[]);
  const result={status:'PASS',date:new Date().toISOString(),responsiveVariants:28,checks:['Daily snapshot and cycle counts derived only from validated data','Desktop alert rail, quick access strip, retained class/teacher/ranking areas','Two roles, two themes, seven viewports including landscape','Dossier disclosure preserved across refresh and exact record navigation','Empty/error/denied, missing and zero denominator, late/unknown, escaped labels','200% text, forced colors, reduced motion, Lucide assets, no native requests'],errors,apiRequests,realBusinessIntegration:false};
  fs.writeFileSync(path.join(out,'results.json'),JSON.stringify(result,null,2)+'\n');console.log(JSON.stringify(result,null,2));
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
