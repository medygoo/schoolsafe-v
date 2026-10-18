/*
 * SchoolSafe — ecran de connexion : le panneau ne recouvre plus rien.
 *
 * Defaut corrige (P4 de docs/audit/ROLES_ACCES_ETAT_DES_LIEUX.md) : en 1440x900
 * le panneau etait positionne en absolu et sa hauteur ne comptait pas dans le
 * flux, si bien qu'il recouvrait la rangee d'icones (86 px), le slogan (55 px)
 * et le debut de la zone legale (33 px).
 *
 * Le test mesure de vraies boites dans un navigateur, a plusieurs tailles, et
 * verifie l'invariant : aucune intersection entre le panneau et ce qui le suit.
 * Il injecte aussi un faux message d'erreur de 200 px pour prouver que la
 * reserve d'espace suit la hauteur REELLE du panneau, pas une valeur fige.
 *
 * Les 7 fonds sont mesures a 1440x900, et le test attend la fin du voile
 * d'accueil (#ss-splash, 0,9 s + 0,4 s) avant de capturer : sans cela la
 * capture montre le logo sur fond bleu, pas l'ecran de connexion mesure.
 *
 * Le pli est verifie au pixel pres (aucune tolerance) : le cadre historique
 * table.postbox ajoutait 1 px de bordure en haut et en bas, d'ou 2 px de
 * document au-dessus de l'ecran ; desktop.css supprime cette bordure.
 *
 * Usage : node app/qa-login-desktop-layout.cjs
 * Variables : SCHOOLSAFE_URL (defaut http://127.0.0.1:8081).
 * Captures : app/qa-output/.
 */
const crypto = require('node:crypto');
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');

const baseUrl = (process.env.SCHOOLSAFE_URL || 'http://127.0.0.1:8081').replace(/\/$/, '');
const outputDir = path.join(__dirname, 'qa-output');

// fold: true => l'ecran doit tenir entierement sous le pli (cible 1440-1920).
// panel: true => le panneau entier (bouton compris) doit etre au-dessus du pli.
const VIEWPORTS = [
  { width: 1920, height: 1080, fold: true, panel: true },
  { width: 1600, height: 900, fold: true, panel: true },
  { width: 1440, height: 900, fold: true, panel: true },
  { width: 1440, height: 1200, fold: true, panel: true },
  { width: 1366, height: 768, fold: false, panel: true },
  { width: 1280, height: 720, fold: false, panel: true },
  { width: 1280, height: 800, fold: false, panel: true },
  { width: 1024, height: 768, fold: false, panel: true },
  { width: 1024, height: 600, fold: false, panel: false },
  { width: 390, height: 844, fold: false, panel: false },
];

// Les fonds changent les couleurs et ajoutent un calque au heros : la mise en page
// ne doit pas bouger pour autant (verifie sur le mode par defaut, 1440x900).
const THEMES = ['night', 'white', 'blue', 'gray', 'mint', 'rose', 'yellow'];
const THEME_VIEWPORT = { width: 1440, height: 900, fold: true, panel: true };

const FOLLOWING = ['.ss-login-features', '.ss-login-slogan', '#ss-login-legal-slot'];

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

/** Boites mesurees dans la page : panneau, ce qui le suit, l'accueil, le bas du document. */
function measure() {
  const box = (selector) => {
    const element = document.querySelector(selector);
    if (!element) return null;
    const rect = element.getBoundingClientRect();
    return {
      top: Math.round(rect.top),
      bottom: Math.round(rect.bottom),
      left: Math.round(rect.left),
      right: Math.round(rect.right),
      width: Math.round(rect.width),
      height: Math.round(rect.height),
    };
  };

  const panel = box('#ss-login-form-slot');
  const following = {};

  // Le panneau doit etre ce que l'oeil voit : ni voile d'accueil, ni superposition.
  const slot = document.getElementById('ss-login-form-slot');
  const x = Math.round(Math.min(Math.max(panel.left + panel.width / 2, 1), window.innerWidth - 2));
  const y = Math.round(Math.min(Math.max(panel.top + 24, 1), window.innerHeight - 2));
  const painted = document.elementFromPoint(x, y);

  for (const selector of ['.ss-login-features', '.ss-login-slogan', '#ss-login-legal-slot']) {
    following[selector] = box(selector);
  }

  return {
    panel,
    following,
    welcome: box('.ss-login-welcome'),
    position: getComputedStyle(slot).position,
    scrollHeight: document.documentElement.scrollHeight,
    viewportHeight: window.innerHeight,
    hasForm: !!document.querySelector('#ss-login-form-slot #loginform'),
    panelPainted: !!painted && (painted === slot || slot.contains(painted)),
  };
}

/** Le voile d'accueil (z-index 100000) doit avoir fini son fondu avant toute capture. */
async function waitForSplash(page) {
  await page.waitForFunction(() => {
    const splash = document.querySelector('.ss-splash');
    if (!splash) return true;
    const style = getComputedStyle(splash);
    return style.display === 'none' || style.visibility === 'hidden' || Number(style.opacity) === 0;
  }, null, { timeout: 15000 });
}

/** Somme des canaux : distingue un fond clair d'un fond bleu nuit. */
function brightness(rgb) {
  return rgb[0] + rgb[1] + rgb[2];
}

/** Aire de recouvrement de deux boites, en pixels carres. */
function intersectionArea(a, b) {
  if (!a || !b) return 0;
  const width = Math.min(a.right, b.right) - Math.max(a.left, b.left);
  const height = Math.min(a.bottom, b.bottom) - Math.max(a.top, b.top);
  return width > 0 && height > 0 ? width * height : 0;
}

(async () => {
  fs.mkdirSync(outputDir, { recursive: true });

  const browser = await chromium.launch({
    args: [
      '--disable-background-timer-throttling',
      '--disable-renderer-backgrounding',
      '--disable-backgrounding-occluded-windows',
    ],
  });

  /** Charge l'ecran de connexion et verifie la mise en page a une taille donnee. */
  async function checkViewport(page, viewport, label, captureName) {
    await page.goto(baseUrl + '/index.php', { waitUntil: 'load', timeout: 30000 });
    await page.waitForSelector('#ss-login-form-slot #loginform', { timeout: 15000 });
    await waitForSplash(page);
    await page.waitForTimeout(250); // laisser la mise en page se stabiliser

    let state = await page.evaluate(measure);

    check(label + ' : le formulaire de connexion est rendu dans le panneau', state.hasForm);
    check(label + ' : le panneau est ce que l\'oeil voit (aucun voile par-dessus)', state.panelPainted);
    check(label + ' : le panneau ne recouvre ni icones, ni slogan, ni zone legale',
      FOLLOWING.every((selector) => intersectionArea(state.panel, state.following[selector]) === 0),
      JSON.stringify(Object.fromEntries(FOLLOWING.map((selector) => [selector,
        intersectionArea(state.panel, state.following[selector])]))));
    check(label + ' : le panneau ne recouvre pas le titre d\'accueil',
      intersectionArea(state.panel, state.welcome) === 0);

    if (viewport.width >= 900) {
      check(label + ' : le panneau est dans le flux (position ' + state.position + ')',
        state.position !== 'absolute',
        'une hauteur reelle reserve la place des blocs suivants');
    }

    if (viewport.panel) {
      check(label + ' : le panneau entier est au-dessus du pli',
        state.panel.bottom <= viewport.height,
        'bas du panneau ' + state.panel.bottom + ' px pour ' + viewport.height + ' px de haut');
    }

    if (viewport.fold) {
      // Exact, sans tolerance : le moindre pixel sous le pli doit faire echouer.
      const legalBottom = state.following['#ss-login-legal-slot'].bottom;
      check(label + ' : toute la page tient sous le pli (au pixel pres)',
        legalBottom <= viewport.height && state.scrollHeight <= viewport.height,
        'bas de la zone legale ' + legalBottom + ' px, document ' + state.scrollHeight + ' px');
    }

    // Capture de l'ecran tel quel, AVANT l'injection du faux message d'erreur.
    if (captureName) {
      await page.screenshot({ path: path.join(outputDir, captureName + '.png') }).catch(() => {});
    }

    // Le message d'erreur allonge le panneau : l'espace reserve doit suivre.
    if (viewport.width >= 900) {
      await page.evaluate(() => {
        const filler = document.createElement('div');
        filler.style.height = '200px';
        filler.id = 'qa-tall-error';
        document.getElementById('ss-login-form-slot').append(filler);
      });
      await page.waitForTimeout(150);
      state = await page.evaluate(measure);

      check(label + ' : un panneau allonge de 200 px ne recouvre toujours rien',
        FOLLOWING.every((selector) => intersectionArea(state.panel, state.following[selector]) === 0),
        'hauteur du panneau ' + state.panel.height + ' px, icones a ' + state.following['.ss-login-features'].top + ' px');
    }
  }

  try {
    for (const viewport of VIEWPORTS) {
      const context = await browser.newContext({
        viewport: { width: viewport.width, height: viewport.height },
        serviceWorkers: 'block',
      });
      const page = await context.newPage();

      await checkViewport(page, viewport, viewport.width + 'x' + viewport.height,
        viewport.width === 1440 && viewport.height === 900 ? 'login-desktop-1440x900' : null);

      await context.close();
    }

    // Chaque fond doit conserver la meme mise en page que le fond par defaut.
    for (const theme of THEMES) {
      const label = 'fond ' + theme + ' (1440x900)';
      const context = await browser.newContext({
        viewport: { width: THEME_VIEWPORT.width, height: THEME_VIEWPORT.height },
        serviceWorkers: 'block',
      });
      const page = await context.newPage();

      // Le fond est lu au chargement depuis le stockage local : on le pose puis on recharge.
      await page.goto(baseUrl + '/index.php', { waitUntil: 'load', timeout: 30000 });
      await page.evaluate((value) => {
        try { localStorage.setItem('schoolsafe-background', value); } catch {};
      }, theme);
      await page.reload({ waitUntil: 'load', timeout: 30000 });
      await page.waitForSelector('#ss-login-form-slot #loginform', { timeout: 15000 });
      await waitForSplash(page);

      const applied = await page.evaluate(() => {
        const host = document.getElementById('ss-login-reference');
        const panel = document.querySelector('.ss-login-panel');
        const rgb = (value) => (value.match(/\d+/g) || []).slice(0, 3).map(Number);

        return {
          theme: host.dataset.theme,
          host: rgb(getComputedStyle(host).backgroundColor),
          panel: rgb(getComputedStyle(panel).backgroundColor),
        };
      });
      check(label + ' : le fond demande est bien applique', applied.theme === theme,
        'theme actif : ' + applied.theme);
      // Le bleu nuit est le seul fond sombre : les six autres doivent etre peints en clair.
      check(label + ' : le fond est reellement peint (' + (theme === 'night' ? 'sombre' : 'clair') + ')',
        brightness(applied.host) > 600 ? theme !== 'night' : theme === 'night',
        'ecran ' + applied.host.join(',') + ', panneau ' + applied.panel.join(','));

      // Deux captures (nuit et blanc) : leur difference prouve que le fond est rendu.
      await checkViewport(page, { ...THEME_VIEWPORT, fold: false }, label,
        theme === 'night' || theme === 'white' ? 'login-desktop-' + theme + '-1440x900' : null);

      await context.close();
    }

    const digest = (name) => crypto.createHash('md5')
      .update(fs.readFileSync(path.join(outputDir, name))).digest('hex');

    check('les captures nuit et blanc different (le fond est bien rendu)',
      digest('login-desktop-night-1440x900.png') !== digest('login-desktop-white-1440x900.png'),
      'md5 ' + digest('login-desktop-night-1440x900.png').slice(0, 8) + ' / ' +
        digest('login-desktop-white-1440x900.png').slice(0, 8));
  } finally {
    await browser.close();
  }

  console.log(JSON.stringify({
    passed,
    failed,
    base: baseUrl,
    proof: 'navigateur Chromium reel (Playwright), mesures de boites',
  }));

  process.exitCode = failed ? 1 : 0;
})().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
