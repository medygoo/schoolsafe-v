'use strict';
const fs=require('fs'),path=require('path'),assert=require('assert/strict'),crypto=require('crypto'),{execFileSync}=require('child_process'),{chromium}=require('playwright');
// Synthetic image QA only: never point this harness at production.
const out=process.env.AUDIT_OUT,state=JSON.parse(fs.readFileSync(process.env.AUDIT_STATE,'utf8')),base='http://127.0.0.1:18098';
assert.match(state.web,/^schoolsafe-recovery-qa-web-[a-f0-9]{12}$/);assert.match(state.db,/^schoolsafe-recovery-qa-db-[a-f0-9]{12}$/);assert.equal(state.label,'prodeli-school-boundary-20261022');
fs.mkdirSync(out,{recursive:true});assert.ok(!fs.existsSync(path.join(out,'mobile-audit.json')),'Use a fresh evidence directory');
const sq=x=>"'"+x.replaceAll("'","'\\''")+"'",run=(a,o={})=>execFileSync('ssh',['-o','BatchMode=yes','prodeli-hub-01','docker '+a.map(sq).join(' ')],{encoding:'utf8',timeout:45000,...o});
for(const n of [state.web,state.db])assert.equal(run(['inspect','--format','{{index .Config.Labels "schoolcef.qualification"}}',n]).trim(),state.label);
assert.equal(run(['inspect','--format','{{.Image}}',state.web]).trim(),state.image);
assert.match(state.network,/^schoolsafe-recovery-qa-[a-f0-9]{12}$/);assert.equal(run(['network','inspect',state.network,'--format','{{.Internal}}']).trim(),'true');
const sql=q=>run(['exec','-i',state.db,'psql','-X','-U','schoolcef_fixture','-d','schoolcef_fixture','-qAt','-v','ON_ERROR_STOP=1'],{input:q}).trim(),quote=s=>"'"+s.replaceAll("'","''")+"'";
const prefix='qa.mobile.'+Date.now(),pw=crypto.randomBytes(24).toString('hex'),ids=[];let browser;const results=[];
(async()=>{try{
 const hash=run(['exec','-i',state.web,'php','-r',"require '/var/www/html/functions/Password.php';echo encrypt_password(stream_get_contents(STDIN));"],{input:pw});
 const school=Number(sql('SELECT id FROM schools WHERE syear=2026 ORDER BY id LIMIT 1'));
 const manager=Number(sql(`INSERT INTO staff(syear,username,first_name,last_name,password,profile,profile_id,schools,current_school_id,last_login) VALUES(2026,${quote(prefix+'.manager')},'QA','Mobile',${quote(hash)},'admin',1,NULL,${school},CURRENT_TIMESTAMP) RETURNING staff_id`));ids.push(manager);
 for(const suffix of ['complete1','complete2']){let id=Number(sql(`INSERT INTO staff(syear,username,first_name,last_name,password,profile,profile_id,schools,current_school_id,last_login) VALUES(2026,${quote(prefix+'.'+suffix)},'QA','Completed',${quote(hash)},'admin',1,${quote(','+school+',')},${school},CURRENT_TIMESTAMP) RETURNING staff_id`));ids.push(id);sql(`INSERT INTO schoolsafe_native_onboarding(staff_id,syear,created_by,completed_at) VALUES(${id},2026,${manager},CURRENT_TIMESTAMP)`);}
 browser=await chromium.launch({executablePath:'C:/Users/PC/AppData/Local/ms-playwright/chromium_headless_shell-1234/chrome-headless-shell-win64/chrome-headless-shell.exe'});
 const c=await browser.newContext({extraHTTPHeaders:{'User-Agent':prefix,'Connection':'close'}});await c.route('**/*',r=>r.request().url().startsWith(base+'/')?r.continue():r.abort());const p=await c.newPage();p.setDefaultNavigationTimeout(90000);
 await p.goto(base+'/index.php');if(await p.locator('#enterSplash').isVisible())await p.locator('#enterSplash').click();await p.locator('#USERNAME').fill(prefix+'.manager');await p.locator('#PASSWORD').fill(pw);await Promise.all([p.waitForURL(u=>u.pathname.includes('schoolsafe-administrators')),p.locator('#loginform button[type=submit]').click()]);
 await p.goto(base+'/schoolsafe-administrators.php');await p.setViewportSize({width:390,height:844});
 const rows=[];for(const id of ids.slice(1)){const row=p.locator('#sskc-account-'+id);rows.push({id,innerText:await row.innerText(),textContent:await row.textContent(),help:await row.locator('.sskc-action-help').textContent(),visible:await row.locator('.sskc-action-help').isVisible(),renewButtons:await row.locator('input[value="native_temporary"]').count()});}
 fs.writeFileSync(path.join(out,'completed-account-diagnostic.json'),JSON.stringify({rows,locale:await p.locator('html').getAttribute('lang')},null,2));
 for(const width of [1280,390,360])for(const [key,route] of [['home','/schoolsafe-administrators.php?home=1'],['accounts','/schoolsafe-administrators.php'],['wizard','/schoolsafe-administrators.php?wizard=1'],['opening','/schoolsafe-administrators.php?opening=1'],['console','/admin-console.php'],['sms','/plateforme/prodeli/sms'],['whatsapp','/plateforme/prodeli/whatsapp'],['preferences','/Modules.php?modname=Users/Preferences.php']]){
  await p.setViewportSize({width,height:844});const response=await p.goto(base+route);assert.equal(response.status(),200,key+' status');const heading=p.locator(key==='preferences'?'.ss-page-heading h2':'h1').first();await heading.waitFor({state:'visible'});const body=await p.locator('body').innerText();assert.ok(body.length>150&& !/DB Execute Failed|Fatal error:|Uncaught (?:Error|Exception)/.test(body),key+' rendering');
  assert.ok(!(await p.locator('script[src*="assets/themes/SchoolSafeCodex/scripts.js"]').count())||(await p.locator('#ss-central-chrome').count())===1,key+' native theme requires global-only chrome boundary');
  const nativeChromeNonObstructing=await p.evaluate(()=>[...document.querySelectorAll('#ss-mobile-navigation,#ss-mobile-backdrop,.ss-page-back')].every(e=>getComputedStyle(e).display==='none')&&[...document.querySelectorAll('#footer,#footerwrap')].every(e=>!['fixed','sticky','absolute'].includes(getComputedStyle(e).position)));
  assert.ok(nativeChromeNonObstructing,key+' native school chrome must not overlay ProDeli');
  if(key==='preferences'){assert.ok(await p.locator('#footerwrap a[href*="modfunc=logout"]').isVisible(),'Native preferences keep a visible logout');assert.ok((await p.locator('#footerwrap a[href*="modfunc=logout"]').boundingBox()).height>=44,'Logout touch target');}
  const overflow=await p.evaluate(()=>document.documentElement.scrollWidth-document.documentElement.clientWidth);
  await p.screenshot({path:path.join(out,key+'-'+width+'.png'),fullPage:true});
  assert.ok(overflow<=1,key+' viewport overflow '+width);
  results.push({key,width,overflow,headingVisible:await heading.isVisible(),nativeChromeNonObstructing});
 }
 console.log(JSON.stringify({results}));
}finally{if(browser)await browser.close();if(ids.length)sql(`BEGIN;DELETE FROM access_log WHERE user_agent=${quote(prefix)} OR username LIKE ${quote(prefix+'%')};DELETE FROM schoolsafe_native_onboarding WHERE staff_id IN (${ids});DELETE FROM staff WHERE staff_id IN (${ids}) AND username LIKE ${quote(prefix+'%')};COMMIT;`);assert.equal(sql(`SELECT count(*) FROM staff WHERE username LIKE ${quote(prefix+'%')}`),'0');fs.writeFileSync(path.join(out,'mobile-audit.json'),JSON.stringify({status:results.length===24?'PASS':'FAIL',results,cleanup:true,image:state.image},null,2));console.log('Synthetic mobile fixture cleanup complete');}})().catch(e=>{console.error(String(e.stack).replaceAll(pw,'[synthetic-secret]'));process.exitCode=1;});
