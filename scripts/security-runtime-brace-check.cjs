'use strict';
// Run only in a disposable, network-disabled container using the delivery image.
const assert = require('node:assert/strict');
const { createRequire } = require('node:module');
const { spawnSync } = require('node:child_process');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const appRequire = createRequire(path.join(process.cwd(), 'package.json'));
const mode = process.argv[2];
assert.ok(['before', 'after'].includes(mode));
const report = { mode, node: process.version, checks: [] };
(async () => {
  let app, directory;
  try {
    const version = appRequire('brace-expansion/package.json').version;
    assert.equal(version, mode === 'before' ? '5.0.9' : '5.0.11');
    assert.equal(appRequire('fastify/package.json').version, '5.12.4');
    report.braceExpansion = version;
    const { expand } = appRequire('brace-expansion');
    assert.deepEqual(expand('school-{a,b}'), ['school-a', 'school-b']);
    assert.deepEqual(expand('class-{1..3}'), ['class-1', 'class-2', 'class-3']);
    report.checks.push('ordinary-expansions');
    const cases = {
      nested: "'{'.repeat(4000)+'a,b'+'}'.repeat(4000)",
      commaGroups: "'{'+'{a},'.repeat(8000)+'b}'",
      arrayAppend: "'{{x},'+'a,'.repeat(130000)+'b}'",
    };
    for (const [name, expression] of Object.entries(cases)) {
      const code = `const {expand}=require(${JSON.stringify(appRequire.resolve('brace-expansion'))});if(!Array.isArray(expand(${expression},{max:1,maxLength:64})))process.exit(2);`;
      const r = spawnSync(process.execPath, ['--max-old-space-size=128', '-e', code], { encoding: 'utf8', timeout: 10000, maxBuffer: 1024 * 1024 });
      assert.ifError(r.error);
      if (mode === 'before') {
        assert.notEqual(r.status, 0);
        assert.match(r.stderr, /RangeError: Maximum call stack size exceeded/);
      } else { assert.equal(r.status, 0, name + ': ' + r.stderr.slice(0, 200)); }
      report.checks.push(name + (mode === 'before' ? '-defect-reproduced' : '-no-overflow'));
    }
    directory = fs.mkdtempSync(path.join(os.tmpdir(), 'schoolsafe-brace-fixture-'));
    fs.writeFileSync(path.join(directory, 'fixture.txt'), 'SchoolSafe fictional fixture');
    app = appRequire('fastify')({ logger: false });
    await app.register(appRequire('@fastify/static'), { root: directory, prefix: '/fixture/', wildcard: false });
    await app.ready();
    const response = await app.inject({ method: 'GET', url: '/fixture/fixture.txt' });
    assert.equal(response.statusCode, 200);
    assert.equal(response.body, 'SchoolSafe fictional fixture');
    assert.equal((await app.inject({ method: 'HEAD', url: '/fixture/fixture.txt' })).statusCode, 200);
    assert.equal((await app.inject({ method: 'GET', url: '/fixture/missing.txt' })).statusCode, 404);
    report.checks.push('real-fastify-static-get-head-missing');
    report.status = 'PASS';
  } catch (e) { report.status = 'FAIL'; report.error = e.message; process.exitCode = 1; }
  finally {
    if (app) await app.close();
    if (directory) fs.rmSync(directory, { recursive: true });
    console.log(JSON.stringify(report));
  }
})();
