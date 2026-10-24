'use strict';
// Intercepted browser proof only: native handlers/adapter, synthetic responses.
const {chromium}=require('playwright');const fs=require('node:fs');const path=require('node:path');const assert=require('node:assert/strict');
const root=path.join(__dirname,'modules/codex-ui-v1/extensions-test-runtime/schoolsafe-card-bridge');
(async()=>{const browser=await chromium.launch({headless:true,executablePath:process.env.CHROMIUM_PATH||'C:/Users/PC/AppData/Local/ms-playwright/chromium_headless_shell-1234/chrome-headless-shell-win64/chrome-headless-shell.exe'});let passed=0;const check=(v,n)=>{assert(v,n);passed++;console.log('PASS '+n);};try{
 const page=await browser.newPage();let issued=false;let posts=[];let allow=true;const errors=[];page.on('pageerror',e=>errors.push(e.message));
 const qr='schoolsafe://card/0123456789abcdef0123456789abcdef/0123456789abcdef0123456789abcdef';
 await page.route('**/*',async route=>{const u=new URL(route.request().url());if(u.hostname!=='native-card-fixture.invalid')throw new Error('External request forbidden');
 if(u.pathname==='/card-bridge-prepare.php'){const data={backend:'native-registry',issuanceAllowed:false,operationPerformed:false,nativeCredentialIssuanceAllowed:allow&&!issued,missing:['class.mapping','class.cycle','class.tutor'],nativeReference:{studentId:'5',schoolId:'1',year:'2026'},targetReference:{studentId:'5',schoolId:'1',year:'2026'},input:{record:{id:'5',school_id:'1',first_name:'SYNTHETIC'},credential:issued?{status:'active',qr_payload:qr}:{}}};return route.fulfill({contentType:'application/json',body:JSON.stringify({data})});}
 if(u.pathname==='/card-bridge-issue.php'){posts.push(Object.fromEntries(new URLSearchParams(route.request().postData())));issued=true;return route.fulfill({contentType:'application/json',body:JSON.stringify({result:'ISSUED',operationPerformed:true})});}
 return route.fulfill({contentType:'text/html',body:'<div id="PC"></div><form id="native-lookup"><input id="native-student"><button>Consulter</button><button id="native-issue" type="button" disabled>Emettre</button><button id="native-print" type="button" disabled>Imprimer</button><button id="native-print-preview" type="button" disabled>Voir</button><input id="native-issue-csrf" value="synthetic-csrf"></form>'});});
 await page.goto('http://native-card-fixture.invalid/');await page.evaluate(()=>{window.SchoolSafeNativeSchool='1';window.SchoolSafeNativeYear='2026';window.render=()=>{};window.installOfficialQrGuard=()=>{};window.toast=()=>{};window.SchoolSafeStudio={setData:()=>{}};window.toRestoredStudioData=()=>{throw new Error('Do not invent class data for QR-only preparation');};});
 for(const f of ['studio-original-adapter.js','studio-native-client.js','restored-studio-native.js'])await page.addScriptTag({content:fs.readFileSync(path.join(root,f),'utf8')});
 await page.locator('#native-student').fill('5');await page.locator('#native-lookup button').first().click();await page.waitForFunction(()=>!document.getElementById('native-issue').disabled);
 check(await page.locator('#native-issue').innerText()==='Enregistrer le QR officiel','Unmapped native class does not block the separate authorized QR registration');
 check(await page.locator('#native-print').isDisabled()&&await page.locator('#native-print-preview').isDisabled(),'QR capability never invents official-print readiness');
 check(posts.length===0,'Preparation performs no issuance');
 await page.locator('#native-issue').click();await page.waitForFunction(()=>document.getElementById('native-credential-status').textContent.includes('schoolsafe://card/'));
 check(posts.length===1,'Explicit action emits one request, not a preparation-time mutation');
 check(posts[0].expected_school==='1'&&posts[0].expected_year==='2026'&&posts[0].csrf==='synthetic-csrf','Mutation carries the original school/year intent and CSRF');
 check((await page.locator('#native-credential-status').textContent()).includes(qr),'Freshly reread QR is available to the existing scanner without bridge');
 check(await page.locator('#native-print').isDisabled()&&await page.locator('#native-print-preview').isDisabled(),'Issuance does not bypass protected printing controls');
 await page.locator('#native-student').fill('6');check(await page.locator('#native-credential-status').textContent()==='','Selection change removes the old credential immediately');
 issued=false;allow=false;await page.locator('#native-student').fill('5');await page.locator('#native-lookup button').first().click();await page.waitForFunction(()=>!document.querySelector('#native-lookup button').disabled);
 check(await page.locator('#native-issue').isDisabled(),'Reader without issuance capability has no enabled write action');
 check(errors.length===0,'No script errors in the native no-class-mapping flow');
 console.log(JSON.stringify({passed,failed:0,evidence:'intercepted synthetic browser, not authenticated HTTP or physical camera'}));
 }finally{await browser.close();}})().catch(e=>{console.error(e);process.exitCode=1;});
