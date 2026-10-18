'use strict';

const assert = require('node:assert/strict');

const base = 'http://127.0.0.1:18682/card-bridge-read.php';
const cases = [
  ['session required', '?student_id=1', 'GET', 401, 'NATIVE_SESSION_REQUIRED'],
  ['school override rejected', '?student_id=1&school_id=2', 'GET', 400, 'INVALID_QUERY'],
  ['year override rejected', '?student_id=1&syear=2025', 'GET', 400, 'INVALID_QUERY'],
  ['action override rejected', '?student_id=1&modfunc=update', 'GET', 400, 'INVALID_QUERY'],
  ['module override rejected', '?student_id=1&modname=Users/User.php', 'GET', 400, 'INVALID_QUERY'],
  ['locale override rejected', '?student_id=1&locale=en_US.utf8', 'GET', 400, 'INVALID_QUERY'],
  ['invalid identity rejected', '?student_id=-1', 'GET', 400, 'INVALID_STUDENT_ID'],
  ['array identity rejected', '?student_id%5B%5D=1', 'GET', 400, 'INVALID_STUDENT_ID'],
  ['write rejected', '?student_id=1', 'POST', 405, 'METHOD_NOT_ALLOWED'],
];

(async () => {
  for (const [name, query, method, status, error] of cases) {
    const response = await fetch(base + query, { method, redirect: 'manual', signal: AbortSignal.timeout(10000) });
    assert.equal(response.status, status, name);
    assert.ok((response.headers.get('cache-control') || '').split(',').map((value) => value.trim()).includes('no-store'), name);
    assert.equal(response.headers.get('x-content-type-options'), 'nosniff', name);
    assert.deepEqual(await response.json(), { error }, name);
    console.log('PASS ' + name);
  }
  console.log('9 HTTP boundary checks passed; authenticated access not tested.');
})().catch((error) => { console.error(error.message); process.exitCode = 1; });
