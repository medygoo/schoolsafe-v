'use strict';
// Genuine application assets, synthetic school DOM. No session, SQL or business submissions.
const {chromium}=require('playwright');
const {fixture}=require('./qa-school-dashboard-navigation.cjs');
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),{execFileSync}=require('node:child_process');
const runtime=path.join(__dirname,'modules/codex-ui-v1/extensions-test-runtime');
const evidence=process.env.SCHOOL_NAV_EVIDENCE;
(async()=>{
 const browser=await chromium.launch({headless:true,executablePath:process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH||'C:/Users/PC/AppData/Local/ms-playwright/chromium_headless_shell-1234/chrome-headless-shell-win64/chrome-headless-shell.exe'});
 const results=[],errors=[];let destinations;
 async function check(name,fn){try{await fn();results.push({name,status:'PASS'});}catch(e){results.push({name,status:'FAIL',error:e.message});}}
 try{for(const width of [360,390,700,701,768,900,901,1280]){
  const ctx=await browser.newContext({viewport:{width,height:1000},serviceWorkers:'block'}),p=await ctx.newPage();p.setDefaultTimeout(2500);
  p.on('pageerror',e=>errors.push({width,message:e.message}));
  await ctx.route('**/*',r=>{const u=new URL(r.request().url());if(u.hostname!=='127.0.0.1'||r.request().method()!=='GET')return r.abort();
   if(u.pathname==='/Modules.php'){
    let html=fixture(u.searchParams.get('fixture')==='limited').replace('<section class="ss-closing-finance">','<section id="ss-photo-birthdays"><section class="ss-birthday-visual"><details open><summary>Anniversaires synthétiques</summary><ul><li>Aucune personne réelle</li></ul></details></section></section><section class="ss-closing-finance">');
    if(u.searchParams.has('preview')&&process.env.SCHOOL_COMPOSITION_RENDERED_HTML)html=html.replace(/<main id="ss-photo-dashboard"[\s\S]*?<\/main>/,()=>fs.readFileSync(process.env.SCHOOL_COMPOSITION_RENDERED_HTML,'utf8'));
    return r.fulfill({contentType:'text/html',body:html});
   }
   if(u.pathname==='/central-navigation.php')return r.fulfill({contentType:'application/json',body:'{"links":[],"profile":{"name":"Compte fictif","url":"Modules.php?modname=Users/User.php&category_id=1&staff_id=1"}}'});
   if(/\.(css|js)$/.test(u.pathname)){try{return r.fulfill({contentType:u.pathname.endsWith('.css')?'text/css':'text/javascript',body:fs.readFileSync(path.join(runtime,u.pathname.slice(1)))});}catch{return r.fulfill({status:404,body:''});}}
   const asset=path.join(runtime,u.pathname.slice(1));
   if(/\.(webp|png|svg)$/.test(u.pathname)&&fs.existsSync(asset))return r.fulfill({contentType:u.pathname.endsWith('.svg')?'image/svg+xml':u.pathname.endsWith('.webp')?'image/webp':'image/png',body:fs.readFileSync(asset)});
   return r.fulfill({contentType:'image/svg+xml',body:'<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"/>'});
  });
  const load=async(q='')=>{await p.goto('http://127.0.0.1/Modules.php?modname=misc/Portal.php'+q,{waitUntil:'load'});await p.waitForFunction(()=>document.querySelector('#menu .adminmenu')?.dataset.ssOrganized==='1');};
  const openActions=async()=>{if(width<=700&&await p.locator('#ss-photo-actions-panel').isHidden())await p.locator('#ss-photo-actions-button').click();};
  await load();
  await check(`${width}: one composition, no pager or parked content`,async()=>{assert.equal(await p.locator('.ss-fixed-pager,.ss-fixed-park,.ss-fixed-view').count(),0);for(const selector of ['.ss-photo-hero','.ss-photo-metrics','.ss-closing-finance','.ss-photo-modules','.ss-photo-charts','.ss-photo-school-life','.ss-photo-alerts','.ss-dashboard-details','.ss-detail-panel'])assert(await p.locator(selector).isVisible(),selector);assert.equal(await p.locator('.ss-photo-chart').count(),3);assert.equal(await p.locator('.ss-photo-module').count(),8);});
  await check(`${width}: document scrolls, no fixed dashboard viewport`,async()=>{assert.notEqual(await p.locator('#body').evaluate(e=>getComputedStyle(e).position),'fixed');assert(await p.evaluate(()=>document.scrollingElement.scrollHeight>innerHeight));assert(await p.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));await p.locator('.ss-detail-panel').scrollIntoViewIfNeeded();assert(await p.evaluate(()=>scrollY>0));});
  await check(`${width}: all permitted metrics remain visible on phone and desktop`,async()=>{assert.equal(await p.locator('.ss-photo-metric:not(.ss-photo-mobile-presence):visible').count(),6);});
  await check(`${width}: original header and authorized menu retained`,async()=>{assert.equal(await p.locator('.ss-fixed-account').count(),0);assert.equal(await p.locator('#ss-background-button').count(),1);const links=await p.locator('#menu .wp-submenu a').evaluateAll(a=>a.map(x=>x.getAttribute('href')).sort());if(!destinations)destinations=links;assert.deepEqual(links,destinations);});
  await check(`${width}: module opens existing branch without immediate closure`,async()=>{await p.locator('[data-open-native-branch=school]').click();assert(await p.locator('#menu [data-ss-branch=school]>.wp-submenu').isVisible());await p.keyboard.press('Escape');assert.equal(await p.locator('#body').evaluate(e=>e.inert),false);});
  await load();
  await check(`${width}: anchors reach modules and alerts without paging`,async()=>{await openActions();assert(await p.getByRole('link',{name:'Configurer mon école',exact:true}).isVisible());await p.getByRole('link',{name:'Modules de l’école'}).click();assert(await p.locator('#ss-photo-rubriques').isVisible());await p.locator('.ss-photo-metric[href="#ss-photo-alerts"]').first().click();assert(await p.locator('#ss-photo-alerts').isVisible());assert.equal(await p.locator('.ss-fixed-pager').count(),0);});
  await check(`${width}: theme and disconnected Watch retain native behavior`,async()=>{await openActions();await p.locator('.ss-photo-theme').click();assert.equal(await p.locator('html').getAttribute('data-ss-background'),'night');await p.locator(width<=700?'#ss-watch-button':'#ss-header-watch').click();assert.match(await p.locator('#ss-watch-dialog').innerText(),/Aucune montre raccord/);await p.getByRole('button',{name:'Fermer SchoolSafe Watch'}).click();});
  await check(`${width}: teardown/remount does not lose content or duplicate handlers`,async()=>{await p.evaluate(()=>window.schoolSafePhotoDashboard.destroy());await p.addScriptTag({url:'/assets/js/schoolsafe-photo-dashboard.js'});const before=await p.locator('html').getAttribute('data-ss-background');await openActions();await p.locator('.ss-photo-theme').click();assert.notEqual(await p.locator('html').getAttribute('data-ss-background'),before);assert.equal(await p.locator('.ss-photo-module').count(),8);assert(await p.locator('.ss-detail-panel').isVisible());});
  await load('&dashboard_view=modules-2');
  await check(`${width}: legacy view URLs never hide other information`,async()=>{assert(await p.locator('.ss-photo-hero').isVisible());assert(await p.locator('.ss-detail-panel').isVisible());assert.equal(await p.locator('.ss-fixed-pager').count(),0);});
  await check(`${width}: GET filters keep their values without pager injection`,async()=>{await p.getByRole('button',{name:'Filtrer les dossiers fictifs'}).click();await p.waitForLoadState('load');assert.equal(new URL(p.url()).searchParams.get('matrix_students_search'),'fictif');assert.equal(new URL(p.url()).searchParams.has('dashboard_view'),false);assert(await p.locator('.ss-detail-panel').isVisible());});
  await load('&details_tab=students#ss-closing-matrices');
  await check(`${width}: native detail fragment remains available after reload`,async()=>{await p.reload({waitUntil:'load'});assert(await p.locator('.ss-detail-panel').isVisible());});
  await load();
  await check(`${width}: birthdays are above modules and detailed information remains below`,async()=>{assert(await p.locator('#ss-photo-birthdays').isVisible());assert(await p.locator('#ss-photo-birthdays').evaluate(e=>Boolean(e.compareDocumentPosition(document.querySelector('.ss-photo-modules'))&Node.DOCUMENT_POSITION_FOLLOWING)));});
  if(width<=700){
   await check(`${width}: four KPIs and four-by-two quick access layout`,async()=>{assert.equal(await p.locator('.ss-photo-metrics .ss-photo-metric:visible').count(),4);assert.equal(await p.locator('.ss-photo-module-grid').evaluate(e=>getComputedStyle(e).gridTemplateColumns.split(' ').length),4);assert.equal(await p.locator('.ss-photo-secondary-metrics .ss-photo-metric').count(),3);});
   await check(`${width}: only Actions and Notifications outside the panel`,async()=>{assert.equal(await p.locator('#footerwrap .ss-header-tools > :is(a,button):visible').count(),2);assert(await p.locator('#ss-photo-actions-button').isVisible());assert(await p.locator('#footerwrap [aria-label="Notifications"]').isVisible());await openActions();const panelBox=await p.locator('#ss-photo-actions-panel').boundingBox();assert(panelBox.x>=0&&panelBox.x+panelBox.width<=width+1,'Open panel must fit the phone');assert(await p.locator('#ss-background-button').isVisible());assert(await p.locator('#ss-photo-actions-panel .ss-header-profile').isVisible());await p.keyboard.press('Escape');assert(await p.locator('#ss-photo-actions-panel').isHidden());assert.equal(await p.locator('#ss-photo-actions-button').evaluate(e=>e===document.activeElement),true);});
   await check(`${width}: responsive regrouping restores originals on desktop`,async()=>{await p.setViewportSize({width:1280,height:1000});await p.waitForFunction(()=>!document.querySelector('#ss-photo-actions-button'));assert.equal(await p.locator('.ss-photo-metrics .ss-photo-metric:not(.ss-photo-mobile-presence)').count(),6);assert.equal(await p.locator('#ss-background-button').count(),1);await p.setViewportSize({width,height:1000});await p.waitForSelector('#ss-photo-actions-button');assert.equal(await p.locator('#ss-background-button').count(),1);});
  }
  await check(`${width}: reduced-motion disables card animation`,async()=>{await p.emulateMedia({reducedMotion:'reduce'});assert.equal(await p.locator('.ss-photo-module').first().evaluate(e=>getComputedStyle(e).animationName),'none');await p.emulateMedia({reducedMotion:'no-preference'});});
  await load('&fixture=limited');
  await check(`${width}: no unauthorized modules added`,async()=>{assert.equal(await p.locator('[data-open-native-branch=finance]').count(),0);assert.equal(await p.locator('.ss-photo-module').count(),2);});
  if(evidence){fs.mkdirSync(evidence,{recursive:true});await load();await p.screenshot({path:path.join(evidence,`composition-${width}.png`),fullPage:true});}
  if(evidence&&process.env.SCHOOL_COMPOSITION_RENDERED_HTML&&[390,1280].includes(width)){
   await load('&preview=1');
   await check(`${width}: real PHP renderer with substituted data composes without overflow`,async()=>{assert.match(await p.locator('#ss-photo-dashboard').innerText(),/École synthétique de composition/);assert(await p.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));assert(await p.locator('#ss-photo-birthdays').isVisible());});
   await p.waitForTimeout(450);await p.screenshot({path:path.join(evidence,`rendered-${width}-light.png`),fullPage:true});
   await p.evaluate(()=>document.documentElement.dataset.ssBackground='night');await p.waitForTimeout(450);
   await p.screenshot({path:path.join(evidence,`rendered-${width}-night.png`),fullPage:true});
   if(width===390){await openActions();await p.screenshot({path:path.join(evidence,'rendered-390-actions.png')});}
  }
  await ctx.close();
 }
 await check('approved menu source unchanged',async()=>assert.equal(execFileSync('git',['hash-object',path.join(runtime,'assets/themes/SchoolSafeCodex/scripts.js')],{encoding:'utf8'}).trim(),'0c3ce810e8f6d7445c4390e80c39b71b44f33dc6'));
 await check('new CSS and JS cache tokens',async()=>{const php=fs.readFileSync(path.join(runtime,'ProgramFunctions/SchoolSafePhotoDashboard.fnc.php'),'utf8');for(const ext of ['css','js'])assert(php.includes(`schoolsafe-photo-dashboard.${ext}?v=school-composition-20261022`));});
 await check('no browser runtime errors',async()=>assert.deepEqual(errors,[]));
 if(evidence)fs.writeFileSync(path.join(evidence,'result.json'),JSON.stringify({scope:'Synthetic DOM with real assets, not authenticated PostgreSQL or visual owner acceptance',results,errors},null,2));
 results.forEach(r=>console.log(`${r.status} ${r.name}${r.error?' — '+r.error:''}`));console.log(JSON.stringify({pass:results.filter(r=>r.status==='PASS').length,fail:results.filter(r=>r.status==='FAIL').length}));if(results.some(r=>r.status==='FAIL'))process.exitCode=1;
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
