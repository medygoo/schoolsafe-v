#!/usr/bin/env node
// Real Chromium, native PHP-rendered synthetic fragment; no authenticated HTTP.
const fs=require('node:fs'),path=require('node:path');
const {chromium}=require('playwright');
(async()=>{
 const source=process.env.SCHOOL_ACCESS_HTML,out=process.env.SCHOOL_ACCESS_EVIDENCE;
 if(!source||!out)throw Error('Explicit private rendered fixture and evidence required');
 fs.mkdirSync(out,{recursive:false});const checks=[];
 const browser=await chromium.launch({headless:true,executablePath:process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH});
 const page=await browser.newPage();let errors=[];page.on('pageerror',e=>errors.push(e.message));
 const check=(ok,name)=>{if(!ok)throw Error(name);checks.push(name)};
 try{
  for(const width of [1280,390]){
   await page.setViewportSize({width,height:844});
   await page.setContent('<!doctype html><html lang="fr"><head><meta charset="utf-8"><style>body{font:16px system-ui;padding:16px}table{width:100%;border-collapse:collapse}td,th{padding:5px;overflow-wrap:anywhere}small{overflow-wrap:anywhere}button{padding:10px;margin:4px}</style></head><body>'+fs.readFileSync(source,'utf8')+'</body></html>');
   const form=page.locator('[data-native-access-form]');await form.waitFor();
   check(await form.locator('[data-access-use]').count()>=5,width+' independent native task controls');
   await form.locator('[data-access-clear]').click();
   check(await form.locator('[data-access-use]:checked,[data-access-edit]:checked').count()===0,width+' clear does not submit');
   check(!await form.evaluate(f=>f.checkValidity()),width+' human confirmation mandatory');
   await form.locator('[data-access-all]').click();
   check(await form.locator('[data-access-use]:not(:checked)').count()===0,width+' full selection includes every offered use');
   check(await form.locator('[data-access-edit]:not(:disabled):not(:checked)').count()===0,width+' full selection includes every offered edit');
   const first=form.locator('tr').filter({has:page.locator('[data-access-use]')}).first();
   await first.locator('[data-access-use]').uncheck();
   check(!await first.locator('[data-access-edit]').isChecked(),width+' removing use also removes edit');
   await first.locator('[data-access-edit]').check();
   check(await first.locator('[data-access-use]').isChecked(),width+' choosing edit selects its required use');
   check((await form.locator('[name="access_version"]').inputValue()).length===64,width+' version precondition rendered');
   await form.locator('[name="access_confirm"]').check();
   check(await form.evaluate(f=>f.checkValidity()),width+' explicit confirmation completes valid form');
   check(await form.getAttribute('action')==='/ecoles/1/profils#school-native-access',width+' school form never targets ProDéli');
   await page.screenshot({path:path.join(out,'native-access-'+width+'.png'),fullPage:true});
  }
  check(errors.length===0,'no JavaScript page errors');
  fs.writeFileSync(path.join(out,'result.json'),JSON.stringify({status:'PASS',checks:checks.length,results:checks,evidence:'Real Chromium on PHP-rendered synthetic HTML; no authenticated HTTP or production'},null,2));
  console.log(JSON.stringify({status:'PASS',checks:checks.length}));
 }finally{await browser.close()}
})().catch(e=>{console.error(e.message);process.exitCode=1});
