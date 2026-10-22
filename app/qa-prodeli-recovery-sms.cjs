'use strict';
// Local fictitious QA only. Internal Docker network; simulated SMS, no external delivery.
const assert = require('node:assert/strict');
const crypto = require('node:crypto');
const fs = require('node:fs');
const path = require('node:path');
const {execFileSync} = require('node:child_process');
const {chromium} = require('playwright');
const base = process.env.PRODELI_QA_URL || 'http://127.0.0.1:8096';
assert.equal(new URL(base).hostname,'127.0.0.1','QA HTTP transport must stay loopback');
const web = process.env.PRODELI_QA_WEB || 'schoolsafe-prodeli-presentation-qa';
const db = process.env.PRODELI_QA_DB || 'schoolcef-harmonization-pg';
const network = process.env.PRODELI_QA_NETWORK || 'schoolcef-harmonization-local';
const label = process.env.PRODELI_QA_LABEL || 'harmonization-20261021';
const out = process.env.PRODELI_RECOVERY_EVIDENCE;
assert.ok(out, 'Set PRODELI_RECOVERY_EVIDENCE to a private evidence directory');
fs.mkdirSync(out, {recursive:true});
const shellQuote = s => "'"+s.replaceAll("'","'\\''")+"'";
const docker = (args, options={}) => process.env.PRODELI_QA_SSH
 ? execFileSync('ssh',['-o','BatchMode=yes',process.env.PRODELI_QA_SSH,'docker '+args.map(shellQuote).join(' ')],{encoding:'utf8',timeout:45000,...options}).trim()
 : execFileSync('docker', args, {encoding:'utf8',timeout:45000,...options}).trim();
for (const name of [web,db]) assert.equal(docker(['inspect','--format','{{index .Config.Labels "schoolcef.qualification"}}',name]),label);
assert.equal(docker(['network','inspect',network,'--format','{{.Internal}}']), 'true');
const sql = q => docker(['exec','-i',db,'psql','-X','-v','ON_ERROR_STOP=1','-U','schoolcef_fixture','-d','schoolcef_fixture','-qAt'],{input:q});
const quote = value => "'"+String(value).replaceAll("'","''")+"'";
const prefix = 'qa.recovery.'+crypto.randomBytes(6).toString('hex');
const initial = 'Initial fixture '+crypto.randomBytes(16).toString('hex');
const personal = 'Changed fixture '+crypto.randomBytes(16).toString('hex');
const phone = '+243810000001', ip = '192.0.2.193';
const php = code => docker(['exec','-i',web,'php','-r',code]);
const crypt = value => docker(['exec','-i',web,'php','-r',"require '/var/www/html/functions/Password.php'; echo encrypt_password(stream_get_contents(STDIN));"],{input:value});
let browser, userId, globalId, configSnapshot, lineSnapshot;
const results = [];
const tokenOf = html => (html.match(/name="token" value="([^"]+)"/)||[])[1];
// SQL/SSH checks can outlive Apache's 5-second keep-alive window. Avoid
// retaining idle HTTP connections across those checks; never retry a POST.
const requestOptions = {headers:{'User-Agent':prefix,'X-Forwarded-For':ip,'Connection':'close'}};
async function group(name, fn) {await fn(); results.push(name); console.log('PASS '+name);}
(async()=>{try {
 assert.equal(sql("SELECT (to_regclass('schoolsafe_recovery_otp') IS NOT NULL)::text"),'true','Apply additive QA migration 035 before running browser tests');
 configSnapshot = JSON.parse(sql("SELECT COALESCE(json_agg(c),'[]') FROM config c WHERE title IN ('RECOVERY_SMS_SIMULATE','SMS_SIMULATE')"));
 assert.equal(configSnapshot.length,0,'QA simulation settings must be absent before this run');
 lineSnapshot = JSON.parse(sql('SELECT row_to_json(l) FROM schoolsafe_sms_lines l WHERE id=2'));
 sql("INSERT INTO config(school_id,title,config_value) VALUES(0,'RECOVERY_SMS_SIMULATE','Y'),(0,'SMS_SIMULATE','Y');");
 const source = Number(sql('SELECT id FROM schools WHERE syear=2026 ORDER BY id LIMIT 1'));
 const hash = crypt(initial);
 const makeUser = (suffix,scope) => Number(sql(`INSERT INTO staff(syear,first_name,last_name,username,password,email,profile,profile_id,schools,current_school_id,custom_200000001,last_login) VALUES(2026,'Recovery','Fixture',${quote(prefix+suffix)},${quote(hash)},${quote(prefix+suffix+'@example.invalid')},'admin',1,${scope},${source},${quote(phone)},CURRENT_TIMESTAMP) RETURNING staff_id`));
 userId=makeUser('.school',quote(','+source+',')); globalId=makeUser('.global','NULL');
 browser = await chromium.launch({executablePath:process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE||'C:/Users/PC/AppData/Local/ms-playwright/chromium_headless_shell-1234/chrome-headless-shell-win64/chrome-headless-shell.exe'});
 const context = async()=>{const c=await browser.newContext({extraHTTPHeaders:requestOptions.headers});await c.route('**/*',r=>r.request().url().startsWith(base+'/')?r.continue():r.abort());return c;};
 const c=await context(), old=await context();
 const login=async(ctx,user,pw)=>{await ctx.request.get(base+'/index.php');const r=await ctx.request.post(base+'/index.php',{form:{USERNAME:user,PASSWORD:pw},maxRedirects:0});assert.equal(r.status(),302);const central=user===prefix+'.global';assert.equal((await ctx.request.get(base+(central?'/schoolsafe-administrators.php?home=1':'/Modules.php?modname=misc/Portal.php'),{maxRedirects:0})).status(),200);if(central)assert.equal((await ctx.request.get(base+'/Modules.php?modname=misc/Portal.php',{maxRedirects:0})).status(),403);};
 await login(old,prefix+'.school',initial);
 let token;
 const getHub=async()=>{const r=await c.request.get(base+'/compte/recuperation');assert.equal(r.status(),200);const html=await r.text();token=tokenOf(html);assert.ok(token);return html;};
 const post=async(form)=>c.request.post(base+'/compte/recuperation',{form:{token,...form},maxRedirects:0});
 const request=async(username=prefix+'.school',number=phone)=>post({l4_action:'otp_demande',username,phone:number});
 const current=()=>JSON.parse(sql(`SELECT row_to_json(o) FROM schoolsafe_recovery_otp o WHERE user_id=${userId} AND consumed_at IS NULL ORDER BY id DESC LIMIT 1`)||'null');
 await group('official hub links, desktop/mobile and no-store',async()=>{
  const page=await c.newPage();await page.goto(base+'/compte/recuperation');
  assert.equal(new URL(await page.locator('form').first().evaluate(f=>f.action)).pathname,'/PasswordReset.php');
  const headerLinks=await page.locator('header a').evaluateAll(links=>links.map(a=>a.href));
  assert.equal(headerLinks.length,2,'SchoolSafe brand and explicit return both link to login');
  for(const href of headerLinks){const url=new URL(href);assert.equal(url.origin,new URL(base).origin);assert.equal(url.pathname,'/index.php');}
  for(const width of [1280,390]){await page.setViewportSize({width,height:900});assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth-document.documentElement.clientWidth)<=1);await page.screenshot({path:path.join(out,'recovery-'+width+'.png'),fullPage:true});}
  const response=await c.request.get(base+'/compte/recuperation');assert.match(response.headers()['cache-control'],/no-store/);await page.close();await getHub();
 });
 await group('CSRF rejects challenge creation',async()=>{const count=sql(`SELECT count(*) FROM schoolsafe_recovery_otp WHERE user_id=${userId}`);const r=await post({token:'invalid',l4_action:'otp_demande',username:prefix+'.school',phone});assert.match(await r.text(),/Requête expirée/);assert.equal(sql(`SELECT count(*) FROM schoolsafe_recovery_otp WHERE user_id=${userId}`),count);});
 await group('same response for existing account, unknown account and wrong phone; stale session cleared',async()=>{
  const known=await request(),good=current();assert.ok(good);assert.match(good.code_plain,/^[0-9]{6}$/);
  const unknown=await request(prefix+'.missing');assert.equal(await unknown.text(),await known.text());
  const wrong=await request(prefix+'.school','+243820000001');assert.equal(await wrong.text(),await known.text());
  const reuse=await post({l4_action:'otp_verifie',code:good.code_plain});assert.equal(reuse.status(),200);assert.match(await reuse.text(),/incorrect ou expiré/);
 });
 let resetPath, resetHash;
 await group('SMS challenge hands off to the real native reset form, no browser code disclosure',async()=>{
  const r=await request();const challenge=current();assert.ok(challenge);assert.ok(!(await r.text()).includes(challenge.code_plain));
  const wrong=challenge.code_plain==='999999'?'888888':'999999';assert.match(await (await post({l4_action:'otp_verifie',code:wrong})).text(),/incorrect ou expiré/);
  const valid=await post({l4_action:'otp_verifie',code:challenge.code_plain});assert.equal(valid.status(),302);
  const url=new URL(valid.headers().location);assert.equal(url.pathname,'/PasswordReset.php');resetPath=url.pathname+url.search;resetHash=url.searchParams.get('h');
  assert.match(await (await c.request.get(base+resetPath)).text(),/name="h"/);
  assert.match(await (await post({l4_action:'otp_verifie',code:challenge.code_plain})).text(),/incorrect ou expiré/);
 });
 await group('native password change, link single use, old sessions revoked and new login works',async()=>{
  const r=await c.request.post(base+'/PasswordReset.php',{form:{h:resetHash,PASSWORD:personal},maxRedirects:0});assert.equal(r.status(),302);
  assert.doesNotMatch(await (await c.request.get(base+resetPath)).text(),/name="h"/);
  assert.ok(Number(sql(`SELECT session_epoch FROM schoolsafe_account_state WHERE person_key=${quote(prefix+'.school')} AND school_id=0`))>0);
  assert.notEqual((await old.request.get(base+'/Modules.php?modname=misc/Portal.php',{maxRedirects:0})).status(),200);
  await login(await context(),prefix+'.school',personal);
 });
 await group('expired challenge and five incorrect attempts refuse even the correct code',async()=>{
  await request();let challenge=current();sql(`UPDATE schoolsafe_recovery_otp SET expires_at=CURRENT_TIMESTAMP-interval '1 second' WHERE id=${challenge.id}`);
  assert.match(await (await post({l4_action:'otp_verifie',code:challenge.code_plain})).text(),/incorrect ou expiré/);
  await request();challenge=current();const wrong=challenge.code_plain==='999999'?'888888':'999999';
  for(let i=0;i<5;i++)assert.match(await (await post({l4_action:'otp_verifie',code:wrong})).text(),/incorrect ou expiré/);
  assert.match(await (await post({l4_action:'otp_verifie',code:challenge.code_plain})).text(),/incorrect ou expiré/);
  assert.equal(sql(`SELECT attempts||':'||(consumed_at IS NOT NULL)::text FROM schoolsafe_recovery_otp WHERE id=${challenge.id}`),'5:true');
 });
 await group('request rate limit refuses further challenges',async()=>{
  // The loopback QA relay replaces X-Forwarded-For. Bound the loop and
  // count this run's log entries, not a client-supplied address.
  for(let i=0;i<10&&Number(sql(`SELECT count(*) FROM access_log WHERE status='O' AND user_agent=${quote(prefix)} AND created_at>CURRENT_TIMESTAMP-interval '10 minute'`))<10;i++)await request(prefix+'.missing');
  const n=sql(`SELECT count(*) FROM schoolsafe_recovery_otp WHERE user_id=${userId}`);assert.match(await (await request()).text(),/Trop de demandes/);assert.equal(sql(`SELECT count(*) FROM schoolsafe_recovery_otp WHERE user_id=${userId}`),n);
 });
 const mint=(accepted=true)=>JSON.parse(php(`$_SERVER['SERVER_NAME']='127.0.0.1'; $_SERVER['SERVER_PORT']='8096'; $_SERVER['HTTP_HOST']='127.0.0.1:8096'; $_SERVER['REQUEST_URI']='/PasswordReset.php'; $_SERVER['SCRIPT_NAME']='/PasswordReset.php'; chdir('/var/www/html'); require 'Warehouse.php'; require_once 'ProgramFunctions/SchoolSafeRecovery.fnc.php'; echo json_encode(SchoolSafeRecoveryIssueReset(${userId},'staff',${JSON.stringify(prefix+'.school@example.invalid')},static function(){return ${accepted?'true':'false'};}));`));
 await group('email failure preserves deadline; native links expire; unknown emails respond generically',async()=>{
  const before=sql(`SELECT last_login FROM staff WHERE staff_id=${userId}`);assert.equal(mint(false).sent,false);assert.equal(sql(`SELECT last_login FROM staff WHERE staff_id=${userId}`),before);
  const valid=mint();assert.ok(valid.sent);const url=new URL(valid.link,base+'/');
  sql(`UPDATE staff SET last_login=CURRENT_TIMESTAMP-interval '3 hour' WHERE staff_id=${userId}`);
  assert.doesNotMatch(await (await c.request.get(base+url.pathname+url.search)).text(),/name="h"/);
  const a=await c.request.post(base+'/PasswordReset.php',{form:{email:prefix+'.unknown@example.invalid'},maxRedirects:0});
  const b=await c.request.post(base+'/PasswordReset.php',{form:{email:prefix+'.other@example.invalid'},maxRedirects:0});
  assert.equal(a.status(),302);assert.equal(a.headers().location,b.headers().location);assert.match(a.headers().location,/reason=password_reset/);
 });
 await group('native reset refuses pending first access without changing its deadline',async()=>{
  const previous=mint();assert.ok(previous.sent);
  const previousUrl=new URL(previous.link,base+'/');
  assert.equal(previousUrl.pathname,'/PasswordReset.php');
  assert.match(await (await c.request.get(base+previousUrl.pathname+previousUrl.search)).text(),/name="h"/);
  sql(`INSERT INTO schoolsafe_native_onboarding(staff_id,syear,expires_at,created_by) VALUES(${userId},2026,CURRENT_TIMESTAMP+interval '1 day',${globalId})`);
  const before=sql(`SELECT last_login FROM staff WHERE staff_id=${userId}`);
  const result=mint();
  assert.equal(result.sent,false);assert.equal(sql(`SELECT last_login FROM staff WHERE staff_id=${userId}`),before);
  const url=new URL(previous.link,base+'/');
  assert.doesNotMatch(await (await c.request.get(base+url.pathname+url.search)).text(),/name="h"/,'previously issued link cannot bypass first access');
  sql(`DELETE FROM schoolsafe_native_onboarding WHERE staff_id=${userId}`);
  sql(`INSERT INTO schoolsafe_identity_links(staff_id) VALUES(${userId})`);
  try {assert.equal(mint().sent,false);assert.doesNotMatch(await (await c.request.get(base+url.pathname+url.search)).text(),/name="h"/,'previously issued link cannot convert managed identity');}
  finally {sql(`DELETE FROM schoolsafe_identity_links WHERE staff_id=${userId}`);}
 });
 await group('SMS test is global-only, CSRF/confirmation guarded and uses the confirmed line',async()=>{
  const school=await context();await login(school,prefix+'.school',personal);assert.equal((await school.request.get(base+'/plateforme-prodeli-sms.php',{maxRedirects:0})).status(),403);
  const global=await context();await login(global,prefix+'.global',initial);
  const html=await (await global.request.get(base+'/plateforme-prodeli-sms.php')).text(),token=tokenOf(html);assert.ok(token);
  sql("UPDATE schoolsafe_sms_lines SET status='active' WHERE id=2");
  const before=sql(`SELECT count(*) FROM schoolsafe_sms_outbox WHERE created_by=${quote(prefix+'.global')}`);
  const form={token,sms_action:'test_send',line_id:'2',test_to:phone};
  await global.request.post(base+'/plateforme-prodeli-sms.php',{form:{...form,token:'invalid',confirm:'1'}});
  assert.equal(sql(`SELECT count(*) FROM schoolsafe_sms_outbox WHERE created_by=${quote(prefix+'.global')}`),before);
  await global.request.post(base+'/plateforme-prodeli-sms.php',{form});
  assert.equal(sql(`SELECT count(*) FROM schoolsafe_sms_outbox WHERE created_by=${quote(prefix+'.global')}`),before);
  const response=await global.request.post(base+'/plateforme-prodeli-sms.php',{form:{...form,confirm:'1'}});assert.equal(response.status(),200);
  assert.equal(sql(`SELECT line_id||':'||error_code FROM schoolsafe_sms_outbox WHERE created_by=${quote(prefix+'.global')} ORDER BY id DESC LIMIT 1`),'2:SIMULATED');
 });
} finally {
 if(browser)await browser.close();
 if(userId||globalId){const ids=[userId,globalId].filter(Number.isInteger).join(',');sql(`BEGIN;DELETE FROM schoolsafe_recovery_otp WHERE user_type='staff' AND user_id IN (${ids});DELETE FROM schoolsafe_native_onboarding WHERE staff_id IN (${ids});DELETE FROM schoolsafe_identity_links WHERE staff_id IN (${ids});DELETE FROM schoolsafe_account_state WHERE person_key LIKE ${quote(prefix+'%')};DELETE FROM schoolsafe_account_audit WHERE person_key LIKE ${quote(prefix+'%')};DELETE FROM schoolsafe_sms_outbox WHERE created_by LIKE ${quote(prefix+'%')};DELETE FROM schoolsafe_sms_audit WHERE actor LIKE ${quote(prefix+'%')};DELETE FROM staff WHERE staff_id IN (${ids}) AND username LIKE ${quote(prefix+'%')};DELETE FROM access_log WHERE user_agent=${quote(prefix)} OR username LIKE ${quote(prefix+'%')};COMMIT;`);}
 if(configSnapshot?.length===0)sql("DELETE FROM config WHERE school_id=0 AND title IN ('RECOVERY_SMS_SIMULATE','SMS_SIMULATE') AND config_value='Y'");
 if(lineSnapshot)sql(`UPDATE schoolsafe_sms_lines SET status=${quote(lineSnapshot.status)},conn_state=${quote(lineSnapshot.conn_state)},conn_checked_at=${lineSnapshot.conn_checked_at?quote(lineSnapshot.conn_checked_at):'NULL'} WHERE id=2`);
 fs.writeFileSync(path.join(out,'browser-result.json'),JSON.stringify({results,cleanup:true,scope:'Local fictitious PostgreSQL; simulated transport, not an immutable release image.'},null,2));
 console.log(JSON.stringify({groups:results.length,cleanup:true}));
}})().catch(error=>{console.error(error.stack);process.exitCode=1;});
