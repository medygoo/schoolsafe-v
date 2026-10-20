/* SchoolSafe L4 (recette isolee) : recuperation des comptes — email natif
 * (Brevo-d'abord), liens (expiration, usage unique, throttle), revocation
 * des sessions, OTP simule sans SMSGate, non-regression par snapshot.
 * Base jetable schoolsafe_l3_recipe (:8084). Fixtures l4* nettoyees.
 * Usage : node app/qa-l4-recuperation.cjs
 */
const assert = require('node:assert/strict');
const { execFileSync } = require('node:child_process');
const crypto = require('node:crypto');
const BASE = 'http://127.0.0.1:8084';
const DB = 'schoolsafe-app-db-1', PGDATABASE = 'schoolsafe_l3_recipe';
const APP = 'schoolsafe-l3-recipe';
const PGPASSWORD = 'preview-local-jetable-32caracteres-xx';
let passed = 0, failed = 0;
function sql(s) {
  return execFileSync('docker', ['exec', '-e', 'PGPASSWORD=' + PGPASSWORD, DB,
    'psql', '-U', 'schoolsafe', '-d', PGDATABASE, '-At', '-c', s], { encoding: 'utf8' }).trim();
}
function phpCrypt(pw) {
  return execFileSync('docker', ['exec', APP, 'php', '-r',
    'echo crypt($argv[1], "$6$".substr(strtr(base64_encode(random_bytes(12)),"+","."),0,16)."$");',
    pw], { encoding: 'utf8' }).trim();
}
function mint(userId, type, email, stub) {
  const raw = execFileSync('docker', ['exec', APP, 'php', 'l4-mint-helper.tmp.php',
    String(userId), type, email, stub ? '1' : '0'], { encoding: 'utf8' }).trim();
  return JSON.parse(raw);
}
async function check(name, fn) {
  try { const r = await fn(); ++passed; console.log('PASS ' + name + (r ? ' — ' + r : '')); }
  catch (e) { ++failed; console.log('FAIL ' + name + ': ' + String((e && e.message) || e).split('\n').slice(0, 8).join(' | ')); }
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
const tokenOf = (h) => (h.match(/name="token" value="([^"]+)"/) || [])[1] || '';
const snap = () => [
  sql(`SELECT ID||':'||PROFILE||':'||TITLE||':'||COALESCE(SCHOOL_ID::text,'NULL') FROM user_profiles ORDER BY ID;`),
  sql(`SELECT PROFILE_ID||':'||MODNAME||':'||COALESCE(CAN_USE,'')||':'||COALESCE(CAN_EDIT,'') FROM profile_exceptions ORDER BY 1;`),
  sql(`SELECT USERNAME||':'||PROFILE||':'||PROFILE_ID||':'||COALESCE(SCHOOLS,'NULL') FROM staff WHERE SYEAR=2026 ORDER BY 1;`),
  sql(`SELECT USER_ID||':'||MODNAME FROM staff_exceptions ORDER BY 1;`),
  sql(`SELECT count(*) FROM schoolsafe_recovery_otp;`),
  sql(`SELECT count(*) FROM config WHERE TITLE='RECOVERY_SMS_SIMULATE';`),
].join('\n---\n');
function setPw(user, pw) {
  const h = phpCrypt(pw).replace(/'/g, '');
  sql(`UPDATE staff SET PASSWORD='${h}',LAST_LOGIN=CURRENT_TIMESTAMP WHERE USERNAME='${user}' AND SYEAR=2026;`);
}
async function main() {
  // Idempotence : purge des artefacts d'un run partiel (avant snapshot).
  sql(`DELETE FROM schoolsafe_recovery_otp WHERE user_id IN (SELECT STAFF_ID FROM staff WHERE USERNAME LIKE 'l4%');`);
  sql(`DELETE FROM staff WHERE USERNAME LIKE 'l4%' AND SYEAR=2026;`);
  sql(`DELETE FROM config WHERE TITLE='RECOVERY_SMS_SIMULATE';`);
  const before = snap();
  // Canal simule (recette isolee uniquement).
  sql(`INSERT INTO config (SCHOOL_ID,TITLE,CONFIG_VALUE) VALUES (0,'RECOVERY_SMS_SIMULATE','Y');`);
  const PW = { victim: 'L44' + crypto.randomBytes(8).toString('hex') + '!' };
  // Fixture : victime personnel ecole 1, email + telephone +243.
  sql(`DELETE FROM staff WHERE USERNAME='l4victim';`);
  sql(`INSERT INTO staff(syear,staff_id,current_school_id,title,first_name,last_name,username,password,email,profile,profile_id,schools,custom_200000001,last_login) SELECT syear,nextval('staff_staff_id_seq'),current_school_id,title,'L4','Victim','l4victim','${phpCrypt(PW.victim).replace(/'/g, '')}','l4victim@l4.invalid',profile,profile_id,',1,','+243815000041',CURRENT_TIMESTAMP FROM staff WHERE USERNAME='l3enseignant' AND SYEAR=2026;`);
  const VID = sql(`SELECT STAFF_ID FROM staff WHERE USERNAME='l4victim';`);
  assert.ok(parseInt(VID, 10) > 0, 'fixture l4victim');

  await check('T1 migration 035 additive + idempotente (OTP seul, 0 doublon epoque)', async () => {
    assert.equal(sql(`SELECT count(*) FROM information_schema.columns WHERE table_name='staff' AND column_name='password_epoch';`), '0', 'pas de doublon epoque L1');
    assert.equal(sql(`SELECT count(*) FROM information_schema.tables WHERE table_name='schoolsafe_recovery_otp';`), '1', 'table OTP');
    const fs = require('node:fs');
    const file = fs.readFileSync('app/modules/codex-ui-v1/extensions-test-runtime/schoolsafe-remediation/035-recovery-epoch-otp.sql', 'utf8');
    execFileSync('docker', ['exec', '-i', '-e', 'PGPASSWORD=' + PGPASSWORD, DB, 'psql', '-U', 'schoolsafe', '-d', PGDATABASE, '-v', 'ON_ERROR_STOP=1'], { input: file, encoding: 'utf8' });
    return 'table OTP, rejou exit 0';
  });

  await check('T2 demande email : reponse generique, sans enumeration', async () => {
    const c = client();
    const r1 = await c.request('/PasswordReset.php', { method: 'POST', form: { email: 'l4victim@l4.invalid' } });
    const r2 = await c.request('/PasswordReset.php', { method: 'POST', form: { email: 'inexistant@l4.invalid' } });
    assert.ok(r1.status === 302 && /password_reset/.test(r1.location), 'connu generique, got ' + r1.status);
    assert.ok(r2.status === 302 && /password_reset/.test(r2.location), 'inconnu identique, got ' + r2.status);
    assert.equal(r1.location, r2.location, 'aucune distinction');
    assert.ok(parseInt(sql(`SELECT count(*) FROM access_log WHERE STATUS='P' AND CREATED_AT > CURRENT_TIMESTAMP - INTERVAL '5 minute';`), 10) >= 2, 'journalise P');
    return '302 identiques, journal P';
  });

  await check('T3 throttle demandes : 5/10 min/IP puis silence', async () => {
    const c = client();
    sql(`DELETE FROM access_log WHERE STATUS='P';`);
    for (let i = 0; i < 7; i++) {
      const r = await c.request('/PasswordReset.php', { method: 'POST', form: { email: 'l4victim@l4.invalid' } });
      assert.equal(r.status, 302, 'toujours generique #' + (i + 1));
      assert.ok(/password_reset/.test(r.location), 'meme redirection #' + (i + 1));
    }
    const logged = parseInt(sql(`SELECT count(*) FROM access_log WHERE STATUS='P';`), 10);
    assert.equal(logged, 5, '6-7eme ni traitees ni journalisees, got ' + logged);
    sql(`DELETE FROM access_log WHERE STATUS='P';`);
    return '5 traitees puis silence, reponse inchangee';
  });

  let LINK = '';
  await check('T4 lien : valide une fois, rejete ensuite + sessions revoquees', async () => {
    const m = mint(VID, 'staff', 'l4victim@l4.invalid', true);
    assert.ok(m.sent && m.link.includes('?h='), 'lien emis par le moteur');
    LINK = m.link;
    const path = LINK.slice(LINK.indexOf('/', LINK.indexOf('//') + 2));
    const g = await client().request(path);
    assert.equal(g.status, 200);
    assert.match(g.body, /name="h"/, 'formulaire natif');
    const h = (LINK.match(/[?&]h=([^&]+)/) || [])[1];
    const newPw = 'L44new' + crypto.randomBytes(6).toString('hex') + '!';
    const p = await client().request('/PasswordReset.php', { method: 'POST', form: { h, PASSWORD: newPw } });
    assert.equal(p.status, 302, 'changement 302, got ' + p.status);
    PW.victim = newPw;
    const g2 = await client().request(path);
    assert.ok(!/name="h"/.test(g2.body), 'lien non rejouable');
    assert.ok(parseInt(sql(`SELECT SESSION_EPOCH FROM schoolsafe_account_state WHERE PERSON_KEY='l4victim' AND SCHOOL_ID=0;`), 10) >= 1, 'epoque bumpée');
    return 'usage unique + epoque';
  });

  await check('T5 lien expire : refuse proprement', async () => {
    const m = mint(VID, 'staff', 'l4victim@l4.invalid', true);
    assert.ok(m.sent, 'lien emis');
    sql(`UPDATE staff SET LAST_LOGIN=CURRENT_TIMESTAMP - INTERVAL '3 hour' WHERE USERNAME='l4victim';`);
    const path = m.link.slice(m.link.indexOf('/', m.link.indexOf('//') + 2));
    const g = await client().request(path);
    assert.ok(!/name="h"/.test(g.body), 'expire refuse');
    return 'expiration +2h appliquee';
  });

  await check('T6 throttle liens : balayage bloque, valide protege', async () => {
    const m = mint(VID, 'staff', 'l4victim@l4.invalid', true);
    const c = client();
    try {
    for (let i = 0; i < 31; i++) {
      await c.request('/PasswordReset.php?h=$6$' + 'A'.repeat(100) + i.toString().padStart(2, '0'));
    }
    const path = m.link.slice(m.link.indexOf('/', m.link.indexOf('//') + 2));
    const g = await c.request(path);
    assert.ok(!/name="h"/.test(g.body), 'valide bloque apres balayage');
    assert.ok(parseInt(sql(`SELECT count(*) FROM access_log WHERE STATUS='R' AND CREATED_AT > CURRENT_TIMESTAMP - INTERVAL '10 minute';`), 10) >= 31, 'journalise R');
    } finally {
      sql(`DELETE FROM access_log WHERE STATUS='R';`);
    }
    return '30 max puis verrou, journal R';
  });

  await check('T7 changement : ancienne session evincee, nouvelle OK', async () => {
    setPw('l4victim', PW.victim);
    const a = client();
    assert.equal((await a.login('l4victim', PW.victim)).status, 302, 'session A connectee');
    assert.equal((await a.request('/Modules.php?modname=misc/Portal.php')).status, 200, 'A active');
    const m = mint(VID, 'staff', 'l4victim@l4.invalid', true);
    const h = (m.link.match(/[?&]h=([^&]+)/) || [])[1];
    const newPw = 'L44ev' + crypto.randomBytes(6).toString('hex') + '!';
    await client().request('/PasswordReset.php', { method: 'POST', form: { h, PASSWORD: newPw } });
    PW.victim = newPw;
    const r = await a.request('/Modules.php?modname=misc/Portal.php');
    assert.ok(r.status === 302 || r.status === 401 || /account_revoked|logout/.test(r.body + r.location), 'A evincee, got ' + r.status);
    const b = client();
    assert.equal((await b.login('l4victim', PW.victim)).status, 302, 'reconnexion OK');
    assert.equal((await b.request('/Modules.php?modname=misc/Portal.php')).status, 200, 'B active');
    return 'eviction + reconnexion';
  });

  await check('T8 OTP simule : demande generique, code, remise au natif', async () => {
    const c = client();
    const get = await c.request('/compte/recuperation');
    assert.equal(get.status, 200, 'hub 200');
    assert.match(get.body, /name="viewport"/, 'viewport');
    const tok = tokenOf(get.body);
    assert.ok(tok, 'jeton hub');
    const d1 = await c.request('/compte/recuperation', { method: 'POST', form: { token: tok, l4_action: 'otp_demande', username: 'l4victim', phone: '+243815000041' } });
    const d2 = await c.request('/compte/recuperation', { method: 'POST', form: { token: tokenOf(d1.body) || tok, l4_action: 'otp_demande', username: 'l4victim', phone: '+243810000000' } });
    assert.match(d1.body, /code à 6 chiffres/, 'message generique positif');
    assert.match(d2.body, /code à 6 chiffres/, 'message generique negatif identique');
    const oid = sql(`SELECT id FROM schoolsafe_recovery_otp WHERE user_id='${VID}' AND consumed_at IS NULL ORDER BY id DESC LIMIT 1;`);
    assert.ok(parseInt(oid, 10) > 0, 'defi cree pour le positif seul');
    assert.equal(sql(`SELECT count(*) FROM schoolsafe_recovery_otp WHERE user_id != '${VID}' AND consumed_at IS NULL;`), '0', 'rien pour le negatif');
    const code = sql(`SELECT code_plain FROM schoolsafe_recovery_otp WHERE id='${oid}';`);
    assert.match(code, /^[0-9]{6}$/, 'code simule lisible en recette');
    const v = await c.request('/compte/recuperation', { method: 'POST', form: { token: tokenOf(d1.body) || tok, l4_action: 'otp_verifie', code } });
    assert.equal(v.status, 302, 'remise au natif, got ' + v.status);
    assert.ok(/PasswordReset\.php\?h=/.test(v.location), 'vers formulaire natif');
    const vpath = v.location.slice(v.location.indexOf('/', v.location.indexOf('//') + 2));
    const g = await c.request(vpath);
    assert.match(g.body, /name="h"/, 'formulaire natif apres OTP');
    const h = (v.location.match(/[?&]h=([^&]+)/) || [])[1];
    const newPw = 'L44otp' + crypto.randomBytes(6).toString('hex') + '!';
    await c.request('/PasswordReset.php', { method: 'POST', form: { h, PASSWORD: newPw } });
    PW.victim = newPw;
    const t = client();
    assert.equal((await t.login('l4victim', PW.victim)).status, 302, 'connexion post-OTP');
    return 'bout-en-bout simule';
  });

  await check('T9 OTP negatifs : essais, expiration, rejou', async () => {
    const c = client();
    const get = await c.request('/compte/recuperation');
    const tok = tokenOf(get.body);
    await c.request('/compte/recuperation', { method: 'POST', form: { token: tok, l4_action: 'otp_demande', username: 'l4victim', phone: '+243815000041' } });
    const oid = sql(`SELECT id FROM schoolsafe_recovery_otp WHERE user_id='${VID}' AND consumed_at IS NULL ORDER BY id DESC LIMIT 1;`);
    const good = sql(`SELECT code_plain FROM schoolsafe_recovery_otp WHERE id='${oid}';`);
    for (let i = 0; i < 5; i++) {
      const r = await c.request('/compte/recuperation', { method: 'POST', form: { token: tokenOf((await c.request('/compte/recuperation')).body), l4_action: 'otp_verifie', code: '000000' } });
      assert.match(r.body, /incorrect ou expiré/, 'essai ' + (i + 1) + ' refuse');
    }
    const r6 = await c.request('/compte/recuperation', { method: 'POST', form: { token: tokenOf((await c.request('/compte/recuperation')).body), l4_action: 'otp_verifie', code: good } });
    assert.match(r6.body, /incorrect ou expiré/, 'bon code verrouille apres 5 essais');
    await c.request('/compte/recuperation', { method: 'POST', form: { token: tokenOf((await c.request('/compte/recuperation')).body), l4_action: 'otp_demande', username: 'l4victim', phone: '+243815000041' } });
    const oid2 = sql(`SELECT id FROM schoolsafe_recovery_otp WHERE user_id='${VID}' AND consumed_at IS NULL ORDER BY id DESC LIMIT 1;`);
    sql(`UPDATE schoolsafe_recovery_otp SET expires_at=CURRENT_TIMESTAMP - INTERVAL '1 minute' WHERE id='${oid2}';`);
    const good2 = sql(`SELECT code_plain FROM schoolsafe_recovery_otp WHERE id='${oid2}';`);
    const re = await c.request('/compte/recuperation', { method: 'POST', form: { token: tokenOf((await c.request('/compte/recuperation')).body), l4_action: 'otp_verifie', code: good2 } });
    assert.match(re.body, /incorrect ou expiré/, 'expire refuse');
    return '5 essais puis verrou, expiration, pas de rejou';
  });

  await check('T10 transport : Brevo-dabord, repli, echec sans echeance', async () => {
    const unit = execFileSync('docker', ['exec', APP, 'php', '-r',
      'chdir("/var/www/html"); require "Warehouse.php"; require "ProgramFunctions/SchoolSafeRecovery.fnc.php"; require "ProgramFunctions/SchoolSafeBrevo.fnc.php";'
      + ' $a = SchoolSafeBrevoSend(\"t@l4.invalid\", [\"from\"=>\"x@y.zz\",\"subject\"=>\"s\",\"html\"=>\"<p>h</p>\"], [\"api_key\"=>\"l4stub\",\"base_url\"=>\"http://127.0.0.1:9\"],'
      + ' function($u,$h,$b){ return ["http_code"=>201,"body"=>\'{"messageId":"l4mid"}\']; });'
      + ' echo "brevo=".($a["accepted"] ? "accepted" : "refused").";";'
      + ' putenv("BREVO_API_KEY=l4jetable"); putenv("BREVO_API_URL=http://127.0.0.1:9");'
      + ' $f = SchoolSafeRecoverySendMail("t@l4.invalid","s","<p>h</p>");'
      + ' echo "fallback=".($f["via"] ?? "?").";";'
      + ' $m = SchoolSafeRecoveryIssueReset(' + VID + ',"staff","l4victim@l4.invalid",function(){return false;});'
      + ' echo "nosend=".($m["sent"] ? "SENT" : "blocked").";";'
    ], { encoding: 'utf8' }).trim();
    assert.match(unit, /brevo=accepted/, 'Brevo accepte, got ' + unit);
    assert.match(unit, /fallback=mail/, 'repli natif, got ' + unit);
    assert.match(unit, /nosend=blocked/, 'sans envoi pas decheance, got ' + unit);
    assert.ok(!/l4jetable/.test(unit), 'cle jamais exposee');
    return 'Brevo > repli > echec ferme';
  });

  await check('T11 non-regression : snapshot L4 identique', async () => {
    sql(`DELETE FROM schoolsafe_recovery_otp WHERE user_id IN (SELECT STAFF_ID FROM staff WHERE USERNAME LIKE 'l4%');`);
    sql(`DELETE FROM staff WHERE USERNAME LIKE 'l4%' AND SYEAR=2026;`);
    sql(`DELETE FROM config WHERE TITLE='RECOVERY_SMS_SIMULATE';`);
    sql(`DELETE FROM access_log WHERE STATUS IN ('P','R','O');`);
    assert.equal(snap(), before, 'diff vide');
    return 'donnees inchangees';
  });

  console.log(`\nL4 RECUPERATION : ${passed} PASS, ${failed} FAIL`);
  process.exit(failed ? 1 : 0);
}
main().catch((e) => { console.error('FATAL', e); process.exit(2); });
