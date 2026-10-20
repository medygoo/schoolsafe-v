/*
 * SchoolSafe — L2 : rendu /demarrer mobile + ordinateur (vrai navigateur).
 *
 * - 390 px (mobile) et 1280 px (ordinateur) : page servie, les deux
 *   formulaires visibles, remplissage + envoi d'un code errone, message de
 *   refus affiche, zero debordement horizontal.
 * - Captures non sensibles : app/qa-output/l2r-mobile.png, l2r-desktop.png.
 *
 * Usage : node app/qa-l2-ouverture-viewports.cjs
 * Variable : SCHOOLSAFE_L2_URL (defaut http://127.0.0.1:8083).
 */
const assert = require('node:assert/strict');
const { chromium } = require('playwright');

const BASE = process.env.SCHOOLSAFE_L2_URL || 'http://127.0.0.1:8083';

let passed = 0, failed = 0;

async function viewport(name, width, shot) {
  const browser = await chromium.launch();
  try {
    const page = await browser.newPage({ viewport: { width, height: 800 } });
    await page.goto(BASE + '/demarrer', { waitUntil: 'domcontentloaded' });
    assert.equal(await page.title(), 'Ouvrir une école | SchoolSafe', 'titre');
    for (const label of ['J’ai un code d’autorisation', 'Déposer une demande sans code']) {
      assert.ok(await page.getByRole('heading', { name: label }).isVisible(), label + ' visible');
    }
    await page.getByPlaceholder('XXXXXX-XXXXXX-XXXXXX-XXXXXX').fill('AAAAAA-BBBBBB-CCCCCC-DDDDDD');
    await page.locator('section.card').first().locator('input[name="request_id"]').fill('999999');
    await page.locator('section.card').first().locator('input[name="email"]').fill('viewport@example.invalid');
    await page.locator('section.card').first().getByRole('button', { name: 'Valider mon autorisation' }).click();
    await page.getByRole('alert').waitFor({ timeout: 15000 });
    const alert = await page.getByRole('alert').innerText();
    assert.ok(/Code non reconnu|introuvable|indisponible/.test(alert), 'refus affiche, got ' + alert.slice(0, 60));
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    assert.ok(overflow <= 0, 'zero debordement (got ' + overflow + 'px)');
    await page.screenshot({ path: shot });
    ++passed;
    console.log(`PASS ${name} ${width}px — refus affiche, zero debordement, capture ${shot}`);
  } catch (error) {
    ++failed;
    console.log(`FAIL ${name} ${width}px: ` + String(error.message).split('\n')[0]);
  } finally {
    await browser.close();
  }
}

(async () => {
  await viewport('mobile', 390, 'app/qa-output/l2r-mobile.png');
  await viewport('ordinateur', 1280, 'app/qa-output/l2r-desktop.png');
  console.log(`\nL2 viewports : ${passed} PASS, ${failed} FAIL`);
  process.exit(failed ? 1 : 0);
})().catch((e) => { console.error('FATAL', e); process.exit(2); });
