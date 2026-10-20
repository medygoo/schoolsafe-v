/*
 * SchoolSafe — L3 : unification des comptes, profils et gestionnaire PRODELI.
 *
 * Recette reelle sur pile Docker isolee (Apache + PHP 8.3 + PostgreSQL),
 * base et comptes fictifs jetables (schoolsafe_l3_recipe, conteneur frere
 * schoolsafe-l3-recipe :8084, socle rosariosis.sql + 014/015/016/018/019/032/033) :
 *   1. creation d'un administrateur scolaire autorise (par le directeur, delegue) ;
 *   2. creation d'un enseignant (formulaire adaptatif -> moteur natif) ;
 *   3. creation d'un parent lie a deux enfants (lien natif, pas de compte eleve) ;
 *   4. creation d'un gardien ;
 *   5. creation d'un personnel de cantine ;
 *   6. premiere connexion de chaque categorie (1ere connexion forcee) ;
 *   7. modification d'un profil dans les limites des droits ;
 *   8. refus d'elevation de privileges (pair/global) ;
 *   9. refus de creation d'un GlobalAdmin par une ecole ;
 *  10. refus d'acces entre ecoles (/ecoles/2 pour l'ecole 1) ;
 *  11. detection des doublons (email/phone +243, username ; sans fusion auto) ;
 *  12. multi-ecoles autorise, sans acces supplementaire ;
 *  13. responsabilites cumulees, un seul compte, jamais d'elevation ;
 *  14. desactivation et reactivation autorisees ;
 *  15. permissions verifiees cote serveur (AllowUse/AllowEdit/CSRF) ;
 *  16. navigation mobile et ordinateur (routes officielles + viewport) ;
 *  17. non-regression L1 et L2 (/connexion, /demarrer) ;
 *  18. non-regression des fonctions Rosario dependantes des comptes.
 *
 * Rendu visuel : voir qa-l3-viewports.cjs (390 + 1280).
 *
 * Usage : SCHOOLSAFE_L3_PW_CENTRAL=<jetable> node app/qa-l3-comptes.cjs
 * Variables : SCHOOLSAFE_L3_URL (defaut http://127.0.0.1:8084),
 *             SCHOOLSAFE_L3_DB (defaut schoolsafe-app-db-1),
 *             SCHOOLSAFE_L3_PGDATABASE (defaut schoolsafe_l3_recipe),
 *             SCHOOLSAFE_L3_DB_PASSWORD, SCHOOLSAFE_L3_CONTAINER.
 */
const assert = require('node:assert/strict');
const { execFileSync } = require('node:child_process');
const crypto = require('node:crypto');

const BASE = process.env.SCHOOLSAFE_L3_URL || 'http://127.0.0.1:8084';
const DB = process.env.SCHOOLSAFE_L3_DB || 'schoolsafe-app-db-1';
const PGDATABASE = process.env.SCHOOLSAFE_L3_PGDATABASE || 'schoolsafe_l3_recipe';
const APP = process.env.SCHOOLSAFE_L3_CONTAINER || 'schoolsafe-l3-recipe';
const PGUSER = 'schoolsafe';
const PGPASSWORD = process.env.SCHOOLSAFE_L3_DB_PASSWORD || 'preview-local-jetable-32caracteres-xx';
const PW_CENTRAL = process.env.SCHOOLSAFE_L3_PW_CENTRAL || '';

let passed = 0, failed = 0;
function sql(statement) {
  return execFileSync('docker', ['exec', '-e', 'PGPASSWORD=' + PGPASSWORD, DB,
    'psql', '-U', PGUSER, '-d', PGDATABASE, '-At', '-c', statement], { encoding: 'utf8' }).trim();
}
function phpCrypt(password) {
  return execFileSync('docker', ['exec', APP, 'php', '-r',
    'echo crypt($argv[1], "$6$".substr(strtr(base64_encode(random_bytes(12)),"+","."),0,16)."$");',
    password], { encoding: 'utf8' }).trim();
}
async function check(name, fn) {
  try {
    const r = await fn();
    ++passed; console.log('PASS ' + name + (r ? ' — ' + r : ''));
  } catch (e) { ++failed; console.log('FAIL ' + name + ': ' + String((e && e.message) || e).split('\n').slice(0, 6).join(' | ')); }
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
    const setCookies = response.headers.getSetCookie ? response.headers.getSetCookie() : [];
    for (const raw of setCookies) {
      const [pair] = raw.split(';');
      const i = pair.indexOf('=');
      const name = pair.slice(0, i).trim(), value = pair.slice(i + 1).trim();
      if (value === '' || /deleted/i.test(raw)) jar.delete(name); else jar.set(name, value);
    }
    return { status: response.status, location: response.headers.get('location') || '', body: await response.text() };
  }
  return {
    request,
    async login(username, password) {
      await request('/index.php');
      return request('/index.php', { method: 'POST', form: { USERNAME: username, PASSWORD: password } });
    },
  };
}
const tokenOf = (html) => (html.match(/name="token" value="([^"]+)"/) || [])[1]
  || (html.match(/<meta name="token" content="([^"]+)"/) || [])[1] || '';
const tempOf = (html) => {
  const m = html.match(/Mot de passe temporaire[^<]*<\/dt><dd><code>([^<]+)<\/code>/)
    || html.match(/Mot de passe temporaire<\/dt><dd><code>([^<]+)<\/code>/);
  return m ? m[1] : '';
};
function hiddenStaff(html) {
  const fields = {};
  for (const m of html.matchAll(/<input[^>]*type="hidden"[^>]*>/gi)) {
    const name = (m[0].match(/name="([^"]+)"/) || [])[1];
    const value = (m[0].match(/value="([^"]*)"/) || [])[1] || '';
    if (name) fields[name] = value;
  }
  return fields;
}
const rnd = (n) => crypto.randomBytes(n).toString('base64').replace(/[^A-Za-z0-9]/g, 'x').slice(0, n);
async function firstLogin(c, username, tempPw) {
  const login = await c.login(username, tempPw);
  assert.ok(login.location.includes('first-login'), '1ere connexion forcee pour ' + username + ', got ' + login.location);
  const flToken = (login.location.match(/[?&]token=([0-9a-f]+)/) || [])[1] || '';
  assert.ok(flToken, 'jeton 1ere connexion');
  const newPw = rnd(14) + 'A9!';
  const done = await c.request('/index.php?modfunc=first-login&token=' + flToken, {
    method: 'POST', form: { 'first_login[PASSWORD]': newPw } });
  assert.ok(done.location.includes('Portal'), 'vers portail, got ' + done.location);
  return newPw;
}
// Creation via le hub officiel : POST hub -> confirmation (moteur natif) -> POST natif.
async function hubCreate(actor, schoolId, fields) {
  const get = await actor.request(`/ecoles/${schoolId}/utilisateurs/nouveau`);
  assert.equal(get.status, 200, 'hub nouveau 200, got ' + get.status);
  const post = await actor.request(`/ecoles/${schoolId}/utilisateurs/nouveau`, {
    method: 'POST', form: { token: tokenOf(get.body), l3_create: '1', ...fields } });
  assert.equal(post.status, 200);
  return post.body;
}
const alertOf = (html) => {
  const m = html.match(/<p class="alert" role="alert">([^<]{0,220})/);
  return m ? ' — hub: ' + m[1] : ' — hub sans alerte';
};
async function hubConfirm(actor, confirmHtml) {
  const fields = hiddenStaff(confirmHtml);
  assert.ok(fields['staff[USERNAME]'], 'confirmation native prete' + alertOf(confirmHtml));
  const r = await actor.request('/Modules.php?modname=Users/User.php&modfunc=update&staff_id=new&token=' + fields.token, {
    method: 'POST', form: fields });
  assert.equal(r.status, 200, 'creation native 200, got ' + r.status);
  return { temp: tempOf(confirmHtml), username: fields['staff[USERNAME]'] };
}

const S = {};
async function main() {
  assert.ok(PW_CENTRAL && /^[A-Za-z0-9!-]{8,64}$/.test(PW_CENTRAL), 'mot de passe central jetable requis en env');
  assert.equal(sql('SELECT count(*) FROM user_profiles;'), '10', 'socle + 015 + 033 = 10 profils');

  // --- Socle L3 : ecole n°2 + central global --------------------------------
  S.profiles = {};
  for (const t of sql('SELECT TITLE||chr(31)||ID FROM user_profiles;').split('\n'))
    if (t) { const [title, id] = t.split('\x1f'); S.profiles[title] = id; }
  for (const need of ['Directeur', 'Administrateur scolaire', 'Gardien', 'Personnel administratif', 'Cantine'])
    assert.ok(S.profiles[need], 'profil ' + need);
  // Parité production : la 1ère connexion forcée exige le flag global (le socle le sème à NULL ;
  // la prod l'a positionné via la console — voir admin-console-bridge, « ligne globale 0 »).
  sql(`UPDATE config SET CONFIG_VALUE='Y' WHERE SCHOOL_ID=0 AND TITLE='FORCE_PASSWORD_CHANGE_ON_FIRST_LOGIN';`);
  sql(`INSERT INTO schools (SYEAR,TITLE,ADDRESS,CITY,REPORTING_GP_SCALE)
    SELECT 2026,'École L3 Secondaire','1 av. Test','Kinshasa',REPORTING_GP_SCALE FROM schools WHERE ID=1 AND SYEAR=2026;`);
  S.school2 = parseInt(sql(`SELECT ID FROM schools WHERE TITLE='École L3 Secondaire' AND SYEAR=2026;`), 10);
  assert.ok(S.school2 > 1, 'ecole n°2');
  sql(`INSERT INTO school_marking_periods (SYEAR,MP,SCHOOL_ID,PARENT_ID,TITLE,SHORT_NAME,SORT_ORDER,START_DATE,END_DATE)
    SELECT SYEAR,MP,${S.school2},NULL,TITLE,SHORT_NAME,SORT_ORDER,START_DATE,END_DATE
    FROM school_marking_periods WHERE SCHOOL_ID=1 AND SYEAR=2026;`);
  const centralHash = phpCrypt(PW_CENTRAL).replace(/'/g, '');
  assert.match(centralHash, /^\$6\$/, 'hash natif');
  sql(`UPDATE staff SET USERNAME='l3central',PROFILE='admin',PROFILE_ID=1,SCHOOLS=NULL,EMAIL='central@l3.invalid',PASSWORD='${centralHash}',LAST_LOGIN=CURRENT_TIMESTAMP WHERE STAFF_ID=1 AND SYEAR=2026;`);
  const central = client();
  assert.equal((await central.login('l3central', PW_CENTRAL)).status, 302, 'central connecte');
  S.central = central;

  await check('T1 routes officielles : hub + nouveau + fiche + profils + PRODELI', async () => {
    for (const [path, needle] of [
      ['/ecoles/1/utilisateurs', 'Nouvel utilisateur'],
      ['/ecoles/1/utilisateurs/nouveau', 'formulaire adaptatif'],
      ['/ecoles/1/profils', 'Matrice appliquée'],
      ['/plateforme/prodeli', 'Comptes'],
    ]) {
      const r = await S.central.request(path);
      assert.equal(r.status, 200, path + ' got ' + r.status);
      assert.match(r.body, new RegExp(needle), path);
      assert.match(r.body, /name="viewport"/, path + ' viewport');
    }
    const anon = await client().request('/ecoles/1/utilisateurs');
    assert.ok(anon.status === 302 || anon.status === 403, 'hub ferme sans session');
    return '5 ecrans, session exigee';
  });

  await check('T2 directeur cree par le central, 1ere connexion', async () => {
    const body = await hubCreate(S.central, 1, { categorie: 'direction', first_name: 'Dora', last_name: 'L3',
      username: 'l3directeur', email: 'directeur@l3.invalid', phone: '+243815000001', 'schools[1]': 'Y' });
    assert.match(body, /Vérifications OK/, 'hub valide' + alertOf(body));
    const created = await hubConfirm(S.central, body);
    S.tempDirector = created.temp;
    assert.ok(S.tempDirector, 'temporaire affiche une fois');
    const row = sql(`SELECT PROFILE||':'||COALESCE(SCHOOLS,'NULL')||':'||COALESCE(LAST_LOGIN::text,'NULL') FROM staff WHERE USERNAME='l3directeur' AND SYEAR=2026;`);
    assert.match(row, /^admin:,1,:NULL$/, 'directeur natif ,1, 1ere connexion, got ' + row);
    S.directorPw = await firstLogin(client(), 'l3directeur', S.tempDirector);
    const d = client();
    assert.equal((await d.login('l3directeur', S.directorPw)).status, 302);
    S.director = d;
    return 'directeur ,1, actif';
  });

  await check('T3 admin scolaire cree PAR le directeur (delegation), enseignant aussi', async () => {
    const body = await hubCreate(S.director, 1, { categorie: 'admin_scolaire', first_name: 'Aline', last_name: 'L3',
      username: 'l3admin', email: 'admin@l3.invalid', phone: '+243815000002', 'schools[1]': 'Y' });
    assert.match(body, /Vérifications OK/);
    const created = await hubConfirm(S.director, body);
    const title = sql(`SELECT u.TITLE FROM staff s JOIN user_profiles u ON u.ID=s.PROFILE_ID WHERE s.USERNAME='l3admin';`);
    assert.equal(title, 'Administrateur scolaire');
    S.tempAdmin = created.temp;
    const tb = await hubCreate(S.director, 1, { categorie: 'enseignant', first_name: 'Theo', last_name: 'L3',
      username: 'l3enseignant', email: 'enseignant@l3.invalid', phone: '+243815000003', 'schools[1]': 'Y' });
    const tc = await hubConfirm(S.director, tb);
    S.tempTeacher = tc.temp;
    assert.equal(sql(`SELECT PROFILE FROM staff WHERE USERNAME='l3enseignant';`), 'teacher');
    return 'delegation directeur->admin scolaire + enseignant';
  });

  await check('T4 parent lie a deux enfants, sans compte eleve', async () => {
    const body = await hubCreate(S.director, 1, { categorie: 'parent', first_name: 'Paula', last_name: 'L3',
      username: 'l3parent', email: 'parent@l3.invalid', phone: '+243815000004' });
    assert.match(body, /Vérifications OK/);
    const created = await hubConfirm(S.director, body);
    S.tempParent = created.temp;
    // Le natif n'attache jamais un parent a une ecole : DBInsert omet la colonne
    // vide, donc NULL (comme le parent du socle), jamais une ecole.
    assert.equal(sql(`SELECT COALESCE(SCHOOLS,'NULL') FROM staff WHERE USERNAME='l3parent';`), 'NULL', 'parent sans ecole');
    const before = sql(`SELECT count(*) FROM staff;`);
    sql(`INSERT INTO students (FIRST_NAME,LAST_NAME) VALUES ('Enfant1','L3'),('Enfant2','L3');`);
    const e1 = sql(`SELECT STUDENT_ID FROM students WHERE FIRST_NAME='Enfant1' AND LAST_NAME='L3' ORDER BY STUDENT_ID DESC LIMIT 1;`);
    const e2 = sql(`SELECT STUDENT_ID FROM students WHERE FIRST_NAME='Enfant2' AND LAST_NAME='L3' ORDER BY STUDENT_ID DESC LIMIT 1;`);
    const pid = sql(`SELECT STAFF_ID FROM staff WHERE USERNAME='l3parent';`);
    sql(`INSERT INTO student_enrollment (SYEAR,SCHOOL_ID,STUDENT_ID) VALUES (2026,1,${e1}),(2026,1,${e2});`);
    sql(`INSERT INTO students_join_users (STUDENT_ID,STAFF_ID) VALUES (${e1},${pid}),(${e2},${pid});`);
    assert.equal(sql(`SELECT count(*) FROM students_join_users WHERE STAFF_ID=${pid};`), '2', 'deux enfants lies');
    assert.equal(sql(`SELECT count(*) FROM staff;`), before, 'inscription eleve = zero compte');
    const parentClient = await (async () => { const c = client(); await firstLogin(c, 'l3parent', S.tempParent); return c; })();
    const portal = await parentClient.request('/Modules.php?modname=misc/Portal.php');
    assert.equal(portal.status, 200);
    // Le socle natif affiche « Utilisateurs » au parent pour SA propre fiche ;
    // la garantie L3 : la fiche d'AUTRUI est fermee (HackingLog + sortie).
    const otherId = sql(`SELECT STAFF_ID FROM staff WHERE USERNAME='l3enseignant';`);
    const other = await parentClient.request('/Modules.php?modname=Users/User.php&staff_id=' + otherId);
    assert.ok(!/Theo/.test(other.body), 'fiche autrui fermee au parent');
    const self = await parentClient.request('/Modules.php?modname=Users/User.php&staff_id=' + pid);
    assert.ok(/Paula/.test(self.body), 'propre fiche accessible (socle natif)');
    return 'parent + 2 enfants natifs';
  });

  await check('T5 gardien + cantine crees, profils 015/033', async () => {
    const gb = await hubCreate(S.director, 1, { categorie: 'gardien', first_name: 'Gaston', last_name: 'L3',
      username: 'l3gardien', email: 'gardien@l3.invalid', phone: '+243815000005' });
    const gc = await hubConfirm(S.director, gb);
    S.tempGuardian = gc.temp;
    assert.equal(sql(`SELECT u.TITLE FROM staff s JOIN user_profiles u ON u.ID=s.PROFILE_ID WHERE s.USERNAME='l3gardien';`), 'Gardien');
    const cb = await hubCreate(S.director, 1, { categorie: 'cantine', first_name: 'Coco', last_name: 'L3',
      username: 'l3cantine', email: 'cantine@l3.invalid', phone: '+243815000006', 'schools[1]': 'Y' });
    const cc = await hubConfirm(S.director, cb);
    S.tempCanteen = cc.temp;
    assert.equal(sql(`SELECT u.TITLE FROM staff s JOIN user_profiles u ON u.ID=s.PROFILE_ID WHERE s.USERNAME='l3cantine';`), 'Cantine');
    return 'gardien + cantine natifs';
  });

  await check('T6 premieres connexions : admin, enseignant, gardien, cantine', async () => {
    S.adminPw = await firstLogin(client(), 'l3admin', S.tempAdmin);
    S.teacherPw = await firstLogin(client(), 'l3enseignant', S.tempTeacher);
    await firstLogin(client(), 'l3gardien', S.tempGuardian);
    await firstLogin(client(), 'l3cantine', S.tempCanteen);
    assert.ok(sql(`SELECT LAST_LOGIN FROM staff WHERE USERNAME='l3admin';`) !== '', 'LAST_LOGIN admin');
    return '4 changements forces OK';
  });

  await check('T7 modification dans les limites (directeur -> enseignant)', async () => {
    const page = await S.director.request('/Modules.php?modname=Users/User.php&staff_id=' + sql(`SELECT STAFF_ID FROM staff WHERE USERNAME='l3enseignant';`));
    assert.equal(page.status, 200);
    const m = page.body.match(/action="(Modules\.php\?modname=Users\/User\.php[^"]*modfunc=update[^"]*)"/);
    assert.ok(m, 'formulaire natif');
    const action = '/' + m[1].replace(/&amp;/g, '&');
    const upd = await S.director.request(action, { method: 'POST',
      form: { token: tokenOf(page.body), 'staff[CUSTOM_200000001]': '+243815000033' } });
    assert.equal(upd.status, 200);
    assert.equal(sql(`SELECT CUSTOM_200000001 FROM staff WHERE USERNAME='l3enseignant';`), '+243815000033');
    return 'telephone modifie';
  });

  await check('T8 refus d’elevation : directeur ne nomme ni pair ni global', async () => {
    const refuse = await hubCreate(S.director, 1, { categorie: 'direction', first_name: 'Eve', last_name: 'L3',
      username: 'l3eve', email: 'eve@l3.invalid', phone: '+243815000007', 'schools[1]': 'Y' });
    assert.match(refuse, /attribuer ce profil/, 'pair refuse au hub');
    assert.equal(sql(`SELECT count(*) FROM staff WHERE USERNAME='l3eve';`), '0', 'zero compte');
    const page = await S.director.request('/Modules.php?modname=Users/User.php&staff_id=new');
    const m = page.body.match(/action="(Modules\.php\?modname=Users\/User\.php[^"]*modfunc=update[^"]*)"/);
    const native = await S.director.request('/' + m[1].replace(/&amp;/g, '&'), { method: 'POST', form: {
      token: tokenOf(page.body), 'staff[FIRST_NAME]': 'Eve', 'staff[LAST_NAME]': 'L3',
      'staff[USERNAME]': 'l3eve2', 'staff[PASSWORD]': 'Temp' + rnd(8), 'staff[PROFILE]': 'admin',
      'staff[PROFILE_ID]': '1', 'staff[SCHOOLS][1]': 'Y' } });
    assert.ok(native.status === 403 || /not allowed|pas autoris/i.test(native.body), 'admin natif refuse, got ' + native.status);
    assert.equal(sql(`SELECT count(*) FROM staff WHERE USERNAME='l3eve2';`), '0');
    return 'elevation fermee hub + natif';
  });

  await check('T9 une ecole ne cree jamais de GlobalAdmin', async () => {
    const page = await S.director.request('/Modules.php?modname=Users/User.php&staff_id=new');
    const m = page.body.match(/action="(Modules\.php\?modname=Users\/User\.php[^"]*modfunc=update[^"]*)"/);
    const r = await S.director.request('/' + m[1].replace(/&amp;/g, '&'), { method: 'POST', form: {
      token: tokenOf(page.body), 'staff[FIRST_NAME]': 'Glob', 'staff[LAST_NAME]': 'L3',
      'staff[USERNAME]': 'l3glob', 'staff[PASSWORD]': 'Temp' + rnd(8), 'staff[PROFILE]': 'teacher' } });
    assert.equal(r.status, 200);
    assert.equal(sql(`SELECT COALESCE(SCHOOLS,'NULL') FROM staff WHERE USERNAME='l3glob';`), ',1,', 'assigne ecole 1, jamais global');
    const profils = await S.director.request('/ecoles/1/profils');
    assert.match(profils.body, /Élévation refusée|Non — élévation refusée/, 'matrice affiche les refus');
    return 'SCHOOLS force ,1,';
  });

  await check('T10 refus d’acces entre ecoles', async () => {
    const t = client();
    await t.login('l3enseignant', S.teacherPw);
    for (const p of [`/ecoles/${S.school2}/utilisateurs`, `/ecoles/${S.school2}/profils`]) {
      const r = await t.request(p);
      assert.ok(r.status === 403, p + ' refuse, got ' + r.status);
    }
    const own = await t.request('/ecoles/1/utilisateurs');
    assert.ok(own.status === 403, 'enseignant non-admin tenu a distance du hub, got ' + own.status);
    return 'perimetres etanches';
  });

  await check('T11 doublons : email/phone/username, sans fusion auto', async () => {
    const dup = await hubCreate(S.director, 1, { categorie: 'enseignant', first_name: 'Theo', last_name: 'Bis',
      username: 'l3theobis', email: 'ENSEIGNANT@l3.invalid', phone: '0815000003', 'schools[1]': 'Y' });
    assert.match(dup, /Doublon possible/, 'email+phone detectes (casse/format ignores)');
    assert.equal(sql(`SELECT count(*) FROM staff WHERE USERNAME='l3theobis';`), '0', 'rien cree sans verification');
    const ok = await hubCreate(S.director, 1, { categorie: 'enseignant', first_name: 'Theo', last_name: 'Bis',
      username: 'l3theobis', email: 'enseignant@l3.invalid', phone: '0815000003',
      'schools[1]': 'Y', doublon_verifie: '1' });
    assert.match(ok, /Vérifications OK/, 'creation possible APRES verification manuelle');
    await hubConfirm(S.director, ok);
    assert.equal(sql(`SELECT count(*) FROM staff WHERE USERNAME IN ('l3enseignant','l3theobis');`), '2', 'deux identites distinctes, zero fusion');
    const page = await S.director.request('/Modules.php?modname=Users/User.php&staff_id=new');
    const m = page.body.match(/action="(Modules\.php\?modname=Users\/User\.php[^"]*modfunc=update[^"]*)"/);
    const du = await S.director.request('/' + m[1].replace(/&amp;/g, '&'), { method: 'POST', form: {
      token: tokenOf(page.body), 'staff[FIRST_NAME]': 'X', 'staff[LAST_NAME]': 'Y',
      'staff[USERNAME]': 'L3ENSEIGNANT', 'staff[PASSWORD]': 'Temp' + rnd(8), 'staff[PROFILE]': 'teacher' } });
    assert.match(du.body, /already exists/i, 'username insensible a la casse refuse par le natif');
    return 'unicite +243/casse, zero fusion';
  });

  await check('T12 multi-ecoles autorise, sans acces supplementaire', async () => {
    const body = await hubCreate(S.central, 1, { categorie: 'personnel', first_name: 'Mani', last_name: 'L3',
      username: 'l3multi', email: 'multi@l3.invalid', phone: '+243815000008', 'schools[1]': 'Y',
      ['schools[' + S.school2 + ']']: 'Y' });
    const created = await hubConfirm(S.central, body);
    assert.equal(sql(`SELECT COALESCE(SCHOOLS,'NULL') FROM staff WHERE USERNAME='l3multi';`), `,1,${S.school2},`);
    const pw = await firstLogin(client(), 'l3multi', created.temp);
    const c = client();
    await c.login('l3multi', pw);
    for (const sid of [1, S.school2]) {
      const p = await c.request(`/Modules.php?modname=misc/Portal.php&school=${sid}`);
      assert.ok(p.status === 200 || p.status === 302, 'ecole ' + sid + ' contextuelle');
    }
    assert.ok(!/modname=Users\/Profiles\.php/.test((await c.request('/Modules.php?modname=misc/Portal.php')).body), 'aucun droit global');
    S.multiPw = pw;
    return 'personnel ,1,N, borne au perimetre';
  });

  await check('T13 cumuls : un seul compte, jamais d’elevation', async () => {
    const tid = sql(`SELECT STAFF_ID FROM staff WHERE USERNAME='l3enseignant';`);
    const e3 = sql(`SELECT STUDENT_ID FROM students WHERE FIRST_NAME='Enfant1' ORDER BY STUDENT_ID DESC LIMIT 1;`);
    sql(`INSERT INTO students_join_users (STUDENT_ID,STAFF_ID) VALUES (${e3},${tid}) ON CONFLICT DO NOTHING;`);
    const c = client();
    await c.login('l3enseignant', S.teacherPw);
    const portal = await c.request('/Modules.php?modname=misc/Portal.php');
    assert.equal(portal.status, 200, 'compte unique toujours valide');
    assert.ok(!/modname=Users\/Profiles\.php/.test(portal.body), 'cumul sans droit global');
    const sw = await c.request(`/Modules.php?modname=misc/Portal.php&school=${S.school2}`);
    assert.ok(sw.status === 302 || !sw.body.includes('Secondaire'), 'changement de contexte sans gain');
    const exc = await S.director.request('/Modules.php?modname=Users/Exceptions.php');
    assert.ok(exc.status === 403 || /not allowed|REFRESH/i.test(exc.body), 'directeur sans ecran global');
    sql(`DELETE FROM students_join_users WHERE STAFF_ID=${tid} AND STUDENT_ID=${e3};`);
    return 'enseignant+parent lie, contexte sans gain';
  });

  await check('T14 desactivation puis reactivation (console, meme application)', async () => {
    sql(`INSERT INTO schoolsafe_account_state (PERSON_KEY,SCHOOL_ID,STATE,REASON) VALUES ('l3enseignant',0,'suspended','recette L3') ON CONFLICT (PERSON_KEY,SCHOOL_ID) DO UPDATE SET STATE='suspended';`);
    const refused = await client().login('l3enseignant', S.teacherPw);
    assert.ok(!refused.location.includes('Portal'), 'connexion suspendue refusee');
    const prodeli = await S.central.request('/plateforme/prodeli');
    assert.equal(prodeli.status, 200);
    assert.match(prodeli.body, /l3enseignant/, 'console voit le compte');
    sql(`DELETE FROM schoolsafe_account_state WHERE PERSON_KEY='l3enseignant';`);
    const back = await client().login('l3enseignant', S.teacherPw);
    assert.ok(back.location.includes('Portal') || back.status === 302, 'acces retabli');
    return 'suspendu puis reactif';
  });

  await check('T15 permissions cote serveur : AllowUse/AllowEdit/CSRF', async () => {
    const t = client();
    await t.login('l3enseignant', S.teacherPw);
    const page = await t.request('/Modules.php?modname=Users/User.php&staff_id=new');
    assert.ok(page.status === 403 || /REFRESH|not allowed/i.test(page.body), 'enseignant hors creation');
    const csrf = await S.director.request('/ecoles/1/utilisateurs/nouveau',
      { method: 'POST', form: { l3_create: '1', categorie: 'cantine' } });
    assert.match(csrf.body, /expirée/, 'hub exige le jeton');
    const naked = await t.request('/Modules.php?modname=Users/User.php&modfunc=update&staff_id=new',
      { method: 'POST', form: { 'staff[FIRST_NAME]': 'Hack' } });
    assert.ok(naked.status === 403 || /REFRESH|Portal|not allowed/i.test(naked.body), 'natif exige le jeton');
    return 'gardes serveur OK';
  });

  await check('T16 navigation : 4 ecrans officiels, viewport, convergence', async () => {
    for (const p of ['/ecoles/1/utilisateurs', '/ecoles/1/utilisateurs/nouveau', '/ecoles/1/profils', '/plateforme/prodeli']) {
      const r = await S.director.request(p).catch(() => null);
      const rr = r && r.status === 200 ? r : await S.central.request(p);
      assert.equal(rr.status, 200, p);
      for (const link of ['/ecoles/1/utilisateurs', '/ecoles/1/profils', '/plateforme/prodeli'])
        assert.ok(rr.body.includes(link), p + ' converge vers ' + link);
    }
    const nouveau = await S.central.request('/ecoles/1/utilisateurs/nouveau');
    for (const opt of ['direction', 'enseignant', 'admin_scolaire', 'parent', 'gardien', 'personnel', 'cantine'])
      assert.ok(nouveau.body.includes(`value="${opt}"`), 'categorie ' + opt);
    return 'hub unique, 7 categories';
  });

  await check('T17 non-regression L1/L2', async () => {
    const conn = await client().request('/connexion');
    assert.equal(conn.status, 200);
    assert.match(conn.body, /USERNAME/);
    const dem = await client().request('/demarrer');
    assert.equal(dem.status, 200);
    assert.match(dem.body, /code d’autorisation/);
    const demarrerClient = client();
    const g = await demarrerClient.request('/demarrer');
    const dep = await demarrerClient.request('/demarrer', { method: 'POST',
      form: { token: tokenOf(g.body), demarrer_action: 'request', school_name: 'École L3 Témoin',
        person_name: 'Temoin L3', email: 'temoin@l3.invalid' } });
    assert.match(dep.body, /Demande n°\d+/, 'depot L2 intact');
    const t = client();
    const login = await t.login('l3enseignant', S.teacherPw);
    assert.ok(!login.location.includes('first-login'), '2e connexion directe (L1)');
    return 'connexion + ouverture intactes';
  });

  await check('T18 fonctions Rosario dependantes des comptes', async () => {
    const search = await S.director.request('/Modules.php?modname=Users/Search.php');
    assert.ok(search.status === 200, 'recherche utilisateurs, got ' + search.status);
    const cp = await S.director.request('/Modules.php?modname=Custom/CreateParents.php');
    assert.ok(cp.status === 200, 'liaison parents, got ' + cp.status);
    const prof = await S.central.request('/Modules.php?modname=Users/Profiles.php');
    assert.equal(prof.status, 200, 'profils natifs central');
    const stu = await S.director.request('/Modules.php?modname=Students/Student.php&student_id=new');
    assert.ok(stu.status === 200, 'dossier eleve separe, got ' + stu.status);
    return 'Search + CreateParents + Profiles + Student OK';
  });

  console.log(`\nL3 : ${passed} PASS, ${failed} FAIL`);
  process.exit(failed ? 1 : 0);
}

main().catch((e) => { console.error('FATAL', e); process.exit(2); });
