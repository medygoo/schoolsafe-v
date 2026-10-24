'use strict';
// Real dashboard CSS, synthetic school DOM; no session, real logo or credentials.
const {chromium}=require('playwright'),assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path');
const runtime=path.join(__dirname,'modules/codex-ui-v1/extensions-test-runtime');
const css=fs.readFileSync(path.join(runtime,'assets/css/schoolsafe-photo-dashboard.css'),'utf8');
const php=fs.readFileSync(path.join(runtime,'ProgramFunctions/SchoolSafePhotoDashboard.fnc.php'),'utf8');
const toolbar=php.slice(php.indexOf('echo \'<div class="ss-photo-toolbar"'),php.indexOf('data-photo-search'));assert(toolbar.includes('ss-photo-school-logo'),'Logo must precede search in toolbar');
const hero=php.slice(php.indexOf('echo \'<section id="ss-photo-home"'),php.indexOf('ss-photo-date'));assert(!hero.includes('ss-photo-school-logo'),'Logo must not sit below search in hero');
(async()=>{const browser=await chromium.launch({headless:true,executablePath:process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE||'C:/Users/PC/AppData/Local/ms-playwright/chromium_headless_shell-1234/chrome-headless-shell-win64/chrome-headless-shell.exe'});const results=[];
try{for(const width of [360,390,768,1280])for(const background of ['white','night']){
 const ctx=await browser.newContext({viewport:{width,height:720},reducedMotion:'reduce'}),p=await ctx.newPage();
 const html=`<!doctype html><html data-ss-background="${background}"><head><meta name="viewport" content="width=device-width,initial-scale=1"><style>${css}</style></head><body class="modules ss-school-shell"><div id="wrap"><div id="body"><main id="ss-photo-dashboard" class="ss-photo-dashboard"><div class="ss-photo-toolbar"><figure class="ss-photo-school-logo" data-school-logo><span class="ss-photo-logo-orbit"></span><img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='120' height='120'%3E%3Crect width='120' height='120' fill='%23146b55'/%3E%3C/svg%3E" alt="Logo école"><figcaption>École synthétique</figcaption></figure><div data-photo-search><form><input aria-label="Rechercher" placeholder="Rechercher"><button type="button">⌕</button></form></div><div data-photo-school><div><span>École</span><select><option>Synthétique</option></select></div></div><div data-photo-year><div><span>Année</span><select><option>2026</option></select></div></div></div><section class="ss-photo-hero"><p class="ss-photo-greeting">Bonjour, administrateur</p><div class="ss-photo-introduction"><p>École synthétique · Vue d'ensemble.</p></div></section></main></div></div></body></html>`;
 await p.setContent(html);const logo=await p.locator('.ss-photo-school-logo').boundingBox(),search=await p.locator('[data-photo-search]').boundingBox(),hero=await p.locator('.ss-photo-hero').boundingBox();
 const school=await p.locator('[data-photo-school]').boundingBox(),year=await p.locator('[data-photo-year]').boundingBox();
 const image=await p.locator('.ss-photo-school-logo img').boundingBox();
 assert(logo&&image&&search&&school&&year&&hero);
 if(width<=390){assert(image.width>=logo.width*.88&&image.height>=logo.height*.88,`School image must nearly fill the red frame ${width}: image ${image.width}x${image.height}, frame ${logo.width}x${logo.height}`);assert(logo.height>=145&&logo.width>=140,`Red reference area must be filled at ${width}: ${logo.width}x${logo.height}`);assert(logo.x>=search.x+search.width+5,`Logo right of shortened search ${width}`);assert(logo.y<=search.y+5&&logo.y+logo.height>=Math.max(school.y+school.height,year.y+year.height)-5,`Logo spans search and selectors ${width}`);assert(search.width<=185,`Search must leave right-hand space ${width}`);}
 else{assert(Math.abs((logo.y+logo.height/2)-(search.y+search.height/2))<9,`Aligned row ${width}`);assert(logo.height<=100);assert(search.width<=285);}
 assert(logo.y+logo.height<=hero.y+1,'Logo must sit above hero');
 assert(await p.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'No horizontal overflow');
 assert.equal(await p.locator('.ss-photo-school-logo img').evaluate(e=>getComputedStyle(e).objectFit),'contain');assert(await p.locator('.ss-photo-school-logo img').isVisible(),'Logo image visible');
 assert.equal(await p.locator('.ss-photo-school-logo img').evaluate(e=>getComputedStyle(e).animationName),'none');
 if(width===390&&background==='white'&&process.env.SCHOOL_LOGO_LAYOUT_SHOT)await p.screenshot({path:process.env.SCHOOL_LOGO_LAYOUT_SHOT});
 results.push({width,background,logoHeight:Math.round(logo.height),searchWidth:Math.round(search.width)});await ctx.close();
}}finally{await browser.close();}console.log(JSON.stringify({status:'PASS',results,scope:'Synthetic DOM, real dashboard CSS and renderer placement'}));})().catch(e=>{console.error(e);process.exitCode=1});
