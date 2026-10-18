const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const root = path.resolve(__dirname, '..');
const html = fs.readFileSync(path.join(root, 'app/index.html'), 'utf8');

const firstInlineScript = html.slice(0, html.indexOf('</script>')).match(/<script>([\s\S]*)$/);
const inlineSource = firstInlineScript ? firstInlineScript[1] : '';

// Le correctif ne vaut que s'il est exécuté avant le premier script chargé :
// auth-native.js, cards-module.js et finance-api.js lisent window.schoolSafeApiBase
// au chargement, donc une définition placée dans app.js arrive trop tard.
test("la base d'API est définie dans le premier script inline, avant tout <script src>", () => {
  assert.ok(inlineSource, 'aucun script inline en tête de page');
  assert.match(inlineSource, /window\.schoolSafeApiBase\s*=/);
  assert.ok(
    html.indexOf('window.schoolSafeApiBase') < html.indexOf('<script src='),
    'window.schoolSafeApiBase doit précéder le premier script externe'
  );
});

function runHeadScript(location) {
  const window = { location };
  const context = vm.createContext({
    window,
    localStorage: { getItem: () => null, setItem: () => {}, removeItem: () => {} },
    document: { documentElement: { setAttribute: () => {} } },
  });
  vm.runInContext(inlineSource, context);
  return window.schoolSafeApiBase;
}

test("en production, la base d'API reste l'origine servie (aucun 127.0.0.1)", () => {
  assert.equal(
    runHeadScript({ hostname: 'app.shoolsafe.tech', origin: 'https://app.shoolsafe.tech' }),
    'https://app.shoolsafe.tech'
  );
});

test('en développement local, la base API vise le serveur 8787', () => {
  for (const hostname of ['127.0.0.1', 'localhost', '[::1]']) {
    assert.equal(
      runHeadScript({ hostname, origin: 'http://' + hostname + ':4175' }),
      'http://127.0.0.1:8787'
    );
  }
});

test('une base explicite (meta ou variable globale) garde la priorité', () => {
  const window = { location: { hostname: 'app.shoolsafe.tech', origin: 'https://app.shoolsafe.tech' }, SCHOOLSAFE_API_BASE: 'https://api.example.test' };
  const context = vm.createContext({
    window,
    localStorage: { getItem: () => null, setItem: () => {}, removeItem: () => {} },
    document: { documentElement: { setAttribute: () => {} } },
  });
  vm.runInContext(inlineSource, context);
  assert.equal(window.schoolSafeApiBase, 'https://api.example.test');
});
