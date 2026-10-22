'use strict';
const assert=require('node:assert/strict'),crypto=require('node:crypto'),fs=require('node:fs'),path=require('node:path'),{execFileSync}=require('node:child_process');
const {chromium}=require('playwright');
const out=process.env.PRODELI_SCALE_EVIDENCE;assert.ok(out);fs.mkdirSync(out,{recursive:true});
const base=process.env.PRODELI_QA_URL||'http://127.0.0.1:8096',web=process.env.PRODELI_QA_WEB||'schoolsafe-prodeli-presentation-qa',db=process.env.PRODELI_QA_DB||'schoolcef-harmonization-pg';
assert.equal(new URL(base).hostname,'127.0.0.1');
const shell=s=>"'"+s.replaceAll("'","'\\''")+"'";
const docker=(args,opts={})=>process.env.PRODELI_QA_SSH?execFileSync('ssh',['-o','BatchMode=yes',process.env.PRODELI_QA_SSH,'docker '+args.map(shell).join(' ')],{encoding:'utf8',timeout:45000,...opts}):execFileSync('docker',args,{encoding:'utf8',timeout:45000,...opts});
for(const n of [web,db])assert.equal(docker(['inspect','--format','{{index .Config.Labels "schoolcef.qualification"}}',n]).trim(),process.env.PRODELI_QA_LABEL||'harmonization-20261021');
assert.equal(docker(['network','inspect',process.env.PRODELI_QA_NETWORK||'schoolcef-harmonization-local','--format','{{.Internal}}']).trim(),'true');
const sql=q=>{const value=docker(['exec','-i',db,'psql','-X','-v','ON_ERROR_STOP=1','-U','schoolcef_fixture','-d','schoolcef_fixture','-qAt'],{input:q}).trim();return /^SELECT reporting_gp_scale FROM/.test(q)?String(Number(value)):value;},quote=s=>"'"+String(s).replaceAll("'","''")+"'";
const prefix='qa.scale.'+crypto.randomBytes(6).toString('hex'),secret=crypto.randomBytes(24).toString('hex'),ids=[],results=[];
let browser,school,original,other,failed=false;
(async()=>{try{
 school=Number(sql('SELECT id FROM schools WHERE syear=2026 ORDER BY id LIMIT 1'));assert.ok(school);
 original=sql(`SELECT reporting_gp_scale FROM schools WHERE id=${school} AND syear=2026`);assert.match(original,/^\d+(?:\.\d+)?$/);
 other=Number(sql(`INSERT INTO schools(syear,title,reporting_gp_scale) VALUES(2026,${quote(prefix+' other')},100) RETURNING id`));
 const hash=docker(['exec','-i',web,'php','-r',"require '/var/www/html/functions/Password.php';echo encrypt_password(stream_get_contents(STDIN));"],{input:secret});
 for(const [suffix,use,edit] of [['writer',true,true],['reader',true,false],['denied',false,false]]){
  const id=Number(sql(`INSERT INTO staff(syear,first_name,last_name,username,password,profile,profile_id,schools,current_school_id,last_login) VALUES(2026,'Scale','Fixture',${quote(prefix+'.'+suffix)},${quote(hash)},'admin',NULL,${quote(','+school+',')},${school},CURRENT_TIMESTAMP) RETURNING staff_id`));ids.push(id);
  if(use)sql(`INSERT INTO staff_exceptions(user_id,modname,can_use,can_edit) VALUES(${id},'Grades/ReportCardGrades.php','Y',${quote(edit?'Y':'N')})`);
 }
 browser=await chromium.launch({executablePath:process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE||'C:/Users/PC/AppData/Local/ms-playwright/chromium_headless_shell-1234/chrome-headless-shell-win64/chrome-headless-shell.exe'});
 const login=async suffix=>{const c=await browser.newContext({extraHTTPHeaders:{'User-Agent':prefix,'Connection':'close'}});await c.route('**/*',r=>r.request().url().startsWith(base+'/')?r.continue():r.abort());const p=await c.newPage();await p.goto(base+'/index.php');if(await p.locator('#enterSplash').isVisible())await p.locator('#enterSplash').click();await p.locator('#USERNAME').fill(prefix+'.'+suffix);await p.locator('#PASSWORD').fill(secret);await Promise.all([p.waitForURL(u=>u.pathname!='/index.php'),p.locator('#loginform button[type=submit]').click()]);await p.goto(base+'/Modules.php?modname=Grades/ReportCardGrades.php');return {c,p};};
 const {c,p}=await login('writer');await p.locator('#ss-school-scale input[name=school_scale]').waitFor();
 assert.ok(!/DB Execute Failed|Fatal error:/.test(await p.locator('body').innerText()));
 for(const width of [1440,390]){await p.setViewportSize({width,height:900});assert.ok(await p.evaluate(()=>document.documentElement.scrollWidth-document.documentElement.clientWidth)<=1);await p.screenshot({path:path.join(out,'scale-'+width+'.png'),fullPage:true});}
 results.push('delegated grading permission opens the school setting desktop/mobile without general school-edit access');
 await p.locator('#ss-school-scale input[name=school_scale]').fill('20');await p.locator('[name=school_scale_confirm]').check();await p.getByRole('button',{name:'Enregistrer la base de notation',exact:true}).click();await p.getByRole('status').filter({hasText:'La base de notation de cette école a été enregistrée.'}).waitFor();assert.equal(sql(`SELECT reporting_gp_scale FROM schools WHERE id=${school} AND syear=2026`),'20');await p.reload();assert.equal(Number(await p.locator('[name=school_scale]').inputValue()),20);results.push('actual form saves the base and reload reads the persisted value');
 const token=await p.locator('#ss-school-scale [name=token]').inputValue();
 for(const fields of [{token:'wrong',school_scale:'10',school_scale_confirm:'1'},{token,school_scale:'0',school_scale_confirm:'1'},{token,school_scale:'10'}]){
  await c.request.post(base+'/Modules.php?modname=Grades/ReportCardGrades.php',{form:{school_scale_action:'save',...fields}});assert.equal(sql(`SELECT reporting_gp_scale FROM schools WHERE id=${school} AND syear=2026`),'20');
 }
 results.push('CSRF invalid base and missing confirmation cannot change the school');
 await c.request.post(base+'/Modules.php?modname=Grades/ReportCardGrades.php',{form:{token,school_scale_action:'save',school_scale:'10',school_scale_confirm:'1',school_id:String(other),syear:'2025'}});
 assert.equal(sql(`SELECT reporting_gp_scale FROM schools WHERE id=${school} AND syear=2026`),'10');assert.equal(sql(`SELECT reporting_gp_scale FROM schools WHERE id=${other} AND syear=2026`),'100');results.push('forged school and year never change the target outside the authorized session context');
 for(const suffix of ['reader','denied']){const {c:rc,p:rp}=await login(suffix);assert.equal(await rp.locator('#ss-school-scale button[type=submit]').count(),0);if(suffix==='reader')assert.match(await rp.locator('#ss-school-scale').innerText(),/Consultation uniquement/);const csrf=await rp.locator('meta[name=token]').getAttribute('content');await rc.request.post(base+'/Modules.php?modname=Grades/ReportCardGrades.php',{form:{token:csrf,school_scale_action:'save',school_scale:'30',school_scale_confirm:'1'}});assert.equal(sql(`SELECT reporting_gp_scale FROM schools WHERE id=${school} AND syear=2026`),'10');await rc.close();}
 results.push('read-only and unauthorized staff cannot modify through forged POST');
}catch(e){failed=true;throw e;}finally{
 if(browser)await browser.close();
 if(school&&original)sql(`UPDATE schools SET reporting_gp_scale=${original} WHERE id=${school} AND syear=2026`);
 if(ids.length)sql(`BEGIN;DELETE FROM staff_exceptions WHERE user_id IN (${ids});DELETE FROM access_log WHERE user_agent=${quote(prefix)} OR username LIKE ${quote(prefix+'%')};DELETE FROM staff WHERE staff_id IN (${ids}) AND username LIKE ${quote(prefix+'%')};COMMIT;`);
 if(other)sql(`DELETE FROM schools WHERE id=${other} AND title=${quote(prefix+' other')}`);
 assert.equal(sql(`SELECT count(*) FROM staff WHERE username LIKE ${quote(prefix+'%')}`),'0');
 const result={status:failed?'FAIL':'PASS',results,cleanup:true,scope:'Fictitious PostgreSQL; existing native grading permissions, no production rights changed'};fs.writeFileSync(path.join(out,'scale-result.json'),JSON.stringify(result,null,2));console.log(JSON.stringify({status:result.status,groups:results.length,cleanup:true}));
}})().catch(e=>{console.error(String(e).replaceAll(secret,'[secret]'));process.exitCode=1;});
