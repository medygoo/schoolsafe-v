'use strict';
// Browser rendering and fallback with synthetic images; no production account/media.
const fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict');
const {chromium}=require('playwright');
const runtime=path.join(__dirname,'modules/codex-ui-v1/extensions-test-runtime');
const script=fs.readFileSync(path.join(runtime,'assets/js/schoolsafe-school-logo.js'),'utf8');
const css=fs.readFileSync(path.join(runtime,'assets/css/schoolsafe-photo-dashboard.css'),'utf8');
const out=process.env.SCHOOL_LOGO_EVIDENCE;
(async()=>{const browser=await chromium.launch({headless:true,executablePath:process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE||'C:/Users/PC/AppData/Local/ms-playwright/chromium_headless_shell-1234/chrome-headless-shell-win64/chrome-headless-shell.exe'});const results=[];
try{for(const initial of ['school','campus'])for(const missing of ['none','primary','all'])for(const width of [390,1280]){
 const ctx=await browser.newContext({viewport:{width,height:800},reducedMotion:missing==='none'?'reduce':'no-preference'}),page=await ctx.newPage();
 try{
  await page.route('https://logo.invalid/**',route=>{
   const pathname=new URL(route.request().url()).pathname;
   if(pathname==='/')return route.fulfill({contentType:'text/html',body:`<!doctype html><html><head><style>${css}</style></head><body><main id="ss-photo-dashboard" class="ss-photo-dashboard"><section class="ss-photo-hero"><p class="ss-photo-greeting">École de synthèse</p><div class="ss-photo-introduction"><p>École de synthèse</p></div><figure class="ss-photo-school-logo" data-school-logo data-logo-stage="${initial}"><span class="ss-photo-logo-orbit"></span><img src="${initial==='school'?'/logo.jpg':'/campus.webp'}" data-logo-campus="/campus.webp" data-logo-brand="/brand.png" alt="École de synthèse"><figcaption>École de synthèse</figcaption></figure></section></main><script>${script}</script></body></html>`});
   if((pathname==='/logo.jpg'&&missing!=='none')||(pathname==='/campus.webp'&&missing==='all'))return route.fulfill({status:404});
   return route.fulfill({contentType:'image/svg+xml',body:'<svg xmlns="http://www.w3.org/2000/svg" width="120" height="90"><rect width="100%" height="100%" fill="#176b52"/></svg>'});
  });
  await page.goto('https://logo.invalid/');await page.waitForFunction(()=>{const i=document.querySelector('[data-school-logo] img');return i.hidden||i.complete&&i.naturalWidth>0;});
  const stage=await page.locator('[data-school-logo]').getAttribute('data-logo-stage');
  assert.equal(stage,missing==='none'?initial:missing==='all'?'brand':'campus',`${initial}-${missing}-${width}`);
  assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),true);
  if(missing==='none')assert.equal(await page.locator('[data-school-logo] img').evaluate(e=>getComputedStyle(e).animationName),'none');
  if(out&&initial==='school'&&missing==='none'){fs.mkdirSync(out,{recursive:true});await page.screenshot({path:path.join(out,`logo-${width}.png`),fullPage:true});}
  results.push(`${initial}-${missing}-${width}`);
 }finally{await ctx.close();}
}}finally{await browser.close();}
console.log(JSON.stringify({status:'PASS',checks:results.length,scenarios:results,scope:'synthetic browser assets; no real school logos'}));
})().catch(e=>{console.error(e);process.exitCode=1});
