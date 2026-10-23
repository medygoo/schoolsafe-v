'use strict';
// Called only by the nonce/label-guarded native fixture harness after synthetic activation.
// Reads existing authorized screens; no form submission, account reset or external send.
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path');
module.exports=async function qualifySchoolDashboard(p,base,evidence,key){
 const results=[],reports=[];let canonical;
 const portal=base+'/Modules.php?modname=misc/Portal.php';
 const view=p.getByLabel('Vue du tableau de bord',{exact:true});
 const healthy=async()=>assert(!/DB Execute Failed|WE HAVE A PROBLEM, PLEASE CONTACT|Fatal error:|Uncaught (?:Error|Exception)/.test(await p.locator('body').innerText()),'Real application must render without a PHP/SQL error');
 for(const width of [1280,390,768]){
  await p.setViewportSize({width,height:1000});await p.goto(portal,{waitUntil:'domcontentloaded'});
  await p.waitForFunction(()=>window.schoolSafeNavigation&&document.querySelector('.ss-fixed-pager'));
  await healthy();assert.equal(await p.locator('.ss-fixed-pager option').count(),13,'Keep all thirteen existing views, without dropping data');
  const labels=await p.locator('.ss-fixed-pager option').allTextContents();
  for(const label of labels){await view.selectOption({label});const active=p.locator('.ss-fixed-view:not([hidden])');assert.equal(await active.count(),1);assert((await active.innerText()).trim(),label+' must not be an empty or hidden panel');await healthy();}
  assert(await p.locator('.ss-dashboard-details').isVisible());
  const links=await p.locator('#menu .wp-submenu a').evaluateAll(a=>a.map(x=>x.getAttribute('href')).sort());
  if(!canonical)canonical=links;else assert.deepEqual(links,canonical,'Same account, same authorized native menu across formats');
  await view.selectOption({label:'Modules 1'});await p.locator('[data-open-native-branch=school]').click();
  assert(await p.locator('#menu [data-ss-branch=school]>.wp-submenu').isVisible());
  await p.keyboard.press('Escape');assert.equal(await p.locator('#body').evaluate(e=>e.inert),false);
  await view.selectOption({label:'Modules 2'});await p.reload({waitUntil:'domcontentloaded'});await p.waitForSelector('.ss-fixed-pager');
  assert.equal(await view.evaluate(e=>e.selectedOptions[0].textContent),'Modules 2');
  await view.selectOption({label:'Dossiers détaillés'});await p.goBack();
  assert.equal(await view.evaluate(e=>e.selectedOptions[0].textContent),'Modules 2');
  await p.goForward();assert.equal(await view.evaluate(e=>e.selectedOptions[0].textContent),'Dossiers détaillés');
  await view.selectOption({label:'Accueil'});
  assert.equal(await p.locator('.ss-fixed-account').count(),0,'Do not replace the original account header');
  if(width>700)assert(await p.locator('#menu').isVisible());
  assert(await p.evaluate(()=>document.documentElement.scrollWidth-document.documentElement.clientWidth)<=1,'No global horizontal overflow');
  await p.screenshot({path:path.join(evidence,`school-navigation-${key}-${width}.png`),fullPage:true});
  results.push({width,views:labels,menuDestinations:links.length,navigation:'PASS'});
 }
 // Only read existing dashboard destinations; exclude mutating query parameters entirely.
 const allowed=new Set(['Students/Student.php','Users/User.php','Scheduling/Courses.php','Student_Billing/DailyTransactions.php','Accounting/DailyTransactions.php','School_Setup/PortalNotes.php','Grades/GPARankList.php','Attendance/DailySummary.php','School_Setup/Configuration.php','misc/Portal.php']);
 const targets=await p.locator('#ss-photo-dashboard a[href]').evaluateAll(a=>a.map(x=>x.href));
 for(const href of [...new Set(targets)]){
  const u=new URL(href);if(u.origin!==new URL(base).origin||u.pathname!=='/Modules.php'||!allowed.has(u.searchParams.get('modname')))continue;
  if([...u.searchParams.keys()].some(k=>['modfunc','action','delete_ok','token','student_id','staff_id'].includes(k)))continue;
  const response=await p.context().request.get(u.href);
  assert.equal(response.status(),200,'Authorized dashboard destination '+u.searchParams.get('modname'));
  assert(!/DB Execute Failed|Fatal error:|Uncaught (?:Error|Exception)/.test(await response.text()),'Readable destination, not a PHP/SQL error');
  reports.push({program:u.searchParams.get('modname'),status:response.status()});
 }
 assert(reports.length>=8,'Exercise actual dashboard module/report links, not only the pager');
 await p.goto(portal,{waitUntil:'domcontentloaded'});await p.waitForSelector('.ss-fixed-pager');
 fs.writeFileSync(path.join(evidence,`school-navigation-${key}.json`),JSON.stringify({status:'PASS',scope:'Authenticated synthetic HTTP/PostgreSQL; GET destinations only, no business submission',results,destinations:reports},null,2));
};
