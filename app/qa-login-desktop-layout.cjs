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
 * Usage : node app/qa-login-desktop-layout.cjs
 * Variables : SCHOOLSAFE_URL (defaut http://127.0.0.1:8081).
 * Captures : app/qa-output/.
 */
const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');

const baseUrl = (process.env.SCHOOLSAFE_URL || 'http://127.0.0.1:8081').replace(/\/$/, '');
const outputDir = path.join(__dirname, 'qa-output');

// fold: true => l'ecran doit tenir entierement sous le pli (cible 1440-1920).
const VIEWPORTS = [
  { width: 1920, height: 1080, fold: true },
  { width: 1440, height: 900, fold: true },
  { width: 1440, height: 1200, fold: true },
  { width: 1366, height: 768, fold: false },
  { width: 1024, height: 768, fold: false },
  { width: 390, height: 844, fold: false },
];

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
      height: Math.round(rect.height),
    };
  };

  const panel = box('#ss-login-form-slot');
  const following = {};

  for (const selector of ['.ss-login-features', '.ss-login-slogan', '#ss-login-legal-slot']) {
    following[selector] = box(selector);
  }

  return {
    panel,
    following,
    welcome: box('.ss-login-welcome'),
    position: getComputedStyle(document.querySelector('#ss-login-form-slot')).position,
    scrollHeight: document.documentElement.scrollHeight,
    viewportHeight: window.innerHeight,
    hasForm: !!document.querySelector('#ss-login-form-slot #loginform'),
  };
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

  try {
    for (const viewport of VIEWPORTS) {
      const label = viewport.width + 'x' + viewport.height;
      const context = await browser.newContext({
        viewport: { width: viewport.width, height: viewport.height },
        serviceWorkers: 'block',
      });
      const page = await context.newPage();

      await page.goto(baseUrl + '/index.php', { waitUntil: 'load', timeout: 30000 });
      await page.waitForSelector('#ss-login-form-slot #loginform', { timeout: 15000 });
      await page.waitForTimeout(250); // laisser la mise en page se stabiliser

      let state = await page.evaluate(measure);

      check(label + ' : le formulaire de connexion est rendu dans le panneau', state.hasForm);
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

      if (viewport.fold) {
        const legalBottom = state.following['#ss-login-legal-slot'].bottom;
        check(label + ' : toute la page tient sous le pli',
          legalBottom <= viewport.height + 8 && state.scrollHeight <= viewport.height + 8,
          'bas de la zone legale ' + legalBottom + ' px, document ' + state.scrollHeight + ' px');
      }

      // Capture de l'ecran tel quel, AVANT l'injection du faux message d'erreur.
      if (viewport.width === 1440 && viewport.height === 900) {
        await page.screenshot({ path: path.join(outputDir, 'login-desktop-1440x900.png') }).catch(() => {});
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

      await context.close();
    }
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
