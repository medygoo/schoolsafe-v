/*
 * SchoolSafe — gestion d'ecole et direction d'ecole (console native).
 *
 * Verification reelle sur la pile Docker (Apache + PHP 8.3 + PostgreSQL) :
 *   - migration 018 : table de direction, un seul directeur principal ;
 *   - console proprietaire : une seule porte de creation, vers le parcours
 *   - principal d'office quand l'ecole n'en a aucun, adjoint accepte ensuite ;
 *   - nomination / retrait du directeur principal, un seul principal a la fois ;
 *   - gabarit au calendrier incoherent refuse (aucune ecole partielle) ;
 *   - permissions : un directeur ne cree ni ecole ni directeur ;
 *   - filtre par ecole dans la liste des comptes.
 *
 * Usage : node app/qa-school-leadership.cjs
 * Variables : SCHOOLSAFE_L1_URL (defaut http://127.0.0.1:8081),
 *             SCHOOLSAFE_L1_DB (defaut schoolsafe-app-db-1),
 *             SCHOOLSAFE_L1_CONTAINER (defaut schoolsafe-l1-test).
 */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { execFileSync } = require('node:child_process');

const BASE = process.env.SCHOOLSAFE_L1_URL || 'http://127.0.0.1:8081';
const DB = process.env.SCHOOLSAFE_L1_DB || 'schoolsafe-app-db-1';
const PGUSER = 'schoolsafe';
const PGDATABASE = 'schoolsafe';
const PGPASSWORD = process.env.SCHOOLSAFE_L1_DB_PASSWORD || 'preview-local-jetable-32caracteres-xx';
const PASSWORD = 'L1-Test-2026!';
const STAMP = Date.now();
const SCHOOL_TITLE = 'QA Ecole ' + STAMP;
const SCHOOL2_TITLE = 'QA Gabarit ' + STAMP;
const DIRECTOR = 'qa.directeur.' + STAMP;
const ADJOINT = 'qa.adjoint.' + STAMP;

let passed = 0, failed = 0;

function sql(statement) {
  return execFileSync('docker', ['exec', '-e', 'PGPASSWORD=' + PGPASSWORD, DB,
    'psql', '-U', PGUSER, '-d', PGDATABASE, '-At', '-c', statement], { encoding: 'utf8' }).trim();
}

function sqlInput(statement) {
  return execFileSync('docker', ['exec', '-i', '-e', 'PGPASSWORD=' + PGPASSWORD, DB,
    'psql', '-U', PGUSER, '-d', PGDATABASE, '-At'], { input: statement, encoding: 'utf8' }).trim();
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
      await request('/index.php');
      return request('/index.php', { method: 'POST', form: { USERNAME: username, PASSWORD: password } });
    },
  };
}

/** Console ouverte par le proprietaire de la plateforme, jeton CSRF inclus. */
async function ownerConsole() {
  const owner = client();
  const login = await owner.login('l1.proprietaire');
  assert.match(login.location, /modname=/, 'proprietaire connecte');
  const page = await owner.request('/admin-console.php');
  assert.equal(page.status, 200, 'console accessible');
  const csrf = (page.body.match(/name="console_token" value="([^"]+)"/) || [])[1];
  assert.ok(csrf, 'jeton CSRF present');
  return { owner, csrf, page: page.body };
}

function dropSchool(schoolId) {
  const id = Number(schoolId);
  if (!Number.isInteger(id) || id < 1) return;
  sql(`DELETE FROM config WHERE SCHOOL_ID=${id}`);
  sql(`DELETE FROM school_gradelevels WHERE SCHOOL_ID=${id}`);
  sql(`DELETE FROM school_periods WHERE SCHOOL_ID=${id}`);
  sql(`DELETE FROM school_marking_periods WHERE SCHOOL_ID=${id}`);
  sql(`DELETE FROM schoolsafe_school_state WHERE SCHOOL_ID=${id}`);
  sql(`DELETE FROM schoolsafe_school_leadership WHERE SCHOOL_ID=${id}`);
  sql(`DELETE FROM schools WHERE ID=${id}`);
}

function dropStaff(username) {
  sql(`DELETE FROM schoolsafe_account_audit WHERE PERSON_KEY='${username}'`);
  sql(`DELETE FROM schoolsafe_account_state WHERE PERSON_KEY='${username}'`);
  sql(`DELETE FROM schoolsafe_school_leadership WHERE STAFF_ID IN (SELECT STAFF_ID FROM staff WHERE USERNAME='${username}')`);
  sql(`DELETE FROM staff WHERE USERNAME='${username}'`);
}

(async () => {
  const createdSchools = [];

  // --- Migration 018 ------------------------------------------------------
  const migration = path.join(__dirname, 'modules', 'codex-ui-v1', 'extensions-test-runtime',
    'schoolsafe-remediation', '018-school-leadership.sql');

  check('migration 018 appliquee (idempotente) : table de direction presente', () => {
    sqlInput(fs.readFileSync(migration, 'utf8'));
    const exists = sql("SELECT 1 FROM information_schema.tables WHERE table_name='schoolsafe_school_leadership'");
    assert.equal(exists, '1');
    sqlInput(fs.readFileSync(migration, 'utf8')); // deuxieme passage : doit rester sans erreur
    return 'table + index unique principal';
  });

  const templateId = sql("SELECT ID FROM schools WHERE SYEAR='2026' ORDER BY ID LIMIT 1");
  assert.match(templateId, /^[0-9]+$/, 'ecole gabarit disponible');

  try {
    // --- Cartes et filtre dans la console ---------------------------------
    await checkAsync('console proprietaire : une seule porte de creation, vers le parcours guide', async () => {
      const { page } = await ownerConsole();
      assert.match(page, /schoolsafe-administrators\.php\?wizard=1/, 'lien vers le parcours guide');
      assert.doesNotMatch(page, /console_action" value="school_create"/, 'aucun second formulaire d ecole');
      assert.doesNotMatch(page, /console_action" value="school_director_create"/, 'aucun second formulaire de directeur');
      assert.match(page, /school_filter/, 'filtre par ecole');
      return 'une carte + filtre';
    });

    await checkAsync('un directeur ne voit pas la carte de creation', async () => {
      const c = client();
      assert.match((await c.login('l1.directeur2')).location, /modname=/, 'directeur connecte');
      const page = await c.request('/admin-console.php');
      assert.equal(page.status, 200);
      assert.doesNotMatch(page.body, /schoolsafe-administrators\.php\?wizard=1/);
      return 'aucune carte de creation';
    });

    // --- Creation d'ecole ---------------------------------------------------
    let newSchoolId = 0;

    await checkAsync('creation d\'ecole : ecole reelle, calendrier complet, identite enregistree', async () => {
      const { owner, csrf } = await ownerConsole();
      const action = await owner.request('/admin-console.php', {
        method: 'POST',
        form: {
          console_token: csrf, console_action: 'school_create',
          title: SCHOOL_TITLE, short_name: 'QA' + STAMP.toString().slice(-4),
          email: 'qa.ecole.' + STAMP + '@test.local', slogan: 'Ecole de verification QA',
          address: '1 rue QA', city: 'Kinshasa', phone: '+243 000',
          template_school_id: templateId,
        },
      });
      assert.equal(action.status, 302, 'creation acceptee (redirection)');

      newSchoolId = Number(sql(`SELECT ID FROM schools WHERE SYEAR='2026' AND TITLE='${SCHOOL_TITLE}'`));
      assert.ok(newSchoolId > 0, 'ecole creee');
      createdSchools.push(newSchoolId);

      const levels = Number(sql(`SELECT count(*) FROM school_gradelevels WHERE SCHOOL_ID=${newSchoolId}`));
      const periods = Number(sql(`SELECT count(*) FROM school_periods WHERE SCHOOL_ID=${newSchoolId}`));
      const marking = Number(sql(`SELECT count(*) FROM school_marking_periods WHERE SCHOOL_ID=${newSchoolId}`));
      const parents = Number(sql(`SELECT count(*) FROM school_marking_periods WHERE SCHOOL_ID=${newSchoolId} AND PARENT_ID IS NOT NULL`));
      assert.ok(levels > 0, 'niveaux recopies');
      assert.ok(periods > 0, 'periodes recopiees');
      assert.ok(marking > 0, 'periodes de notation recopiees');
      assert.ok(parents > 0, 'trimestres rattaches a un semestre');
      assert.equal(sql(`SELECT CONFIG_VALUE FROM config WHERE SCHOOL_ID=${newSchoolId} AND TITLE='SCHOOLSAFE_SCHOOL_EMAIL'`),
        'qa.ecole.' + STAMP + '@test.local', 'email de l\'ecole enregistre');
      assert.equal(sql(`SELECT CONFIG_VALUE FROM config WHERE SCHOOL_ID=${newSchoolId} AND TITLE='SCHOOLSAFE_SCHOOL_SLOGAN'`),
        'Ecole de verification QA', 'slogan enregistre');

      const after = await owner.request('/admin-console.php');
      assert.match(after.body.replace(/\s+/g, ' '), /Ecole creee/);
      return 'ID ' + newSchoolId + ', ' + levels + ' niveaux, ' + marking + ' periodes de notation';
    });

    await checkAsync('creation d\'ecole : identite manquante refusee, aucune ecole creee', async () => {
      const { owner, csrf } = await ownerConsole();
      const before = Number(sql("SELECT count(*) FROM schools WHERE SYEAR='2026'"));
      const action = await owner.request('/admin-console.php', {
        method: 'POST',
        form: {
          console_token: csrf, console_action: 'school_create',
          title: 'QA Sans Identite ' + STAMP, email: 'invalide',
          slogan: '', template_school_id: templateId,
        },
      });
      assert.equal(action.status, 200, 'refus affiche');
      assert.match(action.body, /Email valide et slogan obligatoires/);
      assert.equal(Number(sql("SELECT count(*) FROM schools WHERE SYEAR='2026'")), before, 'aucune ecriture');
      return 'refus explicite';
    });

    await checkAsync('gabarit au calendrier incoherent : creation refusee, rien de partiel', async () => {
      const { owner, csrf } = await ownerConsole();

      // Deuxieme ecole, puis on casse son calendrier (periodes de notation
      // supprimees) : elle devient un gabarit dangereux.
      const created = await owner.request('/admin-console.php', {
        method: 'POST',
        form: {
          console_token: csrf, console_action: 'school_create',
          title: SCHOOL2_TITLE, short_name: 'QAG',
          email: 'qa.gabarit.' + STAMP + '@test.local', slogan: 'Gabarit volontairement casse',
          template_school_id: templateId,
        },
      });
      assert.equal(created.status, 302);

      const brokenId = Number(sql(`SELECT ID FROM schools WHERE SYEAR='2026' AND TITLE='${SCHOOL2_TITLE}'`));
      assert.ok(brokenId > 0);
      createdSchools.push(brokenId);

      sql(`DELETE FROM school_marking_periods WHERE SCHOOL_ID=${brokenId}`);

      const before = Number(sql("SELECT count(*) FROM schools WHERE SYEAR='2026'"));
      const action = await owner.request('/admin-console.php', {
        method: 'POST',
        form: {
          console_token: csrf, console_action: 'school_create',
          title: 'QA Depuis Gabarit Casse ' + STAMP,
          email: 'qa.casse.' + STAMP + '@test.local', slogan: 'Ne doit pas naitre',
          template_school_id: brokenId,
        },
      });
      assert.equal(action.status, 200, 'refus affiche sans redirection');
      assert.match(action.body, /calendrier incoherent/i);
      assert.equal(Number(sql("SELECT count(*) FROM schools WHERE SYEAR='2026'")), before,
        'aucune ecole creee depuis le gabarit casse');
      return 'refus + aucune ecriture';
    });

    // --- Creation d'un directeur d'ecole -----------------------------------
    await checkAsync('creation directeur : profil Directeur, ecole unique, mot de passe temporaire, principal d\'office', async () => {
      const { owner, csrf } = await ownerConsole();
      const action = await owner.request('/admin-console.php', {
        method: 'POST',
        form: {
          console_token: csrf, console_action: 'school_director_create',
          first_name: 'QA', last_name: 'Directeur', username: DIRECTOR,
          email: DIRECTOR + '@test.local', school_id: newSchoolId, principal: '1',
        },
      });
      assert.equal(action.status, 302, 'creation acceptee');

      const profileId = sql("SELECT PROFILE_ID FROM staff WHERE SYEAR='2026' AND USERNAME='" + DIRECTOR + "'");
      const directeurId = sql("SELECT ID FROM user_profiles WHERE TITLE='Directeur'");
      assert.equal(profileId, directeurId, 'profil nomme Directeur');
      assert.equal(sql(`SELECT SCHOOLS FROM staff WHERE USERNAME='${DIRECTOR}'`), ',' + newSchoolId + ',', 'une seule ecole');
      assert.equal(sql(`SELECT LAST_LOGIN FROM staff WHERE USERNAME='${DIRECTOR}'`), '', 'premiere connexion a faire');
      assert.equal(sql(`SELECT ROLE FROM schoolsafe_school_leadership WHERE SCHOOL_ID=${newSchoolId}
        AND STAFF_ID=(SELECT STAFF_ID FROM staff WHERE USERNAME='${DIRECTOR}')`), 'principal',
        'principal d\'office : l\'ecole n\'en avait aucun');

      const after = await owner.request('/admin-console.php');
      const temp = (after.body.match(/<code>([^<]+)<\/code>/) || [])[1];
      assert.ok(temp, 'mot de passe temporaire affiche une fois');
      assert.equal(sql(`SELECT count(*) FROM schoolsafe_account_state
        WHERE PERSON_KEY='${DIRECTOR}'`), '0', 'etat actif par defaut : aucune ligne');

      // La personne se connecte reellement avec ce mot de passe provisoire.
      const fresh = client();
      const firstLogin = await fresh.login(DIRECTOR, temp);
      assert.match(firstLogin.location, /first-login/, 'changement de mot de passe impose');
      return 'profil Directeur, ecole ' + newSchoolId + ', premiere connexion forcee';
    });

    await checkAsync('creation second directeur : adjoint, principal inchange', async () => {
      const { owner, csrf } = await ownerConsole();
      const action = await owner.request('/admin-console.php', {
        method: 'POST',
        form: {
          console_token: csrf, console_action: 'school_director_create',
          first_name: 'QA', last_name: 'Adjoint', username: ADJOINT,
          email: ADJOINT + '@test.local', school_id: newSchoolId,
        },
      });
      assert.equal(action.status, 302);
      assert.equal(sql(`SELECT ROLE FROM schoolsafe_school_leadership WHERE SCHOOL_ID=${newSchoolId}
        AND STAFF_ID=(SELECT STAFF_ID FROM staff WHERE USERNAME='${ADJOINT}')`), 'adjoint');
      assert.equal(sql(`SELECT STAFF_ID FROM schoolsafe_school_leadership
        WHERE SCHOOL_ID=${newSchoolId} AND ROLE='principal'`),
        sql(`SELECT STAFF_ID FROM staff WHERE USERNAME='${DIRECTOR}'`), 'un seul principal, inchange');
      return 'adjoint accepte, principal conserve';
    });

    await checkAsync('creation directeur refusee : identifiant deja pris, ecole inconnue', async () => {
      const { owner, csrf } = await ownerConsole();
      const taken = await owner.request('/admin-console.php', {
        method: 'POST',
        form: {
          console_token: csrf, console_action: 'school_director_create',
          first_name: 'QA', last_name: 'Doublon', username: DIRECTOR,
          school_id: newSchoolId,
        },
      });
      assert.match(taken.body, /deja pris/i, 'doublon refuse');

      const unknown = await owner.request('/admin-console.php', {
        method: 'POST',
        form: {
          console_token: csrf, console_action: 'school_director_create',
          first_name: 'QA', last_name: 'Inconnu', username: 'qa.inconnu.' + STAMP,
          school_id: 999999,
        },
      });
      assert.match(unknown.body, /ecole existante/i, 'ecole inconnue refusee');
      assert.equal(sql(`SELECT count(*) FROM staff WHERE USERNAME='qa.inconnu.${STAMP}'`), '0', 'aucun compte cree');
      return 'refus + aucune ecriture';
    });

    // --- Nomination et retrait du principal --------------------------------
    await checkAsync('nomination : l\'adjoint devient principal, l\'ancien est retrograde', async () => {
      const { owner, csrf } = await ownerConsole();
      const adjointId = sql(`SELECT STAFF_ID FROM staff WHERE USERNAME='${ADJOINT}'`);
      const action = await owner.request('/admin-console.php', {
        method: 'POST',
        form: {
          console_token: csrf, console_action: 'school_principal_set',
          school_id: newSchoolId, staff_id: adjointId,
        },
      });
      assert.equal(action.status, 302, 'nomination acceptee');
      assert.equal(sql(`SELECT STAFF_ID FROM schoolsafe_school_leadership
        WHERE SCHOOL_ID=${newSchoolId} AND ROLE='principal'`), adjointId, 'nouveau principal');
      assert.equal(sql(`SELECT count(*) FROM schoolsafe_school_leadership
        WHERE SCHOOL_ID=${newSchoolId} AND ROLE='principal'`), '1', 'un seul principal');
      assert.equal(sql(`SELECT ROLE FROM schoolsafe_school_leadership
        WHERE SCHOOL_ID=${newSchoolId} AND STAFF_ID=(SELECT STAFF_ID FROM staff WHERE USERNAME='${DIRECTOR}')`),
        'adjoint', 'ancien principal retrograde');
      return 'un principal, ancien retrograde';
    });

    await checkAsync('retrait de la direction : la ligne disparait, l\'ecole est signalee sans principal', async () => {
      const { owner, csrf } = await ownerConsole();
      const principalId = sql(`SELECT STAFF_ID FROM schoolsafe_school_leadership
        WHERE SCHOOL_ID=${newSchoolId} AND ROLE='principal'`);
      const action = await owner.request('/admin-console.php', {
        method: 'POST',
        form: {
          console_token: csrf, console_action: 'school_leadership_remove',
          school_id: newSchoolId, staff_id: principalId,
        },
      });
      assert.equal(action.status, 302);
      assert.equal(sql(`SELECT count(*) FROM schoolsafe_school_leadership
        WHERE SCHOOL_ID=${newSchoolId} AND STAFF_ID=${principalId}`), '0', 'direction retiree');
      const page = await owner.request('/admin-console.php');
      assert.match(page.body, /Aucun directeur principal/, 'ecole sans principal signalee');
      return 'retrait effectif + avertissement';
    });

    // --- Permissions --------------------------------------------------------
    await checkAsync('un directeur ne peut creer ni ecole ni directeur', async () => {
      const c = client();
      assert.match((await c.login('l1.directeur2')).location, /modname=/);
      const form = await c.request('/admin-console.php');
      const csrf = (form.body.match(/name="console_token" value="([^"]+)"/) || [])[1];
      assert.ok(csrf, 'jeton CSRF du directeur');

      const before = Number(sql("SELECT count(*) FROM schools WHERE SYEAR='2026'"));
      const school = await c.request('/admin-console.php', {
        method: 'POST',
        form: {
          console_token: csrf, console_action: 'school_create',
          title: 'QA Interdit ' + STAMP, email: 'qa.interdit.' + STAMP + '@test.local',
          slogan: 'Refuse', template_school_id: templateId,
        },
      });
      assert.match(school.body, /Seul le proprietaire/);
      assert.equal(Number(sql("SELECT count(*) FROM schools WHERE SYEAR='2026'")), before, 'aucune ecole creee');

      const director = await c.request('/admin-console.php', {
        method: 'POST',
        form: {
          console_token: csrf, console_action: 'school_director_create',
          first_name: 'QA', last_name: 'Interdit', username: 'qa.interdit.' + STAMP,
          school_id: 2,
        },
      });
      assert.match(director.body, /Seul le proprietaire/);
      assert.equal(sql(`SELECT count(*) FROM staff WHERE USERNAME='qa.interdit.${STAMP}'`), '0');
      return 'refus + aucune ecriture';
    });

    // --- Filtre par ecole ---------------------------------------------------
    await checkAsync('filtre par ecole : seuls les comptes de l\'ecole choisie', async () => {
      const { owner } = await ownerConsole();
      const filtered = await owner.request('/admin-console.php?school_filter=' + newSchoolId);
      assert.equal(filtered.status, 200);
      assert.match(filtered.body, new RegExp(DIRECTOR), 'directeur de l\'ecole present');
      // Le proprietaire (aucune ecole assignee) est hors du filtre.
      assert.doesNotMatch(filtered.body, /l1\.proprietaire/, 'compte sans ecole absent sous filtre');
      const all = await owner.request('/admin-console.php');
      assert.match(all.body, /l1\.proprietaire/, 'sans filtre, la plateforme entiere reste visible');
      return 'filtre effectif';
    });
  } finally {
    // --- Nettoyage ----------------------------------------------------------
    dropStaff(DIRECTOR);
    dropStaff(ADJOINT);
    createdSchools.forEach(entry => dropSchool(entry));
  }

  const leftovers = sql(`SELECT count(*) FROM schools WHERE SYEAR='2026' AND TITLE LIKE 'QA %'`);
  console.log('nettoyage : ' + leftovers + ' ecole(s) QA restante(s)');

  console.log(JSON.stringify({ passed, failed, base: BASE,
    proof: 'pile Docker reelle (Apache/PHP/PostgreSQL), migration 018' }));
  process.exitCode = failed ? 1 : 0;
})().catch(error => {
  console.error(error);
  process.exitCode = 1;
});
