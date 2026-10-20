/*
 * SchoolSafe — Lot PICKUP : carte → personnes autorisées → confirmation
 * humaine → sortie → tableaux parent/direction (HTTP, L1 uniquement).
 *
 * Usage : TEST_CARD_SECRET=<64 hex> node app/qa-scan-pickup.cjs
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
const CARD = 'TEST-PICKUP-HTTP';
const STAMP = Date.now();
const QAUSER = 'qa.pickup.' + STAMP;
const QAADMIN = 'qa.pickup.admin.' + STAMP;

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
  execWeb(['sh', '-c', `printf 'SetEnv SCHOOLSAFE_CARD_HMAC_SECRET ${SECRET}\\n' > /etc/apache2/conf-enabled/pickup-test.conf && apache2ctl graceful`]);
  await new Promise((r) => setTimeout(r, 6000));
  // Photo réelle sous la racine web (1 px PNG, cadre validé par revue humaine).
  const png1px = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';
  execWeb(['sh', '-c', 'mkdir -p /var/www/html/assets/schoolsafe-pickup']);
  execWeb(['php', '-r', `file_put_contents('/var/www/html/assets/schoolsafe-pickup/qa-${STAMP}.jpg', base64_decode('${png1px}')); echo 'ok';`]);
  const hash = execWeb(['php', '-r', 'require "/var/www/html/functions/Password.php"; echo encrypt_password("L1-Test-2026!");']).trim();
  sql(`INSERT INTO staff(SYEAR, FIRST_NAME, LAST_NAME, USERNAME, PASSWORD, PROFILE, PROFILE_ID, SCHOOLS, LAST_LOGIN)`
    + ` VALUES(2026, 'QA', 'Pickup', '${QAUSER}', '${hash}', 'teacher', 2, ',1,', NOW())`);
  sql(`INSERT INTO staff(SYEAR, FIRST_NAME, LAST_NAME, USERNAME, PASSWORD, PROFILE, PROFILE_ID, SCHOOLS, LAST_LOGIN)`
    + ` VALUES(2026, 'QA', 'PickupAdmin', '${QAADMIN}', '${hash}', 'admin', 1, ',1,', NOW())`);
  for (const mod of ['SchoolSafe/ScanPickup.php', 'Student_ID_Card/StudentIDCard.php']) {
    sql(`INSERT INTO profile_exceptions(PROFILE_ID, MODNAME, CAN_USE) SELECT 2, '${mod}', 'Y'`
      + ` WHERE NOT EXISTS(SELECT 1 FROM profile_exceptions WHERE PROFILE_ID=2 AND MODNAME='${mod}')`);
  }
  // Admin + module mutant => CAN_EDIT exigé aussi (SchoolSafeNativeCommandPermission).
  for (const mod of ['SchoolSafe/FamilyRegister.php', 'SchoolSafe/FamilyApprove.php', 'SchoolSafe/FamilyRead.php']) {
    sql(`INSERT INTO profile_exceptions(PROFILE_ID, MODNAME, CAN_USE, CAN_EDIT) SELECT 1, '${mod}', 'Y', 'Y'`
      + ` WHERE NOT EXISTS(SELECT 1 FROM profile_exceptions WHERE PROFILE_ID=1 AND MODNAME='${mod}')`);
  }
  sql(`INSERT INTO profile_exceptions(PROFILE_ID, MODNAME, CAN_USE) SELECT 3, 'SchoolSafe/Notifications.php', 'Y'`
    + ` WHERE NOT EXISTS(SELECT 1 FROM profile_exceptions WHERE PROFILE_ID=3 AND MODNAME='SchoolSafe/Notifications.php')`);
  sql(`INSERT INTO entry_exit_checkpoints(TITLE, TYPE) VALUES('Portail QA Pickup', NULL)`);
  const cpId = sql(`SELECT ID FROM entry_exit_checkpoints WHERE TITLE='Portail QA Pickup'`);
  sql(`INSERT INTO schoolsafe_student_cards(school_id, student_id, card_number, signature, status)`
    + ` VALUES(1, 1, '${CARD}', '${sign(CARD)}', 'active')`);
  const today = new Date().toISOString().slice(0, 10);
  const photoPath = `/assets/schoolsafe-pickup/qa-${STAMP}.jpg`;

  const loginAs = async (username) => {
    // Après le graceful Apache, la première tentative peut tomber pendant le
    // recyclage (302 logout&reason=cookie) : on réessaie, sans assouplir l'assertion.
    let last = '';
    for (let attempt = 1; attempt <= 4; attempt++) {
      const c = client();
      await c.request('/index.php');
      const login = await c.request('/index.php', { method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ USERNAME: username, PASSWORD }).toString() });
      last = login.location || '';
      if (/modname=/.test(last)) return c;
      await new Promise((r) => setTimeout(r, 3000));
    }
    assert.match(last, /modname=/, username + ' connecte');
    throw new Error('inatteignable');
  };

  try {
    const adm = await loginAs(QAADMIN);
    // Un appel relay initialise le CSRF de session avant lecture.
    await adm.request('/scanner-relay.php?operation=capabilities');
    const csrfA = sessionCsrf(adm.jar.get('RosarioSIS'));
    assert.match(csrfA, /^[a-f0-9]{64}$/, 'jeton CSRF admin');
    const manage = (body) => adm.request('/scanner-relay.php?operation=management', { method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-SchoolSafe-Csrf': csrfA }, body: JSON.stringify(body) });

    let gid = '';
    await checkAsync('rattachement : mère principale enregistrée (pending, sans récupération)', async () => {
      const r = await manage({ action: 'register-family', nativeStudentId: '1',
        guardianType: 'mere', fullName: 'Maman QA HTTP', phone: '+22370000000', isPrimary: true });
      assert.equal(r.status, 200);
      gid = JSON.parse(r.text).data.guardian_id;
      assert.match(gid, /^[0-9]+$/);
      return 'personne ' + gid;
    });

    await checkAsync('photo associée puis validée (cadre humain)', async () => {
      let r = await manage({ action: 'photo-set', nativeStudentId: '1', guardianId: gid, photoPath });
      assert.equal(r.status, 200);
      r = await manage({ action: 'photo-review', nativeStudentId: '1', guardianId: gid,
        decision: 'accepted', reason: 'Visage et haut du corps nets' });
      assert.equal(r.status, 200);
      return 'photo validée';
    });

    await checkAsync('approbation école : récupération autorisée', async () => {
      const r = await manage({ action: 'school-family-approve', nativeStudentId: '1',
        guardianId: gid, reason: 'Présentée à l’école avec pièce', consent: 'Consentement mère' });
      assert.equal(r.status, 200);
      return 'autorisée';
    });

    const gt = await loginAs(QAUSER);
    await gt.request('/scanner-relay.php?operation=capabilities');
    const csrf = sessionCsrf(gt.jar.get('RosarioSIS'));
    assert.match(csrf, /^[a-f0-9]{64}$/, 'jeton CSRF gardien');
    const scan = (body) => gt.request('/scanner-relay.php?operation=scan', { method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-SchoolSafe-Csrf': csrf }, body: JSON.stringify(body) });

    await checkAsync('scan pickup => PICKUP_CHOICE + bouton Personnes autorisées', async () => {
      const r = await scan({ mode: 'pickup', qr_payload: qr(CARD), location_id: String(cpId) });
      assert.equal(r.status, 200);
      const d = JSON.parse(r.text).data;
      assert.equal(d.result, 'PICKUP_CHOICE');
      assert.equal(d.people.length, 1);
      assert.equal(d.people[0].role, 'Mère (principale)');
      assert.ok(d.people[0].photo_path, 'photo affichée');
      assert.ok('photo_url' in d, 'portrait élève conservé');
      return d.people[0].name;
    });

    let proof = '';
    await checkAsync('choix personne => preuve humaine, aucune sortie', async () => {
      const r = await scan({ mode: 'pickup', qr_payload: qr(CARD), location_id: String(cpId),
        authorized_person_id: 'g:' + gid });
      assert.equal(r.status, 200);
      const d = JSON.parse(r.text).data;
      assert.equal(d.result, 'NEED_HUMAN_CONFIRMATION');
      assert.match(d.proof_token, /^[a-f0-9]{64}$/);
      proof = d.proof_token;
      const n = sql(`SELECT COUNT(*) FROM entry_exit_student_records WHERE STUDENT_ID=1 AND TYPE='out'`
        + ` AND RECORDED_AT::date='${today}'`);
      assert.equal(n, '0');
      return 'preuve émise';
    });

    await checkAsync('confirmation humaine => sortie enregistrée (personne + staff)', async () => {
      const r = await scan({ mode: 'pickup', qr_payload: qr(CARD), location_id: String(cpId),
        proof_token: proof, human_confirmed: true });
      assert.equal(r.status, 200);
      const d = JSON.parse(r.text).data;
      assert.equal(d.result, 'CONFIRMED');
      assert.ok(d.record_id > 0);
      const c = sql(`SELECT COMMENTS FROM entry_exit_student_records WHERE ID=${d.record_id}`);
      assert.match(c, /Maman QA HTTP/);
      assert.match(c, /staff #/);
      return 'sortie ' + d.record_id;
    });

    await checkAsync('reconfirmation => idempotente, sans doublon', async () => {
      const r = await scan({ mode: 'pickup', qr_payload: qr(CARD), location_id: String(cpId),
        proof_token: proof, human_confirmed: true });
      assert.equal(JSON.parse(r.text).data.result, 'CONFIRMED');
      const n = sql(`SELECT COUNT(*) FROM entry_exit_student_records WHERE STUDENT_ID=1 AND TYPE='out'`
        + ` AND UNAUTHORIZED IS NULL AND RECORDED_AT::date='${today}'`);
      assert.equal(n, '1');
      return '1 seule sortie';
    });

    await checkAsync('personne inconnue => DENIED, sans sortie', async () => {
      const r = await scan({ mode: 'pickup', qr_payload: qr(CARD), location_id: String(cpId),
        authorized_person_id: 'a:999999' });
      assert.equal(JSON.parse(r.text).data.result, 'DENIED');
      return 'refus propre';
    });

    await checkAsync('retrait école => personne révoquée, choix refusé', async () => {
      const r = await manage({ action: 'school-retire', nativeStudentId: '1',
        guardianId: gid, reason: 'Départ', consent: 'Direction' });
      assert.equal(r.status, 403); // principal : transfert séparé, jamais de retrait simple
      return 'principal protégé';
    });

    const phash = execWeb(['php', '-r', 'require "/var/www/html/functions/Password.php"; echo encrypt_password("L1-Test-2026!");']).trim();
    const oldHash = sql(`SELECT PASSWORD FROM staff WHERE USERNAME='qa.parent'`);
    sql(`UPDATE staff SET PASSWORD='${phash}' WHERE USERNAME='qa.parent'`);
    try {
      const p = await loginAs('qa.parent');
      await checkAsync('tableau parent : sortie notifiée (student.pickup)', async () => {
        const r = await p.request('/parent-notifications.php');
        assert.equal(r.status, 200);
        const items = JSON.parse(r.text).data.notifications || [];
        assert.ok(items.some((n) => typeof n.id === 'string' && n.id.startsWith('departure:')), 'item departure: présent');
        return items.length + ' notification(s)';
      });
    } finally {
      sql(`UPDATE staff SET PASSWORD='${oldHash}' WHERE USERNAME='qa.parent'`);
    }
  } finally {
    const day = new Date().toISOString().slice(0, 10);
    sql(`DELETE FROM entry_exit_student_records WHERE STUDENT_ID=1 AND TYPE='out' AND RECORDED_AT::date='${day}'`);
    sql(`DELETE FROM schoolsafe_pickup_proofs WHERE STUDENT_ID=1`);
    sql(`DELETE FROM schoolsafe_pickup_authorizations WHERE STUDENT_ID=1`);
    sql(`DELETE FROM schoolsafe_pickup_guardians WHERE STUDENT_ID=1 AND FULL_NAME='Maman QA HTTP'`);
    sql(`DELETE FROM schoolsafe_student_cards WHERE CARD_NUMBER='${CARD}'`);
    sql(`DELETE FROM entry_exit_checkpoints WHERE TITLE='Portail QA Pickup'`);
    sql(`DELETE FROM profile_exceptions WHERE PROFILE_ID=2 AND MODNAME IN ('SchoolSafe/ScanPickup.php','Student_ID_Card/StudentIDCard.php')`);
    sql(`DELETE FROM profile_exceptions WHERE PROFILE_ID=1 AND MODNAME IN ('SchoolSafe/FamilyRegister.php','SchoolSafe/FamilyApprove.php','SchoolSafe/FamilyRead.php')`);
    sql(`DELETE FROM profile_exceptions WHERE PROFILE_ID=3 AND MODNAME='SchoolSafe/Notifications.php'`);
    sql(`DELETE FROM staff WHERE USERNAME IN ('${QAUSER}','${QAADMIN}')`);
    execWeb(['sh', '-c', `rm -f /var/www/html/assets/schoolsafe-pickup/qa-${STAMP}.jpg && rmdir /var/www/html/assets/schoolsafe-pickup 2>/dev/null; rm -f /etc/apache2/conf-enabled/pickup-test.conf && apache2ctl graceful`]);
  }

  console.log(failed === 0 ? `GREEN : ${passed} PASS.` : `STOP : ${failed} FAIL / ${passed} PASS.`);
  process.exit(failed === 0 ? 0 : 1);
})().catch((e) => { console.error('STOP : ' + e.message); process.exit(1); });
