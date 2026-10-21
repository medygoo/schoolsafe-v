'use strict';
const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { spawnSync } = require('node:child_process');
const root = path.resolve(__dirname, '..');
const load = file => JSON.parse(fs.readFileSync(path.join(root, file), 'utf8'));

test('workspace and standalone locks retain the patched dependency contract', () => {
  for (const file of ['package-lock.json', 'server/package-lock.json']) {
    const lock = load(file);
    assert.equal(lock.packages['node_modules/brace-expansion'].version, '5.0.11');
    assert.equal(lock.packages['node_modules/fastify'].version, '5.12.4');
  }
  assert.equal(load('server/package-lock.json').packages[''].dependencies.fastify,
    load('server/package.json').dependencies.fastify);
});

test('ordinary brace expansions retain their meaning', () => {
  const { expand } = require('brace-expansion');
  assert.deepEqual(expand('school-{a,b}'), ['school-a', 'school-b']);
  assert.deepEqual(expand('class-{1..3}'), ['class-1', 'class-2', 'class-3']);
  assert.deepEqual(expand('x{a,{b,c}}'), ['xa', 'xb', 'xc']);
});

// Public upstream advisory regressions, confined to short-lived local processes.
// No request is sent to SchoolSafe or to any network endpoint.
const cases = {
  nested: "'{'.repeat(4000) + 'a,b' + '}'.repeat(4000)",
  commaGroups: "'{' + '{a},'.repeat(8000) + 'b}'",
  arrayAppend: "'{{x},' + 'a,'.repeat(130000) + 'b}'",
};
for (const [name, expression] of Object.entries(cases)) {
  test(`bounded parser regression: ${name}`, () => {
    const code = `const {expand}=require(${JSON.stringify(require.resolve('brace-expansion'))}); const result=expand(${expression},{max:1,maxLength:64}); if(!Array.isArray(result))process.exit(2);`;
    const result = spawnSync(process.execPath, ['--max-old-space-size=128', '-e', code], {
      cwd: root, encoding: 'utf8', timeout: 10000, maxBuffer: 1024 * 1024,
    });
    assert.ifError(result.error);
    assert.equal(result.status, 0, `Parser must not overflow or terminate (${name}); ${result.stderr.slice(0, 300)}`);
  });
}
