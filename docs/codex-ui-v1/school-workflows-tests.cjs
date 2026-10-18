const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {chromium} = require('C:/Users/PC/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const out = path.join(__dirname,'school-workflows-tests');
fs.mkdirSync(out,{recursive:true});
const routes = ['rosario-152','rosario-012','rosario-155','rosario-151','rosario-149','rosario-150','rosario-007','rosario-008','rosario-153'];
(async()=>{
  const browser=await chromium.launch({headless:true});
  try {
    const page=await browser.newPage({viewport:{width:1440,height:1000}}),errors=[],apiRequests=[],checks=[];
    page.on('pageerror',e=>errors.push(e.message));
    page.on('request',r=>{if(/\/native\/|\/api\//.test(r.url())||!['GET','HEAD'].includes(r.method()))apiRequests.push(r.method()+' '+r.url());});
    await page.goto('http://127.0.0.1:5187/modules/codex-ui-v1/preview.html');
    await page.evaluate(()=>codexPreview.ready);
    const ready=()=>page.waitForFunction(()=>document.querySelector('[data-school-workflow]')&&!document.querySelector('[data-school-workflow]').textContent.includes('Chargement des exemples'));
    async function open(id,choose=false){
      await page.evaluate(id=>codexPreview.open(id),id);
      if(await page.locator('[data-school-command="start"]').count())await page.locator('[data-school-command="start"]').click();
      await ready();
      if(choose&&await page.locator('#school-record').count())await page.locator('#school-record').selectOption({index:1});
    }
    const submit=()=>page.locator('[data-school-workflow-form] button[type="submit"]').click();
    const fill=async(name,value)=>page.locator('[name="'+name+'"]').fill(value);
    const fits=async()=>{
      assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false,'Viewport overflow');
      assert.deepEqual(await page.locator('.sscx-school-workflow').evaluateAll(nodes=>nodes.filter(n=>n.scrollWidth>n.clientWidth+1).map(n=>n.className)),[]);
    };
    await open('rosario-152');await submit();
    assert.equal(await page.locator('.sscx-school-errors').evaluate(n=>n===document.activeElement),true);
    assert.equal(await page.locator('[aria-invalid="true"]').count(),3);
    await page.locator('[data-school-field="first_name"]').click();assert.equal(await page.locator('[name="first_name"]').evaluate(n=>n===document.activeElement),true);
    await fill('reference','DEMO-TEST');await fill('first_name','<img src=x onerror=alert(1)>');await fill('last_name','EXEMPLE');
    await page.locator('[name="gender"]').selectOption('F');
    assert.equal(await page.locator('[aria-invalid="true"]').count(),0);await submit();
    await submit();assert.equal(await page.locator('[aria-invalid="true"]').count(),3);
    await page.locator('[name="academic_year_id"]').selectOption({index:1});await page.locator('[name="planned_class_id"]').selectOption({index:1});await fill('enrollment_starts_on','2026-09-01');
    await submit();assert.match(await page.locator('.sscx-school-facts').textContent(),/DEMO-TEST/);
    assert.equal(await page.locator('.sscx-school-workflow img').count(),0);
    await submit();assert.match(await page.locator('.sscx-school-notice').textContent(),/Non sauvegardés et non transmis/);
    await page.locator('[data-school-command="previous"]').click();assert.equal(await page.locator('[name="enrollment_starts_on"]').inputValue(),'2026-09-01');
    await page.locator('[data-school-command="previous"]').click();assert.equal(await page.locator('[name="reference"]').inputValue(),'DEMO-TEST');
    const before=await page.locator('[data-school-workflow-form]').evaluate(f=>Object.fromEntries(new FormData(f)));
    await page.locator('[data-theme-choice="dark"]').click();assert.deepEqual(await page.locator('[data-school-workflow-form]').evaluate(f=>Object.fromEntries(new FormData(f))),before);
    checks.push('Creation: two validated steps, recap, focusable linked errors, values retained on previous/theme, escaped content, no save');
    await open('rosario-012');await submit();assert.equal(await page.locator('[aria-invalid="true"]').count(),2);
    await fill('filename','exemple.csv');await fill('file_content','123');await submit();assert.equal(await page.locator('[aria-invalid="true"]').count(),1);
    await fill('file_content','matricule,nom\nDEMO-1,<script>alert(1)</script>');await submit();
    assert.match(await page.locator('.sscx-school-source').textContent(),/<script>/);assert.equal(await page.locator('.sscx-school-source script').count(),0);
    await submit();assert.match(await page.locator('.sscx-school-notice').textContent(),/Non sauvegardés/);
    await page.locator('[data-school-command="previous"]').click();assert.equal(await page.locator('[name="filename"]').inputValue(),'exemple.csv');
    checks.push('Import: filename/content validation, raw source escaping, no CSV interpretation, upload or commit');
    await open('rosario-149',true);assert.equal(await page.locator('.sscx-school-review li').count(),3);
    await fill('note','Observation temporaire');await submit();assert.match(await page.locator('.sscx-school-notice').textContent(),/aucune décision/);
    await page.locator('#school-record').selectOption({index:2});assert.equal(await page.locator('[name="note"]').inputValue(),'');
    await open('rosario-007',true);assert.equal(await page.locator('.sscx-school-roster li').count(),12);
    await page.locator('#school-record').selectOption({index:2});assert.equal(await page.locator('.sscx-school-roster li').count(),12);
    await page.locator('[data-school-command="close"]').click();assert.equal(await page.locator('[data-lot2-search]').count(),1);
    await open('rosario-008',true);await fill('email','invalide');await submit();assert.equal(await page.locator('[name="email"]').getAttribute('aria-invalid'),'true');
    await fill('email','famille@example.invalid');await submit();assert.match(await page.locator('.sscx-school-notice').textContent(),/Non sauvegardés/);
    await page.locator('#school-record').selectOption({index:2});assert.equal(await page.locator('[name="email"]').inputValue(),'');
    await page.locator('[data-school-command="close"]').click();assert.equal(await page.locator('[data-lot2-operation]').count(),5);
    checks.push('Admissions, classes, family: selection scopes details; notes/contact reset per record; lists and five protected family operations retained');
    let responsiveVariants=0;
    for(const theme of ['light','dark']){
      await page.locator('[data-theme-choice="'+theme+'"]').click();
      for(const width of [320,390,768,1280,1920]){
        await page.setViewportSize({width,height:1000});
        for(const id of routes){
          await open(id,true);await fits();responsiveVariants++;
          assert.deepEqual(await page.locator('.sscx-school-grid,.sscx-school-facts').evaluateAll(nodes=>nodes.flatMap(n=>{const surface=n.closest('.sscx-school-workflow');return surface.clientWidth<=600&&getComputedStyle(n).gridTemplateColumns.split(' ').length!==1?['Expected one column']:[];})),[]);
          assert.deepEqual(await page.locator('.sscx-school-workflow input,.sscx-school-workflow select,.sscx-school-workflow textarea').evaluateAll(nodes=>nodes.flatMap(n=>{const r=n.getBoundingClientRect();return !n.labels.length||r.height<44?[n.name||n.id]:[];})),[]);
          if([390,1280].includes(width)&&['rosario-152','rosario-149','rosario-007','rosario-008','rosario-012'].includes(id))await page.screenshot({path:path.join(out,id+'-'+theme+'-'+width+'.png'),fullPage:true});
        }
      }
    }
    const big=await page.addStyleTag({content:'html{font-size:200% !important}'});
    for(const id of routes){await page.setViewportSize({width:375,height:812});await open(id,true);await fits();}
    await big.evaluate(n=>n.remove());await page.emulateMedia({forcedColors:'active',reducedMotion:'reduce'});await fits();await page.emulateMedia({forcedColors:'none',reducedMotion:'no-preference'});
    checks.push('90 responsive variants, 320-1920px, white/blue themes, labels, 44px fields, 200% text and forced colors');
    for(const scenario of ['empty','error','denied']){
      await page.locator('#previewScenario').selectOption(scenario);
      for(const id of routes){await open(id);assert.equal(await page.locator('[data-school-workflow-form],#school-record').count(),0);assert.doesNotMatch(await page.locator('[data-school-workflow]').textContent(),/Amira|DEMO-TEST|famille@example/);}
    }
    await page.locator('#previewScenario').selectOption('normal');await open('rosario-152');assert.equal(await page.locator('[name="reference"]').inputValue(),'');
    await fill('reference','SESSION-LOCAL');await page.locator('#previewRole').selectOption('parent');await page.evaluate(()=>codexPreview.open('rosario-152'));assert.equal(await page.locator('[data-school-workflow]').count(),0);
    await page.locator('#previewRole').selectOption('admin');await open('rosario-152');assert.equal(await page.locator('[name="reference"]').inputValue(),'');
    checks.push('27 empty/error/denied destinations; scenario/role transitions erase drafts and deny unauthorized school routes');
    await page.evaluate(async()=>{codexPreview.destroy();window.liveWorkflowUi=SchoolSafeCodexUiV1.mountApplication('#codexDashboardPreview',{schoolWorkflows:true,getSession:()=>({identityKey:'test',schoolId:'test',role:'admin',revision:1}),authorize:async()=>true,demoData:{schoolWorkflow:()=>{throw new Error('Fixture must not run');}},consult:async()=>({rows:[],fields:{}})});await liveWorkflowUi.ready;await liveWorkflowUi.open('rosario-152');});
    assert.equal(await page.locator('[data-school-workflow]').count(),0);assert.equal(await page.locator('[data-lot2-search]').count(),1);
    checks.push('Live mode ignores preview workflow flag and never invokes fixtures');
    assert.deepEqual(errors,[]);assert.deepEqual(apiRequests,[]);
    const result={status:'PASS',date:new Date().toISOString(),destinations:routes.length,responsiveVariants,checks,errors,apiRequests,realBusinessIntegration:false};
    fs.writeFileSync(path.join(out,'results.json'),JSON.stringify(result,null,2)+'\n');console.log(JSON.stringify(result,null,2));
  }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
