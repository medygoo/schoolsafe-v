'use strict';
// Geometry/style checks only: never collect field values, tokens or confidential pages.
const assert = require('node:assert/strict');
const path = require('node:path');
async function presentation(page, kind, out) {
  for (const width of [1440,390]) {
    await page.setViewportSize({width,height:844});
    await page.evaluate(() => document.fonts.ready);
    assert.ok(await page.evaluate(() => document.documentElement.scrollWidth-document.documentElement.clientWidth)<=1,kind+' overflow '+width);
    if (kind === 'recovery') {
      await page.locator('.ss-recovery-brand img[src="/schoolsafe-logo.png"]').waitFor({state:'visible',timeout:3000});
      assert.ok(await page.getByRole('link',{name:'SchoolSafe — connexion'}).isVisible());
      const controls = await page.locator('form input:not([type=hidden]),form button').evaluateAll(es=>es.filter(e=>e.getClientRects().length).map(e=>({height:e.getBoundingClientRect().height,font:parseFloat(getComputedStyle(e).fontSize)})));
      assert.ok(controls.length>=5,'Recovery native forms retained');
      assert.ok(controls.every(c=>c.height>=44 && c.font>=16),'Recovery readable touch controls '+width);
      assert.equal(await page.locator('form[action="/PasswordReset.php"]').count(),1);
      assert.equal(await page.locator('form[action="/compte/recuperation"] input[name=l4_action][value=otp_demande]').count(),1);
    }
    if (kind === 'central' && width===390) {
      assert.ok(await page.locator('#sskc-schools-title').evaluate(e=>e.getBoundingClientRect().bottom)<844,'School directory heading in the first mobile viewport');
    }
    if (kind === 'wizard' && width===390) {
      assert.ok(await page.locator('.sskc-workarea input:not([type=hidden])').first().evaluate(e=>e.getBoundingClientRect().bottom)<844,'First wizard input in the first mobile viewport');
      assert.equal(await page.locator('.sskc-progress li').count(),6,'All steps remain available');
    }
    if (kind === 'settings') {
      const controls=await page.locator('#body input:not([type=hidden]):not([type=checkbox]):not([type=radio]),#body select,#body textarea').evaluateAll(es=>es.filter(e=>e.getClientRects().length).map(e=>({height:e.getBoundingClientRect().height,font:parseFloat(getComputedStyle(e).fontSize)})));
      assert.ok(controls.length>0,'Configuration controls present');
      assert.ok(controls.every(c=>c.height>=44 && c.font>=14),'Configuration touch controls '+width);
      const editTargets=await page.locator('#body .onclick').evaluateAll(es=>es.filter(e=>e.getClientRects().length).map(e=>e.getBoundingClientRect().height));
      assert.ok(editTargets.length>0 && editTargets.every(h=>h>=44),'Native click-to-edit targets remain accessible '+width);
    }
    if (kind === 'school') {
      const headerControls=await page.locator('#footerwrap .ss-photo-mobile-brand,#footerwrap #BottomButtonMenu,#footerwrap .ss-central-dashboard-return a,#footerwrap .ss-fixed-account>summary').evaluateAll(es=>es.map(e=>({x:e.getBoundingClientRect().x,right:e.getBoundingClientRect().right,bottom:e.getBoundingClientRect().bottom,height:e.getBoundingClientRect().height})));
      assert.ok(headerControls.length>=3,'Mounted school header controls');
      assert.ok(headerControls.every(c=>c.x>=0 && c.right<=width && c.height>=40),'All school header controls fit, not just hidden document overflow');
      const headerBottom=await page.locator('#footerwrap').evaluate(e=>e.getBoundingClientRect().bottom);
      const bodyTop=await page.locator('#body').evaluate(e=>e.getBoundingClientRect().top);
      assert.ok(headerControls.every(c=>c.bottom<=headerBottom+1),'Header controls remain inside their shell');
      assert.ok(bodyTop>=headerBottom-1,'Content does not hide under school header');
      const brand=page.locator('.ss-fixed-header-brand .ss-photo-mobile-brand strong');
      assert.ok(await brand.isVisible(),'SchoolSafe wordmark retained on school mobile header');
      const rect=await brand.boundingBox();assert.ok(rect.x>=0 && rect.x+rect.width<=width,'School brand fits viewport');
    }
    await page.screenshot({path:path.join(out,'presentation-'+kind+'-'+width+'.png'),fullPage:true});
  }
}
module.exports={presentation};
