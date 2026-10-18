const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const dir = path.resolve(__dirname, '../../app/modules/codex-ui-v1');
const window = { location: { origin: 'http://127.0.0.1:5187' } };
const context = vm.createContext({ window, URL, URLSearchParams });
for (const file of ['read-adapter.js', 'native-read-bridge.js']) vm.runInContext(fs.readFileSync(path.join(dir, file), 'utf8'), context);
(async () => {
  let session = { identityKey: 'verified-profile', schoolId: 'verified-school', revision: 1 };
  let permitted = true, calls = [], status = 200, change = false, revoke = false;
  const options = {
    getSession: () => session, authorizeRead: async () => permitted,
    fetch: async (url, request) => {
      calls.push({ url, request });
      if (change) session = { ...session, revision: session.revision + 1 };
      return { ok: status === 200, status, json: async () => { if (revoke) permitted = false; return { data: { total: 12 } }; } };
    }
  };
  const read = window.SchoolSafeCodexNativeReadBridge.create(options);
  assert.equal((await read('schoolBoard')).total, 12);
  assert.equal(calls[0].request.credentials, 'include');
  assert.equal(calls[0].request.method, 'GET');
  assert.equal(calls[0].request.redirect, 'error');
  assert.equal(calls[0].url, window.location.origin + '/native/scan-control/board/school');
  permitted = false;
  await assert.rejects(read('schoolBoard'), /autorisee/);
  assert.equal(calls.length, 1);
  permitted = true;
  await assert.rejects(read('onSiteStudent', { student_id: 'invalid' }), /dossier autorise/);
  assert.equal(calls.length, 1);
  await assert.rejects(read('unknown'), /inconnue/);
  for (status of [401, 403, 500]) await assert.rejects(read('schoolBoard'), error => error.status === status);
  status = 200; change = true;
  await assert.rejects(read('schoolBoard'), /Session modifiee/);
  change = false; revoke = true;
  await assert.rejects(read('schoolBoard'), /autorisee/);
  revoke = false; permitted = true; session.demo = true;
  await assert.rejects(read('schoolBoard'), /Session native/);
  assert.throws(() => window.SchoolSafeCodexNativeReadBridge.create({ ...options, apiBase: 'https://example.org' }), /distincte/);
  console.log('PASS: transport GET, cookie, permission, UUID, erreurs HTTP, changement de session, revocation, demo et origine. Tests simules uniquement.');
})().catch(error => { console.error(error); process.exitCode = 1; });
