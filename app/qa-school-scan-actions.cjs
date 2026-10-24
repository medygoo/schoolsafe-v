'use strict';
// Native header JS; synthetic navigation transport only, not authenticated HTTP.
const fs=require('node:fs'),path=require('node:path');const {chromium}=require('playwright');
const root=path.join(__dirname,'modules/codex-ui-v1/extensions-test-runtime');
(async()=>{
 const browser=await chromium.launch({headless:true,executablePath:process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH||'C:/Users/PC/AppData/Local/ms-playwright/chromium_headless_shell-1234/chrome-headless-shell-win64/chrome-headless-shell.exe'});
 const results=[];const check=(name,ok)=>{results.push({name,status:ok?'PASS':'FAIL'});};
 try{
  for(const role of ['parent','teacher','admin','global','unavailable','forged']){
   const page=await browser.newPage({viewport:{width:390,height:844}});const errors=[],writes=[];page.on('pageerror',error=>errors.push(error.message));
   const visible=['parent','teacher','admin'].includes(role);
   await page.route('**/*',async route=>{
    const request=route.request(),url=new URL(request.url());if(request.method()!=='GET')writes.push(url.pathname);
    if(url.pathname==='/Modules.php')return route.fulfill({contentType:'text/html',body:'<!doctype html><html lang="fr"><body><div id="footerwrap"><a href="Modules.php?modfunc=logout">Déconnexion</a></div><div id="menu"><ul class="adminmenu"></ul></div><div id="body"><div id="ss-photo-create" hidden><h3>Actions rapides</h3></div></div><script src="/assets/themes/SchoolSafeCodex/scripts.js"></script></body></html>'});
    if(url.pathname==='/central-navigation.php')return route.fulfill({status:role==='unavailable'?503:200,json:{links:[],profile:{name:'Synthetic '+role,url:'Modules.php?modname=Users/User.php&category_id=1&staff_id=9'},scanAction:visible?'scanner-native.php':(role==='forged'?'https://evil.invalid/':null)}});
    if(url.pathname==='/assets/themes/SchoolSafeCodex/scripts.js')return route.fulfill({contentType:'text/javascript',body:fs.readFileSync(path.join(root,'assets/themes/SchoolSafeCodex/scripts.js'),'utf8')});
    if(url.pathname==='/scanner-native.php')return route.fulfill({status:403,contentType:'text/html',body:'Permission d’exécution requise.'});
    return route.fulfill({body:''});
   });
   await page.goto('http://actions.fixture.invalid/Modules.php');await page.locator('.ss-header-tools').waitFor();
   const link=page.locator('#ss-header-scan');
   check(role+' scan action visibility is navigation metadata not inferred execution',await link.count()===(visible?1:0));
   check(role+' existing desktop Actions receives the same entry only in school context',await page.locator('#ss-photo-create a[href="scanner-native.php"]').count()===(visible?1:0));
   check(role+' header does not mutate or throw',writes.length===0&&errors.length===0);
   if(visible){
    if(await link.count()===1){const refusal=page.waitForResponse(response=>new URL(response.url()).pathname==='/scanner-native.php');await link.click();check(role+' visible entry cannot bypass independent execution refusal',(await refusal).status()===403&&writes.length===0);}
    else check(role+' visible entry cannot bypass independent execution refusal',false);
   }
   await page.close();
  }
 }finally{await browser.close();}
 const failed=results.filter(x=>x.status==='FAIL').length;console.log(JSON.stringify({status:failed?'FAIL':'PASS',checks:results.length,failed,scope:'Actual native header JS, intercepted synthetic transport; not authenticated HTTP',results},null,2));if(failed)process.exitCode=1;
})().catch(error=>{console.error(error.stack);process.exitCode=1;});
