'use strict';
// Intercepted Chromium + synthetic MediaStream. NOT physical camera/provider proof.
const fs=require('node:fs'),path=require('node:path'),assert=require('node:assert/strict');
const {chromium}=require('playwright');
const root=path.join(__dirname,'modules/codex-ui-v1/extensions-test-runtime');
const source=fs.readFileSync(path.join(root,'schoolsafe-card-bridge/scanner-native.js'),'utf8');
const html=fs.readFileSync(path.join(root,'scanner-native.php'),'utf8').split('<!doctype html>')[1].replace(/<\?php[\s\S]*?\?>/g,'').replace(/<script[\s\S]*?<\/script>/g,'');
(async()=>{
 const browser=await chromium.launch({headless:true,executablePath:process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE||'C:/Users/PC/AppData/Local/ms-playwright/chromium_headless_shell-1234/chrome-headless-shell-win64/chrome-headless-shell.exe'});
 const results=[];
 try{
 for(const scenario of ['close-during-detect','mode-during-detect','old-error-after-restart','close-during-play']){
  const context=await browser.newContext(),page=await context.newPage(),posts=[];
  try{
   await page.addInitScript(()=>{
    window.synthetic={pending:[],plays:[],constructors:0,stops:0,holdPlay:false};
    Object.defineProperty(navigator,'mediaDevices',{value:{getUserMedia:async()=>{
     const canvas=document.createElement('canvas');canvas.width=20;canvas.height=20;
     const stream=canvas.captureStream();for(const track of stream.getTracks()){const stop=track.stop.bind(track);track.stop=()=>{synthetic.stops++;stop();};}return stream;
    }}});
    HTMLMediaElement.prototype.play=function(){return synthetic.holdPlay?new Promise(resolve=>synthetic.plays.push(resolve)):Promise.resolve();};
    window.BarcodeDetector=class{constructor(){synthetic.constructors++;}detect(){return new Promise((resolve,reject)=>synthetic.pending.push({resolve,reject}));}};
   });
   await page.route('https://scan.invalid/**',async route=>{
    const url=new URL(route.request().url());
    if(url.pathname==='/scanner-native.php')return route.fulfill({contentType:'text/html',body:'<!doctype html>'+html});
    if(url.pathname==='/scanner-relay.php'){
     if(route.request().method()==='POST'){posts.push(route.request().postDataJSON());return route.fulfill({json:{data:{result:'VALID'}}});}
     return route.fulfill({json:{csrfToken:'synthetic-only',data:{capabilities:['SCAN_VERIFY','SCAN_FEES'],fee_consult_available:true,fee_policy_active:false,request_key_modes:['fees']}}});
    }
    return route.fulfill({status:204,body:''});
   });
   await page.goto('https://scan.invalid/scanner-native.php');await page.addScriptTag({content:source});await page.locator('#scan-form').waitFor({state:'visible'});
   if(scenario==='close-during-play')await page.evaluate(()=>{synthetic.holdPlay=true;});
   await page.locator('#camera').click();
   await page.waitForFunction(hold=>hold?synthetic.plays.length===1:synthetic.pending.length===1,scenario==='close-during-play');
   if(scenario==='mode-during-detect')await page.locator('[data-mode="verify"]').click();
   else if(scenario==='old-error-after-restart'){await page.locator('#camera').click();await page.waitForFunction(()=>synthetic.pending.length===2);}
   else await page.locator('#camera-stop').click();
   await page.evaluate(scenario=>{
    if(scenario==='close-during-play')synthetic.plays[0]();
    else if(scenario==='old-error-after-restart')synthetic.pending[0].reject(new Error('synthetic old detector failure'));
    else synthetic.pending[0].resolve([{rawValue:'synthetic-not-a-real-card'}]);
   },scenario);
   // A browser event-loop barrier, not a wall-clock race assumption.
   await page.evaluate(()=>new Promise(resolve=>requestAnimationFrame(()=>requestAnimationFrame(resolve))));
   assert.equal(posts.length,0,'Canceled camera must never submit a business request');
   if(scenario==='old-error-after-restart')assert.equal(await page.locator('#camera-area').isVisible(),true,'Old failure must not stop the replacement camera');
   if(scenario==='close-during-play')assert.equal(await page.evaluate(()=>synthetic.constructors),0,'Closed preview must not initialize a late detector');
   results.push({scenario,status:'PASS'});
  }catch(error){results.push({scenario,status:'FAIL',error:error.message});}
  finally{await context.close();}
 }
 }finally{await browser.close();}
 console.log(JSON.stringify({scope:'intercepted Chromium, synthetic media only; not physical camera/HTTPS/provider',results},null,2));
 if(results.some(row=>row.status!=='PASS'))process.exitCode=1;
})().catch(error=>{console.error(error);process.exitCode=1;});
