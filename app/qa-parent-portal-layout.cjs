/*
 * SchoolSafe — portail parent : rien ne déborde, rien n'est masqué.
 *
 * Le portail parent (Modules.php?modname=misc/Portal.php) est une page longue
 * par nature : elle défile verticalement (2 675 px de contenu en 1440x900).
 * Le pli exact ne s'y applique donc pas ; ce qui doit être exact, c'est
 * l'horizontale (aucun pixel hors de l'écran) et la visibilité réelle du
 * contenu (titre et première carte atteignables par le pointeur, colonne de
 * contenu à droite du menu, pas de panneau caché derrière un voile).
 *
 * Le test crée un parent jetable (mot de passe recopié d'un compte connu),
 * le rattache à l'élève 1, puis supprime tout. Aucune donnée de recette
 * n'est modifiée.
 *
 * Usage : node app/qa-parent-portal-layout.cjs
 * Variables :
 *   SCHOOLSAFE_URL           application (défaut http://127.0.0.1:8081)
 *   SCHOOLSAFE_L1_DB         conteneur PostgreSQL (défaut schoolsafe-app-db-1)
 *   SCHOOLSAFE_L1_DB_PASSWORD
 * Captures : app/qa-output/parent-portal-<taille>.png
 */
const fs = require('node:fs');
const path = require('node:path');
const { execFileSync } = require('node:child_process');
const { chromium } = require('playwright');

const baseUrl = (process.env.SCHOOLSAFE_URL || 'http://127.0.0.1:8081').replace(/\/$/, '');
const database = process.env.SCHOOLSAFE_L1_DB || 'schoolsafe-app-db-1';
const dbPassword = process.env.SCHOOLSAFE_L1_DB_PASSWORD || 'preview-local-jetable-32caracteres-xx';
const PASSWORD = 'L1-Test-2026!';
const outputDir = path.join(__dirname, 'qa-output');
const PORTAL = '/Modules.php?modname=misc/Portal.php';

// Desktop, portable et téléphone. Le portail défile : seule l'horizontale est exacte.
const VIEWPORTS = [
  { width: 1920, height: 1080 },
  { width: 1440, height: 900 },
  { width: 1280, height: 800 },
  { width: 1024, height: 768 },
  { width: 390, height: 844 },
];

let passed = 0;
let failed = 0;

function check(name, ok, detail) {
  if (ok) {
    ++passed;
    console.log('PASS ' + name + (detail ? ' — ' + detail : ''));
  } else {
    ++failed;
    console.log('FAIL ' + name + (detail ? ' — ' + detail : ''));
  }
}

function sql(statement) {
  return execFileSync('docker', ['exec', '-e', 'PGPASSWORD=' + dbPassword, database,
    'psql', '-U', 'schoolsafe', '-d', 'schoolsafe', '-At', '-q', '-c', statement], { encoding: 'utf8' }).trim();
}

/**
 * Mesures dans la page : colonne de contenu, menu, titre, première carte,
 * et tout élément visible qui depasse a droite sans ancetre qui le rogne.
 */
function measure() {
  const html = document.documentElement;
  const viewportWidth = html.clientWidth;
  const body = document.getElementById('body');
  const menu = document.getElementById('menu');
  const rect = (element) => {
    if (!element) return null;
    const box = element.getBoundingClientRect();
    return {
      top: Math.round(box.top),
      bottom: Math.round(box.bottom),
      left: Math.round(box.left),
      right: Math.round(box.right),
      width: Math.round(box.width),
      height: Math.round(box.height),
    };
  };

  const visible = (element) => {
    const style = getComputedStyle(element);
    return style.display !== 'none' && style.visibility !== 'hidden' && Number(style.opacity) > 0;
  };

  // Un element rogne par un ancetre scrollable n'est pas un debordement de page ;
  // rogne par #body (ou au-dessus), il est invisible pour l'utilisateur.
  const clippedBy = (element) => {
    for (let ancestor = element.parentElement; ancestor && ancestor !== body; ancestor = ancestor.parentElement) {
      const style = getComputedStyle(ancestor);
      if (['auto', 'scroll', 'hidden', 'clip'].includes(style.overflowX)) return ancestor.id || ancestor.className || ancestor.tagName;
    }
    return null;
  };

  const overflow = [];
  for (const element of body ? body.querySelectorAll('*') : []) {
    if (!visible(element)) continue;
    const box = element.getBoundingClientRect();
    if (box.width < 1 || box.height < 1) continue;
    if (box.right <= viewportWidth + 0.5) continue;
    const clipper = clippedBy(element);
    if (clipper) continue;
    overflow.push((element.id ? '#' + element.id : element.tagName + '.' +
      String(element.className).split(' ')[0]) + ' right=' + Math.round(box.right));
  }

  // Le premier bloc de contenu qui a une vraie hauteur : la premiere carte du portail.
  const firstCard = Array.from(body ? body.children : [])
    .map((element) => ({ element, box: element.getBoundingClientRect() }))
    .find((entry) => entry.box.width > 100 && entry.box.height > 80) || null;

  const hit = (target) => {
    if (!target) return false;
    const box = target.getBoundingClientRect();
    const x = Math.round(Math.min(Math.max(box.left + box.width / 2, 1), viewportWidth - 2));
    const y = Math.round(Math.min(Math.max(box.top + Math.min(box.height / 2, 40), 1), html.clientHeight - 2));
    const painted = document.elementFromPoint(x, y);
    return !!painted && (painted === target || target.contains(painted));
  };

  const header = document.querySelector('.ss-dashboard-context') || document.querySelector('.header1');

  return {
    scrollWidth: html.scrollWidth,
    clientWidth: viewportWidth,
    scrollHeight: html.scrollHeight,
    clientHeight: html.clientHeight,
    body: rect(body),
    menu: rect(menu),
    menuVisible: !!menu && visible(menu) && menu.getBoundingClientRect().width > 0,
    header: rect(header),
    headerPainted: hit(header),
    firstCard: firstCard ? rect(firstCard.element) : null,
    firstCardPainted: firstCard ? hit(firstCard.element) : false,
    overflow: overflow.slice(0, 5),
    overflowCount: overflow.length,
  };
}

(async () => {
  fs.mkdirSync(outputDir, { recursive: true });

  const parentUser = 'layout.parent.' + Date.now();
  const parentId = sql(`INSERT INTO staff (syear,first_name,last_name,username,password,profile,profile_id,schools,last_login)
    VALUES (2026,'Layout','Parent','${parentUser}',
    (SELECT PASSWORD FROM staff WHERE USERNAME='l1.proprietaire' LIMIT 1),'parent',3,NULL,CURRENT_TIMESTAMP)
    RETURNING STAFF_ID`);

  if (!/^[0-9]+$/.test(parentId)) throw new Error('parent jetable non cree');
  sql(`INSERT INTO students_join_users (student_id,staff_id) VALUES (1,${parentId})`);

  const browser = await chromium.launch({
    args: [
      '--disable-background-timer-throttling',
      '--disable-renderer-backgrounding',
      '--disable-backgrounding-occluded-windows',
    ],
  });

  try {
    for (const viewport of VIEWPORTS) {
      const label = viewport.width + 'x' + viewport.height;
      const context = await browser.newContext({ viewport, serviceWorkers: 'block' });
      const page = await context.newPage();

      await page.goto(baseUrl + '/index.php', { waitUntil: 'load', timeout: 30000 });
      await page.waitForSelector('#ss-login-form-slot #loginform', { timeout: 15000 });
      await page.fill('#USERNAME', parentUser);
      await page.fill('#PASSWORD', PASSWORD);
      await Promise.all([
        page.waitForNavigation({ waitUntil: 'load', timeout: 30000 }),
        page.click('.ss-login-submit'),
      ]);
      await page.waitForSelector('#body', { timeout: 15000 });
      await page.waitForTimeout(300);

      check(label + ' : le parent ouvre son portail', page.url().includes('modname=misc/Portal.php'),
        page.url().replace(baseUrl, ''));

      let state = await page.evaluate(measure);

      check(label + ' : aucun pixel de page hors de l\'ecran', state.scrollWidth <= state.clientWidth,
        'document ' + state.scrollWidth + ' px pour ' + state.clientWidth + ' px de large');
      check(label + ' : aucun element visible ne depasse a droite', state.overflowCount === 0,
        state.overflowCount ? state.overflow.join(' | ') : '0 element');
      check(label + ' : la colonne de contenu reste a droite du menu',
        !state.menuVisible || (state.body.left >= state.menu.right - 1 && state.body.right <= state.clientWidth),
        'menu ' + (state.menuVisible ? state.menu.right : 'masque') + ' px, contenu ' + state.body.left + '→' + state.body.right);
      check(label + ' : le titre du portail est dans le premier ecran',
        !!state.header && state.header.top >= 0 && state.header.bottom <= state.clientHeight,
        state.header ? 'bas du titre ' + state.header.bottom + ' px' : 'titre absent');
      check(label + ' : le titre est ce que l\'oeil voit (aucun voile)', state.headerPainted);
      check(label + ' : la premiere carte est visible et atteignable',
        !!state.firstCard && state.firstCard.top < state.clientHeight && state.firstCardPainted,
        state.firstCard ? 'carte ' + state.firstCard.width + 'x' + state.firstCard.height + ' a ' + state.firstCard.top + ' px' : 'aucune carte');

      await page.screenshot({ path: path.join(outputDir, 'parent-portal-' + label + '.png') });

      // Le portail defile : apres defilement, toujours aucune largeur en trop.
      await page.evaluate(() => window.scrollTo(0, document.documentElement.scrollHeight));
      await page.waitForTimeout(150);
      state = await page.evaluate(measure);
      check(label + ' : en bas de page, toujours aucun debordement horizontal',
        state.scrollWidth <= state.clientWidth,
        'document ' + state.scrollWidth + ' px, position ' + (await page.evaluate(() => Math.round(window.scrollY))) + ' px');

      // Preuve que l'audit n'est pas complaisant : un debordement et un voile
      // injectes doivent etre vus. Sinon les PASS ci-dessus ne valent rien.
      if (viewport.width === 1440 && viewport.height === 900) {
        await page.evaluate(() => window.scrollTo(0, 0));
        await page.evaluate(() => {
          const probe = document.createElement('div');
          probe.id = 'qa-overflow-probe';
          probe.style.cssText = 'position:absolute;top:0;left:calc(100% - 10px);width:60px;height:10px';
          document.getElementById('body').append(probe);
        });
        const withOverflow = await page.evaluate(measure);
        check(label + ' : l\'audit detecte un debordement injecte de 50 px',
          withOverflow.overflowCount > 0,
          withOverflow.overflow.length + ' element(s) : ' + withOverflow.overflow.join(' | '));

        await page.evaluate(() => {
          document.getElementById('qa-overflow-probe').remove();
          const veil = document.createElement('div');
          veil.id = 'qa-veil-probe';
          veil.style.cssText = 'position:fixed;inset:0;z-index:99999;background:#000';
          document.body.append(veil);
        });
        const withVeil = await page.evaluate(measure);
        check(label + ' : l\'audit detecte un voile injecte par-dessus le titre',
          !withVeil.headerPainted);
      }

      await context.close();
    }
  } finally {
    await browser.close();
    sql(`DELETE FROM students_join_users WHERE staff_id=${parentId}`);
    sql(`DELETE FROM staff WHERE staff_id=${parentId}`);
  }

  console.log(JSON.stringify({
    passed,
    failed,
    base: baseUrl,
    proof: 'navigateur Chromium reel (Playwright), mesures de boites et elementFromPoint',
  }));

  process.exitCode = failed ? 1 : 0;
})().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
