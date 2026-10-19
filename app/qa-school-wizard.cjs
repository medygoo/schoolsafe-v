/*
 * SchoolSafe — parcours guide de creation d'ecole (6 etapes, natif Rosario).
 *
 * Verification reelle sur la pile Docker (Apache + PHP 8.3 + PostgreSQL) :
 *   - les 6 etapes s'affichent (Ecole, Documents, Responsable, Images,
 *     Configuration, Verification) ;
 *   - etapes 1 a 5 acceptees sans creer d'ecole (brouillon de session) ;
 *   - etape 3 : identifiant deja pris refuse ;
 *   - etape 5 : gabarit au calendrier incoherent refuse, 0 ecole creee ;
 *   - confirmation : ecole reelle creee par le controleur natif CopySchool,
 *     calendrier complet, identite enregistree, puis ecole supprimee.
 *
 * Aucun compte n'est cree : l'etape compte principal (identites externes)
 * reste hors de ce test. L'ecole de test est supprimee en fin de parcours.
 *
 * Usage : node app/qa-school-wizard.cjs
 * Variables : SCHOOLSAFE_L1_URL (defaut http://127.0.0.1:8081),
 *             SCHOOLSAFE_L1_DB (defaut schoolsafe-app-db-1).
 */
const assert = require('node:assert/strict');
const { execFileSync } = require('node:child_process');

const BASE = process.env.SCHOOLSAFE_L1_URL || 'http://127.0.0.1:8081';
const DB = process.env.SCHOOLSAFE_L1_DB || 'schoolsafe-app-db-1';
const PGUSER = 'schoolsafe';
const PGDATABASE = 'schoolsafe';
const PGPASSWORD = process.env.SCHOOLSAFE_L1_DB_PASSWORD || 'preview-local-jetable-32caracteres-xx';
const PASSWORD = 'L1-Test-2026!';
const STAMP = Date.now();
const WIZ_TITLE = 'QA Wizard ' + STAMP;
const WIZ_USER = 'qawiz.' + STAMP;
const WIZ_SCHOOL_EMAIL = 'qa.wiz.' + STAMP + '@test.local';
const BROKEN_TITLE = 'QA Wizard Gabarit ' + STAMP;

let passed = 0, failed = 0;

function sql(statement) {
  return execFileSync('docker', ['exec', '-e', 'PGPASSWORD=' + PGPASSWORD, DB,
    'psql', '-U', PGUSER, '-d', PGDATABASE, '-At', '-c', statement], { encoding: 'utf8' }).trim();
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
    const rawCookies = response.headers.getSetCookie ? response.headers.getSetCookie() : [];
    for (const raw of rawCookies) {
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

function hidden(body, name) {
  const match = body.match(new RegExp('name="' + name + '" value="([^"]*)"'));
  return match ? match[1] : '';
}

/** Parcours ouvert par le proprietaire : jeton CSRF + brouillon de session. */
async function openWizard() {
  const owner = client();
  const login = await owner.login('l1.proprietaire');
  assert.match(login.location, /modname=/, 'proprietaire connecte');
  const page = await owner.request('/schoolsafe-administrators.php?wizard=1&step=1');
  assert.equal(page.status, 200, 'parcours accessible');
  const token = hidden(page.body, 'token');
  const draft = hidden(page.body, 'draft_id');
  assert.ok(token, 'jeton CSRF present');
  assert.ok(draft, 'brouillon de session present');
  return { owner, token, draft, page: page.body };
}

async function postStep(wiz, step, fields) {
  return wiz.owner.request('/schoolsafe-administrators.php?wizard=1&step=' + step, {
    method: 'POST',
    form: { token: wiz.token, draft_id: wiz.draft, ...fields },
  });
}

function dropSchool(schoolId) {
  const id = Number(schoolId);
  if (!Number.isInteger(id) || id < 1) return;
  // Chaque suppression est isolee : une table absente de l'environnement
  // (ex. schoolsafe_opening_requests sans sa migration) ne doit jamais
  // empecher la suppression de l'ecole elle-meme.
  // Le controleur natif CopySchool recopie plus de tables que la creation
  // console (notes, presences, program_config...) : tout est nettoye avant
  // l'ecole elle-meme, dans l'ordre des dependances.
  for (const stmt of [
    'DELETE FROM config WHERE SCHOOL_ID=' + id,
    'DELETE FROM school_gradelevels WHERE SCHOOL_ID=' + id,
    'DELETE FROM school_periods WHERE SCHOOL_ID=' + id,
    'DELETE FROM school_marking_periods WHERE SCHOOL_ID=' + id,
    'DELETE FROM report_card_grades WHERE SCHOOL_ID=' + id,
    'DELETE FROM report_card_comments WHERE SCHOOL_ID=' + id,
    'DELETE FROM report_card_grade_scales WHERE SCHOOL_ID=' + id,
    'DELETE FROM report_card_comment_categories WHERE SCHOOL_ID=' + id,
    'DELETE FROM eligibility_activities WHERE SCHOOL_ID=' + id,
    'DELETE FROM attendance_codes WHERE SCHOOL_ID=' + id,
    'DELETE FROM attendance_code_categories WHERE SCHOOL_ID=' + id,
    'DELETE FROM program_config WHERE SCHOOL_ID=' + id,
    'DELETE FROM schoolsafe_school_state WHERE SCHOOL_ID=' + id,
    'DELETE FROM schoolsafe_school_leadership WHERE SCHOOL_ID=' + id,
    'DELETE FROM schoolsafe_opening_requests WHERE SCHOOL_ID=' + id,
    'DELETE FROM schools WHERE ID=' + id,
  ]) {
    try { sql(stmt); } catch (error) { console.log('nettoyage (ignore) : ' + String(error.message).split('\n')[0]); }
  }
}

(async () => {
  const createdSchools = [];
  const templateId = sql("SELECT ID FROM schools WHERE SYEAR='2026' ORDER BY ID LIMIT 1");
  assert.match(templateId, /^[0-9]+$/, 'ecole gabarit disponible');
  let adminProfile = sql("SELECT ID FROM user_profiles WHERE PROFILE='admin' AND ID>3 ORDER BY ID LIMIT 1");
  if (!adminProfile) adminProfile = '1';

  const schoolsBefore = Number(sql("SELECT count(*) FROM schools WHERE SYEAR='2026'"));

  try {
    await checkAsync('parcours : les 6 etapes s affichent', async () => {
      const wiz = await openWizard();
      for (const n of [1, 2, 3, 4, 5, 6]) {
        assert.match(wiz.page, new RegExp(n + '\\. '), 'etape ' + n + ' listee');
      }
      assert.match(wiz.page, /Vérification/, 'derniere etape nommee');
      return '6 etapes';
    });

    await checkAsync('etapes 1 a 5 acceptees, aucune ecole creee', async () => {
      const wiz = await openWizard();
      let res = await postStep(wiz, 1, { TITLE: WIZ_TITLE, SHORT_NAME: 'QAW', CITY: 'Kinshasa', PHONE: '+243 000' });
      assert.equal(res.status, 302, 'etape 1 -> 302');
      res = await postStep(wiz, 2, { school_email: WIZ_SCHOOL_EMAIL, school_slogan: 'Devise de verification QA' });
      assert.equal(res.status, 302, 'etape 2 -> 302');
      res = await postStep(wiz, 3, {
        first_name: 'QA', last_name: 'Wizard', username: WIZ_USER,
        email: WIZ_USER + '@test.local', profile_id: adminProfile,
      });
      assert.equal(res.status, 302, 'etape 3 -> 302');
      res = await postStep(wiz, 4, {});
      assert.equal(res.status, 302, 'etape 4 (sans image) -> 302');
      res = await postStep(wiz, 5, { REPORTING_GP_SCALE: '100', source: templateId });
      assert.equal(res.status, 302, 'etape 5 -> 302');
      const review = await wiz.owner.request('/schoolsafe-administrators.php?wizard=1&step=6');
      assert.equal(review.status, 200);
      assert.match(review.body, /Confirmer la cr.+ation/, 'recapitulatif de verification');
      assert.equal(Number(sql("SELECT count(*) FROM schools WHERE SYEAR='2026'")), schoolsBefore, '0 ecole creee');
      return 'brouillon pret, 0 ecriture';
    });

    await checkAsync('etape 3 : identifiant deja pris refuse', async () => {
      const wiz = await openWizard();
      await postStep(wiz, 1, { TITLE: 'QA Wizard Doublon ' + STAMP });
      await postStep(wiz, 2, { school_email: 'qa.dup.' + STAMP + '@test.local', school_slogan: 'Doublon' });
      const res = await postStep(wiz, 3, {
        first_name: 'QA', last_name: 'Doublon', username: 'l1.proprietaire',
        email: 'dup@test.local', profile_id: adminProfile,
      });
      assert.equal(res.status, 200, 'refus affiche sans redirection');
      assert.match(res.body, /existe d.+j.+/, 'doublon signale');
      assert.equal(Number(sql("SELECT count(*) FROM schools WHERE SYEAR='2026'")), schoolsBefore, '0 ecole creee');
      return 'refus explicite';
    });

    await checkAsync('etape 5 : gabarit au calendrier incoherent refuse, 0 ecole', async () => {
      const c = client();
      const login = await c.login('l1.proprietaire');
      assert.match(login.location, /modname=/);
      const consolePage = await c.request('/admin-console.php');
      const csrf = hidden(consolePage.body, 'console_token');
      assert.ok(csrf, 'jeton console');
      const made = await c.request('/admin-console.php', {
        method: 'POST',
        form: {
          console_token: csrf, console_action: 'school_create', title: BROKEN_TITLE,
          email: 'qa.wizgab.' + STAMP + '@test.local', slogan: 'Gabarit volontairement casse',
          template_school_id: templateId,
        },
      });
      assert.equal(made.status, 302, 'gabarit cree');
      const brokenId = Number(sql("SELECT ID FROM schools WHERE SYEAR='2026' AND TITLE='" + BROKEN_TITLE + "'"));
      assert.ok(brokenId > 0);
      createdSchools.push(brokenId);
      sql('DELETE FROM school_marking_periods WHERE SCHOOL_ID=' + brokenId);

      const wiz = await openWizard();
      await postStep(wiz, 1, { TITLE: 'QA Depuis Gabarit Casse ' + STAMP });
      await postStep(wiz, 2, { school_email: 'qa.wizcasse.' + STAMP + '@test.local', school_slogan: 'Ne doit pas naitre' });
      await postStep(wiz, 3, {
        first_name: 'QA', last_name: 'Casse', username: 'qawiz.casse.' + STAMP,
        email: 'qawiz.casse.' + STAMP + '@test.local', profile_id: adminProfile,
      });
      await postStep(wiz, 4, {});
      const res = await postStep(wiz, 5, { REPORTING_GP_SCALE: '100', source: String(brokenId) });
      assert.equal(res.status, 200, 'refus affiche sans redirection');
      assert.match(res.body, /calendrier incoh.+rent/, 'calendrier incoherent signale');
      assert.equal(Number(sql("SELECT count(*) FROM schools WHERE SYEAR='2026'")), schoolsBefore + 1, 'seul le gabarit casse existe');
      return 'refus avant copie';
    });

    await checkAsync('confirmation : ecole reelle creee, calendrier complet, puis supprimee', async () => {
      const wiz = await openWizard();
      await postStep(wiz, 1, { TITLE: WIZ_TITLE, SHORT_NAME: 'QAW', CITY: 'Kinshasa' });
      await postStep(wiz, 2, { school_email: WIZ_SCHOOL_EMAIL, school_slogan: 'Devise de verification QA' });
      await postStep(wiz, 3, {
        first_name: 'QA', last_name: 'Wizard', username: WIZ_USER,
        email: WIZ_USER + '@test.local', profile_id: adminProfile,
      });
      await postStep(wiz, 4, {});
      await postStep(wiz, 5, { REPORTING_GP_SCALE: '100', source: templateId });
      const done = await wiz.owner.request('/Modules.php?modname=School_Setup/CopySchool.php&delete_ok=1', {
        method: 'POST',
        form: {
          token: wiz.token, sskc_school_draft: wiz.draft, title: WIZ_TITLE,
          school_email: WIZ_SCHOOL_EMAIL, school_slogan: 'Devise de verification QA',
          'school_identity[ADDRESS]': '', 'school_identity[CITY]': 'Kinshasa',
          'school_identity[PHONE]': '', 'school_identity[WWW_ADDRESS]': '',
          'tables[config]': 'Y', 'tables[school_marking_periods]': 'Y',
          'tables[school_periods]': 'Y', 'tables[school_gradelevels]': 'Y',
          'tables[report_card_grades]': 'Y', 'tables[report_card_comments]': 'Y',
          'tables[eligibility_activities]': 'Y', 'tables[attendance_codes]': 'Y',
        },
      });
      assert.ok([200, 302].includes(done.status), 'confirmation acceptee');
      const newId = Number(sql("SELECT ID FROM schools WHERE SYEAR='2026' AND TITLE='" + WIZ_TITLE + "'"));
      assert.ok(newId > 0, 'ecole creee par le parcours');
      createdSchools.push(newId);
      assert.ok(Number(sql('SELECT count(*) FROM school_gradelevels WHERE SCHOOL_ID=' + newId)) > 0, 'niveaux recopies');
      assert.ok(Number(sql('SELECT count(*) FROM school_marking_periods WHERE SCHOOL_ID=' + newId)) > 0, 'notation recopiee');
      assert.equal(
        sql("SELECT CONFIG_VALUE FROM config WHERE SCHOOL_ID=" + newId + " AND TITLE='SCHOOLSAFE_SCHOOL_EMAIL'"),
        WIZ_SCHOOL_EMAIL, 'e-mail officiel enregistre');
      return 'ecole ' + newId + ' verifiee';
    });
  } finally {
    createdSchools.forEach(dropSchool);
  }

  const leftovers = sql("SELECT count(*) FROM schools WHERE SYEAR='2026' AND (TITLE LIKE 'QA Wizard%' OR TITLE LIKE 'QA Depuis Gabarit%')");
  console.log('nettoyage : ' + leftovers + ' ecole(s) QA restante(s)');

  console.log(JSON.stringify({
    passed, failed, base: BASE, proof: 'pile Docker reelle (Apache/PHP/PostgreSQL), parcours 6 etapes',
  }));
  process.exitCode = failed ? 1 : 0;
})().catch(error => {
  console.error(error);
  process.exitCode = 1;
});
