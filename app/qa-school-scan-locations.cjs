'use strict';
// Actual page markup + actual JS; all HTTP intercepted. No login, real DB or sends.
const fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict');
const {chromium}=require('playwright');
const root=path.resolve(__dirname,'modules/codex-ui-v1/extensions-test-runtime');
(async()=>{
 const checks=[];const check=(name,ok)=>{assert.ok(ok,name);checks.push(name);};
 const browser=await chromium.launch({headless:true,executablePath:process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH||'C:/Users/PC/AppData/Local/ms-playwright/chromium_headless_shell-1234/chrome-headless-shell-win64/chrome-headless-shell.exe'});
 try {
  for(const [engine,read,save] of [['native',true,true],['bridge',true,true],['native',true,false],['native',false,true]]) {
   const page=await browser.newPage({viewport:{width:390,height:844}});const errors=[],writes=[],requests=[];let rejectSave=false;
   page.on('pageerror',error=>errors.push(error.message));
   let rows=engine==='native'?[{id:'7',label:'Synthetic gate',direction:'in',is_active:false}]:[{id:'11111111-1111-1111-1111-111111111111',label:'Synthetic bridge gate',code:'GATE-1',kind:'gate',is_active:true}];
   let html=fs.readFileSync(path.join(root,'scan-locations.php'),'utf8');html=html.slice(html.indexOf('<!doctype html>')).replace(/<\?[\s\S]*?\?>/g,'');
   html=html.replace('data-csrf=""','data-csrf="synthetic-only-csrf"').replace('data-read=""',`data-read="${read?'1':'0'}"`).replace('data-save=""',`data-save="${save?'1':'0'}"`).replace('data-engine=""',`data-engine="${engine}"`);
   if(!save)html=html.replace(/<form id="location-form">[\s\S]*?<\/form>/,'');
   await page.route('**/*',async route=>{
    const request=route.request(),url=new URL(request.url());
    if(url.pathname==='/scan-locations.php')return route.fulfill({contentType:'text/html',body:html});
    if(url.pathname.endsWith('/scan-locations.js'))return route.fulfill({contentType:'text/javascript',body:fs.readFileSync(path.join(root,'schoolsafe-card-bridge/scan-locations.js'),'utf8')});
    if(url.pathname==='/scanner-relay.php') {
     const input=request.postDataJSON();requests.push(input);
     if(input.action==='list')return route.fulfill({json:{data:{locations:rows}}});
     writes.push({input,csrf:request.headers()['x-schoolsafe-csrf']});
     if(rejectSave)return route.fulfill({status:503,json:{error:'LOCATION_CONFIGURATION_UNAVAILABLE'}});
     const id=input.id||(engine==='native'?'42':'22222222-2222-2222-2222-222222222222');
     rows=rows.filter(row=>row.id!==id).concat([{...input,id}]);
     return route.fulfill({json:{data:{id}}});
    }
    return route.fulfill({body:'',contentType:'text/css'});
   });
   await page.goto('http://scan.fixture.invalid/scan-locations.php');
   if(read)await page.locator('#location-list p').first().waitFor();
   if(!save) {
    check('read-only native page has no form or edit button',await page.locator('form').count()===0&&await page.getByRole('button',{name:'Modifier'}).count()===0);
   } else {
    const native=engine==='native';
    check(engine+' correct visible fields',await page.locator('[name=code]').isVisible()===!native&&await page.locator('[name=direction]').isVisible()===native);
    check(engine+' hidden controls cannot block validity',await page.locator('[name=code]').isDisabled()===native&&await page.locator('[name=direction]').isDisabled()===!native);
    check(engine+' respects actual title length',await page.locator('[name=label]').getAttribute('maxlength')===(native?'100':'200'));
    if(!read)check('save-only native page does not borrow list permission',requests.length===0);
    await page.locator('[name=label]').fill('Synthetic new location');
    if(native)await page.locator('[name=direction]').selectOption('out');
    else {await page.locator('[name=code]').fill('DOOR-2');await page.locator('[name=kind]').selectOption('door');}
    await page.locator('[name=is_active]').check();await page.getByRole('button',{name:'Enregistrer',exact:true}).click();
    await page.waitForFunction(()=>!document.querySelector('button[type=submit]').disabled);
    check(engine+' create uses existing CSRF header',writes.length===1&&writes[0].csrf==='synthetic-only-csrf');
    const input=writes[0].input;
    check(engine+' sends only its own model',native?input.direction==='out'&&!('code' in input)&&!('kind' in input):input.code==='DOOR-2'&&input.kind==='door'&&!('direction' in input));
    check(engine+' create remains explicit active without hidden id',input.is_active===true&&!('id' in input));
    if(read) {
     await page.getByRole('button',{name:'Modifier'}).first().click();
     check(engine+' edit retains native identifier and active state',(await page.locator('[name=id]').inputValue())===rows[0].id&&(await page.locator('[name=is_active]').isChecked())===rows[0].is_active);
    }
    rejectSave=true;await page.locator('[name=label]').fill('Synthetic failed save');await page.getByRole('button',{name:'Enregistrer',exact:true}).click();
    await page.waitForFunction(()=>document.querySelector('#location-status').textContent.includes('indisponible'));
    check(engine+' rejected save is not shown as success',!(await page.locator('#location-status').textContent()).includes('enregistre'));
   }
   check(engine+' no JavaScript exception',errors.length===0);await page.close();
  }
  console.log(JSON.stringify({status:'PASS',checks:checks.length,scope:'Actual markup and JS with synthetic intercepted transport; not authenticated HTTP, PostgreSQL or camera',results:checks},null,2));
 } finally {await browser.close();}
})().catch(error=>{console.error(error.stack);process.exitCode=1;});
