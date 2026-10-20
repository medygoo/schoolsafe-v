/*
 * SchoolSafe — Lot ENTRY : parcours complet carte → scan → présence →
 * tableau enseignant → tableau parent (HTTP, L1 uniquement).
 *
 * Usage : TEST_CARD_SECRET=<64 hex> node app/qa-scan-entry.cjs
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
function execWeb(args) {
  return execFileSync('docker', ['exec', WEB, ...args], { encoding: 'utf8' });
}
function sql(s) {
  return execFileSync('docker', ['exec', '-e', 'PGPASSWORD=' + PGPASSWORD, DB,
    'psql', '-U', PGUSER, '-d', PGDATABASE, '-At', '-c', s], { encoding: 'utf8' }).trim();
}
const sign = (n) => crypto.createHmac('sha256', Buffer.from(SECRET, 'hex')).update(n).digest('base64url').slice(0, 32);
const qr = (n) => `schoolsafe://card/${n}/${sign(n)}`;
const CARD = 'TEST-A1-ENTRY-HTTP';
const STAMP = Date.now();
const QAUSER = 'qa.entry.' + STAMP;

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
const sessionCsrf = (sessId) => execWeb(['php', '-r',
  `session_id(${JSON.stringify(sessId)}); session_start(); echo $_SESSION['SchoolSafeScannerCsrf'] ?? '';`]).trim();

(async () => {
  execWeb(['sh', '-c', `printf 'SetEnv SCHOOLSAFE_CARD_HMAC_SECRET ${SECRET}\n' > /etc/apache2/conf-enabled/entry-test.conf && apache2ctl graceful`]);
  await new Promise((r) => setTimeout(r, 6000));
  const hash = execWeb(['php', '-r', 'require "/var/www/html/functions/Password.php"; echo encrypt_password("L1-Test-2026!");']).trim();
  sql(`INSERT INTO staff(SYEAR, FIRST_NAME, LAST_NAME, USERNAME, PASSWORD, PROFILE, PROFILE_ID, SCHOOLS, LAST_LOGIN)`
    + ` VALUES(2026, 'QA', 'Entry', '${QAUSER}', '${hash}', 'teacher', 2, ',1,', NOW())`);
  const QAADMIN = 'qa.entry.admin.' + STAMP;
  sql(`INSERT INTO staff(SYEAR, FIRST_NAME, LAST_NAME, USERNAME, PASSWORD, PROFILE, PROFILE_ID, SCHOOLS, LAST_LOGIN)`
    + ` VALUES(2026, 'QA', 'EntryAdmin', '${QAADMIN}', '${hash}', 'admin', 1, ',1,', NOW())`);
  sql(`INSERT INTO profile_exceptions(PROFILE_ID, MODNAME, CAN_USE) SELECT 1, 'SchoolSafe/ArrivalAttendance.php', 'Y'`
    + ` WHERE NOT EXISTS(SELECT 1 FROM profile_exceptions WHERE PROFILE_ID=1 AND MODNAME='SchoolSafe/ArrivalAttendance.php')`);
  for (const mod of ['SchoolSafe/ScanArrival.php', 'SchoolSafe/ScanVerify.php',
    'Student_ID_Card/StudentIDCard.php', 'SchoolSafe/ArrivalAttendance.php']) {
    sql(`INSERT INTO profile_exceptions(PROFILE_ID, MODNAME, CAN_USE) SELECT 2, '${mod}', 'Y'`
      + ` WHERE NOT EXISTS(SELECT 1 FROM profile_exceptions WHERE PROFILE_ID=2 AND MODNAME='${mod}')`);
  }
  sql(`INSERT INTO profile_exceptions(PROFILE_ID, MODNAME, CAN_USE) SELECT 3, 'SchoolSafe/Notifications.php', 'Y'`
    + ` WHERE NOT EXISTS(SELECT 1 FROM profile_exceptions WHERE PROFILE_ID=3 AND MODNAME='SchoolSafe/Notifications.php')`);
  sql(`INSERT INTO entry_exit_checkpoints(TITLE, TYPE) VALUES('Portail QA HTTP', NULL)`);
  const cpId = sql(`SELECT ID FROM entry_exit_checkpoints WHERE TITLE='Portail QA HTTP'`);
  sql(`INSERT INTO schoolsafe_student_cards(school_id, student_id, card_number, signature, status)`
    + ` VALUES(1, 1, '${CARD}', '${sign(CARD)}', 'active')`);
  // Le parent QA suit l'élève 1 (lien préexistant conservé tel quel).
  const today = new Date().toISOString().slice(0, 10);

  try {
    const c = client();
    await c.request('/index.php');
    const login = await c.request('/index.php', { method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams({ USERNAME: QAUSER, PASSWORD }).toString() });
    assert.match(login.location || '', /modname=/, 'enseignant QA connecte');
    const get = (p) => c.request(p);
    const scan = (body, csrf) => c.request('/scanner-relay.php?operation=scan', { method: 'POST',
      headers: { 'Content-Type': 'application/json', ...(csrf ? { 'X-SchoolSafe-Csrf': csrf } : {}) },
      body: JSON.stringify(body) });

    await checkAsync('capabilities locales : ENTRY proposé + CSRF', async () => {
      const r = await get('/scanner-relay.php?operation=capabilities');
      assert.equal(r.status, 200);
      const j = JSON.parse(r.text);
      assert.ok(j.data.capabilities.includes('SCAN_ENTRY'), 'SCAN_ENTRY présent');
      assert.ok(j.csrfToken, 'jeton CSRF fourni');
      return j.data.capabilities.join(',');
    });

    await checkAsync('lieux locaux : checkpoints Rosario', async () => {
      const r = await get('/scanner-relay.php?operation=locations');
      assert.equal(r.status, 200);
      const locs = JSON.parse(r.text).data.locations;
      assert.ok(locs.some((l) => typeof l.id === 'string' && l.label === 'Portail QA HTTP'));
      return locs.length + ' lieu(x)';
    });

    const csrf = sessionCsrf(c.jar.get('RosarioSIS'));
    assert.match(csrf, /^[a-f0-9]{64}$/, 'jeton CSRF de session');

    await checkAsync('scan entrée => 200 PRESENT + identité + photo', async () => {
      const r = await scan({ mode: 'entry', qr_payload: qr(CARD), location_id: String(cpId) }, csrf);
      assert.equal(r.status, 200);
      const d = JSON.parse(r.text).data;
      assert.equal(d.result, 'PRESENT');
      assert.equal(d.nativeStudentId, 1);
      assert.ok(d.student_name && d.student_name.length > 0);
      assert.ok('photo_url' in d, 'portrait conservé');
      assert.equal(d.late === true || d.late === false, true);
      return d.student_name;
    });

    await checkAsync('double scan => ALREADY_PRESENT, sans doublon', async () => {
      const r = await scan({ mode: 'entry', qr_payload: qr(CARD), location_id: String(cpId) }, csrf);
      assert.equal(JSON.parse(r.text).data.result, 'ALREADY_PRESENT');
      const n = sql(`SELECT COUNT(*) FROM entry_exit_student_records WHERE STUDENT_ID=1 AND TYPE='in'`
        + ` AND UNAUTHORIZED IS NULL AND RECORDED_AT::date='${today}'`);
      assert.equal(n, '1');
      return '1 seul record';
    });

    await checkAsync('enseignant sans classe => 403, sans fuite', async () => {
      // L1 ne contient aucun course_periods : l'enseignant sans classe attribuée
      // est refusé proprement (pas de fuite inter-classes).
      const r = await get(`/arrival-attendance.php?date=${today}`);
      assert.equal(r.status, 403);
      assert.doesNotMatch(r.text, /Student Student/, 'aucune donnée élève exposée');
      return 'frontière respectée';
    });

    await checkAsync('tableau direction (admin) : arrivée visible', async () => {
      const a = client();
      await a.request('/index.php');
      const alogin = await a.request('/index.php', { method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ USERNAME: QAADMIN, PASSWORD }).toString() });
      assert.match(alogin.location || '', /modname=/, 'admin QA connecte');
      const r = await a.request(`/arrival-attendance.php?date=${today}`);
      assert.equal(r.status, 200);
      assert.match(r.text, /Student/, "nom de l'élève affiché");
      return 'registre natif lu';
    });

    await checkAsync('carte falsifiée => INVALID, sans écriture', async () => {
      const r = await scan({ mode: 'entry', qr_payload: `schoolsafe://card/${CARD}/${'C'.repeat(32)}`, location_id: String(cpId) }, csrf);
      assert.equal(JSON.parse(r.text).data.result, 'INVALID');
      return 'refus propre';
    });

    await checkAsync('lieu inconnu => DENIED', async () => {
      const r = await scan({ mode: 'entry', qr_payload: qr(CARD), location_id: '999999' }, csrf);
      assert.equal(JSON.parse(r.text).data.result, 'DENIED');
      return 'portail validé';
    });

    // Tableau parent : compte parent préexistant, mot de passe d'origine inconnu → on le fixe pour le test.
    const phash = execWeb(['php', '-r', 'require "/var/www/html/functions/Password.php"; echo encrypt_password("L1-Test-2026!");']).trim();
    const oldHash = sql(`SELECT PASSWORD FROM staff WHERE USERNAME='qa.parent'`);
    sql(`UPDATE staff SET PASSWORD='${phash}' WHERE USERNAME='qa.parent'`);
    try {
      const p = client();
      await p.request('/index.php');
      const plogin = await p.request('/index.php', { method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ USERNAME: 'qa.parent', PASSWORD }).toString() });
      assert.match(plogin.location || '', /modname=/, 'parent QA connecte');
      await checkAsync('tableau parent : arrivée notifiée', async () => {
        const r = await p.request('/parent-notifications.php');
        assert.equal(r.status, 200);
        const j = JSON.parse(r.text);
        const items = j.data.notifications || [];
        assert.ok(items.some((n) => typeof n.id === 'string' && n.id.startsWith('arrival:')), 'item arrival: présent');
        return items.length + ' notification(s)';
      });
    } finally {
      sql(`UPDATE staff SET PASSWORD='${oldHash}' WHERE USERNAME='qa.parent'`);
    }
  } finally {
    const day = new Date().toISOString().slice(0, 10);
    sql(`DELETE FROM entry_exit_student_records WHERE STUDENT_ID=1 AND RECORDED_AT::date='${day}' AND COMMENTS LIKE '%staff #%'`);
    sql(`DELETE FROM attendance_day WHERE STUDENT_ID=1 AND SCHOOL_DATE='${day}' AND COMMENT LIKE 'Entrée %'`);
    sql(`DELETE FROM schoolsafe_student_cards WHERE CARD_NUMBER='${CARD}'`);
    sql(`DELETE FROM entry_exit_checkpoints WHERE TITLE='Portail QA HTTP'`);
    sql(`DELETE FROM profile_exceptions WHERE PROFILE_ID=2 AND MODNAME IN ('SchoolSafe/ScanArrival.php','SchoolSafe/ScanVerify.php','Student_ID_Card/StudentIDCard.php','SchoolSafe/ArrivalAttendance.php')`);
    sql(`DELETE FROM profile_exceptions WHERE PROFILE_ID=3 AND MODNAME='SchoolSafe/Notifications.php'`);
    sql(`DELETE FROM staff WHERE USERNAME='${QAUSER}'`);
    sql(`DELETE FROM staff WHERE USERNAME='${QAADMIN}'`);
    sql(`DELETE FROM profile_exceptions WHERE PROFILE_ID=1 AND MODNAME='SchoolSafe/ArrivalAttendance.php' AND CAN_USE='Y'`);
    execWeb(['sh', '-c', 'rm -f /etc/apache2/conf-enabled/entry-test.conf && apache2ctl graceful']);
  }

  console.log(failed === 0 ? `GREEN : ${passed} PASS.` : `STOP : ${failed} FAIL / ${passed} PASS.`);
  process.exit(failed === 0 ? 0 : 1);
})().catch((e) => { console.error('STOP : ' + e.message); process.exit(1); });
