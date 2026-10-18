/*
 * SchoolSafe — périodes scolaires incomplètes : le tableau de bord ne doit jamais
 * tomber en erreur technique.
 *
 * Défaut reproduit : une école dont les trimestres n'ont pas de semestre parent
 * (PARENT_ID NULL) faisait produire à GetAllMP() une liste contenant un élément
 * vide. PostgreSQL refusait alors `MARKING_PERIOD_ID IN('8','','12')` et le
 * directeur voyait « WE HAVE A PROBLEM, PLEASE CONTACT TECHNICAL SUPPORT » à la
 * place de son tableau de bord, avec la requête SQL affichée.
 *
 * Le test crée une école jetable réellement à plat (aucun parent), se connecte
 * comme son administrateur et vérifie que le portail se rend sans erreur, puis
 * supprime tout ce qu'il a créé. Il vérifie aussi qu'une école correctement
 * imbriquée continue de fonctionner.
 *
 * Usage : node app/qa-dashboard-periods.cjs
 * Variables : SCHOOLSAFE_L1_URL (défaut http://127.0.0.1:8081),
 *             SCHOOLSAFE_L1_DB (conteneur PostgreSQL, défaut schoolsafe-app-db-1).
 */
const assert = require('node:assert/strict');
const { execFileSync } = require('node:child_process');

const BASE = process.env.SCHOOLSAFE_L1_URL || 'http://127.0.0.1:8081';
const DB = process.env.SCHOOLSAFE_L1_DB || 'schoolsafe-app-db-1';
const PGUSER = 'schoolsafe';
const PGDATABASE = 'schoolsafe';
const PGPASSWORD = process.env.SCHOOLSAFE_L1_DB_PASSWORD || 'preview-local-jetable-32caracteres-xx';
const PASSWORD = 'L1-Test-2026!';

let passed = 0;
let failed = 0;

function sql(statement) {
  return execFileSync('docker', ['exec', '-e', 'PGPASSWORD=' + PGPASSWORD, DB,
    'psql', '-U', PGUSER, '-d', PGDATABASE, '-At', '-q', '-c', statement], { encoding: 'utf8' }).trim();
}

function firstId(output) {
  const ids = output.split('\n').map(line => line.trim()).filter(line => /^[0-9]+$/.test(line));
  assert.ok(ids.length, 'identifiant inséré renvoyé');
  return ids[ids.length - 1];
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
    const response = await fetch(BASE + path, { redirect: 'manual', ...options, headers });
    for (const raw of response.headers.getSetCookie ? response.headers.getSetCookie() : []) {
      const [pair] = raw.split(';');
      const index = pair.indexOf('=');
      const name = pair.slice(0, index).trim();
      const value = pair.slice(index + 1).trim();
      if (value === '' || /deleted/i.test(raw)) jar.delete(name); else jar.set(name, value);
    }
    return { status: response.status, location: response.headers.get('location') || '', body: await response.text() };
  }
  return {
    request,
    async login(username, password = PASSWORD) {
      await request('/index.php');
      return request('/index.php', { method: 'POST', form: { USERNAME: username, PASSWORD: password } });
    },
  };
}

async function check(name, fn) {
  try {
    const detail = await fn();
    ++passed;
    console.log('PASS ' + name + (detail ? ' — ' + detail : ''));
  } catch (error) {
    ++failed;
    console.log('FAIL ' + name + ': ' + String(error.message).split('\n')[0]);
  }
}

function assertCleanDashboard(body, label) {
  assert.doesNotMatch(body, /DB Execute Failed/i, label + ' : aucune erreur SQL affichée');
  assert.doesNotMatch(body, /WE HAVE A PROBLEM/i, label + ' : aucun écran de panne');
  assert.doesNotMatch(body, /invalid input syntax for type integer/i, label + ' : aucune erreur de type SQL');
}

(async () => {
  const stamp = Date.now();
  const username = 'qa.periodes.' + stamp;
  const schoolTitle = 'QA Periodes Plates ' + stamp;
  let schoolId = 0;
  let staffId = 0;

  try {
    // --- École jetable réellement à plat : aucun PARENT_ID -------------------
    schoolId = firstId(sql(`INSERT INTO schools (syear,title) VALUES (2026,'${schoolTitle}') RETURNING id`));

    sql(`INSERT INTO school_marking_periods
      (syear,mp,school_id,title,short_name,start_date,end_date,sort_order,parent_id)
      VALUES
      (2026,'FY',${schoolId},'Full Year','FY','2026-08-01','2027-06-30',NULL,NULL),
      (2026,'SEM',${schoolId},'Semester 1','S1','2026-08-01','2026-12-31',1,NULL),
      (2026,'SEM',${schoolId},'Semester 2','S2','2027-01-01','2027-06-30',2,NULL),
      (2026,'QTR',${schoolId},'Quarter 1','Q1','2026-08-01','2026-10-31',1,NULL),
      (2026,'QTR',${schoolId},'Quarter 2','Q2','2026-11-01','2026-12-31',2,NULL),
      (2026,'QTR',${schoolId},'Quarter 3','Q3','2027-01-01','2027-03-31',3,NULL),
      (2026,'QTR',${schoolId},'Quarter 4','Q4','2027-04-01','2027-06-30',4,NULL)`);

    // current_school_id renseigné : compte « déjà utilisé », comme un vrai
    // directeur, pour que le sélecteur de période soit résolu dès la requête.
    staffId = firstId(sql(`INSERT INTO staff
      (syear,first_name,last_name,username,password,profile,profile_id,schools,last_login,current_school_id)
      VALUES (2026,'QA','Periodes','${username}',
      (SELECT PASSWORD FROM staff WHERE USERNAME='l1.proprietaire' LIMIT 1),'admin',1,',${schoolId},',CURRENT_TIMESTAMP,${schoolId})
      RETURNING STAFF_ID`));

    const flat = sql(`SELECT COUNT(1) FROM school_marking_periods WHERE school_id=${schoolId} AND parent_id IS NULL`);
    assert.equal(flat, '7', 'précondition : les sept périodes sont bien à plat');

    await check('ecole a periodes plates : le portail du directeur se rend sans panne', async () => {
      const c = client();
      const login = await c.login(username);
      assert.ok(login.location.includes('modname='), 'connexion acceptée');
      const portal = await c.request('/Modules.php?modname=misc/Portal.php');
      assert.equal(portal.status, 200, 'portail accessible');
      assertCleanDashboard(portal.body, 'portail');
      return 'HTTP 200, aucune erreur SQL';
    });

    await check('ecole a periodes plates : le tableau de bord reste utile', async () => {
      const c = client();
      await c.login(username);
      const portal = await c.request('/Modules.php?modname=misc/Portal.php');
      assert.match(portal.body, /Courses/, 'les compteurs de scolarité sont présents');
      assert.match(portal.body, /Quarter [1-4]/, 'les trimestres restent sélectionnables');
      return 'compteurs + sélecteur de trimestre présents';
    });

    await check('ecole a periodes plates : les écrans dérivés ne cassent pas', async () => {
      const c = client();
      await c.login(username);
      const users = await c.request('/Modules.php?modname=Users/User.php');
      assert.equal(users.status, 200, 'liste des utilisateurs accessible');
      assertCleanDashboard(users.body, 'utilisateurs');
      return 'liste des utilisateurs rendue sans erreur';
    });

    await check('ecole correctement imbriquee : comportement inchangé', async () => {
      const c = client();
      await c.login('l1.proprietaire');
      const portal = await c.request('/Modules.php?modname=misc/Portal.php');
      assert.equal(portal.status, 200, 'portail du propriétaire accessible');
      assertCleanDashboard(portal.body, 'propriétaire');
      assert.match(portal.body, /Courses/, 'tableau de bord alimenté');
      return 'école 1 (périodes imbriquées) intacte';
    });

    await check('liste SQL sûre : aucune valeur vide ne peut être injectée', async () => {
      // GetAllMP() ne renvoie plus que des identifiants numériques ; une Liste
      // vide devient '0' (aucune ligne) et jamais IN('') qui casserait la requête.
      const c = client();
      await c.login(username);
      for (const url of [
        '/Modules.php?modname=Scheduling/Courses.php',
        '/Modules.php?modname=Users/User.php&search_modfunc=list&advanced=Y',
      ]) {
        const page = await c.request(url);
        assert.equal(page.status, 200, url + ' accessible');
        assertCleanDashboard(page.body, url);
      }
      return 'cours + liste utilisateurs sans erreur';
    });
  } finally {
    if (staffId) sql(`DELETE FROM staff WHERE STAFF_ID=${staffId}`);
    if (schoolId) {
      sql(`DELETE FROM school_marking_periods WHERE school_id=${schoolId}`);
      sql(`DELETE FROM schools WHERE id=${schoolId}`);
    }
  }

  console.log(JSON.stringify({ passed, failed, base: BASE, school: schoolTitle }));
  process.exitCode = failed ? 1 : 0;
})().catch(error => {
  console.error(error);
  process.exitCode = 1;
});
