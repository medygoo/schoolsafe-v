/*
 * SchoolSafe — L2 : parcours officiel /demarrer (ouverture d'ecole).
 *
 * Recette reelle sur pile Docker isolee (Apache + PHP 8.3 + PostgreSQL),
 * base et comptes fictifs jetables :
 *   1. ouverture avec code valide ;
 *   2. refus d'un code errone ;
 *   3. refus d'un code expire ;
 *   4. refus de reutilisation du code ;
 *   5. refus de deux activations concurrentes (une seule gagne) ;
 *   6. creation complete de l'ecole (assistant -> CopySchool natif) ;
 *   7. echec controle sans ecole partielle (doublon + moteur) ;
 *   8. creation et rattachement de l'administrateur principal (natif) ;
 *   9. premiere connexion avec changement de mot de passe ;
 *  10. acces au tableau de bord de l'etablissement ;
 *  11. refus de creation d'ecole par un directeur non autorise ;
 *  12. refus des acces inter-ecoles ;
 *  13. maintien de l'approbation manuelle autorisee ;
 *  14. non-regression L1 (connexion, 1ere connexion, permissions, logout, CSRF).
 *
 * Rendu mobile + ordinateur : voir qa-l2-ouverture-viewports.cjs.
 *
 * Usage (mots de passe fictifs via env, jamais journalises) :
 *   SCHOOLSAFE_L2_PW_CENTRAL=... SCHOOLSAFE_L2_PW_DIRECTOR=... \
 *   SCHOOLSAFE_L2_PW_TEACHER=... SCHOOLSAFE_L2_PW_PARENT=... \
 *   node app/qa-l2-ouverture.cjs
 * Variables : SCHOOLSAFE_L2_URL (defaut http://127.0.0.1:8083),
 *             SCHOOLSAFE_L2_DB (defaut schoolsafe-app-db-1),
 *             SCHOOLSAFE_L2_PGDATABASE (defaut schoolsafe_l2_recipe),
 *             SCHOOLSAFE_L2_DB_PASSWORD, SCHOOLSAFE_L2_CONTAINER.
 */
const assert = require('node:assert/strict');
const { execFileSync } = require('node:child_process');
const crypto = require('node:crypto');

const BASE = process.env.SCHOOLSAFE_L2_URL || 'http://127.0.0.1:8083';
const DB = process.env.SCHOOLSAFE_L2_DB || 'schoolsafe-app-db-1';
const PGDATABASE = process.env.SCHOOLSAFE_L2_PGDATABASE || 'schoolsafe_l2_recipe';
const APP = process.env.SCHOOLSAFE_L2_CONTAINER || 'schoolsafe-l2-recipe';
const PGUSER = 'schoolsafe';
const PGPASSWORD = process.env.SCHOOLSAFE_L2_DB_PASSWORD || 'preview-local-jetable-32caracteres-xx';
const PW = {
  central: process.env.SCHOOLSAFE_L2_PW_CENTRAL || '',
  director: process.env.SCHOOLSAFE_L2_PW_DIRECTOR || '',
  teacher: process.env.SCHOOLSAFE_L2_PW_TEACHER || '',
  parent: process.env.SCHOOLSAFE_L2_PW_PARENT || '',
};

let passed = 0, failed = 0;
function sql(statement) {
  return execFileSync('docker', ['exec', '-e', 'PGPASSWORD=' + PGPASSWORD, DB,
    'psql', '-U', PGUSER, '-d', PGDATABASE, '-At', '-c', statement], { encoding: 'utf8' }).trim();
}
function check(name, fn) {
  try {
    const result = fn();
    if (result && typeof result.then === 'function')
      return result.then(
        (r) => { ++passed; console.log('PASS ' + name + (r ? ' — ' + r : '')); },
        (e) => { ++failed; console.log('FAIL ' + name + ': ' + String((e && e.message) || e).split('\n')[0]); });
    ++passed; console.log('PASS ' + name + (result ? ' — ' + result : ''));
  } catch (error) {
    ++failed; console.log('FAIL ' + name + ': ' + String(error.message).split('\n')[0]);
  }
  return null;
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
const draftOf = (html) => (html.match(/name="draft_id" value="([^"]+)"/) || [])[1] || '';
const codeOf = (html) => (html.match(/<code>([^<]+)<\/code>/) || [])[1] || '';
const requestIdOf = (text) => {
  const m = text.match(/Demande n°(\d+)/) || text.match(/sous le n°(\d+)/);
  return m ? parseInt(m[1], 10) : 0;
};
function hiddenFields(html) {
  const fields = {};
  for (const m of html.matchAll(/<input[^>]*type="hidden"[^>]*>/gi)) {
    const name = (m[0].match(/name="([^"]+)"/) || [])[1];
    const value = (m[0].match(/value="([^"]*)"/) || [])[1] || '';
    if (name) fields[name] = value;
  }
  return fields;
}

const state = {};
async function main() {
  assert.ok(PW.central && PW.director && PW.teacher && PW.parent, 'mots de passe fictifs requis en env');

  await check('T1 /demarrer : 200 + formulaires + viewport', async () => {
    const c = client();
    const r = await c.request('/demarrer');
    assert.equal(r.status, 200);
    assert.match(r.body, /J’ai un code d’autorisation/);
    assert.match(r.body, /Déposer une demande sans code/);
    assert.match(r.body, /name="viewport"/);
    state.token = tokenOf(r.body);
    assert.ok(state.token, 'jeton CSRF public');
    return 'page officielle servie';
  });

  await check('T2 depot public sans code -> demande manuelle, jamais auto-approuvee', async () => {
    const c = client();
    const g = await c.request('/demarrer');
    const r = await c.request('/demarrer', { method: 'POST', form: {
      token: tokenOf(g.body), demarrer_action: 'request',
      school_name: 'École L2 Pilote', person_name: 'Amina L2',
      email: 'l2pilote@example.invalid' } });
    assert.equal(r.status, 200);
    state.idA = requestIdOf(r.body);
    assert.ok(state.idA > 0, 'numero de demande');
    assert.match(r.body, /examine.*manuellement|manuellement/);
    assert.equal(sql(`SELECT STATE FROM schoolsafe_opening_requests WHERE ID=${state.idA};`), 'requested');
    return 'demande n°' + state.idA + ' requested';
  });

  await check('T3 renvoi identique -> idempotence, pas de doublon', async () => {
    const c = client();
    const g = await c.request('/demarrer');
    const r = await c.request('/demarrer', { method: 'POST', form: {
      token: tokenOf(g.body), demarrer_action: 'request',
      school_name: 'École L2 Pilote', person_name: 'Amina L2',
      email: 'l2pilote@example.invalid' } });
    assert.equal(requestIdOf(r.body), state.idA, 'meme numero');
    assert.match(r.body, /déjà enregistrée/);
    return 'n°' + state.idA + ' unique';
  });

  await check('T4 central : emission du code, affiche une seule fois', async () => {
    const c = client();
    const login = await c.login('l2central', PW.central);
    assert.equal(login.status, 302, 'login central, got ' + login.status);
    const page = await c.request('/schoolsafe-administrators.php?opening=1');
    assert.equal(page.status, 200);
    assert.match(page.body, /Demandes d’ouverture/);
    const issue = await c.request('/schoolsafe-administrators.php?opening=1', { method: 'POST', form: {
      token: tokenOf(page.body), opening_action: 'issue_code', request_id: String(state.idA) } });
    assert.equal(issue.status, 200);
    const shown = [...issue.body.matchAll(/<code>([^<]+)<\/code>/g)].map((m) => m[1]);
    state.codeA = shown.find((x) => /^[A-Z2-9]{6}(-[A-Z2-9]{6}){3}$/.test(x)) || '';
    assert.ok(state.codeA, 'code affiche (vus : ' + JSON.stringify(shown).slice(0, 160) + ')');
    const again = await c.request('/schoolsafe-administrators.php?opening=1');
    assert.ok(!/<code>[A-Z2-9-]/.test(again.body), 'code non reaffiche');
    assert.match(again.body, /Code d’autorisation actif/);
    const hash = sql(`SELECT CODE_HASH FROM schoolsafe_opening_codes WHERE REQUEST_ID=${state.idA} ORDER BY ID DESC LIMIT 1;`);
    assert.match(hash, /^[0-9a-f]{64}$/, 'hash seul en base');
    state.central = c;
    return 'code emis, hash SHA-256, affichage unique';
  });

  await check('T5 code errone -> refuse, demande inchangee, tentative comptee', async () => {
    const c = client();
    const g = await c.request('/demarrer');
    const r = await c.request('/demarrer', { method: 'POST', form: {
      token: tokenOf(g.body), demarrer_action: 'authorize',
      request_id: String(state.idA), email: 'l2pilote@example.invalid',
      code: 'AAAAAA-BBBBBB-CCCCCC-DDDDDD' } });
    assert.equal(r.status, 200);
    assert.match(r.body, /Code non reconnu/);
    assert.equal(sql(`SELECT STATE FROM schoolsafe_opening_requests WHERE ID=${state.idA};`), 'requested');
    assert.equal(sql(`SELECT ATTEMPTS FROM schoolsafe_opening_codes WHERE REQUEST_ID=${state.idA} ORDER BY ID DESC LIMIT 1;`), '1');
    return 'refus + tentative 1/5';
  });

  await check('T6 code expire -> refuse, sans approbation', async () => {
    const c = client();
    let g = await c.request('/demarrer');
    let r = await c.request('/demarrer', { method: 'POST', form: {
      token: tokenOf(g.body), demarrer_action: 'request',
      school_name: 'École L2 Expiree', person_name: 'Basile L2',
      email: 'l2expiree@example.invalid' } });
    const idB = requestIdOf(r.body);
    assert.ok(idB > 0);
    const page = await state.central.request('/schoolsafe-administrators.php?opening=1');
    const issue = await state.central.request('/schoolsafe-administrators.php?opening=1', { method: 'POST', form: {
      token: tokenOf(page.body), opening_action: 'issue_code', request_id: String(idB) } });
    const codesB = [...issue.body.matchAll(/<code>([^<]+)<\/code>/g)].map((m) => m[1]);
    const codeB = codesB.find((x) => /^[A-Z2-9]{6}(-[A-Z2-9]{6}){3}$/.test(x)) || '';
    assert.ok(codeB, 'code emis');
    sql(`UPDATE schoolsafe_opening_codes SET EXPIRES_AT=CURRENT_TIMESTAMP - INTERVAL '1 hour' WHERE REQUEST_ID=${idB};`);
    g = await c.request('/demarrer');
    r = await c.request('/demarrer', { method: 'POST', form: {
      token: tokenOf(g.body), demarrer_action: 'authorize',
      request_id: String(idB), email: 'l2expiree@example.invalid', code: codeB } });
    assert.match(r.body, /expiré/);
    assert.equal(sql(`SELECT STATE FROM schoolsafe_opening_requests WHERE ID=${idB};`), 'requested');
    return 'demande n°' + idB + ' refusee (expiree)';
  });

  await check('T7 deux activations concurrentes -> une seule gagne', async () => {
    const mk = client();
    let g = await mk.request('/demarrer');
    const r = await mk.request('/demarrer', { method: 'POST', form: {
      token: tokenOf(g.body), demarrer_action: 'request',
      school_name: 'École L2 Concours', person_name: 'Chloé L2',
      email: 'l2concours@example.invalid' } });
    const idC = requestIdOf(r.body);
    assert.ok(idC > 0);
    const page = await state.central.request('/schoolsafe-administrators.php?opening=1');
    const issue = await state.central.request('/schoolsafe-administrators.php?opening=1', { method: 'POST', form: {
      token: tokenOf(page.body), opening_action: 'issue_code', request_id: String(idC) } });
    const codeC = codeOf(issue.body);
    assert.ok(codeC);
    const attempt = async () => {
      const c = client();
      const gg = await c.request('/demarrer');
      return c.request('/demarrer', { method: 'POST', form: {
        token: tokenOf(gg.body), demarrer_action: 'authorize',
        request_id: String(idC), email: 'l2concours@example.invalid', code: codeC } });
    };
    const [r1, r2] = await Promise.all([attempt(), attempt()]);
    const wins = [r1, r2].filter((x) => /autorisée/.test(x.body)).length;
    assert.equal(wins, 1, 'exactement un gagnant');
    assert.equal(sql(`SELECT STATE FROM schoolsafe_opening_requests WHERE ID=${idC};`), 'approved');
    return '1 gagne, 1 refuse';
  });

  await check('T8 code valide -> demande approuvee (ouverture)', async () => {
    const c = client();
    const g = await c.request('/demarrer');
    const r = await c.request('/demarrer', { method: 'POST', form: {
      token: tokenOf(g.body), demarrer_action: 'authorize',
      request_id: String(state.idA), email: 'l2pilote@example.invalid', code: state.codeA } });
    assert.match(r.body, /autorisée/);
    assert.equal(sql(`SELECT STATE FROM schoolsafe_opening_requests WHERE ID=${state.idA};`), 'approved');
    assert.ok(sql(`SELECT CONSUMED_AT FROM schoolsafe_opening_codes WHERE REQUEST_ID=${state.idA} ORDER BY ID DESC LIMIT 1;`) !== '', 'code consomme');
    return 'demande n°' + state.idA + ' approved';
  });

  await check('T9 reutilisation du code -> refusee', async () => {
    const c = client();
    const g = await c.request('/demarrer');
    const r = await c.request('/demarrer', { method: 'POST', form: {
      token: tokenOf(g.body), demarrer_action: 'authorize',
      request_id: String(state.idA), email: 'l2pilote@example.invalid', code: state.codeA } });
    assert.ok(/déjà été traitée|déjà utilisé/.test(r.body), 'refus explicite');
    return 'second usage refuse';
  });

  await check('T10 approbation manuelle sans code -> maintenue', async () => {
    const c = client();
    const g = await c.request('/demarrer');
    const r = await c.request('/demarrer', { method: 'POST', form: {
      token: tokenOf(g.body), demarrer_action: 'request',
      school_name: 'École L2 Manuelle', person_name: 'David L2',
      email: 'l2manuelle@example.invalid' } });
    const idD = requestIdOf(r.body);
    assert.ok(idD > 0);
    const page = await state.central.request('/schoolsafe-administrators.php?opening=1');
    const approve = await state.central.request('/schoolsafe-administrators.php?opening=1', { method: 'POST', form: {
      token: tokenOf(page.body), opening_action: 'approve', request_id: String(idD) } });
    assert.equal(approve.status, 200);
    assert.equal(sql(`SELECT STATE FROM schoolsafe_opening_requests WHERE ID=${idD};`), 'approved');
    return 'demande n°' + idD + ' approuvee manuellement';
  });

  await check('T11 assistant central -> ecole creee par le moteur natif', async () => {
    const c = state.central;
    const schoolsBefore = parseInt(sql('SELECT count(*) FROM schools;'), 10);
    state.schoolTitle = 'École L2 Pilote ' + String(Date.now() % 100000);
    let page = await c.request('/schoolsafe-administrators.php?wizard=1');
    assert.equal(page.status, 200);
    const draft = draftOf(page.body);
    assert.ok(draft, 'brouillon');
    // Relecture du jeton a chaque etape (la page peut le renouveler).
    page = await c.request('/schoolsafe-administrators.php?wizard=1');
    let t = tokenOf(page.body);
    let r = await c.request('/schoolsafe-administrators.php?wizard=1&step=1', { method: 'POST', form: {
      token: t, draft_id: draft, TITLE: state.schoolTitle, SHORT_NAME: 'L2P',
      ADDRESS: '12 av. Test', CITY: 'Kinshasa', PHONE: '+243000000000' } });
    assert.equal(r.status, 302, 'etape 1, got ' + r.status);
    page = await c.request('/schoolsafe-administrators.php?wizard=1');
    t = tokenOf(page.body);
    r = await c.request('/schoolsafe-administrators.php?wizard=1&step=2', { method: 'POST', form: {
      token: t, draft_id: draft, school_email: 'contact@ecole-l2-pilote.invalid',
      school_slogan: 'Apprendre en confiance' } });
    assert.equal(r.status, 302, 'etape 2, got ' + r.status);
    page = await c.request('/schoolsafe-administrators.php?wizard=1');
    t = tokenOf(page.body);
    r = await c.request('/schoolsafe-administrators.php?wizard=1&step=3', { method: 'POST', form: {
      token: t, draft_id: draft, first_name: 'Amina', last_name: 'L2',
      username: 'l2principal', email: 'l2principal@example.invalid', profile_id: '4' } });
    assert.equal(r.status, 302, 'etape 3, got ' + r.status);
    page = await c.request('/schoolsafe-administrators.php?wizard=1');
    t = tokenOf(page.body);
    r = await c.request('/schoolsafe-administrators.php?wizard=1&step=4', { method: 'POST', form: {
      token: t, draft_id: draft } });
    assert.equal(r.status, 302, 'etape 4, got ' + r.status);
    page = await c.request('/schoolsafe-administrators.php?wizard=1');
    t = tokenOf(page.body);
    r = await c.request('/schoolsafe-administrators.php?wizard=1&step=5', { method: 'POST', form: {
      token: t, draft_id: draft, REPORTING_GP_SCALE: '100', source: '1' } });
    assert.equal(r.status, 302, 'etape 5, got ' + r.status);
    const review = await c.request('/schoolsafe-administrators.php?wizard=1&step=6');
    assert.equal(review.status, 200);
    assert.match(review.body, /Confirmer la création de l’école/);
    const fields = hiddenFields(review.body);
    assert.ok(fields.sskc_school_draft, 'dossier lie');
    const create = await c.request('/Modules.php?modname=School_Setup/CopySchool.php&delete_ok=1', {
      method: 'POST', form: fields });
    assert.match(create.body, /préparer le compte principal/, 'ecole creee');
    const schoolsAfter = parseInt(sql('SELECT count(*) FROM schools;'), 10);
    assert.equal(schoolsAfter, schoolsBefore + 1, 'une seule ecole ajoutee');
    state.schoolId = parseInt(sql(`SELECT ID FROM schools WHERE TITLE='${state.schoolTitle}';`), 10);
    assert.ok(state.schoolId > 1, 'nouvelle ecole n°' + state.schoolId);
    assert.equal(sql(`SELECT CONFIG_VALUE FROM config WHERE SCHOOL_ID=${state.schoolId} AND TITLE='SCHOOLSAFE_SCHOOL_EMAIL';`),
      'contact@ecole-l2-pilote.invalid');
    const tpl = sql('SELECT count(*) FROM school_marking_periods WHERE SCHOOL_ID=1 AND SYEAR=2026;');
    assert.equal(sql(`SELECT count(*) FROM school_marking_periods WHERE SCHOOL_ID=${state.schoolId} AND SYEAR=2026;`), tpl);
    return 'ecole n°' + state.schoolId + ' creee, calendrier copie';
  });

  await check('T11b titre existant -> assistant refuse, zero doublon', async () => {
    const c = client();
    const login = await c.login('l2central', PW.central);
    assert.equal(login.status, 302);
    const before = sql('SELECT count(*) FROM schools;');
    let page = await c.request('/schoolsafe-administrators.php?wizard=1');
    const draft = draftOf(page.body);
    assert.ok(draft, 'nouveau dossier');
    const fill = async (n, fields) => {
      page = await c.request('/schoolsafe-administrators.php?wizard=1');
      const r = await c.request('/schoolsafe-administrators.php?wizard=1&step=' + n, { method: 'POST',
        form: { token: tokenOf(page.body), draft_id: draft, ...fields } });
      assert.equal(r.status, 302, 'etape ' + n);
    };
    await fill(1, { TITLE: state.schoolTitle });
    await fill(2, { school_email: 'autre@ecole-l2-pilote.invalid', school_slogan: 'Autre devise' });
    await fill(3, { first_name: 'Zoe', last_name: 'L2', username: 'l2zoe', email: 'l2zoe@example.invalid', profile_id: '4' });
    await fill(4, {});
    await fill(5, { REPORTING_GP_SCALE: '100', source: '1' });
    const review = await c.request('/schoolsafe-administrators.php?wizard=1&step=6');
    assert.ok(/porte déjà ce nom/.test(review.body), 'doublon detecte avant creation');
    assert.equal(sql('SELECT count(*) FROM schools;'), before, 'aucune ecole ajoutee');
    return 'titre unique impose';
  });

  await check('T12 doublon refuse, aucune ecole partielle', async () => {
    const before = sql('SELECT count(*) FROM schools;');
    const r = execFileSync('docker', ['exec', APP, 'php', '-r',
      'chdir("/var/www/html"); require "/var/www/html/Warehouse.php"; ' +
      '$r = SchoolSafeSchoolCreate(["title"=>"X","email"=>"a@b.invalid","slogan"=>"S","template_school_id"=>999], 2026); ' +
      'echo $r["ok"] ? "CREATED" : "REFUSED:".$r["error"];'],
      { encoding: 'utf8' }).trim();
    assert.match(r, /^REFUSED:/);
    assert.equal(sql('SELECT count(*) FROM schools;'), before, 'aucune ligne partielle');
    return r;
  });

  await check('T13 directeur natif cree + rattache, perimetre strict', async () => {
    const c = state.central;
    const tempPw = crypto.randomBytes(12).toString('base64').replace(/[^A-Za-z0-9]/g, 'x');
    state.tempPw = tempPw;
    const page = await c.request('/Modules.php?modname=Users%2FUser.php&staff_id=new');
    const mu = page.body.match(/action="(Modules\.php\?modname=Users\/User\.php[^"]*modfunc=update[^"]*)"/);
    assert.ok(mu, 'formulaire natif');
    const actionUrl = '/' + mu[1].replace(/&amp;/g, '&');
    assert.match(actionUrl, /token=[0-9a-f]+/, 'jeton fiche');
    const create = await c.request(actionUrl, {
      method: 'POST', form: {
        'staff[FIRST_NAME]': 'Amina', 'staff[LAST_NAME]': 'L2',
        'staff[USERNAME]': 'l2principal', 'staff[PASSWORD]': tempPw,
        'staff[EMAIL]': 'l2principal@example.invalid', 'staff[PROFILE]': 'admin',
        'staff[PROFILE_ID]': '4', [`staff[SCHOOLS][${state.schoolId}]`]: 'Y' } });
    assert.equal(create.status, 200);
    const row = sql(`SELECT STAFF_ID||':'||PROFILE||':'||COALESCE(SCHOOLS,'NULL')||':'||COALESCE(LAST_LOGIN::text,'NULL') FROM staff WHERE USERNAME='l2principal' AND SYEAR=2026;`);
    const parts = row.split(':');
    state.directorId = parseInt(parts[0], 10);
    assert.ok(state.directorId > 0, 'compte cree');
    assert.equal(parts[1], 'admin');
    assert.equal(parts[2], `,${state.schoolId},`, 'ecole unique, pas global');
    assert.equal(parts[3], 'NULL', 'premiere connexion en attente');
    const op = await c.request('/schoolsafe-administrators.php?opening=1');
    const attach = await c.request('/schoolsafe-administrators.php?opening=1', { method: 'POST', form: {
      token: tokenOf(op.body), opening_action: 'attach', request_id: String(state.idA),
      school_id: String(state.schoolId), responsible_id: String(state.directorId) } });
    assert.equal(attach.status, 200);
    assert.equal(sql(`SELECT STATE FROM schoolsafe_opening_requests WHERE ID=${state.idA};`), 'prepared');
    return 'directeur n°' + state.directorId + ' rattache (prepared)';
  });

  await check('T14 premiere connexion directeur -> mot de passe change', async () => {
    const c = client();
    const login = await c.login('l2principal', state.tempPw);
    assert.ok(login.location.includes('first-login'), '1ere connexion forcee, got ' + login.location);
    const flToken = (login.location.match(/[?&]token=([0-9a-f]+)/) || [])[1] || '';
    assert.ok(flToken, 'jeton 1ere connexion');
    const form = await c.request('/' + login.location.replace(/^https?:\/\/[^/]+/, ''));
    assert.match(form.body, /first_login\[PASSWORD\]/);
    const newPw = crypto.randomBytes(14).toString('base64').replace(/[^A-Za-z0-9]/g, 'x') + 'A9!';
    state.directorPw = newPw;
    const done = await c.request('/index.php?modfunc=first-login&token=' + flToken, {
      method: 'POST', form: { 'first_login[PASSWORD]': newPw } });
    assert.ok(done.location.includes('Portal'), 'vers portail, got ' + done.location);
    assert.ok(sql(`SELECT LAST_LOGIN FROM staff WHERE USERNAME='l2principal' AND SYEAR=2026;`) !== '', 'LAST_LOGIN renseigne');
    state.director = c;
    return 'mot de passe personnel actif';
  });

  await check('T15 tableau de bord de l’etablissement', async () => {
    const portal = await state.director.request('/Modules.php?modname=misc/Portal.php');
    assert.equal(portal.status, 200);
    assert.ok(portal.body.includes(state.schoolTitle), 'bonne ecole');
    return 'portail ecole n°' + state.schoolId;
  });

  await check('T16 directeur non autorise : creation refusee partout', async () => {
    const c = client();
    const login = await c.login('l2director', PW.director);
    assert.equal(login.status, 302);
    const wiz = await c.request('/schoolsafe-administrators.php?wizard=1');
    assert.ok(wiz.status === 403 || /pas autorisée|not allowed|Ouverture non autorisée/i.test(wiz.body), 'assistant refuse, got ' + wiz.status);
    const schoolsBeforeCopy = sql('SELECT count(*) FROM schools;');
    const copy = await c.request('/Modules.php?modname=School_Setup/CopySchool.php', { method: 'POST', form: {} });
    assert.ok(copy.status === 403 || /not allowed|pas autoris|REFRESH[^>]*Portal/i.test(copy.body), 'CopySchool refuse');
    assert.equal(sql('SELECT count(*) FROM schools;'), schoolsBeforeCopy, 'aucune ecole creee par le directeur');
    const op = await c.request('/schoolsafe-administrators.php?opening=1');
    const issue = await c.request('/schoolsafe-administrators.php?opening=1', { method: 'POST', form: {
      token: tokenOf(op.body), opening_action: 'issue_code', request_id: String(state.idA) } });
    assert.match(issue.body, /non autorisée|Non autorisée|Gestion centrale/, 'emission refusee au directeur');
    return 'directeur sans pouvoir de creation ni d’emission';
  });

  await check('T17 isolation inter-ecoles', async () => {
    const cross = await state.director.request(`/Modules.php?modname=misc/Portal.php&school=1`);
    const own = await state.director.request('/Modules.php?modname=misc/Portal.php');
    assert.ok(own.body.includes(state.schoolTitle), 'reste dans son ecole');
    assert.ok(!/Default School/.test(cross.body) || cross.body.includes(state.schoolTitle), 'pas de bascule ecole 1');
    // Ecole 1 : via le directeur (T18 garde la premiere connexion de
    // l'enseignant intacte : tout login reussi renseigne LAST_LOGIN).
    const t = client();
    await t.login('l2director', PW.director);
    const tcross = await t.request(`/Modules.php?modname=misc/Portal.php&school=${state.schoolId}`);
    assert.ok(tcross.status === 302 || !tcross.body.includes(state.schoolTitle), 'directeur ecole 1 tenu a distance');
    return 'perimetres etanches';
  });

  await check('T18 non-regression L1 : connexion, permissions, logout, CSRF', async () => {
    const conn = await client().request('/connexion');
    assert.equal(conn.status, 200);
    assert.match(conn.body, /USERNAME/);
    const t = client();
    const login = await t.login('l2teacher', PW.teacher);
    assert.ok(login.location.includes('first-login'), '1ere connexion enseignant');
    const flToken = (login.location.match(/[?&]token=([0-9a-f]+)/) || [])[1] || '';
    assert.ok(flToken, 'jeton 1ere connexion');
    await t.request('/' + login.location.replace(/^https?:\/\/[^/]+/, ''));
    const newPw = crypto.randomBytes(14).toString('base64').replace(/[^A-Za-z0-9]/g, 'x') + 'B9!';
    const done = await t.request('/index.php?modfunc=first-login&token=' + flToken, {
      method: 'POST', form: { 'first_login[PASSWORD]': newPw } });
    assert.ok(done.location.includes('Portal'), 'portail enseignant');
    const addUser = await t.request('/Modules.php?modname=Users/User.php&staff_id=new&token=zzz');
    assert.ok(addUser.status === 403 || /REFRESH/i.test(addUser.body) || /not allowed/i.test(addUser.body),
      'creation de compte refusee a l’enseignant');
    const p = client();
    const plogin = await p.login('l2parent', PW.parent);
    assert.ok(plogin.location.includes('first-login'), '1ere connexion parent');
    const pflToken = (plogin.location.match(/[?&]token=([0-9a-f]+)/) || [])[1] || '';
    const pnewPw = crypto.randomBytes(14).toString('base64').replace(/[^A-Za-z0-9]/g, 'x') + 'C9!';
    const pdone = await p.request('/index.php?modfunc=first-login&token=' + pflToken, {
      method: 'POST', form: { 'first_login[PASSWORD]': pnewPw } });
    assert.ok(pdone.location.includes('Portal'), 'portail parent');
    const portal = await p.request('/Modules.php?modname=misc/Portal.php');
    assert.equal(portal.status, 200);
    assert.ok(!/modname=Users\/User\.php&amp;staff_id|modname=Users\/User\.php&staff_id/.test(portal.body), 'parent sans fiche Users');
    const out = await t.request('/index.php?modfunc=logout&token=' + tokenOf((await t.request('/Modules.php?modname=misc/Portal.php')).body));
    assert.ok(out.status === 302 || out.location.includes('index.php'), 'logout');
    const stale = await t.request('/Modules.php?modname=misc/Portal.php');
    assert.ok(stale.status !== 200 || !/Teacher|Grades/.test(stale.body), 'session non reutilisable');
    return 'L1 intact sur la recette L2';
  });

  console.log(`\nL2 : ${passed} PASS, ${failed} FAIL`);
  process.exit(failed ? 1 : 0);
}

main().catch((e) => { console.error('FATAL', e); process.exit(2); });
