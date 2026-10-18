/*
 * SchoolSafe — calendrier d'une école : correct à la création, visible par PRODELI.
 *
 * Défaut reproduit : la création d'école de la console (admin-console-bridge.php,
 * action create-school) recopiait les périodes du gabarit SANS PARENT_ID. Le
 * remappage des parents ne trouvait donc jamais rien et chaque école créée
 * naissait « à plat » (année, semestres et trimestres tous sans parent). Le
 * directeur de cette école voyait ensuite une erreur SQL sur son tableau de bord.
 *
 * Ce test vérifie les deux moitiés de la correction :
 *   1. une école créée depuis la console a une hiérarchie complète et fidèle au
 *      gabarit (année -> semestres -> trimestres) ;
 *   2. un gabarit incohérent est refusé AVANT toute écriture (aucune école à
 *      moitié créée), avec le motif ;
 *   3. le propriétaire de la plateforme (PRODELI, aucune école assignée) voit
 *      l'état du calendrier de chaque école dans sa console.
 *
 * Usage : node app/qa-school-calendar.cjs
 * Variables :
 *   SCHOOLSAFE_L1_URL        application (défaut http://127.0.0.1:8081)
 *   SCHOOLSAFE_L1_DB         conteneur PostgreSQL (défaut schoolsafe-app-db-1)
 *   SCHOOLSAFE_CONSOLE_TOKEN jeton de service de la console (obligatoire pour 1 et 2)
 *
 * Aucune migration : le test crée ses propres écoles jetables et les supprime.
 */
const assert = require('node:assert/strict');
const { execFileSync } = require('node:child_process');

const BASE = process.env.SCHOOLSAFE_L1_URL || 'http://127.0.0.1:8081';
const DB = process.env.SCHOOLSAFE_L1_DB || 'schoolsafe-app-db-1';
const PGUSER = 'schoolsafe';
const PGDATABASE = 'schoolsafe';
const PGPASSWORD = process.env.SCHOOLSAFE_L1_DB_PASSWORD || 'preview-local-jetable-32caracteres-xx';
const CONSOLE_TOKEN = process.env.SCHOOLSAFE_CONSOLE_TOKEN || '';
const OWNER = { username: 'l1.proprietaire', password: 'L1-Test-2026!' };
const SYEAR = 2026;

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

function esc(value) {
  return String(value).replace(/'/g, "''");
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
    async login(username, password) {
      await request('/index.php');
      return request('/index.php', { method: 'POST', form: { USERNAME: username, PASSWORD: password } });
    },
  };
}

async function bridge(action, payload = {}) {
  const response = await fetch(BASE + '/admin-console-bridge.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-SchoolSafe-Console-Token': CONSOLE_TOKEN,
    },
    body: JSON.stringify({ action, ...payload }),
  });
  let body = {};
  try { body = await response.json(); } catch (error) { body = { raw: String(error.message) }; }
  return { status: response.status, body };
}

/**
 * Périodes d'une école dont le parent est absent, étranger, ou du mauvais type.
 * 0 = calendrier cohérent.
 */
function hierarchyBreaks(schoolId) {
  return sql(`SELECT COUNT(1) FROM school_marking_periods c
    WHERE c.school_id=${schoolId} AND c.syear=${SYEAR} AND (
      (c.mp='SEM' AND NOT EXISTS (SELECT 1 FROM school_marking_periods p
        WHERE p.marking_period_id=c.parent_id AND p.school_id=c.school_id AND p.syear=c.syear AND p.mp='FY'))
      OR (c.mp='QTR' AND NOT EXISTS (SELECT 1 FROM school_marking_periods p
        WHERE p.marking_period_id=c.parent_id AND p.school_id=c.school_id AND p.syear=c.syear AND p.mp='SEM'))
      OR (c.mp='PRO' AND NOT EXISTS (SELECT 1 FROM school_marking_periods p
        WHERE p.marking_period_id=c.parent_id AND p.school_id=c.school_id AND p.syear=c.syear AND p.mp='QTR'))
      OR (c.mp='FY' AND c.parent_id IS NOT NULL)
    )`);
}

function schoolIdByTitle(title) {
  const value = sql(`SELECT COALESCE(MAX(id),0) FROM schools WHERE title='${esc(title)}' AND syear=${SYEAR}`);
  return Number(value);
}

function dropSchool(schoolId) {
  if (!schoolId) return;
  sql(`DELETE FROM config WHERE school_id=${schoolId}`);
  sql(`DELETE FROM school_gradelevels WHERE school_id=${schoolId}`);
  sql(`DELETE FROM school_periods WHERE school_id=${schoolId}`);
  sql(`DELETE FROM school_marking_periods WHERE school_id=${schoolId}`);
  sql(`DELETE FROM schools WHERE id=${schoolId}`);
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

(async () => {
  const stamp = Date.now();
  const createdTitle = 'QA Calendrier Cree ' + stamp;
  const refusedTitle = 'QA Calendrier Refuse ' + stamp;
  const flatTitle = 'QA Calendrier Plat ' + stamp;
  let createdId = 0;
  let flatId = 0;

  try {
    assert.ok(CONSOLE_TOKEN, 'SCHOOLSAFE_CONSOLE_TOKEN requis pour prouver la création d\'école');

    await check('creation d\'une ecole : calendrier complet, comme le gabarit', async () => {
      const result = await bridge('create-school', {
        title: createdTitle,
        email: 'qa.calendrier.' + stamp + '@example.test',
        slogan: 'Un enfant protege, un parent informe',
        template_school_id: 1,
      });

      assert.equal(result.status, 201, JSON.stringify(result.body).slice(0, 200));
      createdId = Number(result.body.data.school_id);
      assert.ok(createdId > 0, 'identifiant de la nouvelle école');

      const periods = sql(`SELECT COUNT(1) FROM school_marking_periods WHERE school_id=${createdId} AND syear=${SYEAR}`);
      assert.equal(periods, '7', 'les sept périodes du gabarit sont recopiées');
      assert.equal(hierarchyBreaks(createdId), '0', 'aucune période sans parent (ni mauvais parent)');

      // Fidélité : mêmes périodes, mêmes titres, mêmes dates que le gabarit.
      const differences = sql(`SELECT COUNT(1) FROM (
        (SELECT mp,title,start_date::text,end_date::text FROM school_marking_periods WHERE school_id=1 AND syear=${SYEAR}
         EXCEPT
         SELECT mp,title,start_date::text,end_date::text FROM school_marking_periods WHERE school_id=${createdId} AND syear=${SYEAR})
        UNION ALL
        (SELECT mp,title,start_date::text,end_date::text FROM school_marking_periods WHERE school_id=${createdId} AND syear=${SYEAR}
         EXCEPT
         SELECT mp,title,start_date::text,end_date::text FROM school_marking_periods WHERE school_id=1 AND syear=${SYEAR})
      ) diff`);
      assert.equal(differences, '0', 'le calendrier recopié est identique au gabarit');

      return 'école ' + createdId + ', 7 périodes, 0 rupture de hiérarchie';
    });

    await check('gabarit incoherent : refus avant ecriture, aucune ecole a moitie creee', async () => {
      flatId = firstId(sql(`INSERT INTO schools (syear,title) VALUES (${SYEAR},'${esc(flatTitle)}') RETURNING id`));
      sql(`INSERT INTO school_marking_periods
        (syear,mp,school_id,title,short_name,start_date,end_date,sort_order,parent_id)
        VALUES
        (${SYEAR},'FY',${flatId},'Full Year','FY','2026-08-01','2027-06-30',NULL,NULL),
        (${SYEAR},'SEM',${flatId},'Semester 1','S1','2026-08-01','2026-12-31',1,NULL),
        (${SYEAR},'SEM',${flatId},'Semester 2','S2','2027-01-01','2027-06-30',2,NULL),
        (${SYEAR},'QTR',${flatId},'Quarter 1','Q1','2026-08-01','2026-10-31',1,NULL),
        (${SYEAR},'QTR',${flatId},'Quarter 2','Q2','2026-11-01','2026-12-31',2,NULL)`);

      const result = await bridge('create-school', {
        title: refusedTitle,
        email: 'qa.refus.' + stamp + '@example.test',
        slogan: 'Un enfant protege, un parent informe',
        template_school_id: flatId,
      });

      assert.equal(result.status, 409, JSON.stringify(result.body).slice(0, 200));
      assert.equal(result.body.error, 'TEMPLATE_CALENDAR_INVALID');
      assert.ok(Array.isArray(result.body.data.issues) && result.body.data.issues.length >= 2, 'les anomalies sont expliquées');	      assert.match(result.body.data.issues.join(' '), /période parente de type Année/, 'le motif nomme le parent manquant');

      assert.equal(schoolIdByTitle(refusedTitle), 0, 'aucune école refusée ne reste en base');
      assert.equal(sql(`SELECT COUNT(1) FROM schools WHERE id=${flatId}`), '1', 'le gabarit incohérent est intact (rien n\'est détruit)');

      return 'HTTP 409 TEMPLATE_CALENDAR_INVALID, 0 école créée';
    });

    await check('console PRODELI : le calendrier de chaque ecole est visible', async () => {
      const c = client();
      const login = await c.login(OWNER.username, OWNER.password);
      assert.ok(login.location.includes('modname=') || login.status === 302, 'connexion propriétaire acceptée');

      const page = await c.request('/admin-console.php');
      assert.equal(page.status, 200, 'console accessible');
      assert.match(page.body, /data-calendar="ok"/, 'une école saine est signalée cohérente');
      assert.match(page.body, /<strong>Calendrier<\/strong>/, 'la rubrique Calendrier est expliquée');

      // École à plat : signalée avec le motif, dans la même page.
      assert.ok(page.body.includes(flatTitle), 'le gabarit incohérent apparaît dans la liste');
      const rowIndex = page.body.indexOf(flatTitle);
      const row = page.body.slice(rowIndex, rowIndex + 1200);
      assert.match(row, /data-calendar="issues"/, 'l\'école en anomalie est marquée');
      assert.match(row, /anomalie\(s\)/, 'le nombre d\'anomalies est affiché');	      assert.match(row, /période parente de type Année/, 'le parent manquant est nommé');

      return 'badge + motif visibles pour PRODELI, sans élargir le tableau';
    });

    await check('deux creations consecutives : meme resultat, aucune derive', async () => {
      const againTitle = 'QA Calendrier Bis ' + stamp;
      const result = await bridge('create-school', {
        title: againTitle,
        email: 'qa.calendrier.bis.' + stamp + '@example.test',
        slogan: 'Un enfant protege, un parent informe',
        template_school_id: 1,
      });

      assert.equal(result.status, 201, JSON.stringify(result.body).slice(0, 200));
      const againId = Number(result.body.data.school_id);

      try {
        assert.equal(sql(`SELECT COUNT(1) FROM school_marking_periods WHERE school_id=${againId} AND syear=${SYEAR}`), '7');
        assert.equal(hierarchyBreaks(againId), '0', 'la deuxième création est saine elle aussi');
      } finally {
        dropSchool(againId);
      }

      assert.equal(schoolIdByTitle(againTitle), 0, 'aucun résidu après suppression');
      return 'école ' + againId + ' créée puis retirée, 0 rupture';
    });
  } finally {
    dropSchool(createdId);
    if (!createdId) dropSchool(schoolIdByTitle(createdTitle));
    dropSchool(schoolIdByTitle(refusedTitle));
    dropSchool(flatId || schoolIdByTitle(flatTitle));
  }

  console.log(JSON.stringify({ passed, failed, base: BASE }));
  process.exitCode = failed ? 1 : 0;
})().catch(error => {
  console.error(error);
  process.exitCode = 1;
});
