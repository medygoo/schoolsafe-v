const fs = require('node:fs');
const assert = require('node:assert/strict');

const live = fs.readFileSync('app/modules/jaspe2d/live-companion.js', 'utf8');

assert.ok(/isVisible/.test(live), 'v12 doit recevoir isVisible');
assert.ok(/isTyping/.test(live), 'v12 doit recevoir isTyping');
assert.ok(/isBust/.test(live), 'v12 doit recevoir isBust');
assert.ok(/destroy\s*[:(]/.test(live), 'le handle v12 doit exposer destroy');
assert.ok(/stop\s*[:(]/.test(live), 'le handle v12 doit exposer stop');
assert.ok(!/closest\(['"]\.auth-screen/.test(live), 'v12 ne doit plus dépendre de .auth-screen');

console.log('JASPE physical v12 contract: PASS');
