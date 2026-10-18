const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path');
const {chromium}=require('C:/Users/PC/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const catalog=require('../../app/modules/codex-ui-v1/feature-catalog.js');
const menu=JSON.parse(fs.readFileSync(path.join(__dirname,'LOT1_BUTTON_SCREEN_MAPPING.json'),'utf8'));
const specs=JSON.parse(fs.readFileSync(path.join(__dirname,'LOT2_SCREEN_SPECS.json'),'utf8'));
const out=path.join(__dirname,'feature-tests');fs.mkdirSync(out,{recursive:true});
const variants=Object.entries(catalog.definitions).map(([key,def])=>({key,def,id:Object.keys(catalog.routes).find(id=>catalog.routes[id].key===key)}));
(async()=>{const browser=await chromium.launch({headless:true});try{
 const page=await browser.newPage({viewport:{width:1440,height:1000}}),errors=[],apiRequests=[],checks=[];
 page.on('pageerror',e=>errors.push(e.message));page.on('request',r=>{if(/\/native\/|\/api\/|\/documents\//.test(r.url())||!['GET','HEAD'].includes(r.method()))apiRequests.push(r.method()+' '+r.url());});
 await page.goto('http://127.0.0.1:5187/modules/codex-ui-v1/preview.html');await page.evaluate(()=>codexPreview.ready);
 const ready=()=>page.waitForFunction(()=>document.querySelector('[data-feature-screen]')&&!Array.from(document.querySelectorAll('[data-feature-screen] [role="status"]')).some(n=>n.textContent==='Chargement des exemples…'));
 async function open(id){await page.evaluate(id=>codexPreview.open(id),id);if(catalog.routes[id].entry)await page.locator('[data-feature-command="start"]').click();await ready();}
 async function fits(){assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false,'Page overflow');assert.deepEqual(await page.locator('.sscx-feature-screen').evaluateAll(nodes=>nodes.filter(n=>n.scrollWidth>n.clientWidth+1).map(n=>n.dataset.featureScreen)),[]);}
 const uncovered=menu.actions.filter(a=>specs.specs[a.id].kind==='legacy'&&!['rosario-012','rosario-151'].includes(a.id)&&!catalog.routes[a.id]);assert.deepEqual(uncovered,[]);
 for(const [id,r]of Object.entries(catalog.routes)){
  await open(id);assert.equal(await page.locator('.sscx-content h1').textContent(),menu.actions.find(a=>a.id===id).label);assert.equal(await page.locator('[data-feature-screen]').getAttribute('data-feature-screen'),r.key);
  assert.equal(await page.locator('.sscx-legacy-target').count(),0);
  const d=catalog.definitions[r.key];
  if(d.examples.length){await page.locator('[data-feature-search]').fill('AbsentDeTousLesExemples');assert.equal(await page.locator('[data-feature-record]').count(),0);await page.locator('[data-feature-command="clear"]').click();
   await page.locator('[data-feature-sort]').selectOption(d.fields[0].key);await page.locator('[data-feature-filter]').selectOption({index:1});assert.ok(await page.locator('[data-feature-record]').count()>0);await page.locator('[data-feature-command="clear"]').click();
   await page.locator('[data-feature-record]').first().click();assert.equal(await page.locator('[data-feature-detail-heading]').evaluate(n=>document.activeElement===n),true);assert.equal(await page.locator('[data-feature-field]').count(),d.fields.length-1);await page.locator('[data-feature-command="list"]').click();
  }
  if(r.entry){await page.locator('[data-feature-command="close"]').click();assert.equal(await page.locator('[data-lot2-search]').count(),1);}
 }
 checks.push('111 destinations: exact titles, specialized surfaces, search, filters, sorting, projected detail and list return');console.log('PASS 111 destinations');
 let responsiveVariants=0;
 const captures=new Set();
 for(const theme of ['light','dark']){
  await page.locator('[data-theme-choice="'+theme+'"]').click();
  for(const width of [320,390,768,1280,1920]){
   await page.setViewportSize({width,height:1000});
   for(const {id,def,key}of variants){await open(id);await fits();responsiveVariants++;
    assert.equal(await page.locator('.sscx-feature-screen i[data-lucide]').count(),0,'Unknown icon: '+key);
    assert.deepEqual(await page.locator('.sscx-feature-screen img').evaluateAll(nodes=>nodes.filter(n=>!n.complete||!n.naturalWidth).map(n=>n.src)),[]);
    const capture=def.layout+'-'+theme+'-'+width;
    if([390,1280].includes(width)&&!captures.has(capture)){captures.add(capture);await page.screenshot({path:path.join(out,capture+'.png'),fullPage:true});}
   }
  }
 }
 checks.push('530 responsive variants: 53 presentations, five widths, both themes, icons, images and contained table scrolling');console.log('PASS 530 responsive variants');
 let forms=0;
 await page.setViewportSize({width:1280,height:1000});
 for(const {id,def,key}of variants.filter(v=>v.def.form.length)){
  await open(id);await page.locator('[data-feature-command="draft"]').click();await page.locator('[data-feature-form] button[type="submit"]').click();
  assert.equal(await page.locator('.sscx-feature-errors').evaluate(n=>document.activeElement===n),true);
  const first=def.fields.find(f=>def.form.includes(f.key)&&f.required);await page.locator('[data-feature-error="'+first.key+'"]').click();assert.equal(await page.locator('[name="'+first.key+'"]').evaluate(n=>document.activeElement===n),true);
  for(const f of def.fields.filter(f=>def.form.includes(f.key))){const input=page.locator('[name="'+f.key+'"]');if(f.type==='select')await input.selectOption({index:1});else await input.fill(f.type==='date'?'2026-10-12':f.type==='time'?'08:00':f.type==='number'?'1':f.type==='email'?'test@example.invalid':'Exemple <img src=x>\nContenu local');}
  const before=await page.locator('[data-feature-form]').evaluate(n=>Object.fromEntries(new FormData(n)));
  for(const width of [320,390,768,1280,1920]){await page.setViewportSize({width,height:1000});await fits();}
  assert.deepEqual(await page.locator('[data-feature-form] input,[data-feature-form] select,[data-feature-form] textarea').evaluateAll(nodes=>nodes.filter(n=>n.getBoundingClientRect().height<44||!n.labels.length).map(n=>n.name)),[]);
  await page.locator('[data-feature-form] button[type="submit"]').click();assert.match(await page.locator('.sscx-feature-review').textContent(),/Non enregistré, non transmis/);assert.equal(await page.locator('.sscx-feature-review img').count(),0);
  await page.locator('[data-feature-command="edit"]').click();assert.deepEqual(await page.locator('[data-feature-form]').evaluate(n=>Object.fromEntries(new FormData(n))),before);
  if(def.form.includes('from')&&def.form.includes('to')){await page.locator('[name="to"]').fill('2026-10-01');await page.locator('[data-feature-form] button[type="submit"]').click();assert.equal(await page.locator('[name="to"]').getAttribute('aria-invalid'),'true');}
  await page.locator('[data-feature-command="cancel"]').click();await page.locator('[data-feature-command="draft"]').click();assert.equal(await page.locator('[name="'+first.key+'"]').inputValue(),'');forms++;
 }
 checks.push(forms+' local forms: required and date-range validation, linked error focus, responsive fields, review, edit, escaping and discard');console.log('PASS '+forms+' local forms');
 for(const scenario of ['empty','error','denied']){
  await page.locator('#previewScenario').selectOption(scenario);
  for(const {id}of variants){await open(id);assert.equal(await page.locator('[data-feature-record]').count(),0);assert.equal(await page.locator('.sscx-feature-detail').count(),0);if(scenario!=='empty')assert.equal(await page.locator('[data-feature-command="draft"]').count(),0);}
 }
 checks.push('159 empty/error/denied cases, no stale fixture detail');
 await page.locator('#previewScenario').selectOption('normal');
 for(const role of Object.keys(menu.roles)){
  await page.locator('#previewRole').selectOption(role);const allowed=role==='admin'?menu.actions:menu.actions.filter(a=>menu.roles[role].actions.includes(a.id));
  for(const branch of menu.branches){const a=allowed.find(a=>a.branch===branch.id&&catalog.routes[a.id]);if(a){await open(a.id);await fits();}}
 }
 await page.locator('#previewRole').selectOption('parent');await page.evaluate(()=>codexPreview.open('rosario-023'));assert.equal(await page.locator('[data-feature-screen]').count(),0);
 checks.push('15 roles across their authorized branches; parent cannot open payroll');
 await page.locator('#previewRole').selectOption('admin');await open('rosario-066');await page.locator('[data-feature-command="draft"]').click();
 await page.locator('[name="name"]').fill('LOCAL-NOT-PERSISTED');await page.locator('#previewRole').selectOption('teacher');await page.locator('#previewRole').selectOption('admin');await open('rosario-066');await page.locator('[data-feature-command="draft"]').click();assert.equal(await page.locator('[name="name"]').inputValue(),'');
 const zoom=await page.addStyleTag({content:'html{font-size:200% !important}'});await page.setViewportSize({width:375,height:812});
 for(const {id}of variants){await open(id);await fits();}await zoom.evaluate(n=>n.remove());await page.emulateMedia({forcedColors:'active',reducedMotion:'reduce'});await fits();
 checks.push('All 53 presentations at 200% text, forced colors/reduced motion, session change clears drafts');
 await page.evaluate(async()=>{const old=SchoolSafeCodexDemo.featureScreen;SchoolSafeCodexDemo.featureScreen=async c=>{await new Promise(r=>setTimeout(r,150));return old(c);};const pending=codexPreview.open('rosario-071');await new Promise(r=>setTimeout(r,10));await codexPreview.open('rosario-006');await pending;SchoolSafeCodexDemo.featureScreen=old;});
 assert.equal(await page.locator('[data-feature-screen]').count(),0);assert.equal(await page.locator('[data-lot2-search]').count(),1);
 await page.evaluate(async()=>{codexPreview.destroy();window.featureLive=SchoolSafeCodexUiV1.mountApplication('#codexDashboardPreview',{featureScreens:true,getSession:()=>({identityKey:'live-test',schoolId:'test',role:'admin',revision:1}),authorize:async()=>true,demoData:{featureScreen:()=>{throw new Error('Must not load demo');}}});await featureLive.ready;await featureLive.open('rosario-071');});
 assert.equal(await page.locator('[data-feature-screen]').count(),0);assert.equal(await page.locator('.sscx-legacy-target').count(),1);
 checks.push('Late demo response discarded; live mode does not activate feature preview or load fixtures');
 assert.deepEqual(errors,[]);assert.deepEqual(apiRequests,[]);
 const result={status:'PASS',date:new Date().toISOString(),destinations:Object.keys(catalog.routes).length,presentations:variants.length,forms,responsiveVariants,checks,errors,apiRequests,realBusinessIntegration:false};fs.writeFileSync(path.join(out,'results.json'),JSON.stringify(result,null,2)+'\n');console.log(JSON.stringify(result,null,2));
 }finally{await browser.close();}})().catch(e=>{console.error(e);process.exitCode=1;});
