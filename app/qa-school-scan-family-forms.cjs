'use strict';
// Actual page/JS, fully intercepted transport. No database, login or external send.
const fs=require('node:fs'),path=require('node:path');const {chromium}=require('playwright');
const root=path.resolve(__dirname,'modules/codex-ui-v1/extensions-test-runtime');
(async()=>{
 const results=[];let failed=0;const check=(name,ok)=>{results.push({name,status:ok?'PASS':'FAIL'});if(!ok)failed++;};
 const source=fs.readFileSync(path.join(root,'family-management.php'),'utf8');
 check('page uses shared existing backend selector',source.includes('SchoolSafeScanBridgeAvailable()')&&source.includes('data-engine='));
 const browser=await chromium.launch({headless:true,executablePath:process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH||'C:/Users/PC/AppData/Local/ms-playwright/chromium_headless_shell-1234/chrome-headless-shell-win64/chrome-headless-shell.exe'});
 try {
  const cases=[
   ['native',true,'parent-activate',['nativeStudentId','authorizationId','reason']],
   ['native',false,'register-external',['nativeStudentId','fullName','phone','reason','consent']],
   ['native',false,'register-family',['nativeStudentId','guardianType','fullName','phone','isPrimary']],
   ['bridge',true,'parent-activate',['nativeStudentId','guardianId','reason']],
   ['bridge',false,'register-external',['nativeStudentId','fullName','phone','email','address','consent','photoPath']],
   ['native',false,'school-family-approve',['nativeStudentId','guardianId','reason','consent','parentStaffId'],true],
   ['native',false,'school-family-approve',['nativeStudentId','guardianId','reason','consent','parentStaffId'],false],
   ['bridge',false,'school-family-approve',['nativeStudentId','guardianId','reason','consent']],
   ['native',false,'register-external',['nativeStudentId','fullName','phone','reason','consent'],true,'PRIMARY_PARENT_REQUIRED']
  ];
  for(const [engine,parent,action,expected,bindParent=true,publicError=null] of cases) {
   const page=await browser.newPage({viewport:{width:390,height:844}}),errors=[],sent=[];
   page.on('pageerror',error=>errors.push(error.message));
   let html=source.slice(source.indexOf('<!doctype html>')).replace(/<\?[\s\S]*?\?>/g,'').replace(' data-engine=""','');
   html=html.replace('<body ',`<body data-engine="${engine}" `).replace('data-actions=""',`data-actions='${JSON.stringify([action])}'`).replace('data-parent=""',`data-parent="${parent}"`);
   await page.route('**/*',async route=>{
    const request=route.request(),url=new URL(request.url());
    if(url.pathname==='/family-management.php')return route.fulfill({contentType:'text/html',body:html});
    if(url.pathname.endsWith('/family-management.js'))return route.fulfill({contentType:'text/javascript',body:fs.readFileSync(path.join(root,'schoolsafe-card-bridge/family-management.js'),'utf8')});
    if(url.pathname==='/scanner-relay.php') {
     if(url.searchParams.get('operation')==='capabilities')return route.fulfill({json:{csrfToken:'synthetic-only',data:{capabilities:[]}}});
     sent.push({input:request.postDataJSON(),csrf:request.headers()['x-schoolsafe-csrf']});
     return route.fulfill({status:publicError?409:403,json:{error:publicError||'NATIVE_SCANNER_UNAVAILABLE'}});
    }
    return route.fulfill({contentType:'text/css',body:''});
   });
   await page.goto('http://family.fixture.invalid/family-management.php');
   await page.waitForFunction(()=>!document.querySelector('#management-submit').disabled);
   const names=await page.locator('#management-fields [name]').evaluateAll(nodes=>nodes.map(node=>node.name));
   const matches=JSON.stringify(names)===JSON.stringify(expected);
   check(engine+' '+action+' sends fields actually consumed by its engine',matches);
   if(matches) {
    for(const name of names) {
     const field=page.locator(`[name=${name}]`);
     if(name==='isPrimary')await field.check();
     else if(name==='guardianType')await field.selectOption('pere');
     else await field.fill(name==='parentStaffId'?(bindParent?'27':''):name==='nativeStudentId'?'11':name==='authorizationId'?'41':name==='guardianId'?(engine==='native'?'21':'11111111-1111-1111-1111-111111111111'):name==='email'?'':name==='phone'?'':name==='photoPath'?'/uploads/authorized-persons/synthetic.jpg':'Synthetic fixture '+name);
    }
    await page.getByRole('button',{name:'Valider',exact:true}).click();
    await page.waitForFunction(()=>!document.querySelector('#management-submit').disabled&&document.querySelector('#management-state').textContent!=='');
    if(publicError)check('missing primary parent binding is explained without a fictitious card requirement',(await page.locator('#management-state').textContent()).includes('compte parent déjà associé'));
    check(engine+' '+action+' retains action and existing CSRF',sent.length===1&&sent[0].input.action===action&&sent[0].csrf==='synthetic-only');
    if(engine==='native'&&action==='school-family-approve') {
     check('account binding is optional, not an account requirement for every person',await page.locator('[name=parentStaffId]').getAttribute('required')===null);
     check('only explicitly supplied native parent ID is sent',bindParent?sent[0].input.parentStaffId==='27':!Object.hasOwn(sent[0].input,'parentStaffId'));
    }
    check(engine+' '+action+' refusal is not displayed as a business success',(await page.locator('#management-result').textContent())==='');
   }
   check(engine+' '+action+' has no JS exception',errors.length===0);await page.close();
  }
 }finally {await browser.close();}
 console.log(JSON.stringify({status:failed?'FAIL':'PASS',checks:results.length,failed,scope:'Real markup/JS, simulated transport; not authenticated HTTP or business mutation',results},null,2));if(failed)process.exitCode=1;
})().catch(error=>{console.error(error.stack);process.exitCode=1;});
