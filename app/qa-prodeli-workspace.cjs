'use strict';
// Real native login and navigation, exclusively synthetic PostgreSQL fixtures.
const assert = require('node:assert/strict');
const crypto = require('node:crypto');
const fs = require('node:fs');
const path = require('node:path');
const {execFileSync} = require('node:child_process');
const {chromium} = require('playwright');
const base = process.env.PRODELI_QA_URL || 'http://127.0.0.1:8096';
assert.equal(new URL(base).hostname, '127.0.0.1', 'Only the loopback qualification relay is allowed');
const web = process.env.PRODELI_QA_WEB || 'schoolsafe-prodeli-presentation-qa';
const db = process.env.PRODELI_QA_DB || 'schoolcef-harmonization-pg';
const network = process.env.PRODELI_QA_NETWORK || 'schoolcef-harmonization-local';
const label = process.env.PRODELI_QA_LABEL || 'harmonization-20261021';
const out = process.env.PRODELI_WORKSPACE_EVIDENCE;
assert.ok(out, 'Set PRODELI_WORKSPACE_EVIDENCE to a private directory');
fs.mkdirSync(out, {recursive:true});
const shellQuote = value => "'" + value.replaceAll("'", "'\\''") + "'";
const docker = (args, options={}) => process.env.PRODELI_QA_SSH
  ? execFileSync('ssh', ['-o','BatchMode=yes',process.env.PRODELI_QA_SSH,'docker ' + args.map(shellQuote).join(' ')], {encoding:'utf8',timeout:45000,...options}).trim()
  : execFileSync('docker', args, {encoding:'utf8',timeout:45000,...options}).trim();
for (const name of [web,db]) assert.equal(docker(['inspect','--format','{{index .Config.Labels "schoolcef.qualification"}}',name]), label);
assert.equal(docker(['network','inspect',network,'--format','{{.Internal}}']), 'true');
const sql = query => docker(['exec','-i',db,'psql','-X','-v','ON_ERROR_STOP=1','-U','schoolcef_fixture','-d','schoolcef_fixture','-qAt'], {input:query});
const quote = value => "'" + String(value).replaceAll("'", "''") + "'";
const prefix = 'qa.workspace.' + crypto.randomBytes(6).toString('hex');
const password = 'Fixture only ' + crypto.randomBytes(14).toString('hex');
const longPassword = 'Long fixture ' + crypto.randomBytes(48).toString('hex');
const results = [], inventory = [];
const ids = [];
let browser, page, failed = false;
async function group(name, action) {await action();results.push(name);console.log('PASS ' + name);}
async function captureInventory(page, key) {
  const controls = await page.evaluate(() => [...document.querySelectorAll('a[href],button,input[type=submit],summary')]
    .filter(e => e.checkVisibility ? e.checkVisibility({checkVisibilityCSS:true}) : e.getClientRects().length && getComputedStyle(e).visibility !== 'hidden')
    .map(e => ({type:e.tagName.toLowerCase(),label:(e.innerText || e.getAttribute('aria-label') || (e.type === 'submit' ? e.value : '') || '').trim().replace(/\s+/g,' ').slice(0,160),
      href:e.tagName === 'A' ? e.getAttribute('href') : null}))
    .filter(e => !e.href || (!e.href.startsWith('data:') && !/credential=/.test(e.href))));
  for (const control of controls) if (control.href && !control.href.startsWith('#')) {
    const url = new URL(control.href,base+'/');
    if (url.origin !== new URL(base).origin) {control.href='[external link]';continue;}
    const allowed = new Set(['home','opening','accounts','wizard','step','modname','tab','locale','school','staff_id','line','session','page']);
    for (const key of [...url.searchParams.keys()]) if (!allowed.has(key)) url.searchParams.delete(key);
    control.href=url.pathname+url.search+url.hash;
  }
  inventory.push({page:key,controls});
}
async function assertHealthyPage(page, key) {
  assert.ok(!/DB Execute Failed|WE HAVE A PROBLEM, PLEASE CONTACT|Fatal error:|Uncaught (?:Error|Exception)/.test(await page.locator('body').innerText()),key+' must render usable application content, not an HTTP-200 server error');
}
async function responsive(page, key) {
  await assertHealthyPage(page,key);
  for (const width of [1440,390]) {
    await page.setViewportSize({width,height:900});
    assert.ok(await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth) <= 1, key + ' horizontal overflow at ' + width);
    await page.screenshot({path:path.join(out,key + '-' + width + '.png'),fullPage:true});
  }
}
(async () => {try {
  assert.equal(sql("SELECT to_regclass('public.schoolsafe_accounting_budgets') IS NOT NULL"),'t','Fixture setup must include existing migration 017; do not disguise an incomplete schema as a product defect');
  const source = Number(sql('SELECT id FROM schools WHERE syear=2026 ORDER BY id LIMIT 1'));
  assert.ok(source > 0);
  const crypt = secret => docker(['exec','-i',web,'php','-r',"require '/var/www/html/functions/Password.php'; echo encrypt_password(stream_get_contents(STDIN));"], {input:secret});
  const hash = crypt(password), longHash = crypt(longPassword);
  for (const [suffix,scope,userHash] of [['global','NULL',hash],['school',quote(','+source+','),hash],['long',quote(','+source+','),longHash]]) {
    ids.push(Number(sql(`INSERT INTO staff(syear,first_name,last_name,username,password,profile,profile_id,schools,current_school_id,last_login)
      VALUES(2026,'Workspace','Fixture',${quote(prefix+'.'+suffix)},${quote(userHash)},'admin',1,${scope},${source},CURRENT_TIMESTAMP) RETURNING staff_id`)));
  }
  browser = await chromium.launch({executablePath:process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE || 'C:/Users/PC/AppData/Local/ms-playwright/chromium_headless_shell-1234/chrome-headless-shell-win64/chrome-headless-shell.exe'});
  const context = async () => {
    const c = await browser.newContext({extraHTTPHeaders:{'User-Agent':prefix,'Connection':'close'}});
    await c.route('**/*', route => route.request().url().startsWith(base+'/') ? route.continue() : route.abort());
    return c;
  };
  const login = async (c, username, secret = password) => {
    const p = await c.newPage();await p.goto(base+'/index.php');
    if (await p.locator('#enterSplash').isVisible()) await p.locator('#enterSplash').click();
    await p.locator('#USERNAME').fill(username);await p.locator('#PASSWORD').fill(secret);
    assert.ok(await p.locator('#USERNAME').inputValue() === username && await p.locator('#PASSWORD').inputValue() === secret, 'Login fields retained synthetic values');
    await Promise.all([p.waitForURL(url => url.pathname !== '/index.php'),p.locator('#loginform button[type=submit]').click()]);
    await assertHealthyPage(p,'native login');
    return p;
  };
  const global = await context();
  await group('native global login lands directly on the distinct ProDéli dashboard', async () => {
    page = await login(global,prefix+'.global');
    assert.equal(new URL(page.url()).pathname,'/schoolsafe-administrators.php','Native global must not land on a school portal');
    assert.equal(new URL(page.url()).searchParams.get('home'),'1');
    await assert.doesNotReject(() => page.getByRole('heading',{name:'Tableau de bord ProDéli',exact:true}).waitFor());
  });
  await group('native login accepts the full password length supported by first access', async () => {
    const anonymous = await context();const p = await anonymous.newPage();await p.goto(base+'/index.php');
    assert.ok(Number(await p.locator('#PASSWORD').getAttribute('maxlength')) >= 128,'Login must not truncate the 12-128 character passwords accepted at first access');
    await anonymous.close();
  });
  await group('long native passphrase can actually log in through the browser', async () => {
    const c = await context();const p = await login(c,prefix+'.long',longPassword);
    assert.equal(new URL(p.url()).pathname,'/Modules.php');await p.locator('#ss-photo-dashboard').waitFor();await c.close();
  });
  await group('global dashboard is usable desktop/mobile and has real scoped summaries', async () => {
    assert.equal(await page.locator('#ss-photo-dashboard').count(),0);
    assert.equal(await page.locator('[data-summary=schools]').innerText(),sql('SELECT count(*) FROM schools WHERE syear=2026'));
    await captureInventory(page,'central-home');await responsive(page,'central-home');
  });
  await group('school search filters and restores the complete authorized directory', async () => {
    const search = page.getByRole('searchbox',{name:'Rechercher un établissement'});
    const total = await page.locator('[data-school-entry]').count();assert.ok(total > 0);
    await search.fill(prefix+'.not-a-school');await page.locator('#sskc-school-search-empty').waitFor({state:'visible'});
    assert.equal(await page.locator('[data-school-entry]:visible').count(),0);
    await search.fill('');assert.equal(await page.locator('[data-school-entry]:visible').count(),total);
  });
  await group('dashboard shortcuts survive reload and expandable actions really open', async () => {
    for (const id of ['sskc-schools-title','sskc-access-title','sskc-communications-title']) {
      await page.locator('.sskc-dashboard-shortcuts a[href="#'+id+'"]').click();assert.equal(new URL(page.url()).hash,'#'+id);
      await page.reload();assert.equal(new URL(page.url()).hash,'#'+id,'Reload must retain the chosen central section');
    }
    for (const label of ['Informations à réunir','Opérations administratives existantes']) {
      const summary=page.locator('.sskc-home summary').filter({hasText:label});
      await summary.click();assert.ok(await summary.evaluate(e=>e.parentElement.open));await captureInventory(page,'central-expanded-'+label);
      await summary.click();assert.equal(await summary.evaluate(e=>e.parentElement.open),false);
    }
  });
  await group('all central read destinations open without silently replacing the workspace', async () => {
    const routes = [
      ['requests','/schoolsafe-administrators.php?opening=1'],['school-accounts','/schoolsafe-administrators.php'],
      ['central-accounts','/schoolsafe-administrators.php?accounts=central'],['sms','/plateforme/prodeli/sms'],
      ['whatsapp','/plateforme/prodeli/whatsapp']
    ];
    for (const [key,route] of routes) {
      const response = await page.goto(base+route);assert.equal(response.status(),200,key);
      assert.equal(new URL(page.url()).pathname.startsWith('/index.php'),false,key+' login wall');
      await captureInventory(page,key);
      assert.ok(await page.locator('a[href="/schoolsafe-administrators.php?home=1"]').count() > 0,key+' central return');
      await responsive(page,key);
    }
  });
  await group('native configuration has SchoolSafe shell, school context and explicit global return', async () => {
    const response = await page.goto(base+'/Modules.php?modname=School_Setup/Configuration.php');assert.equal(response.status(),200);
    await page.locator('.ss-native-workspace-header').waitFor({state:'visible'});
    assert.ok(await page.locator('.ss-native-workspace-header img[src="/schoolsafe-logo.png"]').isVisible());
    assert.ok(await page.locator('.ss-native-workspace-header .ss-native-workspace-return[href="/schoolsafe-administrators.php?home=1"]').isVisible());
    assert.equal(await page.locator('#footerwrap').isVisible(),false,'Legacy black header is not a second app shell');
    await page.locator('.ss-native-workspace-languages').getByRole('link',{name:'Français',exact:true}).click();
    await page.getByRole('button',{name:'Enregistrer',exact:true}).first().waitFor();
    assert.ok(await page.locator('.ss-native-workspace-languages a[hreflang="fr"][aria-current="true"]').isVisible(),'Native French preference is honored, not a forced account conversion');
    await captureInventory(page,'settings');await responsive(page,'settings');
  });
  await group('native operations share the brand and clear central return without changing their handlers', async () => {
    assert.equal((await page.goto(base+'/admin-console.php')).status(),200);
    await page.locator('.ss-native-workspace-header').waitFor({state:'visible'});
    assert.ok(await page.locator('.ss-native-workspace-header .ss-native-workspace-return[href="/schoolsafe-administrators.php?home=1"]').isVisible());
    await captureInventory(page,'operations');await responsive(page,'operations');
  });
  await group('forged school opening is refused before context can change', async () => {
    const schoolBefore = sql(`SELECT current_school_id FROM staff WHERE staff_id=${ids[0]}`);
    const r = await global.request.post(base+'/schoolsafe-administrators.php?home=1',{form:{action:'dashboard_school',token:'invalid',school:String(source)},maxRedirects:0});
    assert.equal(r.status(),403);assert.equal(sql(`SELECT current_school_id FROM staff WHERE staff_id=${ids[0]}`),schoolBefore);
  });
  await group('explicit school opening preserves a visible return to ProDéli at desktop/mobile', async () => {
    await page.goto(base+'/schoolsafe-administrators.php?home=1');
    await page.locator('[data-school-entry] form').first().getByRole('button').click();
    await page.locator('#ss-photo-dashboard').waitFor();
    for (const width of [1440,390]) {
      await page.setViewportSize({width,height:900});
      await page.locator('.ss-fixed-header-brand .ss-central-dashboard-return a').waitFor({state:'visible'});
      assert.ok(await page.evaluate(() => document.documentElement.scrollWidth-document.documentElement.clientWidth)<=1);
      await page.screenshot({path:path.join(out,'global-in-school-'+width+'.png'),fullPage:true});
    }
  });
  await group('school modules action is honestly labeled and does not impersonate a central dashboard', async () => {
    await page.getByRole('link',{name:'Modules de l’école',exact:true}).first().click();
    assert.equal(await page.locator('#ss-photo-dashboard').count(),1);
    assert.match(await page.locator('.ss-fixed-pager select').locator('option:checked').innerText(),/^Modules/);
    await page.locator('a[href="#ss-photo-home"]').click();
    assert.equal(await page.locator('.ss-fixed-pager select').locator('option:checked').innerText(),'Accueil','School dashboard tile must return to its actual home');
    await page.locator('.ss-central-dashboard-return a').click();
    await page.getByRole('heading',{name:'Tableau de bord ProDéli',exact:true}).waitFor();
  });
  const school = await context();let schoolPage;
  await group('school native administrator lands on their own dashboard without central controls', async () => {
    schoolPage = await login(school,prefix+'.school');
    assert.equal(new URL(schoolPage.url()).pathname,'/Modules.php');await schoolPage.locator('#ss-photo-dashboard').waitFor();
    assert.equal(await schoolPage.locator('.ss-central-dashboard-return').count(),0);
    await responsive(schoolPage,'school-home');
  });
  await group('school settings do not promote the school account to a platform operator', async () => {
    await schoolPage.goto(base+'/Modules.php?modname=School_Setup/Configuration.php');
    await schoolPage.locator('.ss-native-workspace-header').waitFor({state:'visible'});
    assert.equal(await schoolPage.locator('.ss-native-workspace-header a[href*="schoolsafe-administrators.php"]').count(),0);
    assert.equal((await school.request.get(base+'/plateforme/prodeli/sms',{maxRedirects:0})).status(),403);
    assert.equal(sql(`SELECT schools FROM staff WHERE staff_id=${ids[1]}`),','+source+',');
    await responsive(schoolPage,'school-settings');
  });
} catch (error) {
  failed = true;
  console.error(String(error.stack || error).replaceAll(password,'[synthetic-secret]').replaceAll(longPassword,'[synthetic-secret]'));
  const lastPage = browser?.contexts().flatMap(c => c.pages()).filter(p => !p.isClosed()).at(-1) || page;
  if (lastPage && !lastPage.isClosed() && !await lastPage.locator('dd code').count()) {
    await lastPage.screenshot({path:path.join(out,'failure.png'),fullPage:true}).catch(()=>{});
    const text = (await lastPage.locator('body').innerText({timeout:5000})).replaceAll(password,'[synthetic-secret]').replaceAll(longPassword,'[synthetic-secret]');
    fs.writeFileSync(path.join(out,'failure-details.json'),JSON.stringify({path:new URL(lastPage.url()).pathname,title:await lastPage.title(),text:text.slice(0,5000)},null,2));
  }
} finally {
  if (browser) await browser.close();
  if (ids.length) {
    sql(`BEGIN;DELETE FROM schoolsafe_account_state WHERE person_key LIKE ${quote(prefix+'%')};DELETE FROM schoolsafe_account_audit WHERE person_key LIKE ${quote(prefix+'%')};DELETE FROM access_log WHERE user_agent=${quote(prefix)} OR username LIKE ${quote(prefix+'%')};DELETE FROM staff WHERE staff_id IN (${ids.join(',')}) AND username LIKE ${quote(prefix+'%')};COMMIT;`);
    assert.equal(sql(`SELECT count(*) FROM staff WHERE username LIKE ${quote(prefix+'%')}`),'0');
  }
  fs.writeFileSync(path.join(out,'workspace-result.json'),JSON.stringify({status:failed?'FAIL':'PASS',results,inventory,cleanup:true,scope:'Synthetic PostgreSQL; real browser login and UI, no production writes or external delivery.'},null,2));
  console.log(JSON.stringify({status:failed?'FAIL':'PASS',groups:results.length,cleanup:true}));
  if (failed) process.exitCode=1;
}})().catch(error => {console.error(String(error.stack||error).replaceAll(password,'[synthetic-secret]').replaceAll(longPassword,'[synthetic-secret]'));process.exitCode=1;});
