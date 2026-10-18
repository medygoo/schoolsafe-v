const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const {chromium}=require('C:/Users/PC/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const root=path.resolve(__dirname,'../..'),output=path.join(__dirname,'pro-max-tests');
fs.mkdirSync(output,{recursive:true});
const checks=[],errors=[],apiRequests=[];
async function check(name,run){await run();checks.push({name,status:'PASS'});console.log('PASS '+name);}
function luminance(hex){const c=hex.match(/\w\w/g).map(v=>parseInt(v,16)/255).map(v=>v<=.04045?v/12.92:((v+.055)/1.055)**2.4);return c[0]*.2126+c[1]*.7152+c[2]*.0722;}
function contrast(a,b){const x=luminance(a.replace('#','')),y=luminance(b.replace('#',''));return (Math.max(x,y)+.05)/(Math.min(x,y)+.05);}
function hex(rgb){return '#'+rgb.match(/[\d.]+/g).slice(0,3).map(v=>Number(v).toString(16).padStart(2,'0')).join('');}
(async()=>{
 const browser=await chromium.launch({headless:true});
 try{
  const page=await browser.newPage({viewport:{width:1440,height:1000}});
  page.on('pageerror',e=>errors.push(e.message));page.on('request',r=>{if(r.url().includes('/native/'))apiRequests.push(r.url());});
  await page.goto('http://127.0.0.1:5187/modules/codex-ui-v1/preview.html');await page.evaluate(()=>codexPreview.ready);
  await check('Shared stylesheet and semantic contrast in both themes',async()=>{
   assert.equal(await page.locator('link[href*="ui-ux-pro-max.css"]').count(),1);
   const pairs=[];
   for(const theme of ['light','dark']){
    await page.locator('[data-theme-choice="'+theme+'"]').click();
    const tokens=await page.locator('.sscx-app').evaluate(e=>Object.fromEntries(['text','muted','accent','bg','surface','soft','control-border','focus','negative','positive','error-bg','success-bg'].map(k=>[k,getComputedStyle(e).getPropertyValue('--sscx-'+k).trim()])));
    for(const foreground of ['text','muted','accent'])for(const background of ['bg','surface','soft']){const ratio=contrast(tokens[foreground],tokens[background]);assert.ok(ratio>=4.5,`${theme} ${foreground}/${background}: ${ratio}`);pairs.push({theme,foreground,background,ratio});}
    for(const [foreground,background,min]of [['control-border','surface',3],['focus','surface',3],['negative','error-bg',4.5],['positive','success-bg',4.5]]){const ratio=contrast(tokens[foreground],tokens[background]);assert.ok(ratio>=min,`${theme} ${foreground}: ${ratio}`);pairs.push({theme,foreground,background,ratio});}
    const selected=page.locator('.sscx-primary [aria-current="page"]');await selected.hover();const colors=await selected.evaluate(e=>({text:getComputedStyle(e).color,bg:getComputedStyle(e).backgroundColor}));assert.ok(contrast(hex(colors.text),hex(colors.bg))>=4.5,'Selected navigation hover contrast');
   }
   fs.writeFileSync(path.join(output,'contrast.json'),JSON.stringify(pairs,null,2)+'\n');
  });
  await check('Readable text, labels and target sizes across consultation families',async()=>{
   for(const id of ['rosario-006','rosario-007','rosario-016','rosario-201','rosario-037','rosario-028','rosario-029','rosario-033','rosario-049','rosario-048','rosario-086','rosario-018','presence-school','scan-history']){
    await page.evaluate(id=>codexPreview.open(id),id);
    const issues=await page.locator('.sscx-app').evaluate(host=>{
     const visible=e=>e.getClientRects().length&&getComputedStyle(e).visibility!=='hidden';const problems=[];
     for(const e of host.querySelectorAll('button,input,select'))if(visible(e)&&!e.disabled){const r=e.getBoundingClientRect();if(r.height<43.5&&e.type!=='checkbox')problems.push('Small target '+(e.textContent||e.type));if(e.tagName==='BUTTON'&&!e.textContent.trim()&&!e.getAttribute('aria-label')&&!e.getAttribute('title'))problems.push('Unnamed button');if(['INPUT','SELECT'].includes(e.tagName)&&!e.labels.length&&!e.getAttribute('aria-label'))problems.push('Unlabelled field');}
     for(const e of host.querySelectorAll('p,small,span,td,th,label'))if(visible(e)&&e.textContent.trim()&&parseFloat(getComputedStyle(e).fontSize)<12)problems.push('Small text '+e.textContent.slice(0,35));
     return problems;
    });assert.deepEqual(issues,[],id);
   }
  });
  await check('Mobile menu and submenu keep keyboard focus on visible controls',async()=>{
   await page.setViewportSize({width:375,height:812});await page.locator('.sscx-mobile [data-command="menu"]').click();
   assert.equal(await page.locator('.sscx-content').evaluate(e=>e.inert),true);
   await page.keyboard.press('Shift+Tab');assert.equal(await page.locator('.sscx-mobile [data-command="menu"]').evaluate(e=>e===document.activeElement),true);
   await page.keyboard.press('Tab');assert.equal(await page.locator('.sscx-primary-list button').first().evaluate(e=>e===document.activeElement),true);
   await page.locator('.sscx-primary [data-branch="school"]').click();assert.equal(await page.locator('.sscx-primary').evaluate(e=>e.inert),true);
   await page.keyboard.press('Shift+Tab');assert.equal(await page.locator('.sscx-mobile [data-command="menu"]').evaluate(e=>e===document.activeElement),true);
   await page.keyboard.press('Escape');assert.equal(await page.locator('.sscx-content').evaluate(e=>e.inert),false);
   await page.locator('.sscx-mobile [data-command="menu"]').click();await page.selectOption('#previewRole','teacher');await page.evaluate(()=>codexPreview.ready);await page.waitForFunction(()=>!document.querySelector('.sscx-content').inert);
   assert.equal(await page.locator('.sscx-menu-open').count(),0);
  });
  await check('Small phone, landscape, tablet, desktop and 200 percent text',async()=>{
   await page.selectOption('#previewRole','admin');await page.waitForFunction(()=>document.querySelectorAll('[data-overview-class]').length===8);
   for(const theme of ['light','dark']){
    await page.locator('[data-theme-choice="'+theme+'"]').click();
    for(const [width,height]of [[375,812],[844,390],[768,1024],[1440,1000]]){
     await page.setViewportSize({width,height});await page.evaluate(()=>new Promise(requestAnimationFrame));
     for(const view of ['dashboard','rosario-006','rosario-201','rosario-028','presence-school']){
      if(view==='dashboard'){await page.locator((width<=700?'.sscx-mobile':'.sscx-primary-list')+' [data-command="dashboard"]').click();await page.waitForFunction(()=>document.querySelectorAll('[data-overview-class]').length===8);}else await page.evaluate(id=>codexPreview.open(id),view);
      assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false,`${theme} ${width} ${view}`);
      if((width===375||width===1440)&&['dashboard','rosario-006'].includes(view))await page.screenshot({path:path.join(output,`${view}-${theme}-${width}.png`),fullPage:false});
     }
    }
   }
   await page.setViewportSize({width:375,height:812});await page.evaluate(()=>document.documentElement.style.fontSize='200%');
   for(const view of ['rosario-006','rosario-201','rosario-028','presence-school']){await page.evaluate(id=>codexPreview.open(id),view);assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false,view+' enlarged text');}
   await page.screenshot({path:path.join(output,'text-200-percent.png')});await page.evaluate(()=>document.documentElement.style.fontSize='');
  });
  await check('JASPE preview image, modal focus, landscape and reduced motion',async()=>{
   await page.emulateMedia({reducedMotion:'reduce'});
   await page.locator('.sscx-mobile [data-command="jaspe"]').click();assert.equal(await page.locator('dialog[open] img').evaluate(e=>e.naturalWidth>0),true);
   await page.locator('dialog[open] input').fill('Essai local');await page.locator('dialog[open] button[type="submit"]').click();assert.match(await page.locator('.sscx-jaspe-status').textContent(),/Aucun message transmis/);
   await page.setViewportSize({width:844,height:390});assert.ok(await page.locator('dialog[open]').evaluate(e=>e.getBoundingClientRect().height<=innerHeight));
   await page.screenshot({path:path.join(output,'jaspe-landscape.png')});await page.keyboard.press('Escape');assert.equal(await page.locator('dialog[open]').count(),0);
   await page.setViewportSize({width:375,height:812});await page.locator('.sscx-mobile [data-command="menu"]').click();assert.equal(await page.locator('.sscx-primary-list button').first().evaluate(e=>getComputedStyle(e).transitionDuration),'0s');await page.keyboard.press('Escape');
  });
  await check('Original JASPE markup with opt-in styling, engine not executed',async()=>{
   const html=fs.readFileSync(path.join(root,'app/index.html'),'utf8');
   await page.evaluate(html=>{codexPreview.destroy();const source=new DOMParser().parseFromString(html,'text/html'),dialog=source.getElementById('jaspePanelOverlay');dialog.dataset.codexUiV1='';document.body.append(document.importNode(dialog,true));lucide.createIcons();},html);
   for(const name of ['modules/jaspe-dashboard.css','codex-ui-v1/jaspe-overlay.css'])await page.addStyleTag({url:'http://127.0.0.1:5187/styles/'+name});
   for(const theme of ['light','dark'])for(const width of [375,768,1440]){
    await page.setViewportSize({width,height:900});await page.evaluate(theme=>{document.body.dataset.theme=theme;document.getElementById('jaspePanelOverlay').showModal();},theme);
    assert.ok(await page.locator('#jaspePanelOverlay').evaluate(e=>e.getBoundingClientRect().width<=innerWidth));
    assert.ok(await page.locator('.jaspe-panel__eyebrow').evaluate(e=>parseFloat(getComputedStyle(e).fontSize)>=12));
    const inputColors=await page.locator('#jaspePanelInput').evaluate(e=>({text:getComputedStyle(e,'::placeholder').color,bg:getComputedStyle(e).backgroundColor}));assert.ok(contrast(hex(inputColors.text),hex(inputColors.bg))>=4.5,'Native JASPE placeholder contrast');
    await page.screenshot({path:path.join(output,`jaspe-original-${theme}-${width}.png`)});await page.evaluate(()=>document.getElementById('jaspePanelOverlay').close());
   }
  });
  assert.deepEqual(errors,[]);assert.deepEqual(apiRequests,[]);
  fs.writeFileSync(path.join(output,'results.json'),JSON.stringify({status:'PASS',date:new Date().toISOString(),scope:'Isolated UI with synthetic data only',checks,errors,apiRequests},null,2)+'\n');
 }finally{await browser.close();}
})().catch(e=>{fs.writeFileSync(path.join(output,'failure.txt'),e.stack);console.error(e);process.exitCode=1;});
