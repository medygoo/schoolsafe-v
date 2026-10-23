'use strict';
const fs=require('node:fs'),assert=require('node:assert/strict'),crypto=require('node:crypto'),{execFileSync}=require('node:child_process');
const {chromium}=require('playwright');
const {loginPresentation,firstAccessPresentation,handoffPresentation}=require('./qa-prodeli-access-presentation.cjs');
const path=require('node:path');
const evidence=process.env.PRODELI_NATIVE_EVIDENCE;assert.ok(evidence,'Set a private PRODELI_NATIVE_EVIDENCE directory');fs.mkdirSync(evidence,{recursive:true});
const base=process.env.PRODELI_QA_URL||'http://127.0.0.1:8096',db=process.env.PRODELI_QA_DB||'schoolcef-harmonization-pg',web=process.env.PRODELI_QA_WEB||'schoolsafe-prodeli-presentation-qa';
assert.equal(new URL(base).hostname,'127.0.0.1','Only the isolated loopback relay is permitted');
const shellQuote=s=>"'"+s.replaceAll("'","'\\''")+"'";
const dockerRun=(args,options={})=>process.env.PRODELI_QA_SSH
 ? execFileSync('ssh',['-4','-o','BatchMode=yes',process.env.PRODELI_QA_SSH,'docker '+args.map(shellQuote).join(' ')],{timeout:45000,...options})
 : execFileSync('docker',args,{timeout:45000,...options});
for(const n of [db,web])assert.equal(dockerRun(['inspect','--format','{{index .Config.Labels "schoolcef.qualification"}}',n],{encoding:'utf8'}).trim(),process.env.PRODELI_QA_LABEL||'harmonization-20261021');
const fixtureSql=require('./qa-fixture-sql.cjs');
const sql=q=>fixtureSql(q)??dockerRun(['exec','-i',db,'psql','-X','-v','ON_ERROR_STOP=1','-U','schoolcef_fixture','-d','schoolcef_fixture','-qAt'],{input:q,encoding:'utf8'}).trim(),quote=s=>"'"+s.replaceAll("'","''")+"'";
assert.equal(dockerRun(['network','inspect',process.env.PRODELI_QA_NETWORK||'schoolcef-harmonization-local','--format','{{.Internal}}'],{encoding:'utf8'}).trim(),'true');
const scopedSetting='SCHOOLSAFE_QA_SCOPE_'+crypto.randomBytes(8).toString('hex').toUpperCase();
const prefix='qa.native.'+Date.now(),manager=prefix+'.global',client=prefix+'.principal',password=crypto.randomBytes(16).toString('hex'),personal='Personal fixture '+crypto.randomBytes(40).toString('hex'),wizardPersonal='Wizard fixture '+crypto.randomBytes(40).toString('hex');
const secrets=[password,personal,wizardPersonal],sanitize=value=>secrets.reduce((text,secret)=>text.replaceAll(secret,'[synthetic-secret]'),String(value));
let school,wizardSchool,adminId,clientId,wizardId,legacyId,browser,page,failed=false,configurationChecked=false;const results=[],pendingRequests=new Map(),recentResponses=[];
async function healthy(p){assert.ok(!/DB Execute Failed|WE HAVE A PROBLEM, PLEASE CONTACT|Fatal error:|Uncaught (?:Error|Exception)/.test(await p.locator('body').innerText()),'Application content must render, not just return HTTP 200');}
async function schoolDashboard(p,id,key){await healthy(p);await p.locator('#ss-photo-dashboard').waitFor();assert.equal(await p.locator('.ss-central-dashboard-return').count(),0);assert.equal(sql(`SELECT current_school_id FROM staff WHERE staff_id=${id}`),key==='wizard'?String(wizardSchool):String(school));for(const width of [1280,390]){await p.setViewportSize({width,height:900});assert.ok(await p.evaluate(()=>document.documentElement.scrollWidth-document.documentElement.clientWidth)<=1);await p.screenshot({path:path.join(evidence,key+'-school-'+width+'.png'),fullPage:true});}if(process.env.SCHOOL_NAV_HTTP==='1'){await require('./qa-school-dashboard-http.cjs')(p,base,evidence,key);results.push('Authenticated school navigation: composed dashboard, shared menu and read-only module destinations ('+key+')');await p.setViewportSize({width:390,height:900});}await schoolConfigurationBoundary(p,id,key);}
async function schoolConfigurationBoundary(p,id,key){
 if(configurationChecked)return;
 const target=key==='wizard'?wizardSchool:school;
 const link=p.getByRole('link',{name:'Configurer mon école',exact:true});
 assert.equal(await link.getAttribute('href'),'/Modules.php?modname=School_Setup/Configuration.php');
 await Promise.all([p.waitForURL(u=>u.pathname==='/Modules.php'&&u.searchParams.get('modname')==='School_Setup/Configuration.php'),link.click()]);
 const header=p.locator('.ss-native-workspace-header[data-workspace="school"]');await header.waitFor({state:'visible'});
 assert.ok((await header.innerText()).includes(prefix+(key==='wizard'?' Wizard':' School')));
 assert.equal(await p.locator('[data-central-route]').count(),0);
 assert.ok(await p.evaluate(()=>document.documentElement.scrollWidth-document.documentElement.clientWidth)<=1,'Own-school settings fit mobile');
 await p.screenshot({path:path.join(evidence,'school-configuration-own-390.png'),fullPage:true});
 sql(`INSERT INTO config(school_id,title,config_value) VALUES(0,${quote(scopedSetting)},'platform-default')`);
 let token=await p.locator('head meta[name="token"]').getAttribute('content');
 const route=base+'/Modules.php?modname=School_Setup/Configuration.php&modfunc=update';
 await p.context().request.post(route,{form:{token:'invalid',['values[config]['+scopedSetting+']']:'invalid-write'}});
 assert.equal(sql(`SELECT count(*) FROM config WHERE school_id=${target} AND title=${quote(scopedSetting)}`),'0');
 await p.goto(base+'/Modules.php?modname=School_Setup/Configuration.php');
 token=await p.locator('head meta[name="token"]').getAttribute('content');assert.ok(token,'Native CSRF metadata required');
 const response=await p.context().request.post(route,{headers:{'X-CSRF-Token':token},form:{token,['values[config]['+scopedSetting+']']:'own-school-only'}});
 assert.ok(!/DB Execute Failed|Fatal error:/.test(await response.text()));
 assert.equal(sql(`SELECT config_value FROM config WHERE school_id=0 AND title=${quote(scopedSetting)}`),'platform-default');
 assert.equal(sql(`SELECT config_value FROM config WHERE school_id=${target} AND title=${quote(scopedSetting)}`),'own-school-only');
 for(const endpoint of ['/schoolsafe-administrators.php?home=1','/admin-console.php','/plateforme/prodeli','/plateforme/prodeli/sms','/plateforme/prodeli/whatsapp']){
  const denied=await p.context().request.get(base+endpoint);assert.equal(denied.status(),403,'School cannot enter '+endpoint);
  assert.ok(!(await denied.text()).includes('href="/schoolsafe-administrators.php?home=1"'));
 }
 for(const tab of ['modules','plugins'])assert.equal((await p.context().request.get(base+'/Modules.php?modname=School_Setup/Configuration.php&tab='+tab)).status(),403);
 assert.equal(sql(`SELECT current_school_id FROM staff WHERE staff_id=${id}`),String(target));
 await p.goto(base+'/Modules.php?modname=misc/Portal.php');await p.locator('#ss-photo-dashboard').waitFor();
 configurationChecked=true;results.push('Configure my school stays in its tenant; platform and installation tabs denied; CSRF and school-only settings preserve platform defaults');
}
(async()=>{try{
 const source=Number(sql('SELECT id FROM schools WHERE syear=2026 ORDER BY id LIMIT 1'));
 school=Number(sql(`INSERT INTO schools(syear,title,reporting_gp_scale) SELECT 2026,${quote(prefix+' School')},reporting_gp_scale FROM schools WHERE id=${source} AND syear=2026 RETURNING id`));assert.ok(Number.isInteger(school));
 sql(`INSERT INTO school_marking_periods(syear,mp,school_id,title,short_name,sort_order,start_date,end_date,post_start_date,post_end_date,does_grades,does_comments,rollover_id,parent_id) SELECT syear,mp,${school},title,short_name,sort_order,start_date,end_date,post_start_date,post_end_date,does_grades,does_comments,marking_period_id,parent_id FROM school_marking_periods WHERE school_id=${source} AND syear=2026; UPDATE school_marking_periods mp SET parent_id=(SELECT mp2.marking_period_id FROM school_marking_periods mp2 WHERE mp2.school_id=${school} AND mp2.syear=2026 AND mp2.rollover_id=mp.parent_id) WHERE mp.school_id=${school} AND mp.syear=2026 AND mp.parent_id IS NOT NULL;`);
 const hash=dockerRun(['exec','-i',web,'php','-r',"require '/var/www/html/functions/Password.php'; echo encrypt_password(stream_get_contents(STDIN));"],{input:password,encoding:'utf8'});
 adminId=Number(sql(`INSERT INTO staff(syear,first_name,last_name,username,password,profile,profile_id,schools,current_school_id,last_login) VALUES(2026,'QA','Native',${quote(manager)},${quote(hash)},'admin',1,NULL,${source},CURRENT_TIMESTAMP) RETURNING staff_id`));
 browser=await require('./qa-browser.cjs')(chromium);
 const newContext=async()=>{const c=await browser.newContext({extraHTTPHeaders:{'User-Agent':prefix,'Connection':'close'}});c.setDefaultTimeout(90000);c.setDefaultNavigationTimeout(90000);c.on('request',r=>pendingRequests.set(r,{path:new URL(r.url()).pathname,type:r.resourceType(),started:Date.now()}));c.on('requestfinished',r=>pendingRequests.delete(r));c.on('requestfailed',r=>pendingRequests.delete(r));c.on('response',r=>{recentResponses.push({path:new URL(r.url()).pathname,status:r.status()});if(recentResponses.length>20)recentResponses.shift();});await c.route('**/*',r=>r.request().url().startsWith(base+'/')?r.continue():r.abort());return c;};
 const context=await newContext();
 const login=async(c,user,pw)=>{const p=await c.newPage();await p.goto(base+'/index.php');if(await p.locator('#enterSplash').isVisible())await p.locator('#enterSplash').click();await p.locator('#USERNAME').fill(user);await p.locator('#PASSWORD').fill(pw);await Promise.all([p.waitForURL(u=>!u.pathname.endsWith('/index.php')||u.searchParams.get('modfunc')==='first-login',{waitUntil:'domcontentloaded'}),p.locator('#loginform button[type="submit"]').click()]);return p;};
 const preview=await context.newPage();await preview.goto(base+'/index.php');await loginPresentation(preview,evidence);await preview.close();
 results.push('SchoolSafe public welcome/login desktop/mobile, native fields and eye control');
 page=await login(context,manager,password);
 await page.goto(base+'/schoolsafe-administrators.php?home=1');
 assert.equal(await page.locator('input[name="action"][value="dashboard_school"]').count(),0);
 assert.equal(await page.locator('a[href*="modname=misc/Portal.php"]').count(),0);
 for(const route of ['/Modules.php?modname=misc/Portal.php','/Modules.php?modname=Users/User.php&staff_id='+adminId,
  '/Modules.php?modname=Students/Student.php','/Modules.php?modname=School_Setup/Configuration.php',
  '/Modules.php?modname=Users/Profiles.php','/ecoles/'+source+'/utilisateurs','/ecoles/'+source+'/profils',
  '/schoolsafe-administrators.php?bottomfunc=print&modname=Users/User.php','/admin-console.php?bottomfunc=help&modname=Students/Student.php',
  '/Side.php?sidefunc=update&school='+school,'/Side.php?sidefunc=update&side_staff_id='+adminId]) {
  assert.equal((await context.request.get(base+route,{maxRedirects:0})).status(),403,'Central boundary refuses '+route);
 }
 await page.goto(base+'/schoolsafe-administrators.php');
 const unexpectedLinks=await page.locator('a[href*="modname=Users/User.php"]').evaluateAll(es=>es.filter(e=>new URL(e.href).pathname==='/Modules.php').map(e=>({label:e.textContent,parents:[e.parentElement,e.parentElement?.parentElement].filter(Boolean).map(p=>({tag:p.tagName,id:p.id,class:p.className}))})));
 fs.writeFileSync(path.join(evidence,'central-link-diagnostic.json'),JSON.stringify(unexpectedLinks,null,2));
 assert.equal(unexpectedLinks.length,0);
 for(const retained of ['/Modules.php?modname=Users/Preferences.php','/plateforme/prodeli/sms','/plateforme/prodeli/whatsapp']) {
  const response=await context.request.get(base+retained);assert.equal(response.status(),200,'Central operation retained '+retained);
  assert.ok(!/DB Execute Failed|Fatal error:|Uncaught (?:Error|Exception)/.test(await response.text()));
 }
 const boundaryToken=await page.locator('#sskc-add-admin input[name="token"]').inputValue();
 assert.equal((await context.request.post(base+'/schoolsafe-administrators.php?home=1',{form:{action:'dashboard_school',token:boundaryToken,school:String(school)}})).status(),403);
 assert.equal(sql(`SELECT current_school_id FROM staff WHERE staff_id=${adminId}`),String(source));
 results.push('ProDéli cannot enter school dashboards, profiles or school context through UI, direct routes or forged POST');
 await page.goto(base+'/admin-console.php');
 assert.equal(await page.locator('a[href^="/ecoles/"]').count(),0);
 assert.equal(await page.locator('a[href*="modname=misc/Portal.php"]').count(),0);
 const consoleToken=await page.locator('input[name="console_token"]').first().inputValue();
 for(const action of ['create','school_director_create','reset']) {
  const refused=await context.request.post(base+'/admin-console.php',{form:{console_token:consoleToken,console_action:action,staff_id:String(adminId),first_name:'QA',last_name:'Forbidden',username:prefix+'.forbidden',school_id:String(school),'school_ids[]':String(school)}});
  assert.match(await refused.text(),/Aucun mot de passe modifié ici/);
 }
 assert.equal(sql(`SELECT count(*) FROM staff WHERE username=${quote(prefix+'.forbidden')}`),'0');
 assert.equal(sql(`SELECT password FROM staff WHERE staff_id=${adminId}`),hash,'Legacy central reset does not mutate the account');
 results.push('legacy console credentials cannot bypass guarded native handoff and personal activation');
 await page.setViewportSize({width:390,height:844});
 await page.goto(base+'/schoolsafe-administrators.php?wizard=1');
 const steps=[{TITLE:prefix+' Wizard',SHORT_NAME:'QA Native'},{school_email:'school@example.invalid',school_slogan:'Fixture school'},
  {first_name:'Alice',last_name:'Wizard',username:prefix+'.wizard',email:'wizard@example.invalid'}, {},{REPORTING_GP_SCALE:'100'}];
 for(let i=1;i<=5;i++){
  for(const [key,value]of Object.entries(steps[i-1]))await page.locator('#'+key).fill(value);
  if(i===3)await page.locator('select[name="profile_id"]').selectOption('1');
  if(i===5)await page.locator('select[name="source"]').selectOption(String(source));
  await page.getByRole('button',{name:'Enregistrer et continuer',exact:true}).waitFor({state:'visible'});
  assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth-document.documentElement.clientWidth)<=1,'Wizard mobile step '+i+' fits');
  await page.screenshot({path:path.join(evidence,'central-wizard-step-'+i+'-390.png'),fullPage:true});
  await Promise.all([page.waitForURL(u=>u.searchParams.get('step')===String(i+1)),page.getByRole('button',{name:'Enregistrer et continuer',exact:true}).click()]);
 }
 await page.getByRole('button',{name:'Confirmer la création de l’école',exact:true}).waitFor({state:'visible'});
 assert.ok(await page.evaluate(()=>document.documentElement.scrollWidth-document.documentElement.clientWidth)<=1,'Wizard mobile review fits');
 await page.screenshot({path:path.join(evidence,'central-wizard-step-6-390.png'),fullPage:true});
 await page.getByRole('button',{name:'Confirmer la création de l’école',exact:true}).click();
 await page.getByRole('link',{name:'Continuer : préparer le compte principal',exact:true}).waitFor();
 assert.equal(await page.locator('.ss-native-workspace-header').count(),1,'Creation result retains the SchoolSafe workspace');
 assert.equal(await page.getByRole('heading',{name:'New school',exact:true}).count(),0,'Wizard does not fall back to the English native copy page');
 wizardSchool=Number(sql(`SELECT id FROM schools WHERE title=${quote(prefix+' Wizard')}`));assert.ok(wizardSchool>0);
 assert.equal(sql(`SELECT COALESCE(schools,'') FROM staff WHERE staff_id=${adminId}`),'');
 await page.getByRole('link',{name:'Continuer : préparer le compte principal',exact:true}).click();
 await page.getByRole('button',{name:'Vérifier le compte principal',exact:true}).click();
 await page.waitForFunction(expected=>[...document.querySelectorAll('dt')].some(e=>e.textContent==='Écoles'&&e.nextElementSibling?.textContent===expected),prefix+' Wizard');
 await page.locator('dt').filter({hasText:/^Écoles$/}).waitFor({state:'visible',timeout:10000});
 assert.equal(await page.locator('dt').filter({hasText:/^Écoles$/}).evaluate(e=>e.nextElementSibling.innerText),prefix+' Wizard','Principal review names the assigned school, not its raw scope');
 await page.getByRole('button',{name:'Créer le compte et préparer l’accès',exact:true}).click();await page.locator('dd code').waitFor();
 wizardId=Number(sql(`SELECT staff_id FROM staff WHERE username=${quote(prefix+'.wizard')}`));assert.ok(wizardId>0);
 assert.equal(sql(`SELECT count(*) FROM schoolsafe_opening_requests WHERE school_id=${wizardSchool} AND responsible_id=${wizardId} AND state='prepared'`),'1');
 assert.equal(sql(`SELECT staff_id FROM schoolsafe_school_leadership WHERE school_id=${wizardSchool} AND role='principal'`),String(wizardId));
 results.push('complete native school wizard, global scope preserved, atomic opening and principal');
 const wizardTemporary=await page.locator('dd code').innerText();secrets.push(wizardTemporary);
 const wizardContext=await newContext();
 const wp=await login(wizardContext,prefix+'.wizard',wizardTemporary);await wp.locator('#first-login-form').waitFor();
 // A recorded connection is not proof that the personal password was saved.
 sql(`UPDATE staff SET last_login=CURRENT_TIMESTAMP WHERE staff_id=${wizardId} AND username=${quote(prefix+'.wizard')}`);
 const pendingHtml=await (await context.request.get(base+'/schoolsafe-administrators.php')).text();
 const pendingRow=pendingHtml.split('id="sskc-account-'+wizardId+'"')[1]?.split('</tr>')[0]||'';
 assert.match(pendingRow,/À activer/);assert.match(pendingRow,/Mot de passe personnel à créer/);
 results.push('temporary login does not activate the displayed school administrator account');
 // Keep the authenticated cookie jar, but stop page background requests before testing
 // the first expired request: the guard intentionally destroys the rejected session.
 await wp.close();
 sql(`UPDATE schoolsafe_native_onboarding SET expires_at=CURRENT_TIMESTAMP-interval '1 minute' WHERE staff_id=${wizardId}`);
 assert.equal(sql(`SELECT count(*) FROM schoolsafe_native_onboarding WHERE staff_id=${wizardId} AND expires_at<CURRENT_TIMESTAMP AND completed_at IS NULL`),'1');
 const expiredResponse=await wizardContext.request.get(base+'/Modules.php?modname=misc/Portal.php',{maxRedirects:0});
 const expiredBody=await expiredResponse.text();
 fs.writeFileSync(path.join(evidence,'expired-access-diagnostic.json'),JSON.stringify({status:expiredResponse.status(),expiredMessage:/expired|expiré|remplacé/.test(expiredBody),loginForm:/id=["']loginform/.test(expiredBody),firstAccessForm:/id=["']first-login-form/.test(expiredBody),dashboard:/id=["']ss-photo-dashboard/.test(expiredBody),cookiesPresent:(await wizardContext.cookies()).length>0,locationPath:expiredResponse.headers().location?new URL(expiredResponse.headers().location,base).pathname:null},null,2));
 assert.equal(expiredResponse.status(),403);
 await page.goto(base+'/schoolsafe-administrators.php');
 await page.locator('#sskc-account-'+wizardId).getByRole('button',{name:'Renouveler l’accès initial natif',exact:true}).click();
 const renewForm=page.locator('form').filter({has:page.locator('input[name="native_renew_nonce"]')});
 const renewPayload=await renewForm.evaluate(form=>Object.fromEntries(new FormData(form)));
 await page.getByRole('button',{name:'Confirmer : Renouveler l’accès initial natif',exact:true}).click();await page.locator('dd code').waitFor();
 const renewedTemporary=await page.locator('dd code').innerText();secrets.push(renewedTemporary);assert.ok(renewedTemporary!==wizardTemporary,'Renewal issues a different initial secret');
 assert.equal(sql(`SELECT generation FROM schoolsafe_native_onboarding WHERE staff_id=${wizardId}`),'2');
 await context.request.post(base+'/schoolsafe-administrators.php',{form:renewPayload});
 assert.equal(sql(`SELECT generation FROM schoolsafe_native_onboarding WHERE staff_id=${wizardId}`),'2');
 const renewedContext=await newContext();
 const rp=await login(renewedContext,prefix+'.wizard',renewedTemporary);await rp.locator('#first-login-form').waitFor();
 results.push('expired access blocked; confirmed native renewal and replay refusal; renewed login works');
 await rp.locator('input[name="first_login[PASSWORD]"]').fill(wizardPersonal);
 await Promise.all([rp.waitForURL(u=>u.pathname==='/Modules.php'),rp.locator('#first-login-form input[type=submit],#first-login-form button[type=submit]').click()]);
 await schoolDashboard(rp,wizardId,'wizard');
 assert.equal(sql(`SELECT count(*) FROM schoolsafe_native_onboarding WHERE staff_id=${wizardId} AND completed_at IS NOT NULL`),'1');
 const activeHtml=await (await context.request.get(base+'/schoolsafe-administrators.php')).text();
 const activeRow=activeHtml.split('id="sskc-account-'+wizardId+'"')[1]?.split('</tr>')[0]||'';
 assert.match(activeRow,/>Actif</);assert.ok(!activeRow.includes('Mot de passe personnel à créer'));
 results.push('wizard-created principal completes first access through the actual form and reaches their own SchoolSafe dashboard');
 results.push('school account becomes displayed active only after successful personal password completion');
 const wizardReconnect=await newContext();const wizardAgain=await login(wizardReconnect,prefix+'.wizard',wizardPersonal);await schoolDashboard(wizardAgain,wizardId,'wizard');
 results.push('wizard principal reconnects using a long personal password, not the temporary password or old session');
 await page.goto(base+'/schoolsafe-administrators.php');let token=await page.locator('#sskc-add-admin input[name="token"]').inputValue();
 const prepare={action:'prepare',token,first_name:'Anne',last_name:"D'Essai",username:client,email:'qa@example.invalid',profile_id:'1',scope_mode:'schools','schools[]':String(school),leadership_role:'principal'};
 let response=await context.request.post(base+'/schoolsafe-administrators.php',{form:prepare});assert.equal(response.status(),200);const review=await response.text();assert.ok(/name="action" value="native_create"/.test(review),'Review offers native creation');assert.ok(!/Gestion des identités indisponible/.test(review),'Paused identity service is not required');const intent=review.match(/name="sskc_intent" value="([a-f0-9]+)"/)[1];results.push('native preparation without identity-provider dependency');
 const confirm={action:'native_create',token,confirmed:'1',sskc_intent:intent};
 response=await context.request.post(base+'/schoolsafe-administrators.php',{form:{...confirm,token:'invalid'},maxRedirects:0});assert.equal(sql(`SELECT count(*) FROM staff WHERE username=${quote(client)}`),'0');results.push('invalid confirmation CSRF creates nothing');
 response=await context.request.post(base+'/schoolsafe-administrators.php',{form:confirm,maxRedirects:0});assert.equal(response.status(),303);const credential=response.headers().location;clientId=Number(sql(`SELECT staff_id FROM staff WHERE username=${quote(client)}`));assert.ok(clientId>0);assert.equal(sql(`SELECT staff_id FROM schoolsafe_school_leadership WHERE school_id=${school} AND role='principal'`),String(clientId));
 await context.request.post(base+'/schoolsafe-administrators.php',{form:confirm});assert.equal(sql(`SELECT count(*) FROM staff WHERE username=${quote(client)}`),'1');results.push('confirmed creation, leadership and replay refusal');
 const handoffResponse=await page.goto(base+credential);const temporary=await page.locator('dd code').innerText();secrets.push(temporary);assert.ok(/24 heures/.test(await page.locator('main').innerText()));const download=await page.locator('a[download]').getAttribute('href');assert.ok(Buffer.from(download.split(',')[1],'base64').toString('utf8').includes('https://app.shoolsafe.tech/index.php'),'Confidential file uses the official login URL');
 await handoffPresentation(page,handoffResponse,evidence);
 await page.reload();assert.equal(await page.locator('dd code').count(),0);results.push('confidential one-display handoff, official URL, script isolation, responsive brand and redacted print PDF');
 const clientContext=await newContext();const cp=await login(clientContext,client,temporary);assert.equal(new URL(cp.url()).searchParams.get('modfunc'),'first-login');await cp.locator('#first-login-form').waitFor();
 assert.equal(await cp.locator('.ss-access-brand img[alt="SchoolSafe"]').count(),1);
 await firstAccessPresentation(cp,evidence);
 const bypass=await clientContext.request.get(base+'/Modules.php?modname=misc/Portal.php',{maxRedirects:0});assert.equal(bypass.status(),303);assert.match(bypass.headers().location,/first-login/);results.push('targeted first-login form and direct-route bypass refused');
 const secondContext=await newContext();const stale=await login(secondContext,client,temporary);await stale.locator('#first-login-form').waitFor();await stale.close();
 const firstToken=await cp.locator('#first-login-form input[name="token"]').inputValue();
 response=await clientContext.request.post(base+'/index.php?modfunc=first-login',{form:{token:firstToken,'first_login[PASSWORD]':'short'}});assert.equal(sql(`SELECT count(*) FROM schoolsafe_native_onboarding WHERE staff_id=${clientId} AND completed_at IS NULL`),'1');results.push('weak personal password cannot complete first access');
 await cp.locator('input[name="first_login[PASSWORD]"]').fill(personal);
 await Promise.all([cp.waitForURL(u=>u.pathname==='/Modules.php'),cp.locator('#first-login-form input[type=submit],#first-login-form button[type=submit]').click()]);
 assert.equal(sql(`SELECT count(*) FROM schoolsafe_native_onboarding WHERE staff_id=${clientId} AND completed_at IS NOT NULL`),'1');
 await cp.goto(base+'/Modules.php?modname=misc/Portal.php');assert.equal(new URL(cp.url()).pathname,'/Modules.php');assert.equal(sql(`SELECT current_school_id FROM staff WHERE staff_id=${clientId}`),String(school));
 response=await secondContext.request.get(base+'/Modules.php?modname=misc/Portal.php',{maxRedirects:0});assert.equal(response.status(),403);
 await clientContext.request.post(base+'/schoolsafe-administrators.php',{form:{...prepare,token:firstToken,username:prefix+'.forbidden'}});
 assert.equal(sql(`SELECT count(*) FROM staff WHERE username=${quote(prefix+'.forbidden')}`),'0');
 results.push('personal password committed, correct school, other temporary sessions revoked');
 await schoolDashboard(cp,clientId,'direct');results.push('directly-created school administrator reaches a complete SchoolSafe dashboard desktop/mobile');
 const reconnect=await newContext();const again=await login(reconnect,client,personal);await schoolDashboard(again,clientId,'direct');
 results.push('directly-created administrator reconnects through the native form with the personal passphrase');
 legacyId=Number(sql(`INSERT INTO staff(syear,first_name,last_name,username,password,profile,profile_id,schools,current_school_id,last_login) VALUES(2026,'QA','Legacy',${quote(prefix+'.legacy')},${quote(hash)},'admin',1,${quote(','+school+',')},${school},NULL) RETURNING staff_id`));
 assert.equal(sql(`SELECT count(*) FROM schoolsafe_native_onboarding WHERE staff_id=${legacyId}`),'0');
 await page.goto(base+'/schoolsafe-administrators.php');
 await page.locator('#sskc-account-'+legacyId).getByRole('button',{name:'Préparer l’accès initial natif',exact:true}).click();
 const legacyForm=page.locator('form').filter({has:page.locator('input[name="native_renew_nonce"]')});
 const legacyPayload=await legacyForm.evaluate(form=>Object.fromEntries(new FormData(form)));
 await context.request.post(base+'/schoolsafe-administrators.php',{form:{...legacyPayload,token:'invalid'}});
 assert.equal(sql(`SELECT count(*) FROM schoolsafe_native_onboarding WHERE staff_id=${legacyId}`),'0');
 await page.getByRole('button',{name:'Confirmer : Préparer l’accès initial natif',exact:true}).click();await page.locator('dd code').waitFor();
 const legacyTemporary=await page.locator('dd code').innerText();secrets.push(legacyTemporary);
 assert.equal(sql(`SELECT generation FROM schoolsafe_native_onboarding WHERE staff_id=${legacyId}`),'1');
 await context.request.post(base+'/schoolsafe-administrators.php',{form:legacyPayload});
 assert.equal(sql(`SELECT generation FROM schoolsafe_native_onboarding WHERE staff_id=${legacyId}`),'1');
 const legacyContext=await newContext();const lp=await login(legacyContext,prefix+'.legacy',legacyTemporary);await lp.locator('#first-login-form').waitFor();
 assert.equal((await legacyContext.request.get(base+'/Modules.php?modname=misc/Portal.php',{maxRedirects:0})).status(),303);
 const legacyPersonal='Legacy fixture '+crypto.randomBytes(30).toString('hex');secrets.push(legacyPersonal);
 await lp.locator('input[name="first_login[PASSWORD]"]').fill(legacyPersonal);
 await Promise.all([lp.waitForURL(u=>u.pathname==='/Modules.php'),lp.locator('#first-login-form input[type=submit],#first-login-form button[type=submit]').click()]);
 await lp.locator('#ss-photo-dashboard').waitFor();
 assert.equal(sql(`SELECT count(*) FROM schoolsafe_native_onboarding WHERE staff_id=${legacyId} AND completed_at IS NOT NULL`),'1');
 results.push('unused legacy school administrator gets a real temporary credential, anti-CSRF/replay, and mandatory personal activation');
 await page.goto(base+'/schoolsafe-administrators.php');
 assert.equal(await page.locator('#sskc-account-'+legacyId+' input[value="native_temporary"]').count(),0);
 const completedHelp=page.locator('#sskc-account-'+legacyId+' .sskc-action-help');
 await completedHelp.waitFor({state:'visible'});
 assert.ok((await completedHelp.innerText()).includes('Aucun accès initial à préparer pour ce compte.'));
 assert.equal(await page.locator('#sskc-account-'+wizardId+' input[value="native_temporary"]').count(),0);
 results.push('completed school accounts are never reset through the initial-access preparation flow');
}catch(error){failed=true;throw error;}finally{
 if(failed){const pages=browser?await Promise.all(browser.contexts().flatMap(c=>c.pages()).filter(p=>!p.isClosed()).map(async p=>({path:new URL(p.url()).pathname,...await p.evaluate(()=>({state:document.readyState,textLength:document.body.innerText.length,headings:document.querySelectorAll('h1').length,bodyOpacity:document.querySelector('#body')?getComputedStyle(document.querySelector('#body')).opacity:null})).catch(()=>({unavailable:true}))}))) : [];fs.writeFileSync(path.join(evidence,'navigation-diagnostic.json'),JSON.stringify({pending:[...pendingRequests.values()].map(r=>({path:r.path,type:r.type,ageMs:Date.now()-r.started})),recentResponses,pages},null,2));}
 console.log(JSON.stringify({completedBeforeCleanup:results},null,2));
 if(failed&&browser){const p=browser.contexts().flatMap(c=>c.pages()).filter(p=>!p.isClosed()).at(-1);if(p&&!await p.locator('dd code').count())await p.screenshot({path:path.join(evidence,'failure.png'),fullPage:true}).catch(()=>{});}
 if(browser)await browser.close();
 const extra=sql(`SELECT staff_id FROM staff WHERE username=${quote(prefix+'.wizard')}`);if(extra)wizardId=Number(extra);
 const extraSchool=sql(`SELECT id FROM schools WHERE title=${quote(prefix+' Wizard')}`);if(extraSchool)wizardSchool=Number(extraSchool);
 sql(`DELETE FROM config WHERE school_id=0 AND title=${quote(scopedSetting)}`);
 const ids=[adminId,clientId,wizardId,legacyId].filter(Number.isInteger);if(ids.length)sql(`BEGIN;DELETE FROM schoolsafe_account_state WHERE person_key LIKE ${quote(prefix+'%')};DELETE FROM schoolsafe_account_audit WHERE person_key LIKE ${quote(prefix+'%')};DELETE FROM access_log WHERE user_agent=${quote(prefix)} OR username LIKE ${quote(prefix+'%')};DELETE FROM schoolsafe_native_onboarding WHERE staff_id IN (${ids});DELETE FROM schoolsafe_identity_audit WHERE actor_staff_id IN (${ids}) OR target_staff_id IN (${ids});DELETE FROM schoolsafe_school_leadership WHERE staff_id IN (${ids});DELETE FROM staff WHERE staff_id IN (${ids}) AND username LIKE ${quote(prefix+'%')};COMMIT;`);
 for(const [id,title]of [[school,prefix+' School'],[wizardSchool,prefix+' Wizard']])if(Number.isInteger(id)){
  const tables=['program_user_config','program_config','config','school_marking_periods','school_periods','school_gradelevels','report_card_grades','report_card_grade_scales','report_card_comments','report_card_comment_categories','eligibility_activities','attendance_codes','attendance_code_categories','schoolsafe_school_state'];
  sql('BEGIN;'+`DELETE FROM schoolsafe_opening_requests WHERE school_id=${id};`+
    tables.map(table=>`DELETE FROM ${table} WHERE school_id=${id};`).join('')+
    `DELETE FROM schools WHERE id=${id} AND title=${quote(title)};COMMIT;`);
 }
 assert.equal(sql(`SELECT count(*) FROM staff WHERE username LIKE ${quote(prefix+'%')}`),'0');
 assert.equal(sql(`SELECT count(*) FROM schools WHERE title LIKE ${quote(prefix+'%')}`),'0');
 fs.writeFileSync(path.join(evidence,'native-result.json'),JSON.stringify({status:failed?'FAIL':'PASS',results,cleanup:true,scope:'Isolated fictitious PostgreSQL, native browser creation/first access/reconnection. No production mutation or external send.'},null,2));console.log(JSON.stringify({status:failed?'FAIL':'PASS',groups:results.length,cleanup:true}));
}})().catch(e=>{console.error(sanitize(e.stack));process.exitCode=1;});
