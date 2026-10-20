/* SchoolSafe L3.1 (jetable) : /ecoles/1/profils + nouveau — mobile 390 + desktop 1280. */
const assert = require('node:assert/strict');
const { execFileSync } = require('node:child_process');
const { chromium } = require('playwright');
const BASE = 'http://127.0.0.1:8084';
const PGPASSWORD = 'preview-local-jetable-32caracteres-xx';
function sql(s) {
  return execFileSync('docker', ['exec', '-e', 'PGPASSWORD=' + PGPASSWORD, 'schoolsafe-app-db-1',
    'psql', '-U', 'schoolsafe', '-d', 'schoolsafe_l3_recipe', '-At', '-c', s], { encoding: 'utf8' }).trim();
}
let passed = 0, failed = 0;
async function httpLogin() {
  const jar = [];
  const post = async (path, form) => {
    const r = await fetch(BASE + path, { redirect: 'manual',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', ...(jar.length ? { Cookie: jar.join('; ') } : {}) },
      ...(form ? { method: 'POST', body: new URLSearchParams(form).toString() } : {}) });
    for (const raw of (r.headers.getSetCookie ? r.headers.getSetCookie() : [])) {
      jar.push(raw.split(';')[0].trim());
    }
    return r;
  };
  await post('/index.php');
  await post('/index.php', { USERNAME: 'l3admin', PASSWORD: process.env.L31_VIEW_PW });
  return jar.map((c) => {
    const i = c.indexOf('=');
    return { name: c.slice(0, i).trim(), value: c.slice(i + 1).trim(), domain: '127.0.0.1', path: '/' };
  });
}
async function viewport(name, width, shot, cookies) {
  const browser = await chromium.launch();
  try {
    const ctx = await browser.newContext({ viewport: { width, height: 800 } });
    await ctx.addCookies(cookies);
    const page = await ctx.newPage();
    await page.goto(BASE + '/ecoles/1/profils', { waitUntil: 'domcontentloaded' });
    assert.ok(await page.getByRole('heading', { name: /Profils de l’école|Matrice appliquée/ }).first().isVisible(), 'titre profils visible');
    const createForm = page.locator('form', { has: page.locator('input[name="titre"]') });
    assert.ok(await createForm.locator('input[name="titre"]').isVisible(), 'champ titre visible');
    await createForm.locator('input[name="titre"]').fill('L31 Viewport ' + width);
    await createForm.locator('select[name="base"]').selectOption('teacher');
    const boxes = createForm.locator('input[name^="droit["]');
    const n = await boxes.count();
    assert.ok(n >= 1, 'droits proposes, got ' + n);
    await boxes.first().check();
    await page.getByRole('button', { name: /Créer le profil/ }).click();
    await page.locator('[role="alert"], [role="status"], p.alert').first().waitFor({ timeout: 30000 });
    const msg = await page.locator('[role="alert"], [role="status"], p.alert').first().innerText();
    assert.ok(/créé pour l’école|hors de vos pouvoirs|réservé/.test(msg), 'retour creation affiche, got ' + msg.slice(0, 80));
    await page.goto(BASE + '/ecoles/1/utilisateurs/nouveau', { waitUntil: 'domcontentloaded' });
    assert.ok(await page.getByRole('heading', { name: /Nouvel utilisateur/ }).first().isVisible(), 'titre nouveau visible');
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    assert.ok(overflow <= 0, 'zero debordement (got ' + overflow + 'px)');
    await page.screenshot({ path: shot });
    ++passed;
    console.log(`PASS ${name} ${width}px — creation operee, zero debordement, capture ${shot}`);
  } catch (error) {
    ++failed;
    console.log(`FAIL ${name} ${width}px: ` + String(error.message).split('\n')[0]);
  } finally {
    await browser.close();
  }
}
(async () => {
  const PW = 'View' + Date.now() + '!';
  const h = execFileSync('docker', ['exec', 'schoolsafe-l3-recipe', 'php', '-r',
    'echo crypt($argv[1], "$6$".substr(strtr(base64_encode(random_bytes(12)),"+","."),0,16)."$");',
    PW], { encoding: 'utf8' }).trim().replace(/'/g, '');
  sql(`UPDATE staff SET PASSWORD='${h}' WHERE USERNAME='l3admin' AND SYEAR=2026;`);
  process.env.L31_VIEW_PW = PW;
  const cookies = await httpLogin();
  assert.ok(cookies.length > 0, 'session http');
  await viewport('ordinateur', 1280, 'app/qa-output/l31-profils-desktop.png', cookies);
  await viewport('mobile', 390, 'app/qa-output/l31-profils-mobile.png', cookies);
  sql(`DELETE FROM profile_exceptions WHERE PROFILE_ID IN (SELECT ID FROM user_profiles WHERE TITLE LIKE 'L31 Viewport%');`);
  sql(`DELETE FROM user_profiles WHERE TITLE LIKE 'L31 Viewport%';`);
  console.log(`\nL3.1 viewports : ${passed} PASS, ${failed} FAIL`);
  process.exit(failed ? 1 : 0);
})().catch((e) => { console.error('FATAL', e); process.exit(2); });
