/*
 * SchoolSafe — Lot A1 : vérification QR locale de bout en bout (HTTP, L1 uniquement).
 *
 * Scénario réel : login proprietaire, POST scanner-relay.php?operation=scan
 * en mode verify, avec le secret de TEST injecté côté Apache (conteneur
 * uniquement, jamais dans git ni en production).
 *
 * Usage : TEST_CARD_SECRET=<64 hex> node app/qa-scan-verify-a1.cjs
 * Variables : SCHOOLSAFE_L1_URL (defaut http://127.0.0.1:8081),
 *             SCHOOLSAFE_L1_DB (defaut schoolsafe-app-db-1),
 *             SCHOOLSAFE_L1_CONTAINER (defaut schoolsafe-l1-test).
 */
const assert = require('node:assert/strict');
const crypto = require('node:crypto');
const { execFileSync } = require('node:child_process');

const BASE = process.env.SCHOOLSAFE_L1_URL || 'http://127.0.0.1:8081';
const DB = process.env.SCHOOLSAFE_L1_DB || 'schoolsafe-app-db-1';
const WEB = process.env.SCHOOLSAFE_L1_CONTAINER || 'schoolsafe-l1-test';
const PGUSER = 'schoolsafe';
const PGDATABASE = 'schoolsafe';
const PGPASSWORD = process.env.SCHOOLSAFE_L1_DB_PASSWORD || 'preview-local-jetable-32caracteres-xx';
const PASSWORD = 'L1-Test-2026!';
const SECRET = process.env.TEST_CARD_SECRET || '';
assert.match(SECRET, /^[a-f0-9]{64}$/, 'secret de test requis (TEST_CARD_SECRET, 64 hex)');

let passed = 0, failed = 0;
async function checkAsync(name, fn) {
  try { const r = await fn(); ++passed; console.log('PASS ' + name + (r ? ' — ' + r : '')); }
  catch (e) { ++failed; console.log('FAIL ' + name + ': ' + String(e.message).split('\n')[0]); }
}

function execWeb(args, env = {}) {
  return execFileSync('docker', ['exec', ...Object.entries(env).flatMap(([k, v]) => ['-e', k + '=' + v]), WEB, ...args], { encoding: 'utf8' });
}
function sql(statement) {
  return execFileSync('docker', ['exec', '-e', 'PGPASSWORD=' + PGPASSWORD, DB,
    'psql', '-U', PGUSER, '-d', PGDATABASE, '-At', '-c', statement], { encoding: 'utf8' }).trim();
}
const sign = (n) => crypto.createHmac('sha256', Buffer.from(SECRET, 'hex')).update(n).digest('base64url').slice(0, 32);
const qr = (n) => `schoolsafe://card/${n}/${sign(n)}`;
const CARD = 'TEST-A1-HTTP-OK';

function client() {
  const jar = new Map();
  async function request(path, options = {}) {
    const headers = { ...(options.headers || {}) };
    if (jar.size) headers.Cookie = [...jar].map(([k, v]) => k + '=' + v).join('; ');
    const response = await fetch(BASE + path, { redirect: 'manual', ...options, headers });
    for (const raw of (response.headers.getSetCookie ? response.headers.getSetCookie() : [])) {
      const [pair] = raw.split(';');
      const i = pair.indexOf('=');
      const k = pair.slice(0, i).trim(), v = pair.slice(i + 1).trim();
      if (v === '' || /deleted/i.test(raw)) jar.delete(k); else jar.set(k, v);
    }
    return { status: response.status, location: response.headers.get('location') || '', text: await response.text(), jar };
  }
  return { request, jar };
}

(async () => {
  // --- Socle de test (conteneur + base L1, nettoyé à la fin) -----------------
  execWeb(['sh', '-c', `printf 'SetEnv SCHOOLSAFE_CARD_HMAC_SECRET ${SECRET}\n' > /etc/apache2/conf-enabled/a1-test.conf && apache2ctl graceful`]);
  await new Promise((r) => setTimeout(r, 6000));
  const STAMP = Date.now();
  const QAUSER = 'qa.scan.' + STAMP;
  const hash = execWeb(['php', '-r', 'require "/var/www/html/functions/Password.php"; echo encrypt_password("L1-Test-2026!");']).trim();
  assert.match(hash, /^\$6\$/, 'mot de passe QA chiffre');
  sql(`INSERT INTO staff(SYEAR, FIRST_NAME, LAST_NAME, USERNAME, PASSWORD, PROFILE, PROFILE_ID, SCHOOLS, LAST_LOGIN)`
    + ` VALUES(2026, 'QA', 'Scan', '${QAUSER}', '${hash}', 'teacher', 2, ',1,', NOW())`);
  for (const mod of ['SchoolSafe/ScanVerify.php', 'Student_ID_Card/StudentIDCard.php']) {
    sql(`INSERT INTO profile_exceptions(PROFILE_ID, MODNAME, CAN_USE) SELECT 2, '${mod}', 'Y'`
      + ` WHERE NOT EXISTS(SELECT 1 FROM profile_exceptions WHERE PROFILE_ID=2 AND MODNAME='${mod}')`);
  }
  sql(`INSERT INTO schoolsafe_student_cards(school_id, student_id, card_number, signature, status)`
    + ` VALUES(1, 1, '${CARD}', '${sign(CARD)}', 'active')`);

  const sessionCsrf = (sessId) => execWeb(['php', '-r',
    `session_id(${JSON.stringify(sessId)}); session_start(); echo $_SESSION['SchoolSafeScannerCsrf'] ?? '';`]).trim();

  try {
    const c = client();
    await c.request('/index.php');
    const login = await c.request('/index.php', { method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({ USERNAME: QAUSER, PASSWORD }).toString() });
    assert.match(login.location || '', /modname=/, 'enseignant QA connecte, redirige portail');

    const scan = (body, csrf) => c.request('/scanner-relay.php?operation=scan', { method: 'POST',
      headers: { 'Content-Type': 'application/json', ...(csrf ? { 'X-SchoolSafe-Csrf': csrf } : {}) },
      body: JSON.stringify(body) });

    // CSRF d'abord refusé sans jeton (contrat préservé), ce qui crée le jeton en session.
    await checkAsync('sans CSRF => 403, motif masqué (contrat)', async () => {
      const r = await scan({ mode: 'verify', qr_payload: qr(CARD) });
      assert.equal(r.status, 403);
      assert.match(r.text, /NATIVE_SCANNER_UNAVAILABLE/);
      assert.doesNotMatch(r.text, /CSRF/);
      return 'porte CSRF intacte, sans fuite';
    });

    const csrf = sessionCsrf(c.jar.get('RosarioSIS'));
    assert.match(csrf, /^[a-f0-9]{64}$/, 'jeton CSRF de session récupéré');

    await checkAsync('carte connue => 200 VALID + identité', async () => {
      const r = await scan({ mode: 'verify', qr_payload: qr(CARD) }, csrf);
      assert.equal(r.status, 200);
      const data = JSON.parse(r.text).data;
      assert.equal(data.result, 'VALID');
      assert.equal(data.nativeStudentId, 1);
      assert.ok((data.student_name || '').length > 0);
      assert.ok('photo_url' in data, 'enrichissement portrait conservé');
      return data.student_name;
    });

    await checkAsync('signature falsifiée => 200 INVALID/signature_mismatch', async () => {
      const r = await scan({ mode: 'verify', qr_payload: `schoolsafe://card/${CARD}/${'B'.repeat(32)}` }, csrf);
      assert.equal(r.status, 200);
      const data = JSON.parse(r.text).data;
      assert.equal(data.result, 'INVALID');
      assert.equal(data.reason, 'signature_mismatch');
      return 'refus propre, sans fuite';
    });

    await checkAsync('carte inconnue, sans bridge => 503 contrat conservé', async () => {
      const r = await scan({ mode: 'verify', qr_payload: qr('TEST-A1-HTTP-ABSENT') }, csrf);
      assert.equal(r.status, 503);
      assert.match(r.text, /NATIVE_SCANNER_UNAVAILABLE/);
      return 'repli bridge inchangé';
    });

    // Contrat vérifié (Gateway SCAN_FEES d'abord, bridge ensuite) : sans droit
    // SCAN_FEES, le refus permission 403 prime sur l'indisponibilité 503.
    // Corps identique NATIVE_SCANNER_UNAVAILABLE, sans fuite du motif.
    await checkAsync('mode fees, sans droit SCAN_FEES => 403 contrat (permission prime)', async () => {
      const r = await scan({ mode: 'fees', qr_payload: qr(CARD) }, csrf);
      assert.equal(r.status, 403);
      assert.match(r.text, /NATIVE_SCANNER_UNAVAILABLE/);
      return 'permission prime sur bridge, sans fuite';
    });
  } finally {
    sql(`DELETE FROM schoolsafe_student_cards WHERE card_number='${CARD}'`);
    sql(`DELETE FROM profile_exceptions WHERE PROFILE_ID=2 AND MODNAME IN ('SchoolSafe/ScanVerify.php','Student_ID_Card/StudentIDCard.php')`);
    sql(`DELETE FROM staff WHERE USERNAME='${QAUSER}'`);
    execWeb(['sh', '-c', 'rm -f /etc/apache2/conf-enabled/a1-test.conf && apache2ctl graceful']);
  }

  console.log(failed === 0 ? `GREEN : ${passed} PASS.` : `STOP : ${failed} FAIL / ${passed} PASS.`);
  process.exit(failed === 0 ? 0 : 1);
})().catch((e) => { console.error('STOP : ' + e.message); process.exit(1); });
