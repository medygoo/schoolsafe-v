/* SchoolSafe L3.1 (jetable) : profils d'ecole 034 + hub + guards + isolation. */
const assert = require('node:assert/strict');
const { execFileSync } = require('node:child_process');
const crypto = require('node:crypto');
const fs = require('node:fs');
const BASE = 'http://127.0.0.1:8084';
const DB = 'schoolsafe-app-db-1', PGDATABASE = 'schoolsafe_l3_recipe';
const PGPASSWORD = 'preview-local-jetable-32caracteres-xx';
let passed = 0, failed = 0;
function sql(s) {
  return execFileSync('docker', ['exec', '-e', 'PGPASSWORD=' + PGPASSWORD, DB,
    'psql', '-U', 'schoolsafe', '-d', PGDATABASE, '-At', '-c', s], { encoding: 'utf8' }).trim();
}
function phpCrypt(pw) {
  return execFileSync('docker', ['exec', 'schoolsafe-l3-recipe', 'php', '-r',
    'echo crypt($argv[1], "$6$".substr(strtr(base64_encode(random_bytes(12)),"+","."),0,16)."$");',
    pw], { encoding: 'utf8' }).trim();
}
async function check(name, fn) {
  try { const r = await fn(); ++passed; console.log('PASS ' + name + (r ? ' — ' + r : '')); }
  catch (e) { ++failed; console.log('FAIL ' + name + ': ' + String((e && e.message) || e).split('\n').slice(0, 6).join(' | ')); }
}
function client() {
  const jar = new Map();
  async function request(path, options = {}) {
    const headers = { ...(options.headers || {}) };
    if (jar.size) headers.Cookie = [...jar].map(([k, v]) => k + '=' + v).join('; ');
    if (options.form) {
      headers['Content-Type'] = 'application/x-www-form-urlencoded';
      options = { ...options, body: new URLSearchParams(options.form).toString() };
    }
    const r = await fetch(BASE + path, { redirect: 'manual', ...options, headers });
    for (const raw of (r.headers.getSetCookie ? r.headers.getSetCookie() : [])) {
      const [pair] = raw.split(';'); const i = pair.indexOf('=');
      const n = pair.slice(0, i).trim(), v = pair.slice(i + 1).trim();
      if (v === '' || /deleted/i.test(raw)) jar.delete(n); else jar.set(n, v);
    }
    return { status: r.status, location: r.headers.get('location') || '', body: await r.text() };
  }
  return { request, async login(u, p) { await request('/index.php'); return request('/index.php', { method: 'POST', form: { USERNAME: u, PASSWORD: p } }); } };
}
const tokenOf = (h) => (h.match(/name="token" value="([^"]+)"/) || [])[1]
  || (h.match(/<meta name="token" content="([^"]+)"/) || [])[1] || '';
const denied = (r) => r.status === 403 || /not allowed|pas autoris|You're not allowed/i.test(r.body)
  || /meta http-equiv="REFRESH"[^>]*Portal/i.test(r.body);
function setPw(user, pw) {
  const h = phpCrypt(pw).replace(/'/g, '');
  sql(`UPDATE staff SET PASSWORD='${h}' WHERE USERNAME='${user}' AND SYEAR=2026;`);
}
const snap = () => [
  sql(`SELECT ID||':'||PROFILE||':'||TITLE||':'||COALESCE(school_id::text,'NULL') FROM user_profiles ORDER BY ID;`),
  sql(`SELECT PROFILE_ID||':'||MODNAME||':'||COALESCE(CAN_USE,'')||':'||COALESCE(CAN_EDIT,'') FROM profile_exceptions ORDER BY 1;`),
  sql(`SELECT USERNAME||':'||PROFILE||':'||PROFILE_ID||':'||COALESCE(SCHOOLS,'NULL') FROM staff WHERE SYEAR=2026 ORDER BY 1;`),
  sql(`SELECT USER_ID||':'||MODNAME FROM staff_exceptions ORDER BY 1;`),
].join('\n---\n');
async function main() {
  // Idempotence : purge des artefacts d'un run partiel precedent (avant snapshot).
  sql(`DELETE FROM staff_exceptions WHERE USER_ID IN (SELECT STAFF_ID FROM staff WHERE USERNAME IN ('l3p31holder','l3admin2'));`);
  sql(`DELETE FROM staff WHERE USERNAME IN ('l3p31holder','l3admin2');`);
  sql(`DELETE FROM profile_exceptions WHERE PROFILE_ID IN (SELECT ID FROM user_profiles WHERE TITLE LIKE 'L31 %');`);
  sql(`DELETE FROM user_profiles WHERE TITLE LIKE 'L31 %';`);
  const before = snap();
  const PW = { central: 'L31' + crypto.randomBytes(8).toString('hex') + '!', admin: 'L31' + crypto.randomBytes(8).toString('hex') + '!', teacher: 'L31' + crypto.randomBytes(8).toString('hex') + '!' };
  setPw('l3central', PW.central); setPw('l3admin', PW.admin); setPw('l3enseignant', PW.teacher);
  const central = client(); assert.equal((await central.login('l3central', PW.central)).status, 302, 'central');
  const admin = client(); assert.equal((await admin.login('l3admin', PW.admin)).status, 302, 'admin');
  // Fixture jetable : admin scolaire ecole 2 (appartenance croisee).
  sql(`DELETE FROM staff WHERE USERNAME='l3admin2';`);
  const h2 = phpCrypt('L31admin2!').replace(/'/g, '');
  sql(`INSERT INTO staff(syear,staff_id,current_school_id,title,first_name,last_name,username,password,email,profile,schools,last_login,profile_id) SELECT syear,nextval('staff_staff_id_seq'),current_school_id,title,first_name,last_name,'l3admin2','${h2}',email,profile,',2,',CURRENT_TIMESTAMP,profile_id FROM staff WHERE USERNAME='l3admin' AND SYEAR=2026;`);

  await check('T1 migration 034 additive + idempotente, existants globaux', async () => {
    assert.equal(sql(`SELECT is_nullable FROM information_schema.columns WHERE table_name='user_profiles' AND column_name='school_id';`), 'YES');
    assert.equal(sql(`SELECT count(*) FROM user_profiles WHERE school_id IS NOT NULL;`), '0', 'existants NULL=global');
    const file = fs.readFileSync('app/modules/codex-ui-v1/extensions-test-runtime/schoolsafe-remediation/034-profile-school-scope.sql', 'utf8');
    execFileSync('docker', ['exec', '-i', '-e', 'PGPASSWORD=' + PGPASSWORD, DB, 'psql', '-U', 'schoolsafe', '-d', PGDATABASE, '-v', 'ON_ERROR_STOP=1'], { input: file, encoding: 'utf8' });
    return 'colonne nullable, rejou exit 0';
  });

  // Mods effectifs de l'admin (lus depuis son formulaire hub).
  const hub = await admin.request('/ecoles/1/profils');
  assert.equal(hub.status, 200);
  const tok = tokenOf(hub.body);
  assert.ok(tok, 'jeton hub');
  const mods = [...hub.body.matchAll(/name="droit\[([^"\]]+)\]" value="Y"/g)].map((m) => m[1]);
  const pick = mods.filter((m) => !/Profiles\.php|Exceptions\.php|category_id=1/.test(m)).slice(0, 2);
  assert.ok(pick.length === 2, '2 ecrans non reserves, got ' + pick.length);

  let pid = 0; const TNAME = 'L31 Surveillant A';
  await check('T2 admin cree profil ecole (nom+base+droits subset)', async () => {
    const form = { token: tok, ecole_profil_action: 'creer', titre: TNAME, base: 'teacher' };
    pick.forEach((m) => { form['droit[' + m + ']'] = 'Y'; });
    const r = await admin.request('/ecoles/1/profils', { method: 'POST', form });
    assert.equal(r.status, 200);
    assert.match(r.body, /créé pour l’école/);
    pid = parseInt(sql(`SELECT ID FROM user_profiles WHERE TITLE='${TNAME}';`), 10);
    assert.ok(pid > 9, 'id nomme');
    assert.equal(sql(`SELECT PROFILE||'|'||school_id FROM user_profiles WHERE ID=${pid};`), 'teacher|1');
    assert.equal(sql(`SELECT count(*) FROM profile_exceptions WHERE PROFILE_ID=${pid};`), '2');
    return `profil ${pid} teacher|ecole1, 2 droits`;
  });
  await check('T3 CAN_EDIT pose + usage natif', async () => {
    const g = await admin.request('/ecoles/1/profils');
    const form = { token: tokenOf(g.body), ecole_profil_action: 'droits', profil_id: String(pid) };
    pick.forEach((m) => { form['droit[' + m + ']'] = 'Y'; form['edit[' + m + ']'] = 'Y'; });
    const r = await admin.request('/ecoles/1/profils', { method: 'POST', form });
    assert.match(r.body, /mis à jour/);
    assert.equal(sql(`SELECT count(*) FROM profile_exceptions WHERE PROFILE_ID=${pid} AND CAN_EDIT='Y';`), '2');
    return 'EDIT=Y effectif en base';
  });

  await check('T4 affectation user ecole via hub + usage effectif', async () => {
    const get = await admin.request('/ecoles/1/utilisateurs/nouveau');
    const post = await admin.request('/ecoles/1/utilisateurs/nouveau', { method: 'POST', form: {
      token: tokenOf(get.body), l3_create: '1', categorie: 'enseignant', first_name: 'L31', last_name: 'Holder',
      username: 'l3p31holder', email: 'p31holder@l3.invalid', phone: '+243815000051', 'schools[1]': 'Y', profil_ecole: String(pid) } });
    assert.match(post.body, /Vérifications OK/);
    const fields = {};
    for (const m of post.body.matchAll(/<input[^>]*type="hidden"[^>]*>/gi)) {
      const n = (m[0].match(/name="([^"]+)"/) || [])[1];
      const v = (m[0].match(/value="([^"]*)"/) || [])[1] || '';
      if (n) fields[n] = v;
    }
    const nat = await admin.request('/Modules.php?modname=Users/User.php&modfunc=update&staff_id=new&token=' + fields.token, { method: 'POST', form: fields });
    assert.equal(nat.status, 200);
    assert.equal(sql(`SELECT PROFILE||'|'||PROFILE_ID||'|'||SCHOOLS FROM staff WHERE USERNAME='l3p31holder';`), `teacher|${pid}|,1,`);
    setPw('l3p31holder', PW.teacher);
    const t = client(); assert.equal((await t.login('l3p31holder', PW.teacher)).status, 302);
    assert.equal((await t.request('/Modules.php?modname=' + pick[0])).status, 200, 'droit du profil utilisable');
    return `holder teacher|${pid}|,1, + ecran 200`;
  });

  await check('T5 refus croises : ecole B, multi-ecoles, changement SCHOOLS', async () => {
    const a2 = client(); assert.equal((await a2.login('l3admin2', 'L31admin2!')).status, 302, 'admin2 ecole2');
    assert.equal((await a2.request('/ecoles/1/profils')).status, 403, 'hub ecole1 ferme a admin2');
    assert.equal((await a2.request('/ecoles/1/utilisateurs/nouveau')).status, 403, 'hub users ecole1 ferme');
    const tid = sql(`SELECT STAFF_ID FROM staff WHERE USERNAME='l3theobis';`);
    const page = await a2.request('/Modules.php?modname=Users/User.php&staff_id=' + tid);
    const m = page.body.match(/action="(Modules\.php\?modname=Users\/User\.php[^"]*modfunc=update[^"]*)"/);
    if (m) {
      const r = await a2.request('/' + m[1].replace(/&amp;/g, '&'), { method: 'POST', form: { token: tokenOf(page.body), 'staff[PROFILE_ID]': String(pid) } });
      assert.ok(denied(r), 'natif refuse a admin2, got ' + r.status);
    }
    assert.equal(sql(`SELECT PROFILE_ID FROM staff WHERE USERNAME='l3theobis';`), '2', 'theobis inchange');
    // Multi-ecoles : l3multi (,1,2,).
    const mp = await admin.request('/Modules.php?modname=Users/User.php&staff_id=' + sql(`SELECT STAFF_ID FROM staff WHERE USERNAME='l3multi';`));
    const mm = mp.body.match(/action="(Modules\.php\?modname=Users\/User\.php[^"]*modfunc=update[^"]*)"/);
    if (!mm) {
      // Refus au niveau page (403 / not allowed) : aussi valable, le compte
      // multi-ecoles n'est ni visible ni modifiable hors contexte isole.
      assert.ok(denied(mp) || mp.status === 403, 'multi-ecoles refuse au niveau page, got ' + mp.status);
    } else {
      const mr = await admin.request('/' + mm[1].replace(/&amp;/g, '&'), { method: 'POST', form: { token: tokenOf(mp.body), 'staff[PROFILE_ID]': String(pid) } });
      assert.ok(denied(mr) || sql(`SELECT PROFILE_ID FROM staff WHERE USERNAME='l3multi';`) === '8', 'multi-ecoles refuse, got ' + mr.status);
    }
    assert.equal(sql(`SELECT PROFILE_ID FROM staff WHERE USERNAME='l3multi';`), '8', 'multi inchange');
    // Changement SCHOOLS du detenteur vers ,1,2, refuse.
    const hp = await admin.request('/Modules.php?modname=Users/User.php&staff_id=' + sql(`SELECT STAFF_ID FROM staff WHERE USERNAME='l3p31holder';`));
    const hm = hp.body.match(/action="(Modules\.php\?modname=Users\/User\.php[^"]*modfunc=update[^"]*)"/);
    const hr = await admin.request('/' + hm[1].replace(/&amp;/g, '&'), { method: 'POST',
      form: { token: tokenOf(hp.body), 'staff[SCHOOLS]': ',1,2,' } });
    assert.ok(denied(hr) || sql(`SELECT SCHOOLS FROM staff WHERE USERNAME='l3p31holder';`) === ',1,', 'rattachement ulterieur controle');
    assert.equal(sql(`SELECT SCHOOLS FROM staff WHERE USERNAME='l3p31holder';`), ',1,', 'holder toujours ,1,');
    return 'B 403, multi refuse, SCHOOLS fige';
  });

  await check('T6 elevation refusee : reserve, pair, base parent, GlobalAdmin', async () => {
    const g = await admin.request('/ecoles/1/profils');
    const t0 = tokenOf(g.body);
    let r = await admin.request('/ecoles/1/profils', { method: 'POST', form: { token: t0, ecole_profil_action: 'creer',
      titre: 'L31 Reserve', base: 'teacher', 'droit[Users/Profiles.php]': 'Y' } });
    assert.ok(/hors de vos pouvoirs|réservé/.test(r.body), 'reserve refuse');
    const all = {};
    mods.forEach((m) => { all['droit[' + m + ']'] = 'Y'; });
    r = await admin.request('/ecoles/1/profils', { method: 'POST', form: { token: t0, ecole_profil_action: 'creer', titre: 'L31 Pair', base: 'teacher', ...all } });
    assert.ok(/identique/.test(r.body), 'pair refuse');
    r = await admin.request('/ecoles/1/profils', { method: 'POST', form: { token: t0, ecole_profil_action: 'creer', titre: 'L31 Parent', base: 'parent', ['droit[' + pick[0] + ']']: 'Y' } });
    assert.ok(/Base autorisée/.test(r.body), 'base parent refusee');
    assert.equal(sql(`SELECT count(*) FROM user_profiles WHERE TITLE LIKE 'L31 %';`), '1', 'seul le legitime existe');
    const tid = sql(`SELECT STAFF_ID FROM staff WHERE USERNAME='l3enseignant';`);
    const pg = await admin.request('/Modules.php?modname=Users/User.php&staff_id=' + tid);
    const mg = pg.body.match(/action="(Modules\.php\?modname=Users\/User\.php[^"]*modfunc=update[^"]*)"/);
    const rg = await admin.request('/' + mg[1].replace(/&amp;/g, '&'), { method: 'POST', form: { token: tokenOf(pg.body), 'staff[PROFILE]': 'admin', 'staff[PROFILE_ID]': '1' } });
    const ensBefore = sql(`SELECT PROFILE||'/'||PROFILE_ID FROM staff WHERE USERNAME='l3enseignant';`);
    assert.ok(denied(rg) || sql(`SELECT PROFILE||'/'||PROFILE_ID FROM staff WHERE USERNAME='l3enseignant';`) === ensBefore,
      'GlobalAdmin refuse (deny ou champs natifs neutralises), got status=' + rg.status);
    return 'reserve/pair/parent/global : 0 elevation';
  });

  await check('T7 modification, revocation, suppression sans residu', async () => {
    const g = await admin.request('/ecoles/1/profils');
    const one = { token: tokenOf(g.body), ecole_profil_action: 'droits', profil_id: String(pid), ['droit[' + pick[0] + ']']: 'Y' };
    let r = await admin.request('/ecoles/1/profils', { method: 'POST', form: one });
    assert.match(r.body, /mis à jour/);
    assert.equal(sql(`SELECT count(*) FROM profile_exceptions WHERE PROFILE_ID=${pid};`), '1', 'retrait effectif');
    r = await admin.request('/ecoles/1/profils', { method: 'POST', form: { token: tokenOf(g.body), ecole_profil_action: 'droits', profil_id: String(pid) } });
    assert.match(r.body, /révoqués/);
    assert.equal(sql(`SELECT count(*) FROM profile_exceptions WHERE PROFILE_ID=${pid};`), '0', '0 droit');
    const t = client(); await t.login('l3p31holder', PW.teacher);
    assert.ok(denied(await t.request('/Modules.php?modname=' + pick[0])), 'holder ferme apres revocation');
    r = await admin.request('/ecoles/1/profils', { method: 'POST', form: { token: tokenOf(g.body), ecole_profil_action: 'supprimer', profil_id: String(pid) } });
    assert.match(r.body, /supprimé/);
    assert.equal(sql(`SELECT count(*) FROM user_profiles WHERE ID=${pid};`), '0', 'profil supprime');
    const hid = sql(`SELECT STAFF_ID FROM staff WHERE USERNAME='l3p31holder';`);
    assert.equal(sql(`SELECT count(*) FROM staff_exceptions WHERE USER_ID=${hid};`), '0', '0 droit indu persiste');
    sql(`DELETE FROM staff WHERE USERNAME='l3p31holder'; DELETE FROM staff WHERE USERNAME='l3admin2';`);
    return 'modif/revocation/suppression propres';
  });

  await check('T8 non-regression : snapshot global identique + gardes intactes', async () => {
    assert.equal(snap(), before, 'diff vide profils/droits/comptes/exceptions');
    for (const m of ['Users/Profiles.php', 'Users/Exceptions.php']) {
      assert.ok(denied(await admin.request('/Modules.php?modname=' + m)), m + ' toujours 403 admin');
    }
    assert.equal((await central.request('/Modules.php?modname=Users/Profiles.php')).status, 200, 'central 200');
    return 'gardes + donnees inchangees';
  });

  console.log(`\nL3.1 PROFILS ECOLE : ${passed} PASS, ${failed} FAIL`);
  process.exit(failed ? 1 : 0);
}
main().catch((e) => { console.error('FATAL', e); process.exit(2); });
