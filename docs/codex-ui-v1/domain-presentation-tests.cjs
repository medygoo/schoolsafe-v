const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const {chromium}=require('C:/Users/PC/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const output=path.join(__dirname,'domain-tests');
const specs=JSON.parse(fs.readFileSync(path.join(__dirname,'LOT2_SCREEN_SPECS.json'),'utf8'));
const variants=Object.entries(specs.definitions).filter(([key,def])=>def.columns.length&&Object.values(specs.specs).some(s=>s.definition===key)).map(([key,def])=>({key,def,id:Object.keys(specs.specs).find(id=>specs.specs[id].definition===key)}));
const layouts=new Set(['classes','family','subjects','staff','assignments','lessons','campaigns','documents','receipts','years','alerts','overview']);
const checks=[],errors=[],apiRequests=[];
let responsiveVariants=0;
fs.mkdirSync(output,{recursive:true});
async function check(name,run){await run();checks.push({name,status:'PASS'});console.log('PASS '+name);}
(async()=>{
 const browser=await chromium.launch({headless:true});
 try{
  const page=await browser.newPage({viewport:{width:1440,height:1000}});
  page.on('pageerror',e=>errors.push(e.message));page.on('request',r=>{if(/\/native\/|\/documents\//.test(r.url()))apiRequests.push(r.url());});
  const url=process.env.SCHOOLSAFE_PREVIEW_URL||'http://127.0.0.1:5187/modules/codex-ui-v1/preview.html';
  await page.goto(url);await page.evaluate(()=>codexPreview.ready);
  async function open(id){await page.evaluate(id=>codexPreview.open(id),id);}
  async function settled(){await page.waitForFunction(()=>document.querySelector('[data-lot2="refresh"]')?.disabled===false);}
  async function noOverflow(label){assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false,label);}
  await check('23 schemas: projected columns preserved, correct record and return, local search',async()=>{
   assert.equal(variants.length,23);
   await page.evaluate(()=>{window.detailCalls=[];const original=SchoolSafeCodexDemo.consult;SchoolSafeCodexDemo.consult=async(a,c)=>{if(c.params.record_id)detailCalls.push(c.params.record_id);return original(a,c);};});
   for(const {key,def,id}of variants){
    await open(id);
    assert.equal(await page.locator('[data-presentation]').getAttribute('data-presentation'),layouts.has(key)?key:'register',key);
    const fixture=await page.evaluate(async({key,id})=>SchoolSafeCodexDemo.consult({id},{definition:key,params:{},session:{role:'admin'}}),{key,id});
    const firstPage=fixture.rows.slice(0,8),buttons=page.locator('[data-lot2-record]');
    assert.equal(await buttons.count(),firstPage.length,key);
    for(let i=0;i<firstPage.length;i++){
     assert.equal(await buttons.nth(i).getAttribute('data-lot2-record'),firstPage[i].id);
     const text=await buttons.nth(i).evaluate(e=>e.closest('tr,li').textContent);
     for(const [field]of def.columns)assert.ok(text.includes(String(firstPage[i][field]??'Non renseigne')),`${key} missing ${field}`);
    }
    await buttons.first().click();await settled();assert.equal(await page.evaluate(()=>detailCalls.at(-1)),firstPage[0].id,key+' exact record');
    assert.ok(await page.locator('.sscx-data-list dd').count()>0,key+' detail');
    await page.locator('[data-lot2="list"]').click();await settled();
    await page.locator('[data-lot2-search]').fill('NoSuchRecordForTest');assert.equal(await buttons.count(),0);assert.match(await page.locator('.sscx-empty').textContent(),/Aucune ligne/);
    await page.locator('[data-lot2-search]').fill('');assert.equal(await buttons.count(),firstPage.length);
   }
  });
  await check('All schemas at 320, 390, 768, 1280, 1920 pixels, both themes; family-specific screenshots',async()=>{
   for(const theme of ['light','dark']){
    await page.locator('[data-theme-choice="'+theme+'"]').click();
    for(const width of [320,390,768,1280,1920]){
     await page.setViewportSize({width,height:1000});
     for(const {key,id}of variants){
      await open(id);await noOverflow(`${key} ${width} ${theme}`);responsiveVariants++;
      const clipped=await page.locator('.sscx-domain-item').evaluateAll(items=>items.filter(e=>e.scrollWidth>e.clientWidth+1).map(e=>e.textContent));assert.deepEqual(clipped,[],key+' clipped item');
      if([390,1280].includes(width)&&['classes','family','assignments','documents','payments'].includes(key))await page.screenshot({path:path.join(output,`${key}-${theme}-${width}.png`),fullPage:true});
     }
    }
   }
  });
  await check('Parent child attendance presentation, authorized subset and exact dossier',async()=>{
   await page.setViewportSize({width:390,height:844});await page.selectOption('#previewRole','parent');await page.waitForFunction(()=>document.querySelector('[data-dashboard-role="parent"]'));
   await open('rosario-194');assert.equal(await page.locator('[data-presentation="children"]').count(),1);assert.equal(await page.locator('[data-lot2-record]').count(),2);
   const id=await page.locator('[data-lot2-record]').first().getAttribute('data-lot2-record');await page.locator('[data-lot2-record]').first().click();await settled();assert.equal(await page.evaluate(()=>detailCalls.at(-1)),id);
   await page.locator('[data-lot2="list"]').click();await settled();await noOverflow('Parent children');await page.screenshot({path:path.join(output,'children-dark-390.png'),fullPage:true});
  });
  await check('New layouts: empty, error, denied, recovery and text at 200 percent',async()=>{
   await page.selectOption('#previewRole','admin');await page.waitForFunction(()=>document.querySelector('[data-dashboard-role="admin"]'));
   for(const scenario of ['empty','error','denied']){
    await page.selectOption('#previewScenario',scenario);
    for(const {key,id}of variants.filter(v=>layouts.has(v.key))){await open(id);assert.equal(await page.locator('[data-lot2-record]').count(),0,key+' '+scenario);assert.match(await page.locator('.sscx-view-status').textContent(),scenario==='empty'?/Aucune ligne/:scenario==='error'?/Chargement impossible/:/Acces refuse/);}
   }
   await page.selectOption('#previewScenario','normal');await page.evaluate(()=>document.documentElement.style.fontSize='200%');
   for(const {key,id}of variants.filter(v=>layouts.has(v.key))){await open(id);await noOverflow(key+' 200 percent');assert.ok(await page.locator('[data-lot2-record]').count()>0);}
   await page.evaluate(()=>document.documentElement.style.fontSize='');
  });
  await check('Substituted family DTO: pagination, status filter, malicious strings and long text',async()=>{
   await page.evaluate(async()=>{
    codexPreview.destroy();window.domainUi=SchoolSafeCodexUiV1.mountApplication('#codexDashboardPreview',{
     getSession:()=>({identityKey:'layout-test',schoolId:'test-school',revision:1,role:'admin'}),
     authorize:async(a)=>a.id==='rosario-008',
     consult:async()=>({rows:Array.from({length:17},(_,i)=>({id:'family-test-'+i,name:i===0?'<img src=x onerror=alert(1)>':'Responsable test '+i,student:i===0?'Long'.repeat(100):'Enfant test '+i,relationship:'Parent test',status:i%2?'Etat A':'Etat B',private:'NEVER_SHOW'}))})
    });await domainUi.ready;await domainUi.open('rosario-008');
   });
   assert.equal(await page.locator('[data-lot2-record]').count(),8);assert.equal(await page.locator('.sscx-domain-list img').count(),0);assert.match(await page.locator('.sscx-domain-list').textContent(),/<img src=x/);assert.equal(await page.locator('.sscx-content').textContent().then(t=>t.includes('NEVER_SHOW')),false);
   await noOverflow('Long strings');await page.locator('[data-lot2-page="1"]').click();assert.equal(await page.locator('[data-lot2-record]').first().getAttribute('data-lot2-record'),'family-test-8');await page.locator('[data-lot2-page="1"]').click();assert.equal(await page.locator('[data-lot2-record]').count(),1);
   await page.selectOption('[data-lot2-filter="status"]','Etat A');assert.equal(await page.locator('[data-lot2-record]').count(),8);assert.match(await page.locator('.sscx-pagination').textContent(),/Page 1 \/ 1/);
  });
  assert.deepEqual(errors,[]);assert.deepEqual(apiRequests,[]);
  fs.writeFileSync(path.join(output,'results.json'),JSON.stringify({status:'PASS',date:new Date().toISOString(),scope:'Isolated domain presentation; synthetic fixtures and substituted adapters only',schemas:variants.length,customLayouts:layouts.size+1,responsiveVariants,checks,errors,apiRequests},null,2)+'\n');
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
