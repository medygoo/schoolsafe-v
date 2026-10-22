'use strict';
// Synthetic contexts only. Never write an unredacted handoff screenshot/PDF.
const assert=require('node:assert/strict'),path=require('node:path');
async function fit(p,key){
  assert.ok(await p.evaluate(()=>document.documentElement.scrollWidth-document.documentElement.clientWidth)<=1,key+' overflow');
}
async function loginPresentation(p,out){
  await p.goto(new URL('/index.php',p.url()).href);
  for(const width of [1440,390]){
    await p.setViewportSize({width,height:844});
    if(!await p.locator('#splash').isVisible())await p.locator('#backToSplash').click();
    await fit(p,'welcome '+width);await p.screenshot({path:path.join(out,'access-welcome-'+width+'.png'),fullPage:true});
    await p.locator('#enterSplash').click();
    await p.locator('#loginform').waitFor({state:'visible'});await fit(p,'login '+width);
    const heading=await p.locator('.auth-message h1').boundingBox();
    assert.ok(heading.width>=250 && heading.height<150,'Login heading is readable, not a narrow vertical column');
    if(width===1440)assert.ok((await p.locator('#ss-blue').boundingBox()).width>=900,'Desktop access shell not constrained by legacy 420px wrapper');
    const heights=await p.locator('#USERNAME,#PASSWORD,#ssBlueEye,#loginform button[type=submit]').evaluateAll(es=>es.map(e=>e.getBoundingClientRect().height));
    assert.ok(heights.every(h=>h>=44),'Login touch controls');
    assert.ok(await p.locator('#loginform button[type=submit]').evaluate(e=>e.getBoundingClientRect().bottom)<844,'Login submit visible on small laptop/mobile');
    await p.locator('#PASSWORD').fill('Synthetic-eye-check');await p.locator('#ssBlueEye').click();
    assert.equal(await p.locator('#PASSWORD').getAttribute('type'),'text');await p.locator('#ssBlueEye').click();
    assert.equal(await p.locator('#PASSWORD').getAttribute('type'),'password');await p.locator('#PASSWORD').fill('');
    await p.screenshot({path:path.join(out,'access-login-'+width+'.png'),fullPage:true});
  }
}
async function firstAccessPresentation(p,out){
  for(const width of [1440,390]){
    await p.setViewportSize({width,height:844});await fit(p,'first access '+width);
    assert.ok(await p.locator('.ss-access-brand').isVisible());
    assert.equal(await p.locator('.sskc-manager-navigation:visible,#ss-mobile-navigation:visible').count(),0,'No business navigation before first access is complete');
    const inputBox=await p.locator('input[name="first_login[PASSWORD]"]').boundingBox();
    const cardBox=await p.locator('.ss-access-card').boundingBox();
    assert.ok(inputBox.width>=cardBox.width-70,'First-access password field uses available card width');
    const controls=await p.locator('#first-login-form input:not([type=hidden]),#first-login-form button').evaluateAll(es=>es.filter(e=>e.getClientRects().length).map(e=>e.getBoundingClientRect().height));
    assert.ok(controls.length>=2&&controls.every(h=>h>=44),'First access controls readable');
    await p.screenshot({path:path.join(out,'access-first-login-'+width+'.png'),fullPage:true});
  }
}
async function handoffPresentation(p,response,out){
  const csp=response.headers()['content-security-policy'];
  assert.match(csp,/default-src 'none'/);assert.match(csp,/img-src 'self'/);assert.doesNotMatch(csp,/unsafe-inline|unsafe-eval|script-src/);
  assert.match(response.headers()['cache-control'],/no-store/);
  assert.equal(await p.locator('script').count(),0,'Confidential page has no scripts');
  assert.equal(await p.locator('link[rel=stylesheet]').count(),1,'One isolated stylesheet');
  assert.ok(await p.locator('.ss-access-brand img').evaluate(e=>e.complete&&e.naturalWidth>0),'Local logo allowed by CSP');
  for(const width of [1440,390]){
    await p.setViewportSize({width,height:844});await fit(p,'handoff '+width);
    assert.ok(await p.locator('a[download]').isVisible());
    await p.screenshot({path:path.join(out,'access-handoff-redacted-'+width+'.png'),fullPage:true,mask:[p.locator('dd code')]});
  }
  const [download]=await Promise.all([p.waitForEvent('download',{timeout:5000}),p.locator('a[download]').click()]);
  assert.match(download.suggestedFilename(),/^SchoolSafe-acces-[0-9]+\.txt$/);
  const stream=await download.createReadStream();assert.ok(stream,'Download stream is readable');
  const chunks=[];for await(const chunk of stream)chunks.push(chunk);
  const document=Buffer.concat(chunks).toString('utf8');
  assert.ok(document.includes('https://app.shoolsafe.tech/index.php'),'Downloaded file has official native destination');
  assert.ok(document.includes(await p.locator('dd code').innerText()),'Downloaded file contains the actual synthetic temporary password');
  await download.delete();
  // The synthetic secret was already held in memory by the native journey.
  // Redact DOM and download payload before emitting any print artifact.
  await p.locator('dd code').evaluate(e=>{e.textContent='[SECRET FICTIF MASQUÉ]';});
  await p.locator('a[download]').evaluate(e=>e.removeAttribute('href'));
  await p.emulateMedia({media:'print'});
  assert.equal(await p.locator('a[download]').isVisible(),false,'Download action omitted from print');
  assert.ok(await p.locator('.ss-access-brand').isVisible(),'Brand retained in print');
  await p.pdf({path:path.join(out,'access-handoff-redacted.pdf'),format:'A4',printBackground:true});
  await p.screenshot({path:path.join(out,'access-handoff-print-redacted.png'),fullPage:true});
  await p.emulateMedia({media:'screen'});
}
module.exports={loginPresentation,firstAccessPresentation,handoffPresentation};
