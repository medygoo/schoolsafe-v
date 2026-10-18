const assert=require('node:assert/strict');
const path=require('node:path');
const fs=require('node:fs');
const {chromium}=require('C:/Users/PC/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
(async()=>{
 const browser=await chromium.launch({headless:true});
 try{
  const page=await browser.newPage({viewport:{width:1440,height:1000}});const errors=[],nativeRequests=[];page.on('pageerror',e=>errors.push(e.message));page.on('request',r=>{if(r.url().includes('/native/'))nativeRequests.push(r.url());});
  await page.goto('http://127.0.0.1:5187/modules/codex-ui-v1/preview.html');await page.evaluate(()=>codexPreview.ready);
  for(const role of ['admin','school_head']){
   await page.selectOption('#previewRole',role);await page.waitForFunction(()=>document.querySelectorAll('[data-overview-class]').length===8);
   const first=await page.locator('[data-overview-class]').first().textContent();assert.match(first,/Premiere maternelle1046/);
   assert.deepEqual(await page.locator('.sscx-class-table tfoot td').allTextContents(),['180','89','91','140','40','0']);
   assert.equal(await page.locator('.sscx-teacher-list li').count(),8);
   assert.equal(await page.locator('.sscx-metric strong').last().textContent(),'7 / 8');
   assert.match(await page.locator('.sscx-palmares').textContent(),/Aucun classement disponible/);
   assert.equal(await page.locator('.sscx-role-overview [data-overview-panel]').count(),3);
   for(const theme of ['light','dark']){await page.locator('[data-theme-choice="'+theme+'"]').click();for(const width of [320,390,768,1280,1920]){
    await page.setViewportSize({width,height:1000});await page.waitForFunction(compact=>!!document.querySelector('[data-overview-tab]')===compact,width<1200);
    assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false);
    if(width<1200){
     assert.equal(await page.locator('[role="tabpanel"]:visible').count(),1);
     await page.locator('[data-overview-tab="students"]').click();await page.locator('[data-overview-tab="students"]').press('ArrowRight');
     assert.equal(await page.locator('[data-overview-tab="teachers"]').getAttribute('aria-selected'),'true');assert.equal(await page.locator('#overview-panel-teachers').isVisible(),true);
     await page.locator('[data-overview-tab="teachers"]').press('End');assert.equal(await page.locator('#overview-panel-palmares').isVisible(),true);
     await page.locator('[data-lot2="dashboard-refresh"]').click();await page.waitForFunction(()=>document.querySelectorAll('[data-overview-class]').length===8);assert.equal(await page.locator('#overview-panel-palmares').isVisible(),true);
     await page.locator('[data-overview-tab="palmares"]').press('Home');assert.equal(await page.locator('#overview-panel-students').isVisible(),true);
     if(width<700){
      assert.equal(await page.locator('.sscx-class-table').isVisible(),false);assert.equal(await page.locator('.sscx-cycle-group[open]').count(),1);
      await page.locator('.sscx-cycle-group summary').nth(1).click();assert.equal(await page.locator('.sscx-cycle-group[open]').count(),2);
      await page.locator('.sscx-cycle-group summary').nth(1).click();
     }
    }else{
     assert.equal(await page.locator('[data-overview-panel]:visible').count(),3);
     const left=await page.locator('#overview-panel-students').boundingBox(),right=await page.locator('#overview-panel-teachers').boundingBox();assert.ok(right.x>=left.x+left.width,'Panels must be side by side');
    }
    if(theme==='light'&&[390,1280].includes(width))await page.locator('.sscx-school-overview').screenshot({path:path.join(__dirname,'lot2-tests','overview-unified-'+role+'-'+width+'.png')});
   }}
   await page.locator('.sscx-overview-followup summary').click();await page.locator('[data-lot2-destination]').first().click();await page.waitForFunction(()=>document.querySelector('.sscx-data-list')?.textContent.includes('Amira Exemple 01'));
   await page.locator('.sscx-primary-list [data-command="dashboard"]').click();await page.waitForFunction(()=>document.querySelectorAll('[data-overview-class]').length===8);
  }
  await page.selectOption('#previewRole','teacher');await page.waitForFunction(()=>document.querySelector('[data-dashboard-role]')?.dataset.dashboardRole==='teacher');assert.equal(await page.locator('.sscx-palmares').count(),0);
  assert.equal(await page.evaluate(()=>SchoolSafeCodexCatalog.actions.length),211);
  const validations=await page.evaluate(()=>{
   const base={metrics:[],items:[],alerts:[]};const valid=SchoolSafeCodexLot2.validateDashboard(base,[]).schoolOverview===null;
   let rejected=false;try{SchoolSafeCodexLot2.validateDashboard({...base,schoolOverview:{date:'Exemple',teachers:[],classes:[{cycle:'M',name:'A',enrolled:10,girls:4,boys:5,unspecified:0,present:8,absent:2,late:0,unverified:0}]}},[]);}catch{rejected=true;}
   return {valid,rejected};
  });assert.deepEqual(validations,{valid:true,rejected:true});assert.deepEqual(errors,[]);assert.deepEqual(nativeRequests,[]);
  const result={status:'PASS',scope:'Unified overview only, simulated data',responsiveVariants:20,checks:['180 enrolled / 89 girls / 91 boys / 140 on time / 40 absent','7 teachers present including 1 late out of 8 expected','One unified section, three desktop areas, mobile tabs and cycle disclosure','Keyboard arrows, Home, End, refresh preserves tab, resizing','Exact dossier access retained through disclosure','211 destinations, other roles unchanged, absent/incoherent DTO validation'],errors,nativeRequests};
  fs.writeFileSync(path.join(__dirname,'lot2-tests','overview-unified-results.json'),JSON.stringify(result,null,2)+'\n');console.log(JSON.stringify(result,null,2));
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
