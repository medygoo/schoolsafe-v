/*
 * SchoolSafe — multi-ecoles : etat le plus restrictif, detail par ecole, et
 * suspension ciblee qui ne coupe pas les autres ecoles du compte.
 *
 * Verification reelle sur la pile Docker (Apache + PHP 8.3 + PostgreSQL) :
 *   - bridge `list-admins` : etat le plus restrictif + detail ecole par ecole ;
 *   - connexion : une ecole suspendue ne bloque pas les autres ecoles du compte ;
 *   - garde de session : la revocation d'une ecole ne coupe pas la session
 *     ouverte dans une autre ecole ;
 *   - changement d'ecole vers une ecole suspendue : refuse, session conservee ;
 *   - toutes les ecoles coupees, ou ligne « toutes les ecoles » coupee : refus ;
 *   - permissions : bridge sans jeton = 401, aucune donnee d'une ecole coupee.
 *
 * Usage : node app/qa-multi-school-bridge.cjs
 * Variables : SCHOOLSAFE_L1_URL (defaut http://127.0.0.1:8081),
 *             SCHOOLSAFE_L1_DB (defaut schoolsafe-app-db-1),
 *             SCHOOLSAFE_L1_CONTAINER (defaut schoolsafe-l1-test).
 */
const assert = require('node:assert/strict');
const { execFileSync } = require('node:child_process');
const crypto = require('node:crypto');

const BASE = process.env.SCHOOLSAFE_L1_URL || 'http://127.0.0.1:8081';
const DB = process.env.SCHOOLSAFE_L1_DB || 'schoolsafe-app-db-1';
const APP = process.env.SCHOOLSAFE_L1_CONTAINER || 'schoolsafe-l1-test';
const PGUSER = 'schoolsafe';
const PGDATABASE = 'schoolsafe';
const PGPASSWORD = process.env.SCHOOLSAFE_L1_DB_PASSWORD || 'preview-local-jetable-32caracteres-xx';
const PASSWORD = 'L1-Test-2026!';
const BRIDGE_PORT = 8099;
const TOKEN = 'qa-multi-' + crypto.randomBytes(8).toString('hex');
const MULTI = 'qa.multi.' + Date.now();

let passed = 0, failed = 0;

function sql(statement) {
  return execFileSync('docker', ['exec', '-e', 'PGPASSWORD=' + PGPASSWORD, DB,
    'psql', '-U', PGUSER, '-d', PGDATABASE, '-At', '-c', statement], { encoding: 'utf8' }).trim();
}

function check(name, fn) {
  try {
    const result = fn();
    ++passed;
    console.log('PASS ' + name + (result ? ' — ' + result : ''));
  } catch (error) {
    ++failed;
    console.log('FAIL ' + name + ': ' + String(error.message).split('\n')[0]);
  }
}

async function checkAsync(name, fn) {
  try {
    const result = await fn();
    ++passed;
    console.log('PASS ' + name + (result ? ' — ' + result : ''));
  } catch (error) {
    ++failed;
    console.log('FAIL ' + name + ': ' + String(error.message).split('\n')[0]);
  }
}

function sleep(ms) {
  Atomics.wait(new Int32Array(new SharedArrayBuffer(4)), 0, 0, ms);
}

/**
 * Client HTTP minimal conservant le cookie de session, sans suivi de redirection.
 */
function client() {
  const jar = new Map();
  const cookieHeader = () => [...jar].map(([k, v]) => k + '=' + v).join('; ');

  async function request(path, options = {}) {
    const headers = { ...(options.headers || {}) };
    if (jar.size) headers.Cookie = cookieHeader();
    if (options.form) {
      headers['Content-Type'] = 'application/x-www-form-urlencoded';
      options = { ...options, body: new URLSearchParams(options.form).toString() };
    }
    const response = await fetch(BASE + path, { redirect: 'manual', ...options, headers });
    for (const raw of response.headers.getSetCookie ? response.headers.getSetCookie() : []) {
      const [pair] = raw.split(';');
      const index = pair.indexOf('=');
      const name = pair.slice(0, index).trim(), value = pair.slice(index + 1).trim();
      if (value === '' || /deleted/i.test(raw)) jar.delete(name); else jar.set(name, value);
    }
    const body = await response.text();
    return { status: response.status, location: response.headers.get('location') || '', body };
  }

  return {
    request,
    async login(username, password = PASSWORD) {
      await request('/index.php'); // cookie de session requis par la connexion native
      return request('/index.php', { method: 'POST', form: { USERNAME: username, PASSWORD: password } });
    },
    hasSession: () => jar.size > 0,
  };
}

/** Pont : le serveur PHP integre tourne DANS le conteneur, curl aussi. */
function bridgeCall(action, options = {}) {
  const args = ['exec', APP, 'curl', '-s', '-m', '20'];
  if (options.token !== false) args.push('-H', 'X-SchoolSafe-Console-Token: ' + (options.token || TOKEN));
  if (options.method === 'POST') {
    args.push('-X', 'POST', '-H', 'Content-Type: application/json',
      '--data-binary', JSON.stringify(options.payload || {}));
  }
  args.push('-w', '\n%{http_code}', 'http://127.0.0.1:' + BRIDGE_PORT + '/admin-console-bridge.php' + action);
  const raw = execFileSync('docker', args, { encoding: 'utf8' });
  const cut = raw.lastIndexOf('\n');
  return { status: Number(raw.slice(cut + 1).trim()), body: raw.slice(0, cut), json: (() => {
    try { return JSON.parse(raw.slice(0, cut)); } catch { return null; }
  })() };
}

function killBridge() {
  try {
    execFileSync('docker', ['exec', APP, 'sh', '-c',
      // Ancre sur le processus php : sinon le shell qui porte la commande
      // contient le motif et se tue avant d'arreter le serveur.
      'for p in $(pgrep -f "^php -S 127.0.0.1:' + BRIDGE_PORT + '"); do kill $p 2>/dev/null || true; done']);
  } catch { /* rien a arreter */ }
}

function startBridge() {
  killBridge();
  sleep(300);
  execFileSync('docker', ['exec', '-d', '-e', 'SCHOOLSAFE_CONSOLE_TOKEN=' + TOKEN, APP,
    'php', '-S', '127.0.0.1:' + BRIDGE_PORT, '-t', '/var/www/html']);
  for (let attempt = 0; attempt < 40; attempt++) {
    try {
      if (bridgeCall('?action=list-admins').status === 200) return;
    } catch { /* demarrage en cours */ }
    sleep(250);
  }
  throw new Error('bridge indisponible sur 127.0.0.1:' + BRIDGE_PORT);
}

function stopBridge() {
  killBridge();
}

function setState(schoolId, state, until = null) {
  sql(`INSERT INTO schoolsafe_account_state (PERSON_KEY,SCHOOL_ID,STATE,REASON,UNTIL)
       VALUES ('${MULTI}',${schoolId},'${state}','QA multi-ecoles',
       ${until === null ? 'NULL' : "'" + until + "'"})
       ON CONFLICT (PERSON_KEY,SCHOOL_ID)
       DO UPDATE SET STATE=EXCLUDED.STATE,UNTIL=EXCLUDED.UNTIL,REASON=EXCLUDED.REASON`);
}

function clearStates() {
  sql(`DELETE FROM schoolsafe_account_state WHERE PERSON_KEY='${MULTI}'`);
}

function dropSchool(schoolId) {
  const id = Number(schoolId);
  if (!Number.isInteger(id) || id < 1) return;
  for (const statement of [
    `DELETE FROM config WHERE SCHOOL_ID=${id}`,
    `DELETE FROM school_gradelevels WHERE SCHOOL_ID=${id}`,
    `DELETE FROM school_periods WHERE SCHOOL_ID=${id}`,
    `DELETE FROM school_marking_periods WHERE SCHOOL_ID=${id}`,
    `DELETE FROM schoolsafe_school_state WHERE SCHOOL_ID=${id}`,
    `DELETE FROM schoolsafe_school_leadership WHERE SCHOOL_ID=${id}`,
    `DELETE FROM schools WHERE ID=${id}`,
  ]) sql(statement);
}

function adminRow() {
  const response = bridgeCall('?action=list-admins');
  assert.equal(response.status, 200, 'list-admins repond 200');
  assert.equal(response.json && response.json.ok, true, 'reponse ok');
  const rows = Object.values(response.json.data);
  const row = rows.find(entry => entry.USERNAME === MULTI);
  assert.ok(row, 'le compte multi-ecoles figure dans list-admins');
  return row;
}

function schoolStates(row) {
  assert.ok(Array.isArray(row.SCHOOL_STATES), 'SCHOOL_STATES est un tableau');
  return row.SCHOOL_STATES.map(entry => entry.school_id + ':' + entry.state).sort();
}

(async () => {
  // --- Fixtures deterministes ---------------------------------------------
  const passwordHash = sql("SELECT PASSWORD FROM staff WHERE USERNAME='l1.proprietaire' LIMIT 1");
  assert.ok(passwordHash, 'fixture l1.proprietaire avec mot de passe connu');
  const school1 = sql("SELECT ID FROM schools WHERE SYEAR='2026' ORDER BY ID LIMIT 1");
  const school2 = sql("SELECT ID FROM schools WHERE SYEAR='2026' ORDER BY ID OFFSET 1 LIMIT 1");
  const title1 = sql(`SELECT TITLE FROM schools WHERE SYEAR='2026' AND ID=${school1}`);
  const title2 = sql(`SELECT TITLE FROM schools WHERE SYEAR='2026' AND ID=${school2}`);
  assert.match(school1, /^[0-9]+$/);
  assert.match(school2, /^[0-9]+$/, 'deux ecoles doivent exister');

  sql(`DELETE FROM staff WHERE SYEAR='2026' AND USERNAME='${MULTI}'`);
  // last_login renseigne : pas de changement de mot de passe force a la
  // premiere connexion, le test porte sur l'etat multi-ecoles.
  sql(`INSERT INTO staff (syear,first_name,last_name,username,password,profile,profile_id,schools,current_school_id,last_login)
       VALUES (2026,'QA','Multi Ecoles','${MULTI}','${passwordHash}','admin',1,
       ',${school1},${school2},',${school1},CURRENT_TIMESTAMP)`);
  const staffId = sql(`SELECT STAFF_ID FROM staff WHERE SYEAR='2026' AND USERNAME='${MULTI}'`);
  assert.match(staffId, /^[0-9]+$/);

  startBridge();

  try {
    // --- 1. Permissions du pont ------------------------------------------
    await checkAsync('bridge sans jeton : 401 (aucune liste exposee)', () => {
      const response = bridgeCall('?action=list-admins', { token: false });
      assert.equal(response.status, 401);
      assert.equal(response.json.ok, false);
      return '401 TOKEN_REQUIRED';
    });

    await checkAsync('bridge avec un mauvais jeton : 401', () => {
      const response = bridgeCall('?action=list-admins', { token: 'faux-jeton' });
      assert.equal(response.status, 401);
      return '401';
    });

    // --- 2. list-admins : etat le plus restrictif + detail par ecole ------
    await checkAsync('list-admins : ecole 1 suspendue, ecole 2 active => etat suspendu + detail par ecole', () => {
      clearStates();
      setState(Number(school1), 'suspended');
      setState(Number(school2), 'active');
      const row = adminRow();
      assert.equal(row.STATE, 'suspended', 'etat le plus restrictif attendu');
      assert.deepEqual(schoolStates(row), [school1 + ':suspended', school2 + ':active']);
      assert.equal(row.STATE_SOURCE, 'account_state');
      return 'STATE=suspended, ' + school1 + ':suspended / ' + school2 + ':active';
    });

    await checkAsync('list-admins : ecole 1 active, ecole 2 suspendue => etat suspendu (pas le premier trouve)', () => {
      clearStates();
      setState(Number(school1), 'active');
      setState(Number(school2), 'suspended');
      const row = adminRow();
      assert.equal(row.STATE, 'suspended', 'le pire etat prime, quelle que soit l\'ordre des ecoles');
      assert.deepEqual(schoolStates(row), [school1 + ':active', school2 + ':suspended']);
      return 'STATE=suspended, ' + school1 + ':active / ' + school2 + ':suspended';
    });

    await checkAsync('list-admins : lecture seule dans une ecole => etat lecture seule', () => {
      clearStates();
      setState(Number(school1), 'readonly');
      setState(Number(school2), 'active');
      const row = adminRow();
      assert.equal(row.STATE, 'readonly');
      assert.deepEqual(schoolStates(row), [school1 + ':readonly', school2 + ':active']);
      return 'STATE=readonly';
    });

    await checkAsync('list-admins : ligne « toutes les ecoles » suspendue => compte suspendu partout', () => {
      clearStates();
      setState(0, 'suspended');
      const row = adminRow();
      assert.equal(row.STATE, 'suspended');
      assert.ok(row.SCHOOL_STATES.some(entry => entry.school_id === 0 && entry.state === 'suspended'),
        'la ligne globale figure dans le detail');
      assert.deepEqual(schoolStates(row), ['0:suspended', school1 + ':suspended', school2 + ':suspended']);
      return 'etat global suspendu propage aux ecoles';
    });

    // --- 2 ter. Bridge : creation d'ecole (logique partagee) -------------
    await checkAsync('bridge create-school : ecole reelle, calendrier et identite copies', () => {
      const title = 'QA Bridge ' + Date.now();
      const response = bridgeCall('', {
        method: 'POST',
        payload: {
          action: 'create-school', title, short_name: 'QAB',
          email: 'qa.bridge@test.local', slogan: 'Verification bridge',
          template_school_id: school1,
        },
      });
      assert.equal(response.status, 201, 'reponse 201 attendue');
      const created = Number(response.json.data.school_id);
      assert.ok(created > 0, 'identifiant retourne');
      try {
        assert.ok(Number(sql(`SELECT count(*) FROM school_gradelevels WHERE SCHOOL_ID=${created}`)) > 0,
          'niveaux copies');
        assert.ok(Number(sql(`SELECT count(*) FROM school_marking_periods WHERE SCHOOL_ID=${created}`)) > 0,
          'periodes de notation copiees');
        assert.equal(sql(`SELECT CONFIG_VALUE FROM config WHERE SCHOOL_ID=${created} AND TITLE='SCHOOLSAFE_SCHOOL_SLOGAN'`),
          'Verification bridge', 'slogan enregistre');
      } finally {
        dropSchool(created);
      }
      return 'ecole ' + created + ' creee puis nettoyee';
    });

    await checkAsync('bridge create-school : identite manquante => 400, aucune ecole', () => {
      const before = Number(sql("SELECT count(*) FROM schools WHERE SYEAR='2026'"));
      const response = bridgeCall('', {
        method: 'POST',
        payload: { action: 'create-school', title: 'QA Bridge Sans Identite ' + Date.now(), email: 'invalide', slogan: '' },
      });
      assert.equal(response.status, 400);
      assert.equal(response.json.error, 'IDENTITY_REQUIRED');
      assert.equal(Number(sql("SELECT count(*) FROM schools WHERE SYEAR='2026'")), before, 'aucune ecriture');
      return '400 + aucune ecriture';
    });

    // --- 2 bis. Console native PRODELI (admin-console.php) --------------
    async function ownerConsole() {
      const owner = client();
      const login = await owner.login('l1.proprietaire');
      assert.match(login.location, /modname=/, 'proprietaire connecte');
      const page = await owner.request('/admin-console.php');
      assert.equal(page.status, 200, 'console accessible au proprietaire');
      const csrf = (page.body.match(/name="console_token" value="([^"]+)"/) || [])[1];
      assert.ok(csrf, 'jeton CSRF de session present');
      return { owner, csrf };
    }

    await checkAsync('console native : etat le plus restrictif + detail ecole par ecole', async () => {
      clearStates();
      setState(Number(school1), 'suspended');
      setState(Number(school2), 'active');
      const { owner } = await ownerConsole();
      const page = await owner.request('/admin-console.php');
      assert.equal(page.status, 200);
      const html = page.body.replace(/\s+/g, ' ');
      assert.match(html, new RegExp(MULTI), 'le compte figure dans la console');
      assert.ok(html.includes(title1 + ' : Suspendu'), 'detail : ecole 1 suspendue');
      assert.ok(html.includes(title2 + ' : Actif'), 'detail : ecole 2 active');
      return 'etat + detail par ecole affiches';
    });

    await checkAsync('console native : suspension ciblee sur l\'ecole 1 laisse l\'ecole 2 ouverte', async () => {
      clearStates();
      setState(Number(school2), 'active');
      const { owner, csrf } = await ownerConsole();
      const action = await owner.request('/admin-console.php', {
        method: 'POST',
        form: {
          console_token: csrf, console_action: 'state', staff_id: staffId,
          state: 'suspended', state_school_id: school1, reason: 'QA ciblee',
        },
      });
      assert.equal(action.status, 302, 'action acceptee (redirection)');
      assert.equal(sql(`SELECT STATE FROM schoolsafe_account_state WHERE PERSON_KEY='${MULTI}' AND SCHOOL_ID=${school1}`),
        'suspended', 'etat suspendu sur l\'ecole 1');
      assert.equal(sql(`SELECT STATE FROM schoolsafe_account_state WHERE PERSON_KEY='${MULTI}' AND SCHOOL_ID=0`),
        '', 'aucune ligne globale creee (suspension ciblee)');
      const c = client();
      const login = await c.login(MULTI);
      assert.match(login.location, /modname=/, 'connexion toujours acceptee : ecole 2 ouverte');
      return 'ecole 1 suspendue, ecole 2 utilisable';
    });

    await checkAsync('console native : ecole hors du compte refusee (aucune ecriture)', async () => {
      clearStates();
      const { owner, csrf } = await ownerConsole();
      const foreign = 9999;
      const action = await owner.request('/admin-console.php', {
        method: 'POST',
        form: {
          console_token: csrf, console_action: 'state', staff_id: staffId,
          state: 'suspended', state_school_id: foreign,
        },
      });
      assert.equal(action.status, 200, 'refus affiche sans redirection');
      assert.match(action.body, /ne fait pas partie du compte/i, 'refus explicite');
      assert.equal(sql(`SELECT count(*) FROM schoolsafe_account_state WHERE PERSON_KEY='${MULTI}' AND SCHOOL_ID=${foreign}`),
        '0', 'aucune ligne creee pour l\'ecole etrangere');
      return 'refus + aucune ecriture';
    });

    // --- 3. Connexion : une ecole suspendue ne bloque pas les autres ------
    await checkAsync('connexion : suspendu dans l\'ecole 1, actif dans l\'ecole 2 => connexion acceptee', async () => {
      clearStates();
      setState(Number(school1), 'suspended');
      setState(Number(school2), 'active');
      const c = client();
      const response = await c.login(MULTI);
      assert.match(response.location, /modname=/, 'connexion acceptee et redirigee vers le portail');
      assert.equal(c.hasSession(), true);
      return 'redirection ' + response.location.replace(BASE, '');
    });

    await checkAsync('connexion : portail accessible dans l\'ecole restee ouverte', async () => {
      clearStates();
      setState(Number(school1), 'suspended');
      setState(Number(school2), 'active');
      const c = client();
      const login = await c.login(MULTI);
      assert.match(login.location, /modname=/, 'connexion acceptee');
      const portal = await c.request('/Modules.php?modname=misc/Portal.php');
      assert.equal(portal.status, 200, 'portail servi');
      assert.doesNotMatch(portal.location, /reason=account_/, 'aucune coupure de session');
      return 'portail 200';
    });

    // --- 4. Revocation ciblee : la session d'une autre ecole survit -------
    await checkAsync('suspension de l\'ecole 1 pendant une session ouverte dans l\'ecole 2 => session conservee', async () => {
      clearStates();
      const c = client();
      const login = await c.login(MULTI);
      assert.match(login.location, /modname=/, 'connexion initiale');
      // L'ecole courante doit etre l'ecole 2 : le selecteur n'est pas encore
      // utilise, la premiere ecole du compte est l'ecole 1 (suspendue apres).
      const selected = await c.request('/Modules.php?modname=misc/Portal.php&school=' + school2);
      assert.equal(selected.status, 200, 'ecole 2 selectionnee et servie');

      setState(Number(school1), 'suspended'); // incremente l'epoque de l'ecole 1
      const after = await c.request('/Modules.php?modname=misc/Portal.php');
      assert.doesNotMatch(after.location, /reason=account_/, 'session non coupee par une autre ecole');
      assert.equal(after.status, 200, 'portail toujours servi (aucune redirection)');
      return 'epoque de l\'ecole 1 sans effet sur la session de l\'ecole 2';
    });

    // --- 5. Changement d'ecole vers une ecole suspendue -------------------
    await checkAsync('changement vers l\'ecole suspendue : refuse, session de l\'ecole ouverte conservee', async () => {
      clearStates();
      setState(Number(school1), 'suspended');
      setState(Number(school2), 'active');
      const c = client();
      assert.match((await c.login(MULTI)).location, /modname=/, 'connexion');
      // Le selecteur natif poste sidefunc=update avec l'ecole choisie.
      const switched = await c.request('/Modules.php?modname=misc/Portal.php', {
        method: 'POST', form: { sidefunc: 'update', school: school1 },
      });
      assert.equal(switched.status, 403, 'l\'ecole suspendue est refusee (403)');
      const back = await c.request('/Modules.php?modname=misc/Portal.php');
      assert.equal(back.status, 200, 'session toujours valide dans l\'ecole ouverte');
      return 'changement refuse (403), session conservee';
    });

    // --- 6. Toutes les ecoles coupees, et ligne globale ------------------
    await checkAsync('toutes les ecoles suspendues => connexion refusee', async () => {
      clearStates();
      setState(Number(school1), 'suspended');
      setState(Number(school2), 'suspended');
      const c = client();
      const refused = await c.login(MULTI);
      assert.match(refused.body, /suspendu/i, 'message de suspension');
      assert.doesNotMatch(refused.location, /modname=/, 'aucune session ouverte');
      return 'refus';
    });

    await checkAsync('ligne « toutes les ecoles » suspendue => connexion refusee malgre des ecoles actives', async () => {
      clearStates();
      setState(0, 'suspended');
      setState(Number(school1), 'active');
      setState(Number(school2), 'active');
      const c = client();
      const refused = await c.login(MULTI);
      assert.match(refused.body, /suspendu/i);
      assert.doesNotMatch(refused.location, /modname=/);
      return 'refus';
    });

    // --- 7. Permissions : aucune donnee d'une ecole coupee ---------------
    await checkAsync('donnees de l\'ecole suspendue inaccessibles depuis la session de l\'ecole ouverte', async () => {
      clearStates();
      setState(Number(school1), 'suspended');
      setState(Number(school2), 'active');
      const c = client();
      assert.match((await c.login(MULTI)).location, /modname=/, 'connexion');
      const direct = await c.request('/Modules.php?modname=misc/Portal.php', {
        method: 'POST', form: { sidefunc: 'update', school: school1 },
      });
      assert.equal(direct.status, 403, 'passage dans l\'ecole suspendue refuse');
      const portal = await c.request('/Modules.php?modname=misc/Portal.php');
      assert.equal(portal.status, 200, 'session toujours valide dans l\'ecole ouverte');
      return 'ecole coupee non servie (403), session intacte';
    });
  } finally {
    stopBridge();
    clearStates();
    sql(`DELETE FROM schoolsafe_account_audit WHERE PERSON_KEY='${MULTI}'`);
    sql(`DELETE FROM staff WHERE SYEAR='2026' AND USERNAME='${MULTI}'`);
  }

  console.log(JSON.stringify({ passed, failed, base: BASE, account: MULTI,
    proof: 'pile Docker reelle (Apache/PHP/PostgreSQL), bridge PHP integre' }));
  process.exitCode = failed ? 1 : 0;
})().catch(error => {
  console.error(error);
  stopBridge();
  process.exitCode = 1;
});
