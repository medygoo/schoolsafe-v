/*
 * SchoolSafe — coque connectee : rien ne deborde, rien n'est masque.
 *
 * Trois roles, deux formes d'ecran :
 *  - parent et enseignant : le portail (Modules.php?modname=misc/Portal.php)
 *    est une page longue qui defile verticalement (2 500 a 4 000 px). Le pli
 *    exact ne s'y applique donc pas ; ce qui doit etre exact, c'est
 *    l'horizontale et la visibilite reelle du contenu.
 *  - directeur : coque fixe (.ss-fixed-school) ou la page ne defile pas ; le
 *    tableau de bord tient dans l'ecran et defile dans son propre panneau.
 *    La, l'exactitude porte aussi sur le bas : la coque, le panneau et leur
 *    zone de defilement doivent finir dans l'ecran, sinon le contenu du bas
 *    est inatteignable puisque html est en overflow hidden.
 *
 * Defaut trouve en ecrivant cette garde : #body de la coque directeur mesurait
 * 68 + 900 px (regle de base body.modules #body{min-height:100vh}), donc 68 px
 * du tableau de bord sortaient de l'ecran sans defilement possible. Corrige
 * par min-height:0 dans schoolsafe-photo-dashboard.css.
 *
 * Le parent du test est jetable (cree puis supprime) ; l'enseignant et le
 * directeur sont les comptes de recette existants et ne sont pas modifies.
 *
 * Usage : node app/qa-portal-layout.cjs
 * Variables :
 *   SCHOOLSAFE_URL           application (defaut http://127.0.0.1:8081)
 *   SCHOOLSAFE_L1_DB         conteneur PostgreSQL (defaut schoolsafe-app-db-1)
 *   SCHOOLSAFE_L1_DB_PASSWORD
 * Captures : app/qa-output/portal-<role>-<taille>.png
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

const VIEWPORTS = [
  { width: 1920, height: 1080 },
  { width: 1440, height: 900 },
  { width: 1280, height: 720 },
  { width: 1024, height: 768 },
  { width: 390, height: 844 },
];

const ROLES = [
  { key: 'parent', label: 'parent', create: true },
  { key: 'teacher', label: 'enseignant', user: 'l1.enseignant1' },
  { key: 'director', label: 'directeur', user: 'l1.directeur2', shell: true },
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
 * Mesures dans la page : cadre, menu, titre, premiere carte, panneau de la coque,
 * et tout element visible qui depasse a droite sans ancetre qui le rogne.
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

  // Rogné par un ancêtre scrollable = pas un débordement de page ; rogné par
  // #body (ou au-dessus), le contenu est invisible pour l'utilisateur.
  const clippedBy = (element) => {
    for (let ancestor = element.parentElement; ancestor && ancestor !== body; ancestor = ancestor.parentElement) {
      const style = getComputedStyle(ancestor);
      if (['auto', 'scroll', 'hidden', 'clip'].includes(style.overflowX)) return true;
    }
    return false;
  };

  const overflow = [];
  for (const element of body ? body.querySelectorAll('*') : []) {
    if (!visible(element)) continue;
    const box = element.getBoundingClientRect();
    if (box.width < 1 || box.height < 1) continue;
    if (box.right <= viewportWidth + 0.5) continue;
    if (clippedBy(element)) continue;
    overflow.push((element.id ? '#' + element.id : element.tagName + '.' +
      String(element.className).split(' ')[0]) + ' right=' + Math.round(box.right));
  }

  const hit = (target) => {
    if (!target || !visible(target)) return false;
    const box = target.getBoundingClientRect();
    if (box.width < 1 || box.height < 1) return false;
    const x = Math.round(Math.min(Math.max(box.left + box.width / 2, 1), viewportWidth - 2));
    const y = Math.round(Math.min(Math.max(box.top + Math.min(box.height / 2, 40), 1), html.clientHeight - 2));
    const painted = document.elementFromPoint(x, y);
    return !!painted && (painted === target || target.contains(painted));
  };

  const firstCard = (container) => {
    if (!container) return null;
    return Array.from(container.children)
      .map((element) => ({ element, box: element.getBoundingClientRect() }))
      .find((entry) => entry.box.width > 100 && entry.box.height > 80)?.element || null;
  };

  const shell = document.querySelector('#ss-photo-dashboard') ? {
    pane: rect(document.querySelector('.ss-fixed-view')),
    scrollable: (() => {
      const pane = document.querySelector('.ss-fixed-view');
      return pane ? pane.scrollHeight > pane.clientHeight + 1 : false;
    })(),
    title: rect(document.querySelector('#ss-photo-dashboard .ss-photo-introduction h1') ||
      document.querySelector('#ss-photo-dashboard h1')),
    titlePainted: hit(document.querySelector('#ss-photo-dashboard .ss-photo-introduction h1') ||
      document.querySelector('#ss-photo-dashboard h1')),
    card: firstCard(document.querySelector('.ss-fixed-view')),
  } : null;

  const header = document.querySelector('.ss-dashboard-context') || document.querySelector('.header1');
  const card = firstCard(body);

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
    card: rect(card),
    cardPainted: hit(card),
    shell: shell ? {
      pane: shell.pane,
      scrollable: shell.scrollable,
      title: shell.title,
      titlePainted: shell.titlePainted,
      card: rect(shell.card),
      cardPainted: hit(shell.card),
    } : null,
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

  // Connexion par les deux ecrans bleus : accueil, puis panneau de connexion.
  async function open(page, user) {
    await page.goto(baseUrl + '/index.php', { waitUntil: 'load', timeout: 30000 });
    await page.waitForSelector('#ss-blue', { timeout: 15000 });
    if (await page.locator('#splash.active').count()) {
      await page.click('#enterSplash');
    }
    await page.waitForSelector('#auth.active #loginform', { timeout: 15000 });
    await page.fill('#USERNAME', user);
    await page.fill('#PASSWORD', PASSWORD);
    await Promise.all([
      page.waitForNavigation({ waitUntil: 'load', timeout: 30000 }),
      page.click('#auth .login-button'),
    ]);
    await page.waitForSelector('#body', { timeout: 15000 });
    await page.waitForTimeout(400);
  }

  try {
    for (const role of ROLES) {
      const user = role.create ? parentUser : role.user;

      for (const viewport of VIEWPORTS) {
        const label = role.label + ' ' + viewport.width + 'x' + viewport.height;
        const context = await browser.newContext({ viewport, serviceWorkers: 'block' });
        const page = await context.newPage();

        await open(page, user);
        check(label + ' : ouvre le portail', page.url().includes('modname=misc/Portal.php'),
          page.url().replace(baseUrl, ''));

        let state = await page.evaluate(measure);

        check(label + ' : aucun pixel de page hors de l\'ecran', state.scrollWidth <= state.clientWidth,
          'document ' + state.scrollWidth + ' px pour ' + state.clientWidth + ' px de large');
        check(label + ' : aucun element visible ne depasse a droite', state.overflowCount === 0,
          state.overflowCount ? state.overflow.join(' | ') : '0 element');
        check(label + ' : la colonne de contenu reste a droite du menu',
          !state.menuVisible || (state.body.left >= state.menu.right - 1 && state.body.right <= state.clientWidth),
          'menu ' + (state.menuVisible ? state.menu.right : 'masque') + ' px, contenu ' + state.body.left + '→' + state.body.right);

        if (role.shell) {
          check(label + ' : la coque ne fait pas defiler la page',
            state.scrollHeight <= state.clientHeight,
            'document ' + state.scrollHeight + ' px pour ' + state.clientHeight + ' px de haut');
          check(label + ' : la coque finit dans l\'ecran',
            !!state.body && state.body.bottom <= state.clientHeight,
            state.body ? 'bas de #body ' + state.body.bottom + ' px' : '#body absent');
          check(label + ' : le panneau de contenu finit dans l\'ecran',
            !!state.shell?.pane && state.shell.pane.bottom <= state.clientHeight,
            state.shell?.pane ? 'bas du panneau ' + state.shell.pane.bottom + ' px' : 'panneau absent');
          check(label + ' : le titre du tableau de bord est ce que l\'oeil voit', state.shell?.titlePainted);
          check(label + ' : la premiere carte du panneau est atteignable',
            !!state.shell?.card && state.shell.cardPainted,
            state.shell?.card ? 'carte ' + state.shell.card.width + 'x' + state.shell.card.height : 'aucune carte');
        } else {
          check(label + ' : le titre du portail est dans le premier ecran',
            !!state.header && state.header.top >= 0 && state.header.bottom <= state.clientHeight,
            state.header ? 'bas du titre ' + state.header.bottom + ' px' : 'titre absent');
          check(label + ' : le titre est ce que l\'oeil voit (aucun voile)', state.headerPainted);
          check(label + ' : la premiere carte est visible et atteignable',
            !!state.card && state.card.top < state.clientHeight && state.cardPainted,
            state.card ? 'carte ' + state.card.width + 'x' + state.card.height + ' a ' + state.card.top + ' px' : 'aucune carte');
        }

        // Deux preuves par role suffisent : le bureau de reference et le telephone.
        if ((viewport.width === 1440 && viewport.height === 900) || viewport.width === 390) {
          await page.screenshot({ path: path.join(outputDir, 'portal-' + role.key + '-' + viewport.width + 'x' + viewport.height + '.png') });
        }

        if (role.shell) {
          // Le panneau defile en interne : en bas de son contenu, aucune largeur en trop.
          const scrolled = await page.evaluate(() => {
            const pane = document.querySelector('.ss-fixed-view');
            if (!pane || pane.scrollHeight <= pane.clientHeight) return false;
            pane.scrollTop = pane.scrollHeight;
            return true;
          });
          if (scrolled) await page.waitForTimeout(150);
          state = await page.evaluate(measure);
          check(label + ' : en bas du panneau, toujours aucun debordement horizontal',
            state.scrollWidth <= state.clientWidth,
            'document ' + state.scrollWidth + ' px, panneau ' + (scrolled ? 'defile' : 'sans defilement interne'));
        } else {
          await page.evaluate(() => window.scrollTo(0, document.documentElement.scrollHeight));
          await page.waitForTimeout(150);
          state = await page.evaluate(measure);
          check(label + ' : en bas de page, toujours aucun debordement horizontal',
            state.scrollWidth <= state.clientWidth,
            'document ' + state.scrollWidth + ' px, position ' + (await page.evaluate(() => Math.round(window.scrollY))) + ' px');
        }

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
            withOverflow.overflow.join(' | '));

          await page.evaluate(() => {
            document.getElementById('qa-overflow-probe').remove();
            const veil = document.createElement('div');
            veil.id = 'qa-veil-probe';
            veil.style.cssText = 'position:fixed;inset:0;z-index:99999;background:#000';
            document.body.append(veil);
          });
          const withVeil = await page.evaluate(measure);
          check(label + ' : l\'audit detecte un voile injecte',
            role.shell ? !withVeil.shell.titlePainted : !withVeil.headerPainted);
        }

        await context.close();
      }
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
