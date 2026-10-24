/* Synthetic PHP fragments + real Chromium. No real session or HTTP backend. */
'use strict';
const fs = require('node:fs');
const path = require('node:path');
const assert = require('node:assert/strict');
const {chromium} = require('playwright');
const out = path.resolve(process.argv[2]);
const fixture = JSON.parse(fs.readFileSync(path.join(out, 'discovery-render.json'), 'utf8'));
const css = fs.readFileSync(path.join(__dirname, 'modules/codex-ui-v1/extensions-test-runtime/plugins/SchoolSafe_Keycloak/central-ui.css'), 'utf8');
(async () => {
  const browser = await chromium.launch({headless:true, executablePath:process.env.SCHOOLSAFE_CHROMIUM || 'C:/Users/PC/AppData/Local/ms-playwright/chromium_headless_shell-1234/chrome-headless-shell-win64/chrome-headless-shell.exe'});
  let checks=0; const results=[];
  try {
    for (const width of [320,390,1280]) for (const section of ['home','account']) {
      const context=await browser.newContext({viewport:{width,height:900}});
      const page=await context.newPage(); const errors=[];
      page.on('pageerror', e=>errors.push(e.message));
      const fragment=section==='account'?'<div class="sskc-table">'+fixture[section]+'</div>':fixture[section];
      const html='<!doctype html><html lang="fr"><head><meta name="viewport" content="width=device-width, initial-scale=1"><style>body{margin:0}'+css+'</style></head><body><main class="sskc-shell"><h1>ProDéli — navigation synthétique</h1>'+fragment+'</main></body></html>';
      await page.route('**/*', async route=>{
        const url=new URL(route.request().url());
        if(url.origin!=='https://schoolsafe.test') return route.abort();
        await route.fulfill({status:200,contentType:'text/html; charset=utf-8',body:url.pathname==='/fixture'?html:'<!doctype html><h1>Destination native simulée</h1>'});
      });
      await page.goto('https://schoolsafe.test/fixture');
      const link=page.getByRole('link',{name:/Gérer les droits — Scan et accès/});
      const check=(name,ok)=>{assert.ok(ok,section+' '+width+': '+name);checks++;results.push({section,width,name});};
      check('one visible action',await link.count()===1 && await link.isVisible());
      const box=await link.boundingBox();
      check('touch target at least 44px',box && box.height>=44 && box.width>=44);
      check('button inside viewport',box.x>=0 && box.x+box.width<=width+1);
      check('no document horizontal overflow',await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));
      await page.keyboard.press('Tab');
      check('keyboard reachable with visible focus',await link.evaluate(el=>document.activeElement===el && getComputedStyle(el).outlineStyle!=='none' && parseFloat(getComputedStyle(el).outlineWidth)>=2));
      await page.screenshot({path:path.join(out,'discovery-'+section+'-'+width+'.png'),fullPage:true});
      await link.click();
      const target=new URL(page.url());
      check('same native school access destination',target.pathname==='/admin-console.php' && target.searchParams.get('access_school')==='3' && target.hash==='#school-native-access');
      check('only account row preselects the account',section==='account'?target.searchParams.get('access_staff')==='17':!target.searchParams.has('access_staff'));
      check('no browser script errors',errors.length===0);
      await context.close();
    }
    const result={status:'PASS',checks,results,evidence:'synthetic PHP render fragments; native CSS; real Chromium; navigation intercepted; no production grant or authenticated HTTP'};
    fs.writeFileSync(path.join(out,'discovery-browser.json'),JSON.stringify(result,null,2));
    console.log(JSON.stringify({status:result.status,checks,evidence:result.evidence}));
  } finally {await browser.close();}
})().catch(error=>{console.error(error.message);process.exitCode=1;});
