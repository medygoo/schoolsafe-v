'use strict';
// Real PHP markup/runtime JS, intercepted transport only. No session, DB, push or external sends.
const fs=require('node:fs'),path=require('node:path'),{chromium}=require('playwright');
const root=path.join(__dirname,'modules/codex-ui-v1/extensions-test-runtime');
const source=fs.readFileSync(path.join(root,'parent-notifications-view.php'),'utf8');
const markup=source.slice(source.indexOf('<!doctype html>'));
const at='2026-10-23 10:30:00+00';
const arrival={id:'arrival:17',student_id:'11',student_name:'Synthetic child',event_type:'security.entry',created_at:at,payload:{record_id:17,message:'Arrivée physique enregistrée\nÉcole : Synthetic school\nClasse : Synthetic class\nAnnée : 2026\nNe vaut pas présence en classe.'}};
const pickup={...arrival,id:'departure:18',event_type:'student.pickup',payload:{record_id:18,message:'Sortie et remise confirmées\nPersonne venue : Synthetic authorized guardian\nAucun nouveau paiement.'}};
const financial={...arrival,id:'financial-control:19',event_type:'financial.control',payload:{consult_id:23,delivery_status:'pending',message:'Contrôle financier — état observé, pas un reçu de paiement.\nÉcole : Synthetic school\nClasse : Synthetic class\nAnnée : 2026\nFrais : 100.00 SYN\nExonération : -25.00 SYN\nNet facturé : 75.00 SYN\nVersement net : 70.00 SYN\nSeuil toléré : 67.50 SYN\n<img src=x onerror=window.fakeExecution=true>'}};
(async()=>{
 const results=[];const check=(name,ok)=>{results.push({name,status:ok?'PASS':'FAIL'});if(!ok)throw new Error(name);};
 const browser=await chromium.launch({headless:true,executablePath:process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH||'C:/Users/PC/AppData/Local/ms-playwright/chromium_headless_shell-1234/chrome-headless-shell-win64/chrome-headless-shell.exe'});
 try{
  for(const width of [1280,390]){
   const page=await browser.newPage({viewport:{width,height:844}}),errors=[];page.on('pageerror',e=>errors.push(e.message));
   let state='ready',requests=0;
   await page.route('**/*',route=>{
    const url=new URL(route.request().url());
    if(url.pathname==='/parent-notifications-view.php')return route.fulfill({contentType:'text/html',body:markup});
    if(url.pathname==='/parent-notifications.php'){
     requests++;
     if(state==='revoked')return route.fulfill({status:403,json:{error:'PARENT_REQUIRED'}});
     return route.fulfill({json:{data:{notifications:state==='degraded'?[arrival,pickup]:[arrival,pickup,financial],degradedSources:state==='degraded'?['financial_controls']:[]}}});
    }
    const name=path.basename(url.pathname);
    if(url.pathname.startsWith('/schoolsafe-card-bridge/')&&['parent-notification-data.js','parent-notifications-view.js','parent-notifications.css'].includes(name))return route.fulfill({contentType:name.endsWith('.js')?'text/javascript':'text/css',body:fs.readFileSync(path.join(root,'schoolsafe-card-bridge',name),'utf8')});
    return route.fulfill({contentType:'text/javascript',body:''}); // unrelated PWA/push not qualified here
   });
   await page.goto('http://scan-notices.fixture.invalid/parent-notifications-view.php');
   await page.waitForFunction(()=>document.querySelector('#notifications').children.length===3);
   check(width+' native timeline accepts arrivals handovers and financial controls',await page.locator('#notifications > li').count()===3);
   await page.locator('[data-filter="student.arrival"]').click();
   check(width+' arrivals category includes native security.entry',await page.locator('#notifications > li').count()===1&&await page.locator('#notifications > li').getAttribute('data-type')==='security.entry');
   await page.locator('[data-filter="student.fee_control"]').click();
   const financialRow=page.locator('#notifications > li');
   check(width+' financial category includes native observation not a receipt',await financialRow.count()===1&&await financialRow.getAttribute('data-type')==='financial.control'&&await financialRow.locator('a,[data-receipt-read]').count()===0);
   const text=await financialRow.textContent();
   check(width+' original signed context and bounds remain readable',text.includes('75.00 SYN')&&text.includes('-25.00 SYN')&&text.includes('70.00 SYN')&&text.includes('67.50 SYN')&&text.includes('pas un reçu'));
   check(width+' notification text never executes imported HTML',await financialRow.locator('img,script').count()===0&&await page.evaluate(()=>window.fakeExecution!==true));
   check(width+' full multiline context is not collapsed into a generic label',await financialRow.locator('p').evaluate(el=>getComputedStyle(el).whiteSpace==='pre-wrap'));
   check(width+' native financial notification fits viewport',await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));
   if(process.env.QA_EVIDENCE_DIR){fs.mkdirSync(process.env.QA_EVIDENCE_DIR,{recursive:true});await page.screenshot({path:path.join(process.env.QA_EVIDENCE_DIR,'native-financial-notice-'+width+'.png'),fullPage:true});}
   await page.locator('[data-filter="student.pickup"]').click();
   check(width+' handover retains the actual supplied person identity',(await page.locator('#notifications').textContent()).includes('Synthetic authorized guardian'));
   await page.locator('[data-filter="all"]').click();state='degraded';
   await page.evaluate(()=>document.dispatchEvent(new Event('visibilitychange')));
   await page.waitForFunction(()=>document.querySelector('#state').textContent.includes('indisponibles'));
   check(width+' unavailable financial source does not invent zero debt or payment',await page.locator('#notifications > li').count()===2&&!((await page.locator('#notifications').textContent()).includes('0.00 SYN')));
   state='revoked';await page.evaluate(()=>document.dispatchEvent(new Event('visibilitychange')));
   await page.waitForFunction(()=>!document.querySelector('#retry').hidden);
   check(width+' revoked parent response removes already loaded private notices',await page.locator('#notifications > li').count()===0&&(await page.locator('#state').textContent()).includes('parent requise'));
   check(width+' runtime view has no script exception and uses intercepted requests only',errors.length===0&&requests===3);
   await page.close();
  }
  console.log(JSON.stringify({status:'PASS',scope:'Real markup/JS with intercepted Chromium requests only; not authenticated HTTP/PG/provider/production',results}));
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
