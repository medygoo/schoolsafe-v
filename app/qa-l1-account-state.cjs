/*
 * SchoolSafe — L1 : etat de compte, revocation de session, perimetre multi-ecoles.
 *
 * Verification reelle sur la pile Docker (Apache + PHP 8.3 + PostgreSQL) :
 *   - connexion refusee pour un compte suspendu / expire ;
 *   - session ouverte coupee par une revocation (etat inchange) ;
 *   - lecture seule : ecriture refusee (AllowEdit + console) ;
 *   - directeur (perimetre ecole 2) : ne voit ni l'ecole 1, ni les comptes globaux ;
 *   - proprietaire (comptes globaux) : voit tout ;
 *   - mot de passe provisoire : premiere connexion => changement force.
 *
 * Usage : node app/qa-l1-account-state.cjs
 * Variables : SCHOOLSAFE_L1_URL (defaut http://127.0.0.1:8081),
 *             SCHOOLSAFE_L1_DB (conteneur PostgreSQL, defaut schoolsafe-app-db-1),
 *             SCHOOLSAFE_L1_CONTAINER (conteneur applicatif, defaut schoolsafe-l1-test).
 */
const assert = require('node:assert/strict');
const { execFileSync } = require('node:child_process');

const BASE = process.env.SCHOOLSAFE_L1_URL || 'http://127.0.0.1:8081';
const DB = process.env.SCHOOLSAFE_L1_DB || 'schoolsafe-app-db-1';
const APP = process.env.SCHOOLSAFE_L1_CONTAINER || 'schoolsafe-l1-test';
const PGUSER = 'schoolsafe';
const PGDATABASE = 'schoolsafe';
const PGPASSWORD = process.env.SCHOOLSAFE_L1_DB_PASSWORD || 'preview-local-jetable-32caracteres-xx';
const PASSWORD = 'L1-Test-2026!';

let passed = 0, failed = 0, skipped = 0;

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

/**
 * Client HTTP minimal conservant le cookie de session, sans suivi de redirection
 * (les redirections portent l'information utile : modfunc=first-login).
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
    cookies: () => [...jar.keys()],
  };
}

(async () => {
  // --- Fixtures deterministes ---------------------------------------------
  const ids = {};
  for (const username of ['l1.proprietaire', 'l1.directeur2', 'l1.enseignant1', 'l1.lecture1', 'l1.suspendu1']) {
    ids[username] = sql(`SELECT STAFF_ID FROM staff WHERE SYEAR='2026' AND USERNAME='${username}'`);
    assert.match(ids[username], /^[0-9]+$/, username + ' doit exister (fixtures)');
  }
  sql("DELETE FROM schoolsafe_account_state WHERE PERSON_KEY LIKE 'l1.%'");
  sql("DELETE FROM schoolsafe_account_state WHERE PERSON_KEY LIKE 'l1.creation%'");
  sql("DELETE FROM staff WHERE SYEAR='2026' AND USERNAME LIKE 'l1.creation%'");
  sql("UPDATE staff SET PROFILE='teacher',PROFILE_ID=2 WHERE SYEAR='2026' AND USERNAME IN ('l1.enseignant1','l1.suspendu1')");
  // Le compte en lecture seule doit etre un administrateur : sinon la console
  // le refuse d'entree (403) et le test ne verifierait pas la regle.
  sql("UPDATE staff SET PROFILE='admin',PROFILE_ID=(SELECT id FROM user_profiles WHERE title='Directeur') WHERE SYEAR='2026' AND USERNAME='l1.lecture1'");

  const state = person => sql(`SELECT STATE FROM schoolsafe_account_state WHERE PERSON_KEY='${person}'`);

  // --- 1. Etat par defaut : aucune ligne => actif --------------------------
  await checkAsync('compte sans ligne d\'etat : connexion normale', async () => {
    const c = client();
    const response = await c.login('l1.enseignant1');
    assert.equal(await state('l1.enseignant1'), '', 'aucune ligne d\'etat attendue');
    assert.ok(response.location.includes('modname='), 'connexion acceptee et redirigee vers le portail');
    return 'redirection ' + response.location.replace(BASE, '');
  });

  // --- 2. Suspension : connexion refusee -----------------------------------
  sql(`INSERT INTO schoolsafe_account_state (PERSON_KEY,SCHOOL_ID,STATE,REASON)
       VALUES ('l1.suspendu1',1,'suspended','test L1')`);

  await checkAsync('compte suspendu : connexion refusee, message explicite', async () => {
    const c = client();
    const response = await c.login('l1.suspendu1');
    assert.equal(response.status, 200, 'la page de connexion est renvoyee');
    assert.match(response.body, /suspendu/i, 'message de suspension affiche');
    assert.equal(c.hasSession() && response.body.includes('modname='), false, 'aucune session ouverte');
    return 'refus + message';
  });

  await checkAsync('compte suspendu : la ligne d\'etat est intacte (donnees conservees)', async () => {
    assert.equal(state('l1.suspendu1'), 'suspended');
    const profile = sql(`SELECT PROFILE FROM staff WHERE STAFF_ID='${ids['l1.suspendu1']}'`);
    assert.equal(profile, 'teacher', 'le profil natif n\'est pas modifie par la suspension');
    return 'profil natif conserve';
  });

  await checkAsync('suspension => levee => connexion a nouveau acceptee', async () => {
    sql(`UPDATE schoolsafe_account_state SET STATE='active' WHERE PERSON_KEY='l1.suspendu1'`);
    const c = client();
    const response = await c.login('l1.suspendu1');
    assert.ok(response.location.includes('modname='), 'reconnexion acceptee');
    return 'etat active';
  });

  // --- 2 ter. Expiration et date de fin -----------------------------------
  await checkAsync('date de fin depassee sur un compte actif : connexion refusee (expire)', async () => {
    sql(`INSERT INTO schoolsafe_account_state (PERSON_KEY,SCHOOL_ID,STATE,REASON,UNTIL)
         VALUES ('l1.suspendu1',1,'active','contrat termine', CURRENT_TIMESTAMP - INTERVAL '1 day')
         ON CONFLICT (PERSON_KEY,SCHOOL_ID) DO UPDATE
         SET STATE='active', UNTIL=CURRENT_TIMESTAMP - INTERVAL '1 day'`);
    const c = client();
    const response = await c.login('l1.suspendu1');
    assert.match(response.body, /Ce compte a expire/, 'message d\'expiration affiche');
    return 'until depasse => expired';
  });

  await checkAsync('suspension temporaire avec date future : refus, puis retour automatique', async () => {
    sql(`UPDATE schoolsafe_account_state
         SET STATE='suspended', UNTIL=CURRENT_TIMESTAMP - INTERVAL '1 minute'
         WHERE PERSON_KEY='l1.suspendu1'`);
    const c = client();
    const response = await c.login('l1.suspendu1');
    assert.ok(response.location.includes('modname='), 'mesure temporaire terminee => acces rendu sans intervention');
    sql(`DELETE FROM schoolsafe_account_state WHERE PERSON_KEY='l1.suspendu1'`);
    return 'until depasse => active';
  });

  // --- 3. Revocation de session -------------------------------------------
  await checkAsync('revocation via la console : la session ouverte tombe a la requete suivante', async () => {
    const target = client();
    const login = await target.login('l1.enseignant1');
    assert.ok(login.location.includes('modname='), 'connexion initiale');
    const before = await target.request('/Modules.php?modname=misc/Portal.php');
    assert.equal(before.status, 200, 'portail accessible avant revocation');

    const admin = client();
    await admin.login('l1.proprietaire');
    const page = await admin.request('/admin-console.php');
    const token = (page.body.match(/name="console_token" value="([^"]+)"/) || [])[1];
    assert.ok(token, 'console accessible au proprietaire');

    const action = await admin.request('/admin-console.php', {
      method: 'POST',
      form: { console_token: token, console_action: 'revoke', staff_id: ids['l1.enseignant1'] },
    });
    assert.equal(action.status, 302, 'action acceptee (redirection)');

    const after = await target.request('/Modules.php?modname=misc/Portal.php');
    assert.match(after.location, /index\.php\?reason=account_revoked/, 'session coupee, retour a la connexion');
    assert.equal(state('l1.enseignant1'), 'active', 'la revocation ne change pas l\'etat : seul l\'acces est coupe');

    const notice = await client().request('/index.php?reason=account_revoked');
    assert.match(notice.body, /fermee par l'administration|session a ete fermee/, 'la personne est informee du motif');
    return 'session coupee sans changer l\'etat';
  });

  // --- 4. Lecture seule ----------------------------------------------------
  await checkAsync('lecture seule : connexion autorisee, ecriture refusee', async () => {
    sql(`INSERT INTO schoolsafe_account_state (PERSON_KEY,SCHOOL_ID,STATE,REASON)
         VALUES ('l1.lecture1',1,'readonly','test L1')`);
    const c = client();
    const login = await c.login('l1.lecture1');
    assert.ok(login.location.includes('modname='), 'un compte en lecture seule se connecte');

    // Ecriture : la console d'administration doit refuser l'action.
    const page = await c.request('/admin-console.php');
    const token = (page.body.match(/name="console_token" value="([^"]+)"/) || [])[1];
    assert.ok(token, 'jeton CSRF present dans la page');

    const attempt = await c.request('/admin-console.php', {
      method: 'POST',
      form: { console_token: token, console_action: 'state', staff_id: ids['l1.enseignant1'], state: 'suspended' },
    });
    assert.match(attempt.body, /lecture seule/i, 'refus explicite pour un compte en lecture seule');
    assert.equal(state('l1.enseignant1'), 'active', 'aucune ecriture n\'a eu lieu');
    return 'connexion ok, ecriture refusee';
  });

  await checkAsync('lecture seule : AllowEdit() refuse l\'ecriture dans toute l\'application', async () => {
    const run = id => JSON.parse(execFileSync('docker', ['exec', APP, 'php',
      '/var/www/html/schoolsafe-remediation/tests/account-state-allow-edit.php', String(id)],
      { encoding: 'utf8' }).trim().split('\n').pop());
    const active = run(ids['l1.directeur2']);
    const readonly = run(ids['l1.lecture1']);
    assert.equal(active.allow_edit_own_screen, true, 'un directeur actif ecrit normalement');
    assert.equal(active.allow_edit_other_screen, true, 'un directeur actif ecrit partout ou son profil l\'autorise');
    assert.equal(readonly.allow_edit_own_screen, false, 'lecture seule : AllowEdit refuse');
    assert.equal(readonly.allow_edit_other_screen, false, 'lecture seule : refus sur tous les ecrans');
    assert.equal(readonly.profile, 'admin', 'profil et droits natifs intacts (seul l\'acces est restreint)');
    return 'AllowEdit true/false selon l\'etat';
  });

  // --- 5. Perimetre : directeur de l'ecole 2 -------------------------------
  await checkAsync('directeur ecole 2 : ne voit ni l\'ecole 1 ni les comptes globaux', async () => {
    const c = client();
    const login = await c.login('l1.directeur2');
    assert.ok(login.location.includes('modname='), 'connexion du directeur');

    const list = await c.request('/Modules.php?modname=Users/User.php&search_modfunc=list&advanced=Y');
    assert.equal(list.status, 200, 'liste accessible');
    assert.match(list.body, /Directeur Ecole2/, 'le directeur se voit lui-meme');
    assert.doesNotMatch(list.body, /Enseignant Ecole1/, 'aucun utilisateur de l\'ecole 1');
    assert.doesNotMatch(list.body, /Proprietaire/, 'aucun compte global (proprietaire de la plateforme)');
    assert.doesNotMatch(list.body, /Lecture Ecole1|Suspendu Ecole1/, 'aucun compte hors perimetre');

    const console = await c.request('/admin-console.php');
    assert.doesNotMatch(console.body, /Enseignant Ecole1/, 'console : pas d\'utilisateur hors perimetre');
    assert.doesNotMatch(console.body, /Proprietaire/, 'console : pas de compte global');
    return 'liste et console limitees a l\'ecole 2';
  });

  await checkAsync('directeur ecole 2 : fiche d\'un utilisateur de l\'ecole 1 refusee', async () => {
    const c = client();
    await c.login('l1.directeur2');
    const denied = await c.request('/Modules.php?modname=Users/User.php&staff_id=' + ids['l1.enseignant1']);
    assert.equal(denied.status, 403, 'acces refuse (403)');
    assert.doesNotMatch(denied.body, /l1\.enseignant1@test\.local/, 'aucune donnee personnelle fuitee');
    return '403';
  });

  await checkAsync('directeur ecole 2 : sa propre fiche reste accessible', async () => {
    const c = client();
    await c.login('l1.directeur2');
    const own = await c.request('/Modules.php?modname=Users/User.php&staff_id=' + ids['l1.directeur2']);
    assert.equal(own.status, 200, 'pas de blocage de soi-meme');
    return '200';
  });

  // --- 6. Proprietaire : voit tout ----------------------------------------
  await checkAsync('proprietaire : voit les deux ecoles', async () => {
    const c = client();
    await c.login('l1.proprietaire');
    const list = await c.request('/Modules.php?modname=Users/User.php&search_modfunc=list&advanced=Y&_search_all_schools=Y');
    assert.equal(list.status, 200);
    assert.match(list.body, /Directeur Ecole2/, 'comptes de l\'ecole 2 visibles');
    assert.match(list.body, /Enseignant Ecole1/, 'comptes de l\'ecole 1 visibles');
    assert.match(list.body, /Proprietaire/, 'comptes globaux visibles');
    return 'vue plateforme complete (2 ecoles + comptes globaux)';
  });

  // --- 7. Mot de passe provisoire ------------------------------------------
  await checkAsync('compte cree par le directeur : premiere connexion => changement force', async () => {
    const created = 'l1.creation.' + Date.now();
    sql(`INSERT INTO staff (syear,first_name,last_name,username,password,profile,profile_id,schools,last_login)
         VALUES (2026,'L1','Creation Directeur','${created}',
         (SELECT PASSWORD FROM staff WHERE USERNAME='l1.proprietaire' LIMIT 1),'teacher',2,',2,',NULL)`);
    const c = client();
    const login = await c.login(created);
    assert.match(login.location, /modfunc=first-login/, 'redirection vers le changement de mot de passe force');
    assert.ok(await stateIsAbsent(created), 'aucune ligne d\'etat necessaire : defaut actif');
    sql(`DELETE FROM staff WHERE SYEAR='2026' AND USERNAME='${created}'`);
    return 'first-login + etat actif par defaut';
  });

  async function stateIsAbsent(person) {
    return sql(`SELECT COUNT(1) FROM schoolsafe_account_state WHERE PERSON_KEY='${person}'`) === '0';
  }

  // --- 7bis. Creation d'un compte par le directeur, ecran natif ------------
  // Regression : la garde de delegation des profils refusait aussi les profils
  // standards (PROFILE_ID vide = "Default permissions"), donc tout directeur
  // delegue recevait un 403 en creant un simple enseignant.
  await checkAsync('directeur ecole 2 : creation d\'un enseignant via l\'ecran natif acceptee', async () => {
    const c = client();
    await c.login('l1.directeur2');

    const form = await c.request('/Modules.php?modname=Users/User.php&staff_id=new&category_id=1');
    const action = (form.body.match(/<form[^>]*action="([^"]*Users\/User\.php[^"]*)"/) || [])[1];
    assert.ok(action, 'formulaire de creation rendu au directeur');

    const username = 'l1.native.' + Date.now();
    const url = action.replace(/&amp;/g, '&');
    const token = (url.match(/token=([^&"]+)/) || [])[1];

    const post = await c.request('/' + url, {
      method: 'POST',
      headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': token },
      form: {
        'staff[FIRST_NAME]': 'Native', 'staff[LAST_NAME]': 'Directeur',
        'staff[USERNAME]': username, 'staff[PASSWORD]': PASSWORD,
        'staff[PROFILE]': 'teacher', 'staff[PROFILE_ID]': '', 'staff[SCHOOLS][2]': 'Y',
      },
    });

    const created = sql(`SELECT PROFILE||'|'||PROFILE_ID||'|'||COALESCE(SCHOOLS,'')
      FROM staff WHERE SYEAR='2026' AND USERNAME='${username}'`);

    sql(`DELETE FROM staff WHERE SYEAR='2026' AND USERNAME='${username}'`);

    assert.notEqual(post.status, 403, 'aucun refus de perimetre sur un compte enseignant');
    assert.equal(created, 'teacher|2|,2,', 'compte cree dans l\'ecole du directeur');

    return 'compte teacher cree dans l\'ecole 2 (' + post.status + ')';
  });

  // --- 7ter. Profil nomme des la creation (P2 : nommer un Directeur) -------
  const directorProfileId = sql("SELECT ID FROM user_profiles WHERE TITLE='Directeur'");
  assert.match(directorProfileId, /^[0-9]+$/, 'profil nomme Directeur (migration 015) present');

  await checkAsync('proprietaire : le formulaire de creation propose le profil nomme Directeur', async () => {
    const c = client();
    await c.login('l1.proprietaire');
    const form = await c.request('/Modules.php?modname=Users/User.php&staff_id=new&category_id=1');
    assert.equal(form.status, 200, 'formulaire rendu');
    assert.match(form.body, new RegExp('value="' + directorProfileId + '"[^>]*>Directeur'), 'Directeur propose a la creation');
    assert.match(form.body, /value="6"[^>]*>Gardien/, 'Gardien propose a la creation');
    assert.doesNotMatch(form.body, /after adding the user/, "l'ancienne consigne (profil choisi apres creation) a disparu");
    return 'profils nommes listes a la creation';
  });

  await checkAsync('proprietaire : nommer un Directeur se fait en une seule creation', async () => {
    const c = client();
    await c.login('l1.proprietaire');
    const form = await c.request('/Modules.php?modname=Users/User.php&staff_id=new&category_id=1');
    const action = (form.body.match(/<form[^>]*action="([^"]*Users\/User\.php[^"]*)"/) || [])[1];
    assert.ok(action, 'formulaire de creation rendu');
    const url = action.replace(/&amp;/g, '&');
    const token = (url.match(/token=([^&"]+)/) || [])[1];

    const username = 'l1.named.' + Date.now();
    try {
      const post = await c.request('/' + url, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': token },
        form: {
          'staff[FIRST_NAME]': 'Nomme', 'staff[LAST_NAME]': 'Directeur',
          'staff[USERNAME]': username, 'staff[PASSWORD]': PASSWORD,
          // La famille saisie ne compte pas : le profil nomme fait foi.
          'staff[PROFILE]': 'teacher', 'staff[PROFILE_ID]': directorProfileId,
          'staff[SCHOOLS][2]': 'Y',
        },
      });
      assert.notEqual(post.status, 403, 'aucun refus de perimetre');

      const created = sql(`SELECT PROFILE||'|'||PROFILE_ID||'|'||COALESCE(SCHOOLS,'')
        FROM staff WHERE SYEAR='2026' AND USERNAME='${username}'`);
      assert.equal(created, 'admin|' + directorProfileId + '|,2,',
        'profil nomme attribue des la creation, famille admin deduite, ecole 2 conservee');

      // Le compte nomme Directeur entre bien dans son perimetre.
      sql(`UPDATE staff SET LAST_LOGIN=CURRENT_TIMESTAMP,
        PASSWORD=(SELECT PASSWORD FROM staff WHERE USERNAME='l1.proprietaire' LIMIT 1)
        WHERE SYEAR='2026' AND USERNAME='${username}'`);
      const login = await client().login(username);
      assert.ok(login.location.includes('modname='), 'le Directeur cree se connecte');
      return 'une creation => admin|' + directorProfileId + ', ecole 2';
    } finally {
      sql(`DELETE FROM staff WHERE SYEAR='2026' AND USERNAME='${username}'`);
    }
  });

  await checkAsync('directeur ecole 2 : nomme un Comptable dans sa propre ecole (C3)', async () => {
    const c = client();
    await c.login('l1.directeur2');
    const form = await c.request('/Modules.php?modname=Users/User.php&staff_id=new&category_id=1');
    assert.match(form.body, /value="5"[^>]*>Comptable/, 'Comptable propose au directeur');

    const action = (form.body.match(/<form[^>]*action="([^"]*Users\/User\.php[^"]*)"/) || [])[1];
    const url = action.replace(/&amp;/g, '&');
    const token = (url.match(/token=([^&"]+)/) || [])[1];
    const username = 'l1.comptable.' + Date.now();
    try {
      const post = await c.request('/' + url, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': token },
        form: {
          'staff[FIRST_NAME]': 'Comptable', 'staff[LAST_NAME]': 'Ecole 2',
          'staff[USERNAME]': username, 'staff[PASSWORD]': PASSWORD,
          'staff[PROFILE]': 'teacher', 'staff[PROFILE_ID]': '5',
          'staff[SCHOOLS][2]': 'Y',
        },
      });
      assert.notEqual(post.status, 403, 'nomination acceptee');

      const created = sql(`SELECT PROFILE||'|'||PROFILE_ID||'|'||COALESCE(SCHOOLS,'')
        FROM staff WHERE SYEAR='2026' AND USERNAME='${username}'`);
      assert.equal(created, 'admin|5|,2,', 'Comptable nomme et borne a l\'ecole du directeur');

      sql(`UPDATE staff SET LAST_LOGIN=CURRENT_TIMESTAMP,
        PASSWORD=(SELECT PASSWORD FROM staff WHERE USERNAME='l1.proprietaire' LIMIT 1)
        WHERE SYEAR='2026' AND USERNAME='${username}'`);
      const login = await client().login(username);
      assert.ok(login.location.includes('modname='), 'le Comptable nomme se connecte');
      return 'admin|5|, ecole 2, connexion verifiee';
    } finally {
      sql(`DELETE FROM staff WHERE SYEAR='2026' AND USERNAME='${username}'`);
    }
  });

  await checkAsync('directeur ecole 2 : un Comptable ne peut pas naitre dans l\'ecole 1', async () => {
    const c = client();
    await c.login('l1.directeur2');
    const form = await c.request('/Modules.php?modname=Users/User.php&staff_id=new&category_id=1');
    const action = (form.body.match(/<form[^>]*action="([^"]*Users\/User\.php[^"]*)"/) || [])[1];
    const url = action.replace(/&amp;/g, '&');
    const token = (url.match(/token=([^&"]+)/) || [])[1];
    const username = 'l1.horsscope.' + Date.now();
    try {
      const post = await c.request('/' + url, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': token },
        form: {
          'staff[FIRST_NAME]': 'Hors', 'staff[LAST_NAME]': 'Perimetre',
          'staff[USERNAME]': username, 'staff[PASSWORD]': PASSWORD,
          // Profil delegable, mais ecole hors perimetre : c'est la portee qui doit refuser.
          'staff[PROFILE]': 'teacher', 'staff[PROFILE_ID]': '5',
          'staff[SCHOOLS][1]': 'Y',
        },
      });
      assert.equal(post.status, 403, 'portee hors perimetre refusee');
      assert.equal(sql(`SELECT COUNT(1) FROM staff WHERE SYEAR='2026' AND USERNAME='${username}'`), '0',
        'aucun Comptable cree dans une ecole qui n\'est pas la sienne');
      return 'Comptable delegable mais ecole 1 refusee (403)';
    } finally {
      sql(`DELETE FROM staff WHERE SYEAR='2026' AND USERNAME='${username}'`);
    }
  });

  await checkAsync('directeur delegue : ni Directeur (pair) ni Administrateur (plus large), meme forge', async () => {
    const c = client();
    await c.login('l1.directeur2');
    const form = await c.request('/Modules.php?modname=Users/User.php&staff_id=new&category_id=1');
    assert.ok(form.status === 200, 'formulaire rendu au directeur');
    assert.doesNotMatch(form.body, new RegExp('value="' + directorProfileId + '"'), 'profil Directeur non propose');
    assert.doesNotMatch(form.body, /value="1"[^>]*>Administrator/, 'profil Administrator non propose non plus');

    const action = (form.body.match(/<form[^>]*action="([^"]*Users\/User\.php[^"]*)"/) || [])[1];
    const url = action.replace(/&amp;/g, '&');
    const token = (url.match(/token=([^&"]+)/) || [])[1];

    for (const forbidden of [directorProfileId, '1']) {
      const username = 'l1.forged.' + forbidden + '.' + Date.now();
      try {
        const post = await c.request('/' + url, {
          method: 'POST',
          headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': token },
          form: {
            'staff[FIRST_NAME]': 'Forge', 'staff[LAST_NAME]': 'Elevation',
            'staff[USERNAME]': username, 'staff[PASSWORD]': PASSWORD,
            'staff[PROFILE]': 'admin', 'staff[PROFILE_ID]': forbidden,
            'staff[SCHOOLS][2]': 'Y',
          },
        });
        assert.equal(post.status, 403, 'refus de perimetre pour le profil ' + forbidden);
        assert.equal(sql(`SELECT COUNT(1) FROM staff WHERE SYEAR='2026' AND USERNAME='${username}'`), '0',
          'aucun compte cree avec le profil ' + forbidden);
      } finally {
        sql(`DELETE FROM staff WHERE SYEAR='2026' AND USERNAME='${username}'`);
      }
    }

    return 'pair et profil plus large : options absentes, forge 403, 0 compte';
  });

  // --- 7quater. Filtre par profil nomme dans la liste Utilisateurs (C5) ----
  const director2Name = sql("SELECT FIRST_NAME||' '||LAST_NAME FROM staff WHERE SYEAR='2026' AND USERNAME='l1.directeur2'");
  const director1Name = sql("SELECT FIRST_NAME||' '||LAST_NAME FROM staff WHERE SYEAR='2026' AND USERNAME='l1.lecture1'");
  const teacher1Name = sql("SELECT FIRST_NAME||' '||LAST_NAME FROM staff WHERE SYEAR='2026' AND USERNAME='l1.enseignant1'");
  assert.ok(director2Name && director1Name && teacher1Name, 'fixtures l1.* nommees');

  await checkAsync('proprietaire : la recherche propose les profils nommes, pas seulement les familles', async () => {
    const c = client();
    await c.login('l1.proprietaire');
    const page = await c.request('/Modules.php?modname=Users/User.php');
    assert.equal(page.status, 200, 'formulaire de recherche rendu');
    const select = (page.body.match(/<select name="profile"[\s\S]*?<\/select>/) || [''])[0];
    assert.match(select, /<option value="admin">/, 'familles natives conservees');
    assert.match(select, new RegExp('value="profile:' + directorProfileId + '"'), 'Directeur propose');
    assert.match(select, /value="profile:5"/, 'Comptable propose');
    assert.match(select, /value="profile:6"/, 'Gardien propose');
    return 'familles + Directeur, Comptable, Gardien';
  });

  await checkAsync('proprietaire : filtrer sur Directeur ne renvoie que des directeurs', async () => {
    const c = client();
    await c.login('l1.proprietaire');
    const filtered = await c.request('/Modules.php?modname=Users/User.php&search_modfunc=list&advanced=Y&_search_all_schools=Y&profile=profile:' + directorProfileId);

    assert.ok(filtered.body.includes(director2Name), 'le directeur de l\'ecole 2 est liste');
    assert.ok(filtered.body.includes(director1Name), 'celui de l\'ecole 1 aussi : le proprietaire voit tout');
    assert.equal(filtered.body.includes(teacher1Name), false, 'aucun enseignant dans le filtre Directeur (meme famille non plus)');
    return 'deux ecoles, profils exacts, 0 enseignant';
  });

  await checkAsync('directeur ecole 2 : le filtre Directeur reste borne a son ecole', async () => {
    const c = client();
    await c.login('l1.directeur2');
    const filtered = await c.request('/Modules.php?modname=Users/User.php&search_modfunc=list&advanced=Y&profile=profile:' + directorProfileId);

    assert.ok(filtered.body.includes(director2Name), 'il voit le directeur de son ecole');
    assert.equal(filtered.body.includes(director1Name), false, 'pas le Directeur de l\'ecole 1 (portee)');
    assert.equal(filtered.body.includes(teacher1Name), false, 'ni un enseignant (profil)');
    return 'meme profil, autre ecole : exclu';
  });

  await checkAsync('enseignant : aucun profil nomme propose, filtre forge ignore proprement', async () => {
    const c = client();
    await c.login('l1.enseignant1');
    const page = await c.request('/Modules.php?modname=Users/User.php');
    assert.equal(page.status, 200, 'formulaire accessible');
    const select = (page.body.match(/<select name="profile"[\s\S]*?<\/select>/) || [''])[0];
    assert.doesNotMatch(select, /value="profile:/, 'options reservees aux administrateurs');

    const forged = await c.request('/Modules.php?modname=Users/User.php&search_modfunc=list&advanced=Y&profile=profile:' + directorProfileId);
    assert.equal(forged.status, 200, 'valeur hors liste blanche ignoree, pas une erreur');
    assert.doesNotMatch(forged.body, /WE HAVE A PROBLEM/, 'aucune erreur SQL');
    return 'options admin seulement, valeur forgee sans effet';
  });

  // --- 8. Console : le directeur ne peut pas creer d'administrateur --------
  await checkAsync('directeur : creation d\'un administrateur de plateforme refusee', async () => {
    const c = client();
    await c.login('l1.directeur2');
    const page = await c.request('/admin-console.php');
    const token = (page.body.match(/name="console_token" value="([^"]+)"/) || [])[1];
    assert.ok(token, 'jeton CSRF present');
    assert.doesNotMatch(page.body, /schoolsafe-administrators\.php\?wizard=1/, 'carte de creation reservee au proprietaire');

    const attempt = await c.request('/admin-console.php', {
      method: 'POST',
      form: { console_token: token, console_action: 'create', first_name: 'X', last_name: 'Y',
        username: 'l1.pirate.' + Date.now(), school_ids: '1' },
    });
    assert.match(attempt.body, /proprietaire de la plateforme/i, 'refus explique');
    assert.equal(sql("SELECT COUNT(1) FROM staff WHERE USERNAME LIKE 'l1.pirate.%'"), '0', 'aucun compte cree');
    return 'refus, 0 compte cree';
  });

  // --- 8 bis. Creation native : un seul choix de profil --------------------
  await checkAsync('creation utilisateur : un seul choix (profil nomme), directeur sans ecole refuse', async () => {
    const c = client();
    assert.match((await c.login('l1.proprietaire')).location, /modname=/, 'proprietaire connecte');

    const page = await c.request('/Modules.php?modname=Users/User.php&staff_id=new');
    assert.equal(page.status, 200, 'formulaire de creation accessible');
    const select = (page.body.match(/<select name="staff\[PROFILE_ID\]"[\s\S]*?<\/select>/) || [''])[0];
    assert.match(select, /value="4">Directeur/, 'profil nomme propose');
    assert.doesNotMatch(page.body, /name="staff\[PROFILE\]"/, 'le double choix a disparu');

    const token = (page.body.match(/name="token" content="([^"]+)"/) || [])[1];
    assert.ok(token, 'jeton CSRF du formulaire');

    const username = 'l1.noschool.' + Date.now();
    const refused = await c.request('/Modules.php?modname=Users/User.php&staff_id=new&modfunc=update', {
      method: 'POST',
      form: {
        token,
        'staff[FIRST_NAME]': 'QA',
        'staff[LAST_NAME]': 'SansEcole',
        'staff[USERNAME]': username,
        'staff[PASSWORD]': 'Test-Pass-2026!',
        'staff[EMAIL]': username + '@test.local',
        'staff[PROFILE_ID]': '4',
      },
    });
    assert.match(refused.body, /must be assigned at least one school/, 'refus explique');
    assert.equal(sql(`SELECT COUNT(1) FROM staff WHERE USERNAME='${username}'`), '0', 'aucun compte cree');
    return 'un seul selecteur + refus sans ecole';
  });

  // --- 9. Compte proprietaire PRODELI --------------------------------------
  const credentialsFile = process.env.SCHOOLSAFE_PRODELI_FILE
    || '.codex-local-reference-v1/PRODELI-owner-credentials.txt';

  // Invariants de provisionnement : le compte est global, administrateur et
  // deja initialise (le changement force de la premiere connexion a eu lieu).
  await checkAsync('PRODELI : proprietaire de la plateforme, portee globale, compte initialise', async () => {
    assert.equal(sql("SELECT COUNT(1) FROM staff WHERE SYEAR='2026' AND USERNAME='PRODELI'"), '1',
      'le compte PRODELI existe');
    assert.equal(sql("SELECT COALESCE(SCHOOLS,'(global)') FROM staff WHERE USERNAME='PRODELI'"), '(global)',
      'portee plateforme : aucune ecole assignee');
    assert.equal(sql("SELECT PROFILE FROM staff WHERE USERNAME='PRODELI'"), 'admin',
      'profil administrateur natif');
    assert.notEqual(sql("SELECT COALESCE(LAST_LOGIN::text,'jamais') FROM staff WHERE USERNAME='PRODELI'"), 'jamais',
      'compte initialise : mot de passe personnel defini lors de la premiere connexion');
    assert.equal(sql("SELECT COUNT(1) FROM schoolsafe_account_state WHERE PERSON_KEY='PRODELI'"), '0',
      'aucune ligne d\'etat : compte actif par defaut');
    return 'global + admin + initialise + actif par defaut';
  });

  // La connexion reelle de PRODELI exige son mot de passe, qui ne vit que dans
  // le fichier local (jamais dans le depot). Fichier absent => verification
  // NON FAITE, comptee comme telle.
  if (!require('node:fs').existsSync(credentialsFile)) {
    ++skipped;
    console.log('SKIP PRODELI : identifiants locaux absents (' + credentialsFile + ') — connexion non verifiee');
  } else {
    await checkAsync('PRODELI : connexion reelle, portail, portee complete, console proprietaire', async () => {
      const password = (require('node:fs').readFileSync(credentialsFile, 'utf8')
        .match(/^Mot de passe\s*:\s*(\S+)/m) || [])[1];
      assert.ok(password, 'mot de passe lu dans le fichier local');

      const c = client();
      const login = await c.login('PRODELI', password);
      assert.ok(login.location.includes('modname='), 'connexion acceptee');
      assert.doesNotMatch(login.location, /first-login/, 'compte initialise : plus de changement force');

      const portal = await c.request('/Modules.php?modname=misc/Portal.php');
      assert.equal(portal.status, 200, 'portail accessible');

      const list = await c.request('/Modules.php?modname=Users/User.php&search_modfunc=list&advanced=Y&_search_all_schools=Y');
      assert.match(list.body, /Enseignant Ecole1/, 'le proprietaire voit l\'ecole 1');
      assert.match(list.body, /Directeur Ecole2/, 'le proprietaire voit l\'ecole 2');

      const consolePage = await c.request('/admin-console.php');
      assert.equal(consolePage.status, 200, 'console accessible');
      assert.match(consolePage.body, /plateforme entiere/, 'portee plateforme presentee');
      assert.match(consolePage.body, /schoolsafe-administrators\.php\?wizard=1/,
        'porte de creation reservee au proprietaire visible');
      return 'connexion + 2 ecoles + console proprietaire verifies';
    });
  }

  // --- 10. Etat d'un etablissement entier --------------------------------
  const schoolState = id => sql(`SELECT COALESCE((SELECT STATE FROM schoolsafe_school_state
    WHERE SCHOOL_ID='${id}'),'active')`);
  const nativeCounts = () => sql(`SELECT (SELECT COUNT(1) FROM staff)||'|'||(SELECT COUNT(1) FROM students)
    ||'|'||(SELECT COUNT(1) FROM student_enrollment)||'|'||(SELECT COUNT(1) FROM schools)`);

  async function setSchoolState(c, id, target, reason = '', until = '') {
    const page = await c.request('/admin-console.php');
    const token = (page.body.match(/name="console_token" value="([^"]+)"/) || [])[1];
    assert.ok(token, 'jeton de console disponible');
    return c.request('/admin-console.php', {
      method: 'POST',
      form: {
        console_token: token, console_action: 'school_state', school_id: String(id),
        school_state: target, school_reason: reason, school_until: until,
      },
    });
  }

  async function ownerClient() {
    const c = client();
    await c.login('l1.proprietaire');
    return c;
  }

  const countsBefore = nativeCounts();

  await checkAsync('etablissement : la console du proprietaire expose les trois etats', async () => {
    const page = await (await ownerClient()).request('/admin-console.php');
    assert.match(page.body, /Etablissements/, 'carte dediee');
    assert.match(page.body, /Suspendre/, 'action suspendre');
    assert.match(page.body, /Desactiver/, 'action desactiver');
    assert.match(page.body, /badge on">Actif/, 'etat courant affiche');
    return '3 etats + actions par ecole';
  });

  await checkAsync('etablissement : un directeur ne peut pas couper une ecole', async () => {
    const c = client();
    await c.login('l1.directeur2');
    const page = await c.request('/admin-console.php');
    assert.doesNotMatch(page.body, /Desactiver/, 'aucune action ecole offerte a un directeur');

    const attempt = await setSchoolState(c, 1, 'inactive', 'tentative directeur');
    assert.match(attempt.body, /Seul le proprietaire de la plateforme/, 'refus explique');
    assert.equal(schoolState(1), 'active', 'aucun changement enregistre');
    return 'refus, etat inchange';
  });

  await checkAsync("ecole suspendue : acces coupe pour ses utilisateurs, donnees conservees", async () => {
    const owner = await ownerClient();

    // Session ouverte AVANT la suspension : elle doit tomber a la requete suivante.
    const openSession = client();
    const openLogin = await openSession.login('l1.directeur2');
    assert.ok(openLogin.location.includes('modname='), 'session ouverte avant la suspension');

    const post = await setSchoolState(owner, 2, 'suspended', 'test L1 ecoles');
    assert.equal(post.status, 302, 'suspension enregistree (redirection)');
    assert.equal(schoolState(2), 'suspended');

    const cut = await openSession.request('/Modules.php?modname=misc/Portal.php');
    assert.match(cut.location, /index\.php\?reason=school_suspended/,
      'session deja ouverte coupee a la requete suivante');

    const denied = client();
    const login = await denied.login('l1.directeur2');
    assert.equal(login.status, 200, 'page de connexion renvoyee');
    assert.match(login.body, /etablissement est suspendu/i, 'motif affiche a la personne');
    assert.doesNotMatch(login.body, /modname=/, 'aucune session ouverte');

    const ownerView = await owner.request('/Modules.php?modname=Users/User.php&search_modfunc=list&advanced=Y&_search_all_schools=Y');
    assert.match(ownerView.body, /Directeur Ecole2/, 'le proprietaire voit toujours les comptes de l\'ecole coupee');

    assert.equal(nativeCounts(), countsBefore, 'aucune donnee native supprimee ou deplacee');
    return 'acces coupe, 0 enregistrement touche';
  });

  await checkAsync("ecole suspendue : une personne de deux ecoles garde l'autre ecole", async () => {
    const username = 'l1.bischool.' + Date.now();
    sql(`INSERT INTO staff (syear,first_name,last_name,username,password,profile,profile_id,schools,last_login)
         VALUES (2026,'L1','Bi Ecoles','${username}',
         (SELECT PASSWORD FROM staff WHERE USERNAME='l1.proprietaire' LIMIT 1),'teacher',2,',1,2,',CURRENT_TIMESTAMP)`);

    try {
      const c = client();
      const login = await c.login(username);
      assert.ok(login.location.includes('modname='), 'connexion acceptee : une ecole encore ouverte');

      const portal = await c.request('/Modules.php?modname=misc/Portal.php');
      assert.equal(portal.status, 200, 'portail accessible dans l\'ecole ouverte');
      assert.match(portal.body, /Default School/, 'bascule sur l\'ecole encore ouverte');
      // Le bandeau d'information nomme legitimement l'ecole coupee : on
      // verifie le SELECTEUR, pas le corps entier.
      const selector = (portal.body.match(/<select[^>]*name="school"[\s\S]*?<\/select>/) || [''])[0];
      assert.ok(selector, 'selecteur d\'ecole present');
      assert.doesNotMatch(selector, /value="2"/, 'l\'ecole coupee a disparu du selecteur');
      return 'ecole 1 conservee, ecole 2 hors perimetre';
    } finally {
      sql(`DELETE FROM staff WHERE SYEAR='2026' AND USERNAME='${username}'`);
    }
  });

  await checkAsync('ecole reactivee : acces retabli, sans ressaisie de compte', async () => {
    const owner = await ownerClient();
    const post = await setSchoolState(owner, 2, 'active', 'fin du test');
    assert.equal(post.status, 302, 'reactivation enregistree');
    assert.equal(schoolState(2), 'active');

    const c = client();
    const login = await c.login('l1.directeur2');
    assert.ok(login.location.includes('modname='), 'connexion de nouveau acceptee');
    const portal = await c.request('/Modules.php?modname=misc/Portal.php');
    assert.equal(portal.status, 200, 'portail accessible');
    assert.equal(nativeCounts(), countsBefore, 'donnees natales intactes apres l\'aller-retour');
    return 'acces retabli, donnees intactes';
  });

  await checkAsync('ecole desactivee : acces coupe avec un motif distinct', async () => {
    const owner = await ownerClient();
    await setSchoolState(owner, 2, 'inactive', 'etablissement hors service');
    assert.equal(schoolState(2), 'inactive');

    const c = client();
    const login = await c.login('l1.directeur2');
    assert.match(login.body, /etablissement est desactive/i, 'motif de desactivation');
    assert.doesNotMatch(login.body, /modname=/, 'aucune session ouverte');

    const notice = await client().request('/index.php?reason=school_inactive');
    assert.match(notice.body, /desactive/i, 'message affiche sur la page de connexion');

    await setSchoolState(owner, 2, 'active', 'nettoyage');
    return 'desactivation distincte de la suspension';
  });

  await checkAsync('suspension temporaire : date de fin depassee => acces rendu tout seul', async () => {
    const owner = await ownerClient();
    const past = new Date(Date.now() - 86400000).toISOString().slice(0, 10);
    await setSchoolState(owner, 2, 'suspended', 'mesure temporaire', past);
    assert.equal(schoolState(2), 'suspended', 'ligne enregistree en suspension');

    const c = client();
    const login = await c.login('l1.directeur2');
    assert.ok(login.location.includes('modname='), 'date depassee => acces rendu sans intervention');

    sql("DELETE FROM schoolsafe_school_state WHERE SCHOOL_ID=2");
    assert.equal(schoolState(2), 'active', 'nettoyage : aucune ligne pour l\'ecole 2');
    return 'until depasse => active';
  });

  // --- 11. Eleves et parents : la meme regle d'ecole ---------------------
  // psql affiche le tag de commande avant les lignes : on ne garde que les
  // identifiants numeriques renvoyes par RETURNING.
  const insertId = statement => {
    const ids = sql(statement).split('\n').map(line => line.trim()).filter(line => /^[0-9]+$/.test(line));
    assert.ok(ids.length, 'identifiant insere renvoye');
    return ids[ids.length - 1];
  };

  await checkAsync('eleve : ecole suspendue coupee, ecole rouverte rendue', async () => {
    const stamp = Date.now();
    const username = 'l1.eleve2.' + stamp;
    // last_login renseigne : le changement force de premiere connexion ne doit
    // pas s'interposer, on teste le blocage d'ecole.
    const studentId = insertId(`INSERT INTO students (last_name,first_name,username,password,last_login)
      VALUES ('L1','Eleve Ecole2','${username}',
      (SELECT PASSWORD FROM staff WHERE USERNAME='l1.proprietaire' LIMIT 1),CURRENT_TIMESTAMP) RETURNING STUDENT_ID`);
    sql(`INSERT INTO student_enrollment (syear,school_id,student_id,start_date)
      VALUES (2026,2,${studentId},CURRENT_DATE - 30)`);

    try {
      const open = client();
      const before = await open.login(username, PASSWORD);
      assert.ok(before.location.includes('modname='), 'eleve connecte tant que son ecole est ouverte');

      const owner = await ownerClient();
      await setSchoolState(owner, 2, 'suspended', 'test eleve');

      const denied = client();
      const after = await denied.login(username, PASSWORD);
      assert.match(after.body, /etablissement est suspendu/i, 'motif affiche a l\'eleve');
      assert.doesNotMatch(after.body, /modname=/, 'aucune session eleve ouverte');

      const cut = await open.request('/Modules.php?modname=misc/Portal.php');
      assert.match(cut.location, /index\.php\?reason=school_suspended/,
        'session eleve deja ouverte coupee a la requete suivante');

      await setSchoolState(owner, 2, 'active', 'fin test eleve');
      const back = client();
      const login = await back.login(username, PASSWORD);
      assert.ok(login.location.includes('modname='), 'acces rendu apres reactivation');
      return 'eleve : coupe puis rendu, donnees intactes';
    } finally {
      sql(`DELETE FROM student_enrollment WHERE STUDENT_ID=${studentId}`);
      sql(`DELETE FROM students WHERE STUDENT_ID=${studentId}`);
      sql(`DELETE FROM schoolsafe_school_state WHERE SCHOOL_ID=2`);
    }
  });

  await checkAsync("parent : garde l'autre ecole et recoit l'information sur son portail", async () => {
    const stamp = Date.now();
    const parentUser = 'l1.parent2.' + stamp;
    const child2 = insertId(`INSERT INTO students (last_name,first_name,username,password,last_login)
      VALUES ('L1','Enfant Ecole2','l1.enfant2.${stamp}',
      (SELECT PASSWORD FROM staff WHERE USERNAME='l1.proprietaire' LIMIT 1),CURRENT_TIMESTAMP) RETURNING STUDENT_ID`);
    const parentId = insertId(`INSERT INTO staff (syear,first_name,last_name,username,password,profile,profile_id,schools,last_login)
      VALUES (2026,'L1','Parent Deux Ecoles','${parentUser}',
      (SELECT PASSWORD FROM staff WHERE USERNAME='l1.proprietaire' LIMIT 1),'parent',3,NULL,CURRENT_TIMESTAMP)
      RETURNING STAFF_ID`);
    sql(`INSERT INTO student_enrollment (syear,school_id,student_id,start_date)
      VALUES (2026,2,${child2},CURRENT_DATE - 30)`);
    sql(`INSERT INTO students_join_users (student_id,staff_id) VALUES (${child2},${parentId})`);
    sql(`INSERT INTO students_join_users (student_id,staff_id) VALUES (1,${parentId})`);

    try {
      const owner = await ownerClient();
      await setSchoolState(owner, 2, 'suspended', 'test parent');

      const c = client();
      const login = await c.login(parentUser, PASSWORD);
      assert.ok(login.location.includes('modname='), 'parent accepte : une ecole de ses enfants reste ouverte');

      const portal = await c.request('/Modules.php?modname=misc/Portal.php');
      assert.equal(portal.status, 200, 'portail parent accessible');
      assert.match(portal.body, /Information sur votre etablissement/, 'bandeau d\'information affiche');
      assert.match(portal.body, /Ecole Test Console/, 'l\'ecole coupee est nommee');
      assert.match(portal.body, /suspendu/i, 'motif lisible par la famille');

      // Parent dont TOUTES les ecoles sont coupees : refuse, comme ses enfants.
      sql(`DELETE FROM students_join_users WHERE STAFF_ID=${parentId} AND STUDENT_ID=1`);
      const alone = client();
      const refused = await alone.login(parentUser, PASSWORD);
      assert.match(refused.body, /etablissement est suspendu/i, 'toutes les ecoles coupees => refus');
      assert.doesNotMatch(refused.body, /modname=/, 'aucune session parent ouverte');
      return 'bandeau quand il garde un acces, refus quand tout est coupe';
    } finally {
      sql(`DELETE FROM students_join_users WHERE STAFF_ID=${parentId}`);
      sql(`DELETE FROM student_enrollment WHERE STUDENT_ID=${child2}`);
      sql(`DELETE FROM students WHERE STUDENT_ID=${child2}`);
      sql(`DELETE FROM staff WHERE STAFF_ID=${parentId}`);
      sql(`DELETE FROM schoolsafe_school_state WHERE SCHOOL_ID=2`);
    }
  });

  console.log(JSON.stringify({ passed, failed, skipped, base: BASE, proof: 'pile Docker reelle (Apache/PHP/PostgreSQL)' }));
  process.exitCode = failed ? 1 : 0;
})().catch(error => { console.error(error); process.exitCode = 1; });
