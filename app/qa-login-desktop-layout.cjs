/*
 * SchoolSafe — les deux ecrans bleus, servis par la connexion Rosario.
 *
 * Ce test remplace celui qui mesurait l'ancien ecran de connexion
 * (#ss-login-reference) : cet ecran n'est plus rendu, l'accueil et la
 * connexion sont desormais les deux ecrans bleus du prototype valide.
 *
 * Il verifie, dans un vrai navigateur et sur de vraies boites mesurees :
 *   - l'accueil s'affiche en premier, avec le bouton « Commencer » ;
 *   - « Commencer » ouvre la connexion, avec les champs NATIFS de Rosario
 *     (USERNAME, PASSWORD), le lien « Mot de passe oublie » et le choix de
 *     langue restes branches sur index.php ;
 *   - le panneau est ce que l'oeil voit : rien ne le recouvre, ni le
 *     decor, ni le heros JASPE, ni un autre ecran ;
 *   - un identifiant refuse ramene sur la connexion et le message de
 *     Rosario s'affiche DANS le panneau ;
 *   - rien ne deborde horizontalement, du bureau (1920) au telephone (360).
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

// fold: true => le panneau entier doit tenir sous le pli au chargement.
const VIEWPORTS = [
  { width: 1920, height: 1080, fold: true, capture: 'login-blue-connexion-1920x1080' },
  { width: 1600, height: 900, fold: true, capture: null },
  { width: 1440, height: 900, fold: true, capture: 'login-blue-connexion-1440x900' },
  { width: 1366, height: 768, fold: false, capture: null },
  { width: 1280, height: 720, fold: false, capture: null },
  { width: 1024, height: 768, fold: false, capture: null },
  { width: 390, height: 844, fold: false, capture: 'login-blue-connexion-390x844' },
  { width: 360, height: 640, fold: false, capture: null },
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

/** Boites mesurees dans la page, plus ce que l'oeil voit aux points cles. */
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

  const panelElement = document.querySelector('#auth .login-panel');
  const panel = box('#auth .login-panel');

  /** Aire de recouvrement de deux boites, en pixels carres. */
  const overlap = (a, b) => {
    if (!a || !b) return 0;
    const width = Math.min(a.right, b.right) - Math.max(a.left, b.left);
    const height = Math.min(a.bottom, b.bottom) - Math.max(a.top, b.top);
    return width > 0 && height > 0 ? width * height : 0;
  };

  const paintedAt = (selector) => {
    const element = document.querySelector(selector);
    if (!element || !panel) return null;
    const rect = element.getBoundingClientRect();
    const x = Math.round(Math.min(Math.max(rect.left + rect.width / 2, 1), window.innerWidth - 2));
    const y = Math.round(Math.min(Math.max(rect.top + Math.min(rect.height / 2, 24), 1), window.innerHeight - 2));
    const painted = document.elementFromPoint(x, y);
    return {
      isPanel: !!painted && !!panelElement &&
        (painted === panelElement || panelElement.contains(painted)),
      tag: painted ? painted.tagName.toLowerCase() + (painted.id ? '#' + painted.id : '') : null,
    };
  };

  const visible = (selector) => {
    const element = document.querySelector(selector);
    if (!element) return false;
    const style = getComputedStyle(element);
    const rect = element.getBoundingClientRect();
    return style.visibility !== 'hidden' && style.display !== 'none' &&
      Number(style.opacity) > 0 && rect.width > 0 && rect.height > 0;
  };

  const username = document.getElementById('USERNAME');
  const password = document.getElementById('PASSWORD');

  return {
    splashActive: !!document.querySelector('#splash.active'),
    authActive: !!document.querySelector('#auth.active'),
    splashVisible: visible('#splash'),
    startLabel: (document.querySelector('#enterSplash span') || {}).textContent || '',
    panel,
    panelPosition: document.querySelector('#auth .login-panel')
      ? getComputedStyle(document.querySelector('#auth .login-panel')).position : null,
    paintedTop: paintedAt('#auth .login-panel'),
    paintedButton: paintedAt('#auth .login-panel .login-button'),
    paintedField: paintedAt('#auth .ss-input-shell'),
    topbarOverlap: overlap(panel, box('.auth-topbar')),
    valuesOverlap: overlap(panel, box('.auth-values')),
    footerOverlap: overlap(panel, box('.auth-footer')),
    messageOverlap: overlap(panel, box('.auth-message')),
    usernameVisible: visible('#USERNAME'),
    passwordVisible: visible('#PASSWORD'),
    submitVisible: visible('#auth .login-button'),
    usernameValue: username ? username.value : null,
    passwordType: password ? password.type : null,
    formAction: document.querySelector('#loginform')
      ? document.querySelector('#loginform').getAttribute('action') : null,
    formMethod: document.querySelector('#loginform')
      ? document.querySelector('#loginform').getAttribute('method') : null,
    resetHref: (document.querySelector('#auth .text-button') || {}).getAttribute
      ? document.querySelector('#auth .text-button').getAttribute('href') : null,
    languageHrefs: Array.from(document.querySelectorAll('#auth .language-switch a'))
      .map((link) => link.getAttribute('href')),
    documentWidth: document.documentElement.scrollWidth,
    viewportWidth: window.innerWidth,
    errorInsidePanel: (() => {
      const message = document.querySelector('#ss-blue .ss-blue-messages');
      if (!message || !panel) return null;
      const rect = message.getBoundingClientRect();
      return rect.top >= panel.top - 1 && rect.bottom <= panel.bottom + 1 &&
        rect.left >= panel.left - 1 && rect.right <= panel.right + 1;
    })(),
    errorText: (document.querySelector('#ss-blue .ss-blue-messages .error') || {}).textContent || '',
  };
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

  /** Ouvre la connexion depuis l'accueil, comme un utilisateur. */
  async function openLogin(page) {
    await page.waitForSelector('#splash.active', { timeout: 15000 });
    await page.click('#enterSplash');
    await page.waitForSelector('#auth.active #loginform', { timeout: 15000 });
    await page.waitForTimeout(150);
  }

  async function checkViewport(page, viewport, label, capture) {
    await page.goto(baseUrl + '/index.php', { waitUntil: 'load', timeout: 30000 });
    await page.waitForSelector('#ss-blue', { timeout: 15000 });
    await page.waitForSelector('#splash.active', { timeout: 15000 });
    await page.waitForTimeout(200);

    let state = await page.evaluate(measure);

    check(label + ' : l\'accueil s\'affiche en premier', state.splashActive && state.splashVisible);
    check(label + ' : le bouton d\'accueil est « Commencer »',
      state.startLabel.trim() === 'Commencer', 'libelle « ' + state.startLabel.trim() + ' »');
    check(label + ' : la connexion n\'est pas encore a l\'ecran', !state.authActive);

    if (capture && viewport.width >= 1400) {
      await page.screenshot({ path: path.join(outputDir, capture.replace('connexion', 'accueil') + '.png') })
        .catch(() => {});
    }

    await openLogin(page);
    state = await page.evaluate(measure);

    check(label + ' : « Commencer » ouvre la connexion', state.authActive && state.usernameVisible);
    check(label + ' : les champs natifs de Rosario sont la (nom d\'utilisateur, mot de passe)',
      state.usernameVisible && state.passwordVisible && state.submitVisible);
    check(label + ' : le formulaire poste vers index.php',
      state.formAction === 'index.php' && (state.formMethod || '').toLowerCase() === 'post',
      state.formAction + ' / ' + state.formMethod);
    check(label + ' : le mot de passe est masque a l\'ouverture', state.passwordType === 'password');
    check(label + ' : « Mot de passe oublie » reste branche sur Rosario',
      state.resetHref === 'PasswordReset.php', String(state.resetHref));
    check(label + ' : le choix de langue reste branche sur Rosario',
      state.languageHrefs.length > 0 && state.languageHrefs.every((href) => (href || '').startsWith('index.php?locale=')),
      state.languageHrefs.join(' '));
    check(label + ' : le panneau est ce que l\'oeil voit (aucun decor par-dessus)',
      state.paintedTop && state.paintedTop.isPanel, 'paint ' + (state.paintedTop || {}).tag);
    check(label + ' : ni le bandeau, ni les engagements, ni le pied ne recouvrent le panneau',
      state.topbarOverlap === 0 && state.valuesOverlap === 0 && state.footerOverlap === 0 &&
        state.messageOverlap === 0,
      JSON.stringify({ bandeau: state.topbarOverlap, engagements: state.valuesOverlap,
        pied: state.footerOverlap, titre: state.messageOverlap }));
    check(label + ' : le bouton « Continuer » est cliquable',
      state.paintedButton && state.paintedButton.isPanel, 'paint ' + (state.paintedButton || {}).tag);
    check(label + ' : le champ recoit bien le clic',
      state.paintedField && state.paintedField.isPanel, 'paint ' + (state.paintedField || {}).tag);
    check(label + ' : aucun debordement horizontal',
      state.documentWidth <= state.viewportWidth + 1,
      'document ' + state.documentWidth + ' px pour ' + state.viewportWidth + ' px');

    if (viewport.fold) {
      check(label + ' : le panneau entier tient sous le pli',
        state.panel.bottom <= viewport.height,
        'bas du panneau ' + state.panel.bottom + ' px pour ' + viewport.height + ' px de haut');
    }

    // Saisie reelle : les deux champs recoivent le texte, le panneau reste stable.
    await page.fill('#USERNAME', 'qa.ecran.bleu');
    await page.fill('#PASSWORD', 'MotDePasse-QA-2026!');
    const filled = await page.evaluate(measure);
    check(label + ' : les champs acceptent la saisie',
      filled.usernameValue === 'qa.ecran.bleu' && filled.passwordType === 'password');
    check(label + ' : la saisie ne bouge pas la mise en page',
      Math.abs(filled.panel.top - state.panel.top) <= 2);

    if (capture) {
      await page.screenshot({ path: path.join(outputDir, capture + '.png') }).catch(() => {});
    }
  }

  try {
    for (const viewport of VIEWPORTS) {
      const context = await browser.newContext({
        viewport: { width: viewport.width, height: viewport.height },
        serviceWorkers: 'block',
      });
      const page = await context.newPage();
      const errors = [];
      page.on('pageerror', (error) => errors.push(error.message));

      await checkViewport(page, viewport, viewport.width + 'x' + viewport.height, viewport.capture);
      check(viewport.width + 'x' + viewport.height + ' : aucune erreur de script dans la page',
        errors.length === 0, errors.join(' | '));

      // Un identifiant refuse : le message de Rosario doit rester dans le panneau.
      if (viewport.width === 1440) {
        await page.fill('#USERNAME', 'qa.inconnu.' + Date.now());
        await page.fill('#PASSWORD', 'MotDePasse-QA-2026!');
        await page.click('#auth .login-button');
        await page.waitForNavigation({ waitUntil: 'load', timeout: 30000 }).catch(() => {});
        await page.waitForSelector('#auth.active #loginform', { timeout: 15000 });
        await page.waitForTimeout(250);
        const refused = await page.evaluate(measure);

        check('1440x900 : un identifiant refuse ramene sur la connexion', refused.authActive);
        check('1440x900 : le message de Rosario s\'affiche DANS le panneau',
          refused.errorInsidePanel === true, JSON.stringify(refused.errorInsidePanel));
        check('1440x900 : le message porte bien le refus', refused.errorText.trim().length > 0,
          refused.errorText.trim().slice(0, 80));
        await page.screenshot({ path: path.join(outputDir, 'login-blue-refus-1440x900.png') })
          .catch(() => {});
      }

      await context.close();
    }

    const digest = (name) => crypto.createHash('md5')
      .update(fs.readFileSync(path.join(outputDir, name))).digest('hex');

    check('l\'accueil et la connexion sont deux ecrans differents',
      digest('login-blue-accueil-1440x900.png') !== digest('login-blue-connexion-1440x900.png'),
      'md5 ' + digest('login-blue-accueil-1440x900.png').slice(0, 8) + ' / ' +
        digest('login-blue-connexion-1440x900.png').slice(0, 8));
    check('bureau et telephone ne rendent pas la meme chose',
      digest('login-blue-connexion-1440x900.png') !== digest('login-blue-connexion-390x844.png'),
      'md5 ' + digest('login-blue-connexion-1440x900.png').slice(0, 8) + ' / ' +
        digest('login-blue-connexion-390x844.png').slice(0, 8));
  } finally {
    await browser.close();
  }

  console.log(JSON.stringify({
    passed,
    failed,
    base: baseUrl,
    proof: 'navigateur Chromium reel (Playwright), mesures de boites et saisie reelle',
  }));

  process.exitCode = failed ? 1 : 0;
})().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
