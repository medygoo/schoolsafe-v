'use strict';
// Real markup and JS, intercepted transport. No authentication or actual grant.
const fs=require('node:fs'),path=require('node:path'),{chromium}=require('playwright');
const root=path.join(__dirname,'modules/codex-ui-v1/extensions-test-runtime');
(async()=>{
 const results=[];const check=(name,ok)=>{results.push({name,ok});if(!ok)throw new Error(name);};
 const browser=await chromium.launch({headless:true,executablePath:process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH||'C:/Users/PC/AppData/Local/ms-playwright/chromium_headless_shell-1234/chrome-headless-shell-win64/chrome-headless-shell.exe'});
 try{
  for(const action of ['grants','delegate'])for(const responseKind of ['valid','empty','unsafe','forbidden']){
   const page=await browser.newPage({viewport:{width:390,height:844}}),sent=[],errors=[];
   page.on('pageerror',e=>errors.push(e.message));
   const source=fs.readFileSync(path.join(root,'family-management.php'),'utf8');
   let html=source.slice(source.indexOf('<!doctype html>')).replace(/<\?[\s\S]*?\?>/g,'').replace(' data-engine=""','');
   html=html.replace('<body ','<body data-engine="native" ').replace('data-actions=""',`data-actions='${JSON.stringify([action])}'`).replace('data-parent=""','data-parent="false"');
   await page.route('**/*',async route=>{
    const req=route.request(),url=new URL(req.url());
    if(url.pathname==='/family-management.php')return route.fulfill({contentType:'text/html',body:html});
    if(url.pathname.endsWith('/family-management.js'))return route.fulfill({contentType:'text/javascript',body:fs.readFileSync(path.join(root,'schoolsafe-card-bridge/family-management.js'),'utf8')});
    if(url.pathname==='/scanner-relay.php'){
     if(url.searchParams.get('operation')==='capabilities')return route.fulfill({json:{csrfToken:'synthetic-csrf',data:{capabilities:[]}}});
     sent.push({body:req.postDataJSON(),csrf:req.headers()['x-schoolsafe-csrf']});
     return route.fulfill({status:responseKind==='forbidden'?403:200,json:{data:{status:'native_editor_required',scope:'school_profiles',editor_url:responseKind==='unsafe'?'//outside.invalid/steal':'/ecoles/1/profils',profiles:responseKind==='empty'?[]:[{profile_id:'8',profile_title:'<img src=x onerror=alert(1)>',capability:'SCAN_ENTRY',can_edit:false}]}}});
    }
    return route.fulfill({body:'',contentType:'text/css'});
   });
   await page.goto('http://family.fixture.invalid/family-management.php');
   await page.waitForFunction(()=>!document.querySelector('#management-submit').disabled);
   check(action+' '+responseKind+' has no misleading UUID or assignment fields',await page.locator('#management-fields [name]').count()===0);
   await page.getByRole('button',{name:'Valider',exact:true}).click();
   await page.waitForFunction(()=>!document.querySelector('#management-submit').disabled);
   check(action+' '+responseKind+' submits only preparation action and CSRF',sent.length===1&&JSON.stringify(sent[0].body)===JSON.stringify({action})&&sent[0].csrf==='synthetic-csrf');
   const output=page.locator('#management-result');
   if(['valid','empty'].includes(responseKind)){
    check(action+' '+responseKind+' links to existing owner',await output.locator('a').getAttribute('href')==='/ecoles/1/profils');
    check(action+' '+responseKind+' explains shared-profile impact without success claim',(await output.textContent()).includes('Aucune permission n’a été modifiée ici'));
    check(action+' '+responseKind+' cannot inject markup',await output.locator('img,script').count()===0);
    if(action==='grants'&&responseKind==='empty')check('empty school profiles never imply no global rights',(await output.textContent()).includes('ne sont pas recensés ici'));
   }else check(action+' '+responseKind+' error leaves no unsafe link or success',await output.textContent()===''&&(await page.locator('#management-state').textContent()).length>0);
   check(action+' '+responseKind+' has no JS exception',errors.length===0);await page.close();
  }
 }finally{await browser.close();}
 console.log(JSON.stringify({status:'PASS',checks:results.length,scope:'Intercepted real markup and JS only',results},null,2));
})().catch(e=>{console.error(e.stack);process.exitCode=1;});
