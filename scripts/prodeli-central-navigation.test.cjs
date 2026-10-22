'use strict';
// Execute the real central script with a minimal DOM: no session or credentials.
const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const root = path.join(__dirname, '../app/modules/codex-ui-v1/extensions-test-runtime');
const script = fs.readFileSync(path.join(root, 'plugins/SchoolSafe_Keycloak/central-ui.js'), 'utf8');
for (const anchor of ['schools-title', 'access-title', 'communications-title', 'followup-title', 'account-5', 'request-2']) {
  test(`native URL normalization preserves #sskc-${anchor}`, () => {
    let replaced = null, focused = false;
    const target = {scrollIntoView() {}, focus() {focused = true;}};
    const shell = {dataset: {centralRoute: '/schoolsafe-administrators.php?home=1'}, querySelector() {return null;}, querySelectorAll() {return [];}};
    const location = {pathname: '/Modules.php', search: '?modname=Users/User.php', hash: '#sskc-' + anchor};
    vm.runInNewContext(script, {
      document: {readyState: 'complete', querySelector() {return shell;}, getElementById(id) {return id === 'sskc-' + anchor ? target : null;}},
      location, history: {state: null, replaceState(_state, _title, url) {replaced = url;}},
      window: {addEventListener() {}}, setTimeout(callback) {callback();}, URL,
    });
    assert.equal(replaced, '/schoolsafe-administrators.php?home=1#sskc-' + anchor);
    assert.equal(focused, true);
  });
}
test('general configuration is not advertised as a dedicated email configuration', () => {
  const wizard = fs.readFileSync(path.join(root, 'plugins/SchoolSafe_Keycloak/Wizard.php'), 'utf8');
  assert.doesNotMatch(wizard, /Réglages existants du courriel/);
  assert.match(wizard, /Configuration générale de l’école/);
});
