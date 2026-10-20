'use strict';
// Dedicated disposable stack only. Never accepts a target URL or database argument.
const { execFileSync } = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');
const crypto = require('node:crypto');
const assert = require('node:assert/strict');
const dependencyRoot = 'C:/Users/PC/SchoolSafeCodex/SCHOOLSAFE_CODEX_LOCAL_SNAPSHOT_V1/workspace/node_modules';
const { request, chromium, expect } = require(path.join(dependencyRoot, '@playwright/test'));
const ROOT = 'C:/Users/PC/SchoolSafeLot1A-20261020';
const BASE = 'http://127.0.0.1:8091';
const DB = 'schoolsafe-lot1a-pg';
const WEB = 'schoolsafe-lot1a-web';
const phase = process.argv[2];
if (!['before', 'after'].includes(phase)) throw new Error('Expected before|after');
const sql = text => execFileSync('docker', ['exec', '-i', DB, 'psql', '-X', '-U', 'lot1a', '-d', 'lot1a', '-v', 'ON_ERROR_STOP=1', '-At'], { input: text, encoding: 'utf8', timeout: 20000 }).trim();
const report = { phase, cases: [], sourceSha256: crypto.createHash('sha256').update(fs.readFileSync(path.join(__dirname, '../app/modules/codex-ui-v1/extensions-test-runtime/ecole-profils.php'))).digest('hex') };
const contexts = [];
let browser;
const tokenOf = body => (body.match(/name="token" value="([^"]+)"/) || body.match(/<meta name="token" content="([^"]+)"/) || [])[1];
function snapshot() {
  return sql("SELECT json_build_object('profiles',(SELECT json_agg(x ORDER BY id) FROM user_profiles x),'rights',(SELECT json_agg(x ORDER BY profile_id,modname) FROM profile_exceptions x),'exceptions',(SELECT json_agg(x ORDER BY user_id,modname) FROM staff_exceptions x))::text;");
}
function fixtures() {
  sql("DELETE FROM profile_exceptions WHERE profile_id IN (SELECT id FROM user_profiles WHERE title LIKE 'LOT1A %'); DELETE FROM user_profiles WHERE title LIKE 'LOT1A %'; INSERT INTO user_profiles(id,profile,title,school_id) VALUES(9001,'teacher','LOT1A existing A',1),(9002,'teacher','LOT1A existing B',2); INSERT INTO profile_exceptions(profile_id,modname,can_use,can_edit) VALUES(9001,'Students/Student.php','Y',NULL),(9002,'Students/Student.php','Y',NULL);");
}
async function check(name, operation) {
  try { await operation(); report.cases.push({ name, status: 'PASS' }); console.log('PASS ' + name); }
  catch (e) { report.cases.push({ name, status: 'FAIL', error: e.message }); console.log('FAIL ' + name + ': ' + e.message.slice(0, 220)); }
}
async function main() {
  const net = JSON.parse(execFileSync('docker', ['network', 'inspect', 'schoolsafe-lot1a-net'], { encoding: 'utf8' }))[0];
  assert.equal(net.Internal, true);
  assert.equal(sql('SELECT current_database()'), 'lot1a');
  const password = crypto.randomBytes(24).toString('base64url') + '!aA9';
  const hash = execFileSync('docker', ['exec', '-i', WEB, 'php', '-r', 'echo crypt(stream_get_contents(STDIN), "$6$".bin2hex(random_bytes(8))."$");'], { input: password, encoding: 'utf8' }).trim();
  assert.match(hash, /^\$6\$/);
  const actors = { active: [501, ',1,', 1], readonly: [502, ',1,', 1], outsider: [503, ',2,', 2], globalro: [504, null, 1], multi: [505, ',1,2,', 1] };
  for (const [name, [id, schools, current]] of Object.entries(actors)) {
    sql(`DELETE FROM staff WHERE staff_id=${id}; INSERT INTO staff(syear,staff_id,current_school_id,first_name,last_name,username,password,profile,schools,last_login,profile_id) VALUES(2026,${id},${current},'LOT1A','Fictional','lot1a_${name}','${hash}','admin',${schools ? "'" + schools + "'" : 'NULL'},CURRENT_TIMESTAMP,1); DELETE FROM schoolsafe_account_state WHERE person_key='lot1a_${name}';`);
  }
  sql("INSERT INTO schoolsafe_account_state(person_key,school_id,state,session_epoch) VALUES('lot1a_readonly',1,'readonly',0),('lot1a_globalro',0,'readonly',0),('lot1a_multi',2,'readonly',0);");
  const clients = {};
  for (const name of Object.keys(actors)) {
    const c = await request.newContext({ baseURL: BASE, timeout: 20000 }); contexts.push(c);
    await c.get('/index.php');
    const response = await c.post('/index.php', { form: { USERNAME: 'lot1a_' + name, PASSWORD: password }, maxRedirects: 0 });
    assert.equal(response.status(), 302, 'native login ' + name);
    clients[name] = c;
  }
  const home = await clients.active.get('/ecoles/1/profils');
  assert.equal(home.status(), 200);
  const body = await home.text();
  const keys = [...body.matchAll(/name="droit\[([^"\]]+)\]" value="Y"/g)].map(m => m[1]);
  const permission = [...new Set(keys)].find(k => k === 'Students/Student.php');
  assert.ok(permission, 'existing actor permission');
  const tokens = {};
  for (const name of Object.keys(actors)) {
    const school = name === 'outsider' ? 2 : 1;
    const r = await clients[name].get(`/ecoles/${school}/profils`);
    assert.equal(r.status(), 200, 'read access ' + name);
    tokens[name] = tokenOf(await r.text()); assert.ok(tokens[name]);
  }
  for (const name of ['readonly', 'globalro', 'multi']) await check(name + ' GET remains readable', async () => {
    const school = name === 'multi' ? 2 : 1;
    assert.equal((await clients[name].get(`/ecoles/${school}/profils`)).status(), 200);
  });
  function form(action, token, school = 1) {
    return { token, ecole_profil_action: action, titre: 'LOT1A created', base: 'teacher', profil_id: String(school === 1 ? 9001 : 9002), [`droit[${permission}]`]: 'Y', [`edit[${permission}]`]: 'Y' };
  }
  for (const actor of ['readonly', 'globalro', 'multi']) {
    for (const action of ['creer', 'droits', 'supprimer']) await check(actor + ' denies ' + action + ' without business changes', async () => {
      fixtures(); const before = snapshot(); const school = actor === 'multi' ? 2 : 1;
      const r = await clients[actor].post(`/ecoles/${school}/profils`, { form: form(action, tokens[actor], school) });
      const unchanged = snapshot() === before;
      assert.ok(unchanged, 'business tables changed despite readonly; HTTP ' + r.status());
      assert.equal(r.status(), 403);
    });
  }
  for (const action of ['creer', 'droits', 'supprimer']) await check('active permits ' + action, async () => {
    fixtures();
    const r = await clients.active.post('/ecoles/1/profils', { form: form(action, tokens.active) });
    assert.equal(r.status(), 200);
    if (action === 'creer') assert.equal(sql("SELECT count(*) FROM user_profiles WHERE title='LOT1A created' AND school_id=1"), '1');
    if (action === 'droits') assert.equal(sql(`SELECT can_edit FROM profile_exceptions WHERE profile_id=9001 AND modname='${permission}'`), 'Y');
    if (action === 'supprimer') assert.equal(sql('SELECT count(*) FROM user_profiles WHERE id=9001'), '0');
  });
  for (const action of ['creer', 'droits', 'supprimer']) {
    await check('invalid CSRF denies ' + action, async () => {
      fixtures(); const before = snapshot();
      const r = await clients.active.post('/ecoles/1/profils', { form: form(action, 'invalid-fixture-token') });
      assert.match(await r.text(), /Requête expirée/); assert.equal(snapshot(), before);
    });
    await check('other school denies ' + action, async () => {
      fixtures(); const before = snapshot();
      const r = await clients.outsider.post('/ecoles/1/profils', { form: form(action, tokens.outsider) });
      assert.equal(r.status(), 403); assert.equal(snapshot(), before);
    });
  }
  await check('readonly applied to an already open session', async () => {
    fixtures(); sql("INSERT INTO schoolsafe_account_state(person_key,school_id,state,session_epoch) VALUES('lot1a_active',1,'readonly',0);");
    try {
      const before = snapshot();
      const r = await clients.active.post('/ecoles/1/profils', { form: form('creer', tokens.active) });
      assert.equal(snapshot(), before); assert.equal(r.status(), 403);
    } finally { sql("DELETE FROM schoolsafe_account_state WHERE person_key='lot1a_active';"); }
  });
  if (phase === 'after') {
    browser = await chromium.launch({ headless: true });
    for (const [actor, width] of [['active', 1280], ['readonly', 390]]) await check('browser form ' + actor + ' width ' + width, async () => {
      fixtures(); const before = snapshot();
      const context = await browser.newContext({ viewport: { width, height: 850 }, storageState: await clients[actor].storageState(), serviceWorkers: 'block' });
      context.setDefaultTimeout(15000);
      context.setDefaultNavigationTimeout(25000);
      // This native form proof needs no external assets or application background calls.
      await context.route('**/*', route => new URL(route.request().url()).origin === BASE
        && route.request().resourceType() === 'document' ? route.continue() : route.abort());
      await context.tracing.start({ screenshots: true, snapshots: true });
      try {
        const page = await context.newPage(); await page.goto(BASE + '/ecoles/1/profils');
        const f = page.locator('form').filter({ has: page.locator('input[name="ecole_profil_action"][value="creer"]') });
        await f.locator('input[name="titre"]').fill('LOT1A browser');
        await f.locator(`input[name="droit[${permission}]"]`).check();
        const response = page.waitForResponse(r => r.request().method() === 'POST' && r.url().startsWith(BASE + '/ecoles/1/profils'));
        await f.locator('input[type="submit"]').click();
        const r = await response;
        if (actor === 'active') {
          assert.equal(r.status(), 200); await expect(page.getByRole('status')).toContainText('créé');
          assert.equal(sql("SELECT count(*) FROM user_profiles WHERE title='LOT1A browser' AND school_id=1"), '1');
        } else { assert.equal(r.status(), 403); await expect(page.getByRole('alert')).toContainText('lecture seule'); assert.equal(snapshot(), before); }
        await page.screenshot({ path: path.join(ROOT, `${phase}-${actor}-${width}.png`), fullPage: false });
      } finally { await context.tracing.stop({ path: path.join(ROOT, `${phase}-${actor}-${width}-trace.zip`) }); await context.close(); }
    });
  }
}
(async () => {
  try { await main(); }
  catch (e) { report.fatal = e.message; console.error('Fixture/setup failed: ' + e.message.slice(0, 300)); }
  finally {
    if (browser) await browser.close();
    await Promise.all(contexts.map(c => c.dispose()));
    report.passed = report.cases.filter(c => c.status === 'PASS').length;
    report.failed = report.cases.filter(c => c.status === 'FAIL').length;
    report.status = !report.fatal && report.failed === 0 ? 'GREEN' : 'RED';
    fs.writeFileSync(path.join(ROOT, `${phase}.json`), JSON.stringify(report, null, 2));
    console.log(JSON.stringify({ phase, passed: report.passed, failed: report.failed, status: report.status }));
    process.exitCode = report.status === 'GREEN' ? 0 : 1;
  }
})();
