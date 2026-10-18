const test = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');
const path = require('node:path');
const { buildStudioBridgeInput } = require('./studio-bridge-input.cjs');
const adapterSource = fs.readFileSync(path.join(__dirname, '../../cards/card-adapter.js'), 'utf8');
const clientSource = fs.readFileSync(path.join(__dirname, 'studio-native-client.js'), 'utf8');
function client(response) {
  const context = vm.createContext({ fetch: async (url, options) => {
    assert.equal(url, '/card-bridge-prepare.php?resource=studio-input&student_id=1');
    assert.equal(options.credentials, 'same-origin');
    return { ok: true, json: async () => response };
  } });
  vm.runInContext('var window = globalThis;', context);
  vm.runInContext(adapterSource, context);
  vm.runInContext(clientSource, context);
  return context.SchoolSafeStudioNativeBridge;
}
test('browser connector consumes existing Studio adapter without modifying it', async () => {
  const data = buildStudioBridgeInput({ authorized: true, student: { STUDENT_ID: '1', FIRST_NAME: 'TEST', LAST_NAME: 'CODEX' },
    school: { ID: '1', SYEAR: '2026', TITLE: 'TEST' }, enrollment: { STUDENT_ID: '1', SCHOOL_ID: '1', SYEAR: '2026' },
    verified: { matricule: 'SCH0023-000001' } }, {
    studentId: '46141a61-9270-4164-bce4-655ac96a5dd1', schoolId: '8f1c3850-6568-4a26-8312-ed582c95891b',
    guardianPhones: [], credential: { matricule: 'SCH0023-000001', card_number: null, signature: null } });
  const result = await client({ data }).loadNativeStudioCard('1');
  assert.equal(result.view.student.id, data.targetReference.studentId);
  assert.equal(result.view.student.mat, 'SCH0023-000001');
  assert.equal(result.view.student.qr, '');
});
test('incomplete target identity is rejected before adaptation', async () => {
  await assert.rejects(client({ data: { issuanceAllowed: false, nativeReference: { studentId: '1' }, input: {} } })
    .loadNativeStudioCard('1'), /STUDIO_BRIDGE_RESULT_INVALID/);
});
