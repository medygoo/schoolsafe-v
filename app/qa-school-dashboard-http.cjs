'use strict';
// Only the nonce/label-guarded native fixture harness may call this after synthetic activation.
// Authorized GET screens only: no business submissions, real users or external messages.
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path');
module.exports=async function qualifySchoolDashboard(p,base,evidence,key){
 const results=[],reports=[];let canonical;
 const portal=base+'/Modules.php?modname=misc/Portal.php';
 const ready=()=>p.waitForFunction(()=>window.schoolSafeNavigation&&document.querySelector('#ss-photo-dashboard')?.dataset.photoReady);
 const healthy=async()=>assert(!/DB Execute Failed|WE HAVE A PROBLEM, PLEASE CONTACT|Fatal error:|Uncaught (?:Error|Exception)/.test(await p.locator('body').innerText()),'Real application must render without a PHP/SQL error');
 for(const width of [1280,390,768]){
  await p.setViewportSize({width,height:1000});await p.goto(portal,{waitUntil:'domcontentloaded'});await ready();
  await p.waitForSelector('#menu [data-ss-organized="1"]',{state:'attached'});
  await healthy();assert.equal(await p.locator('.ss-fixed-pager,.ss-fixed-park,.ss-fixed-view').count(),0,'No slideshow or parked content');
  const sections=['.ss-photo-hero','.ss-photo-metrics','.ss-photo-modules','.ss-photo-charts','.ss-photo-alerts','.ss-dashboard-details'];
  for(const selector of sections)assert(await p.locator(selector).isVisible(),selector+' must remain available');
  assert.equal(await p.locator('.ss-photo-chart').count(),3);
  assert(await p.locator('#ss-photo-birthdays').isVisible(),'Birthdays remain above modules');
  if(width<=700){
   assert.equal(await p.locator('.ss-photo-metrics .ss-photo-metric:visible').count(),4);
   await p.waitForSelector('#ss-photo-actions-button');
   assert.equal(await p.locator('#footerwrap .ss-header-tools > :is(a,button):visible').count(),2);
  }
  const links=await p.locator('#menu .wp-submenu a').evaluateAll(a=>a.map(x=>x.getAttribute('href')).sort());
  if(!canonical)canonical=links;else assert.deepEqual(links,canonical,'Same authorized native menu across formats');
  await p.locator('[data-open-native-branch=school]').click();
  assert(await p.locator('#menu [data-ss-branch=school]>.wp-submenu').isVisible());
  await p.keyboard.press('Escape');assert.equal(await p.locator('#body').evaluate(e=>e.inert),false);
  await p.goto(portal+'&dashboard_view=modules-2',{waitUntil:'domcontentloaded'});await ready();
  assert(await p.locator('.ss-dashboard-details').isVisible(),'Legacy URLs never hide details');
  await p.reload({waitUntil:'domcontentloaded'});await ready();await healthy();
  assert.equal(await p.locator('.ss-fixed-account').count(),0,'Keep the original account controls');
  assert(await p.evaluate(()=>document.documentElement.scrollWidth-document.documentElement.clientWidth)<=1,'No global horizontal overflow');
  await p.evaluate(()=>scrollTo(0,0));
  await p.screenshot({path:path.join(evidence,`school-navigation-${key}-${width}.png`),fullPage:true});
  results.push({width,sections,menuDestinations:links.length,navigation:'PASS'});
 }
 const allowed=new Set(['Students/Student.php','Users/User.php','Scheduling/Courses.php','Student_Billing/DailyTransactions.php','Accounting/DailyTransactions.php','School_Setup/PortalNotes.php','Grades/GPARankList.php','Attendance/DailySummary.php','School_Setup/Configuration.php','misc/Portal.php']);
 // Mobile actions reuse original nodes in the header; include their real authorized hrefs too.
 const targets=await p.locator('#ss-photo-dashboard a[href],#ss-photo-actions-panel a[href]').evaluateAll(a=>a.map(x=>x.href));
 for(const href of [...new Set(targets)]){
  const u=new URL(href);if(u.origin!==new URL(base).origin||u.pathname!=='/Modules.php'||!allowed.has(u.searchParams.get('modname')))continue;
  if([...u.searchParams.keys()].some(k=>['modfunc','action','delete_ok','token','student_id','staff_id'].includes(k)))continue;
  const response=await p.context().request.get(u.href);
  const text=await response.text(),error=/DB Execute Failed|Fatal error:|Uncaught (?:Error|Exception)/.exec(text);
  reports.push({program:u.searchParams.get('modname'),status:response.status(),error:error?.[0]||null});
  fs.writeFileSync(path.join(evidence,`school-destinations-${key}.json`),JSON.stringify(reports,null,2));
  if(error)fs.writeFileSync(path.join(evidence,`school-destination-error-${key}.private.html`),text);
  assert.equal(response.status(),200,'Authorized destination '+u.searchParams.get('modname'));assert(!error,'Readable destination without PHP/SQL error');
 }
 assert(reports.length>=8,'Exercise actual module/report links, not just the layout');
 await p.goto(portal,{waitUntil:'domcontentloaded'});await ready();
 fs.writeFileSync(path.join(evidence,`school-navigation-${key}.json`),JSON.stringify({status:'PASS',scope:'Authenticated synthetic HTTP/PostgreSQL; GET destinations only, no business submission',results,destinations:reports},null,2));
};
