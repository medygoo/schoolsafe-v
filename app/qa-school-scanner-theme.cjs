'use strict';
// Isolated Chromium with intercepted capabilities and synthetic card response; no real user or camera.
const fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict');
const {chromium}=require('playwright');
const root=path.join(__dirname,'modules/codex-ui-v1/extensions-test-runtime');
const html=fs.readFileSync(path.join(root,'scanner-native.php'),'utf8').split('<!doctype html>')[1].replace(/<\?php[\s\S]*?\?>/g,'');
const evidence=process.env.SCANNER_THEME_EVIDENCE;
const palette={white:'rgb(255, 255, 255)',blue:'rgb(220, 236, 255)',gray:'rgb(238, 240, 243)',mint:'rgb(229, 245, 237)',rose:'rgb(251, 233, 239)',yellow:'rgb(255, 245, 217)',night:'rgb(16, 27, 45)'};
(async()=>{
 const browser=await chromium.launch({headless:true,executablePath:process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE||'C:/Users/PC/AppData/Local/ms-playwright/chromium_headless_shell-1234/chrome-headless-shell-win64/chrome-headless-shell.exe'});
 const results=[];try{
 for(const [theme,background] of Object.entries(palette))for(const width of [390,1280]){
  const context=await browser.newContext({viewport:{width,height:820}}),page=await context.newPage();
  try{
   await page.addInitScript(value=>localStorage.setItem('schoolsafe-background',value),theme);
   await page.route('https://scan.invalid/**',route=>{
    const url=new URL(route.request().url());
    if(url.pathname==='/scanner-native.php')return route.fulfill({contentType:'text/html',body:'<!doctype html>'+html});
    if(url.pathname==='/scanner-relay.php')return route.fulfill({json:{csrfToken:'synthetic',data:{capabilities:['SCAN_VERIFY','SCAN_PICKUP'],open_campaign_id:null,request_key_modes:[]}}});
    if(url.pathname==='/schoolsafe-logo.png')return route.fulfill({contentType:'image/png',body:fs.readFileSync(path.join(root,'schoolsafe-logo.png'))});
    if(url.pathname==='/schoolsafe-card-bridge/scanner-theme.js')return route.fulfill({contentType:'application/javascript',body:fs.readFileSync(path.join(root,'schoolsafe-card-bridge/scanner-theme.js'),'utf8')});
    if(url.pathname==='/schoolsafe-card-bridge/scanner-native.js')return route.fulfill({contentType:'application/javascript',body:fs.readFileSync(path.join(root,'schoolsafe-card-bridge/scanner-native.js'),'utf8')});
    if(url.pathname==='/schoolsafe-card-bridge/scanner-native.css')return route.fulfill({contentType:'text/css',body:fs.readFileSync(path.join(root,'schoolsafe-card-bridge/scanner-native.css'),'utf8')});
    return route.fulfill({status:204,body:''});
   });
   await page.goto('https://scan.invalid/scanner-native.php');await page.locator('#scan-form').waitFor({state:'visible'});
   const state=await page.evaluate(()=>({theme:document.documentElement.dataset.ssBackground,bg:getComputedStyle(document.body).backgroundColor,overflow:document.documentElement.scrollWidth>innerWidth,ready:document.getElementById('camera').disabled,empty:document.getElementById('scan-result').textContent.trim(),mode:document.querySelector('[data-mode="verify"]').getAttribute('aria-pressed')}));
   assert.equal(state.theme,theme);assert.equal(state.bg,background);assert.equal(state.overflow,false);assert.equal(state.ready,false);assert.equal(state.empty,'');assert.equal(state.mode,'true');
   assert.equal(await page.locator('[data-mode="entry"]').isDisabled(),true);
   assert.equal(await page.locator('[data-mode="pickup"]').isDisabled(),true); // no configured scan location
   if(theme==='white'&&width===390){
    await page.evaluate(()=>{localStorage.setItem('schoolsafe-background','night');window.dispatchEvent(new StorageEvent('storage',{key:'schoolsafe-background'}));});
    assert.equal(await page.evaluate(()=>getComputedStyle(document.body).backgroundColor),palette.night,'Dashboard preference change must update the scanner');
    await page.evaluate(()=>{localStorage.setItem('schoolsafe-background','white');window.dispatchEvent(new StorageEvent('storage',{key:'schoolsafe-background'}));});
   }
   if(evidence&&['white','mint','night'].includes(theme)){fs.mkdirSync(evidence,{recursive:true});await page.screenshot({path:path.join(evidence,`scanner-${theme}-${width}.png`),fullPage:true});}
   results.push(`${theme}-${width}`);
  }finally{await context.close();}
 }
 }finally{await browser.close();}
 console.log(JSON.stringify({status:'PASS',checks:results.length,scenarios:results,scope:'intercepted Chromium; no physical camera or production user'}));
})().catch(error=>{console.error(error);process.exitCode=1;});
