const assert=require('node:assert/strict');
const fs=require('node:fs'),path=require('node:path');
const {chromium}=require('C:/Users/PC/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright');
const catalog=JSON.parse(fs.readFileSync(path.join(__dirname,'LOT1_BUTTON_SCREEN_MAPPING.json'),'utf8'));
const output=path.join(__dirname,'branch-tests'),checks=[],errors=[],apiRequests=[],contrasts=[];
fs.mkdirSync(output,{recursive:true});
function lum(rgb){const c=rgb.match(/[\d.]+/g).slice(0,3).map(Number).map(v=>v/255).map(v=>v<=.04045?v/12.92:((v+.055)/1.055)**2.4);return c[0]*.2126+c[1]*.7152+c[2]*.0722;}
function contrast(a,b){const x=lum(a),y=lum(b);return (Math.max(x,y)+.05)/(Math.min(x,y)+.05);}
async function check(name,run){await run();checks.push({name,status:'PASS'});console.log('PASS '+name);}
(async()=>{
 const browser=await chromium.launch({headless:true});
 try{
  const page=await browser.newPage({viewport:{width:1440,height:1000}});
  page.on('pageerror',e=>errors.push(e.message));page.on('request',r=>{if(/\/native\/|\/documents\//.test(r.url()))apiRequests.push(r.url());});
  await page.goto(process.env.SCHOOLSAFE_PREVIEW_URL||'http://127.0.0.1:5187/modules/codex-ui-v1/preview.html');await page.evaluate(()=>codexPreview.ready);
  async function open(id){await page.evaluate(id=>codexPreview.open(id),id);}
  async function noOverflow(label){assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false,label);}
  await check('13 branch identities, icons and contrast in both themes',async()=>{
   for(const theme of ['light','dark']){
    await page.locator('[data-theme-choice="'+theme+'"]').click();const colors=[];
    for(const branch of catalog.branches){
     await open(catalog.actions.find(a=>a.branch===branch.id).id);
     assert.equal(await page.locator('.sscx-context').getAttribute('data-ui-branch'),branch.id);
     assert.equal(await page.locator('.sscx-content').getAttribute('data-ui-branch'),branch.id);
     const primary=page.locator('.sscx-primary [data-branch="'+branch.id+'"]');
     assert.equal(await primary.getAttribute('aria-current'),'page');
     assert.equal(await page.locator('.sscx-context [data-action-id] svg').count(),await page.locator('.sscx-context [data-action-id]').count());
     const color=await page.locator('.sscx-breadcrumb').evaluate(e=>({fg:getComputedStyle(e).color,bg:getComputedStyle(e.closest('main')).getPropertyValue('--sscx-bg').trim()}));
     const backgrounds=await page.locator('.sscx-content').evaluate(e=>{const s=getComputedStyle(e),c=document.createElement('canvas').getContext('2d');return ['bg','surface','soft'].map(k=>{c.fillStyle=s.getPropertyValue('--sscx-'+k);return {key:k,hex:c.fillStyle};});});
     for(const bg of backgrounds){const rgb='rgb('+bg.hex.match(/\w\w/g).map(v=>parseInt(v,16)).join(',')+')',ratio=contrast(color.fg,rgb);assert.ok(ratio>=4.5,`${branch.id} ${theme} ${bg.key} ${ratio}`);contrasts.push({branch:branch.id,theme,surface:bg.key,ratio});}
     colors.push(color.fg);
     await primary.hover();const selected=await primary.evaluate(e=>({fg:getComputedStyle(e).color,bg:getComputedStyle(e).backgroundColor,icon:getComputedStyle(e.querySelector('svg')).color}));assert.ok(contrast(selected.fg,selected.bg)>=4.5);assert.ok(contrast(selected.icon,selected.bg)>=3);
    }
    assert.equal(new Set(colors).size,13,'Every branch has a distinct token');
    await page.locator('.sscx-primary-list [data-command="dashboard"]').click();await page.waitForFunction(()=>document.querySelectorAll('[data-overview-class]').length===8);
    for(const button of await page.locator('.sscx-primary [data-branch]').all()){
     const colors=await button.evaluate(e=>({fg:getComputedStyle(e.querySelector('svg')).color,bg:getComputedStyle(e.closest('aside')).backgroundColor}));assert.ok(contrast(colors.fg,colors.bg)>=3,'Inactive menu icon');
    }
   }
  });
  await check('211 destinations retain their branch and exact title',async()=>{
   for(const action of catalog.actions){await open(action.id);assert.equal(await page.locator('.sscx-content').getAttribute('data-ui-branch'),action.branch);assert.equal(await page.locator('.sscx-content h1').textContent(),action.label);assert.equal(await page.locator('.sscx-context button[aria-current="page"]').getAttribute('data-action-id'),action.id);}
   await open('rosario-006');await page.locator('.sscx-primary [data-branch="finance"]').click();assert.equal(await page.locator('.sscx-context').getAttribute('data-ui-branch'),'finance');assert.equal(await page.locator('.sscx-content').getAttribute('data-ui-branch'),'school','Opening another submenu must not recolor the current screen');
  });
  await check('Cards and family operations stay complete within labelled groups',async()=>{
   for(const [id,count,groups]of [['rosario-016',9,['Preparation','Production','Suivi des cartes']],['rosario-201',5,['Personnes','Autorisations','Demandes']]]){
    await open(id);assert.equal(await page.locator('[data-lot2-operation]').count(),count);assert.deepEqual(await page.locator('.sscx-operation-groups h3').allTextContents(),groups);
    assert.equal(await page.locator('.sscx-operation-groups i[data-lucide]').count(),0,'All icons resolved');
    await page.locator('[data-lot2-operation="0"]').click();assert.equal(await page.locator('[data-screen-surface="operation"]').count(),1);await page.locator('[data-lot2="operation-close"]').click();assert.equal(await page.locator('[data-screen-surface="list"]').count(),1);
    await page.locator('[data-lot2-record]').first().click();await page.waitForFunction(()=>document.querySelector('[data-lot2="refresh"]')?.disabled===false);assert.equal(await page.locator('[data-screen-surface="detail"]').count(),1);
   }
   await open('rosario-028');await page.locator('[data-lot2="draft"]').click();assert.equal(await page.locator('[data-screen-surface="draft"]').count(),1);
  });
  await check('13 branches x 5 widths x 2 themes, grouped commands and 200 percent text',async()=>{
   for(const width of [320,390,768,1280,1920]){
    await page.setViewportSize({width,height:1000});
    for(const theme of ['light','dark']){
     await page.locator('[data-theme-choice="'+theme+'"]').click();
     for(const branch of catalog.branches){await open(catalog.actions.find(a=>a.branch===branch.id).id);await noOverflow(branch.id+' '+theme+' '+width);}
     for(const id of ['rosario-016','rosario-201']){
      await open(id);await noOverflow(id+' '+width);
      const clipping=await page.locator('.sscx-operation-groups button,.sscx-context button').evaluateAll(items=>items.filter(e=>e.getClientRects().length&&e.scrollWidth>e.clientWidth+1).map(e=>e.textContent));assert.deepEqual(clipping,[],id+' clipped controls');
      if([390,1280].includes(width))await page.screenshot({path:path.join(output,`${id}-${theme}-${width}.png`),fullPage:true});
     }
    }
   }
   await page.setViewportSize({width:390,height:844});await page.evaluate(()=>document.documentElement.style.fontSize='200%');
   for(const id of ['rosario-016','rosario-201','rosario-028']){await open(id);await noOverflow(id+' enlarged text');}
   await page.evaluate(()=>document.documentElement.style.fontSize='');
  });
  await check('Independent submenu scrolling and destination heading stay visible',async()=>{
   await page.setViewportSize({width:840,height:800});await open('rosario-006');
   const panel=await page.locator('.sscx-context').evaluate(e=>({height:e.getBoundingClientRect().height,scroll:e.scrollHeight,client:e.clientHeight}));assert.ok(panel.scroll>panel.client,'Long submenu scrolls internally');assert.ok(panel.height<800);
   await page.evaluate(()=>window.scrollTo(0,document.documentElement.scrollHeight));await open('rosario-007');
   const title=await page.locator('.sscx-page-title').boundingBox();assert.ok(title.y>=0&&title.y+title.height<800,'Opened title visible, no empty end of page');
   await page.screenshot({path:path.join(output,'classes-navigation-840.png'),fullPage:false});
  });
  await check('Mobile navigation, forced colors and session reset preserve identification',async()=>{
   await page.setViewportSize({width:390,height:844});await page.locator('.sscx-mobile [data-command="menu"]').click();await page.locator('.sscx-primary [data-branch="school"]').click();assert.equal(await page.locator('.sscx-context-title').textContent(),'Ecole');await page.keyboard.press('Escape');
   await page.emulateMedia({forcedColors:'active'});await page.setViewportSize({width:1440,height:1000});await open('rosario-016');assert.equal(await page.locator('.sscx-primary [aria-current="page"]').evaluate(e=>getComputedStyle(e).borderLeftWidth),'3px');await noOverflow('Forced colors');await page.emulateMedia({forcedColors:'none'});
   await page.selectOption('#previewRole','parent');await page.waitForFunction(()=>document.querySelector('[data-dashboard-role="parent"]'));assert.equal(await page.locator('.sscx-content').getAttribute('data-ui-branch'),'');assert.equal(await page.locator('.sscx-context').getAttribute('data-ui-branch'),'');
   await open('rosario-194');assert.equal(await page.locator('[data-presentation="children"]').count(),1);assert.equal(await page.locator('[data-lot2-record]').count(),2);
  });
  assert.deepEqual(errors,[]);assert.deepEqual(apiRequests,[]);
  fs.writeFileSync(path.join(output,'results.json'),JSON.stringify({status:'PASS',date:new Date().toISOString(),scope:'Isolated branch presentation, synthetic data only',branches:13,destinations:211,responsiveVariants:130,checks,contrasts,errors,apiRequests},null,2)+'\n');
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
