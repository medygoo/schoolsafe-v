'use strict';
// Actual PHP markup and browser JS, all transport intercepted. No authenticated HTTP/business write.
const fs=require('node:fs'),path=require('node:path');const {chromium}=require('playwright');
const root=path.join(__dirname,'modules/codex-ui-v1/extensions-test-runtime');
const markup=file=>{const source=fs.readFileSync(path.join(root,file),'utf8');return source.slice(source.indexOf('<!doctype html>')).replace(/<\?[\s\S]*?\?>/g,'');};
(async()=>{
 const results=[];let failed=0;const check=(name,ok)=>{results.push({name,status:ok?'PASS':'FAIL'});if(!ok)failed++;};
 const browser=await chromium.launch({headless:true,executablePath:process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH||'C:/Users/PC/AppData/Local/ms-playwright/chromium_headless_shell-1234/chrome-headless-shell-win64/chrome-headless-shell.exe'});
 const assets=async(route,url)=>{
  const allowed=['fee-control.js','scanner-native.js','parent-notifications.css','fee-colors.css','scanner-native.css'];
  const file=url.pathname.split('/').pop();
  if(url.pathname.startsWith('/schoolsafe-card-bridge/')&&allowed.includes(file))return route.fulfill({contentType:file.endsWith('.js')?'text/javascript':'text/css',body:fs.readFileSync(path.join(root,'schoolsafe-card-bridge',file),'utf8')});
  return route.fulfill({body:''});
 };
 try{
  for(const [canRead,canManage] of [[true,true],[true,false],[false,true]]){
   const page=await browser.newPage({viewport:{width:390,height:844}}),sent=[],errors=[];page.on('pageerror',e=>errors.push(e.message));
   let rows=[{id:'11111111-1111-4111-8111-111111111111',label:'Synthetic <script>window.fakeExecution=true</script>',threshold:'80.00',tolerance:10,active:false}];
   await page.route('**/*',async route=>{
    const request=route.request(),url=new URL(request.url());
    if(url.pathname==='/fee-control.php')return route.fulfill({contentType:'text/html',body:markup('fee-control.php').replace('data-fee-read=""',`data-fee-read="${canRead}"`).replace('data-fee-manage=""',`data-fee-manage="${canManage}"`)});
    if(url.pathname==='/scanner-relay.php'){
     if(url.searchParams.get('operation')==='capabilities')return route.fulfill({json:{csrfToken:'synthetic-only'}});
     const body=request.postDataJSON();sent.push({body,csrf:request.headers()['x-schoolsafe-csrf']});
     if(body.action==='activate')rows=rows.map(row=>({...row,active:row.id===body.id}));
     if(body.action==='stop')rows=rows.map(row=>({...row,active:false}));
     return route.fulfill({json:{data:canRead?rows:[]}});
    }return assets(route,url);
   });
   await page.goto('http://finance.fixture.invalid/fee-control.php');
   if(canRead)await page.waitForFunction(()=>document.querySelector('#fee-models').textContent.includes('Synthetic'));
   else await page.waitForFunction(()=>!document.querySelector('#fee-save').disabled);
   check('read/manage '+canRead+'/'+canManage+' form visibility',await page.locator('#fee-model-form').isVisible()===canManage);
   check('read/manage '+canRead+'/'+canManage+' list visibility',await page.locator('#fee-models').isVisible()===canRead);
   check('model label is rendered as text not executable HTML',await page.evaluate(()=>window.fakeExecution!==true));
   if(canRead&&canManage){
    await page.getByRole('button',{name:'Activer ce barème'}).click();
    await page.waitForFunction(()=>document.querySelector('#fee-models').textContent.includes('Actif'));
    check('activation submits opaque existing model id with CSRF',sent.some(item=>item.body.action==='activate'&&item.body.id===rows[0].id&&item.csrf==='synthetic-only'));
    await page.locator('#fee-stop').click();await page.waitForFunction(()=>document.querySelector('#fee-models').textContent.includes('Inactif'));
    check('stop uses existing management operation',sent.some(item=>item.body.action==='stop'));
   }
   if(canManage){await page.locator('[name=label]').fill('Synthetic new control');await page.locator('[name=threshold]').fill('80.00');await page.locator('[name=tolerance]').fill('10');await page.locator('#fee-save').click();await page.waitForFunction(()=>!document.querySelector('#fee-save').disabled);check('save sends real explicit minimum and integer tolerance',sent.some(item=>item.body.action==='save'&&item.body.threshold==='80.00'&&item.body.tolerance===10&&item.csrf==='synthetic-only'));}
   if(!canManage)check('read-only page cannot initiate activation or save',sent.every(item=>item.body.action==='list'));
   if(!canRead)check('save-only page never requests list',sent.every(item=>item.body.action!=='list'));
   check('finance management JS has no exception',errors.length===0);await page.close();
  }
  for(const [color,reason,resultCode,state] of [['red','FEE_CONTROL_DENIED','DENIED','active'],[null,'FEE_CONTROL_UNAVAILABLE','DENIED','unavailable'],['yellow',null,'PRESENT','active'],['blue',null,'PRESENT','active'],[null,null,'PRESENT','inactive']]){
   const page=await browser.newPage({viewport:{width:390,height:844}});let posts=0;
   await page.route('**/*',async route=>{
    const request=route.request(),url=new URL(request.url());
    if(url.pathname==='/scanner-native.php')return route.fulfill({contentType:'text/html',body:markup('scanner-native.php')});
    if(url.pathname==='/scanner-relay.php'){
     const operation=url.searchParams.get('operation');
     if(operation==='capabilities')return route.fulfill({json:{csrfToken:'synthetic-only',data:{capabilities:['SCAN_ENTRY','SCAN_FEES'],fee_policy_active:state!=='inactive',fee_consult_available:true}}});
     if(operation==='locations')return route.fulfill({json:{data:{locations:[{id:'7',label:'Synthetic gate'}]}}});
     posts++;return route.fulfill({json:{data:{result:resultCode,reason,student_name:'Synthetic fixture',financial:{state,color}}}});
    }return assets(route,url);
   });
   await page.goto('http://finance.fixture.invalid/scanner-native.php');await page.locator('#scan-form').waitFor({state:'visible'});
   if(state==='inactive')check('consultation remains available without fictitious active policy',await page.locator('[data-mode=fees]').isEnabled());
   await page.locator('#scan-qr').fill('Synthetic intercepted QR');await page.locator('#scan-form button[type=submit]').click();
   await page.waitForFunction(()=>document.querySelector('#scan-result').textContent!=='');
   const text=await page.locator('#scan-result').textContent();
   check('financial '+state+'/'+color+' displays only actual color',await page.locator('#scan-result').getAttribute('data-fee-color')===(color||''));
   check('financial '+state+'/'+color+' sends one scan',posts===1);
   if(resultCode==='DENIED')check('refusal never claims arrival collection or absence',!text.includes('Arrivée enregistrée')&&!text.includes('Remise')&&!text.includes('absent'));
   else check('committed fixture arrival is described as arrival',text.includes('Arrivée enregistrée'));
   if(state==='inactive')check('stopped financial control is described honestly',text.includes('Aucun contrôle financier actif'));
   if(state==='unavailable')check('unknown financial data does not claim debt',!text.includes('Impayé')&&!text.includes('jaune')&&!text.includes('bleu'));
   await page.close();
  }
  {
   const page=await browser.newPage({viewport:{width:390,height:844}}),sent=[],errors=[];page.on('pageerror',error=>errors.push(error.message));
   await page.route('**/*',async route=>{
    const request=route.request(),url=new URL(request.url());
    if(url.pathname==='/scanner-native.php')return route.fulfill({contentType:'text/html',body:markup('scanner-native.php')});
    if(url.pathname==='/scanner-relay.php'){
     if(url.searchParams.get('operation')==='capabilities')return route.fulfill({json:{csrfToken:'synthetic-only',data:{capabilities:['SCAN_FEES'],fee_policy_active:true,fee_consult_available:true,request_key_modes:['fees']}}});
     sent.push({body:request.postDataJSON(),csrf:request.headers()['x-schoolsafe-csrf']});
     if(sent.length===1||sent.length===4)return route.abort('failed'); // Lost response, not an external business submission.
     return route.fulfill({json:{data:{result:'FEE_STATUS',student_name:'Synthetic fixture',balance_status:sent.length===5?'paid':'partial',idempotent:sent.length===2||sent.length===5,financial:{state:'active',color:sent.length===5?'blue':'yellow'}}}});
    }return assets(route,url);
   });
   await page.goto('http://finance.fixture.invalid/scanner-native.php');await page.locator('#scan-form').waitFor({state:'visible'});
   await page.locator('#scan-qr').fill('Synthetic intercepted QR');await page.locator('#scan-form button[type=submit]').click();
   await page.waitForFunction(()=>document.querySelector('#scan-result').textContent.includes('Aucune confirmation'));
   check('lost financial response never claims success',await page.locator('#scan-result').textContent()==='Scan indisponible. Aucune confirmation reçue.');
   await page.locator('#scan-form button[type=submit]').click();await page.waitForFunction(()=>document.querySelector('#scan-result').textContent.includes('Opération déjà enregistrée'));
   check('financial retry retains the original opaque operation UUID',sent.length===2&&sent[0].body.request_key===sent[1].body.request_key&&/^[a-f0-9]{8}-(?:[a-f0-9]{4}-){3}[a-f0-9]{12}$/.test(sent[0].body.request_key));
   check('financial replay is explicitly described as no new control',await page.locator('#scan-result').textContent().then(text=>text.includes('aucun nouveau contrôle')));
   await page.locator('#scan-form button[type=submit]').click();await page.waitForFunction(()=>!document.querySelector('#scan-form button[type=submit]').disabled);
   check('a deliberate subsequent control receives a new operation UUID',sent.length===3&&sent[2].body.request_key!==sent[1].body.request_key);
   check('financial request carries no client money or invented financial facts',sent.every(item=>Object.keys(item.body).sort().join(',')==='mode,qr_payload,request_key'&&item.csrf==='synthetic-only'));
   await page.locator('#scan-form button[type=submit]').click();await page.waitForFunction(()=>document.querySelector('#scan-result').textContent.includes('Aucune confirmation'));
   check('lost response after known yellow never retains a financial color',await page.locator('#scan-result').getAttribute('data-fee-color')==='');
   await page.locator('#scan-form button[type=submit]').click();await page.waitForFunction(()=>document.querySelector('#scan-result').textContent.includes('À jour'));
   check('known zero debt never claims that an actual payment took place',await page.locator('#scan-result').textContent().then(text=>!text.includes('Payé')&&!text.includes('Paiement confirmé')));
   check('retry after later response loss still retains its correct operation UUID',sent.length===5&&sent[3].body.request_key===sent[4].body.request_key);
   check('financial retry JS has no exception',errors.length===0);await page.close();
  }
  for(const denied of [false,true]){
   const page=await browser.newPage({viewport:{width:390,height:844}}),sent=[];
   await page.route('**/*',async route=>{
    const request=route.request(),url=new URL(request.url());
    if(url.pathname==='/scanner-native.php')return route.fulfill({contentType:'text/html',body:markup('scanner-native.php')});
    if(url.pathname==='/scanner-relay.php'){
     const operation=url.searchParams.get('operation');
     if(operation==='capabilities')return route.fulfill({json:{csrfToken:'synthetic-only',data:{capabilities:['SCAN_ENTRY'],fee_policy_active:denied,request_key_modes:['entry','fees']}}});
     if(operation==='locations')return route.fulfill({json:{data:{locations:[{id:'7',label:'Synthetic gate'}]}}});
     sent.push(request.postDataJSON());if(sent.length===1)return route.abort('failed');
     return route.fulfill({json:{data:denied?{result:'DENIED',reason:'FEE_CONTROL_DENIED',idempotent:true,financial:{state:'active',color:'red'}}:{result:'ALREADY_RECORDED',original_result:'PRESENT',idempotent:true,record_id:20}}});
    }return assets(route,url);
   });
   await page.goto('http://finance.fixture.invalid/scanner-native.php');await page.locator('#scan-form').waitFor({state:'visible'});
   await page.locator('#scan-qr').fill('Synthetic intercepted QR');await page.locator('#scan-form button[type=submit]').click();
   await page.waitForFunction(()=>document.querySelector('#scan-result').textContent.includes('Aucune confirmation'));
   await page.locator('#scan-form button[type=submit]').click();await page.waitForFunction(flag=>document.querySelector('#scan-result').textContent.includes(flag?'demande précédente':'aucune nouvelle arrivée'),denied);
   check('entry retry keeps its original operation UUID '+denied,sent.length===2&&typeof sent[0].request_key==='string'&&sent[0].request_key===sent[1].request_key&&sent[1].location_id==='7');
   check('entry replay never claims another actual arrival '+denied,await page.locator('#scan-result').textContent().then(text=>!text.includes('Arrivée enregistrée.')));
   if(!denied)check('historical arrival does not reuse blue or yellow authorization',await page.locator('#scan-result').getAttribute('data-fee-color')==='');
   await page.close();
  }
 }finally{await browser.close();}
 console.log(JSON.stringify({status:failed?'FAIL':'PASS',checks:results.length,failed,scope:'Actual markup/JS; fully intercepted transport, not authenticated HTTP/camera',results},null,2));if(failed)process.exitCode=1;
})().catch(error=>{console.error(error.stack);process.exitCode=1;});
