const test = require('node:test');
const assert = require('node:assert/strict');
const { buildStudioBridgeInput } = require('./studio-bridge-input.cjs');
const native = { authorized: true, student: { STUDENT_ID: '1', FIRST_NAME: 'TEST', LAST_NAME: 'CODEX' },
  school: { ID: '1', SYEAR: '2026', TITLE: 'TEST' }, enrollment: { STUDENT_ID: '1', SCHOOL_ID: '1', SYEAR: '2026' },
  verified: { matricule: 'SCH0023-000001' } };
const metadata = { studentId: '46141a61-9270-4164-bce4-655ac96a5dd1', schoolId: '8f1c3850-6568-4a26-8312-ed582c95891b',
  guardianPhones: [], credential: { matricule: 'SCH0023-000001', card_number: null, signature: null } };
test('native identity, target UUID and matricule remain separate without QR invention', () => {
  const result = buildStudioBridgeInput(native, metadata);
  assert.equal(result.input.record.id, metadata.studentId);
  assert.equal(result.nativeReference.studentId, '1');
  assert.equal(result.input.record.matricule, 'SCH0023-000001');
  assert.equal(result.input.credential.qr_payload, '');
  assert.ok(result.missing.includes('card.official_qr'));
  assert.equal(result.issuanceAllowed, false);
});
test('only existing active credential may become an official QR', () => {
  for (const status of ['active', 'suspended', 'revoked', undefined]) {
    const result = buildStudioBridgeInput(native, { ...metadata, credential: { ...metadata.credential,
      status, card_number: 'TEST-NUMBER', signature: 'TEST-SIGNATURE' } });
    assert.equal(Boolean(result.input.credential.qr_payload), status === 'active');
  }
});
test('inconsistent matricule or target mapping is rejected', () => {
  assert.throws(() => buildStudioBridgeInput(native, { ...metadata, studentId: '1' }), /TARGET_MAPPING_REQUIRED/);
  assert.throws(() => buildStudioBridgeInput(native, { ...metadata, credential: { matricule: 'EBY' } }), /MATRICULE_MAPPING_MISMATCH/);
});
