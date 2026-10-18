'use strict';
const test = require('node:test');
const assert = require('node:assert/strict');
const { issuePreparedNativeCard } = require('./native-card-issuer.cjs');

function fixture() {
  let calls = 0;
  const args = {
    context: { studentId: 'student', schoolId: 'school', userId: 'user', profileId: 'profile', requestId: 'request' },
    prepared: { issuanceAllowed: false, missing: ['card.official_qr'], targetReference: { studentId: 'student', schoolId: 'school' } },
    assertSchoolAccess: async () => true,
    service: { issueCardCredential: async (context, studentId) => { calls++; assert.equal(studentId, context.studentId); return { existingServiceResult: true }; } },
  };
  return { args, calls: () => calls };
}

test('delegates to the existing issuer without manufacturing a credential', async () => {
  const f = fixture();
  assert.deepEqual(await issuePreparedNativeCard(f.args), { existingServiceResult: true });
  assert.equal(f.calls(), 1);
});

test('incomplete data never invokes the issuer', async () => {
  const f = fixture(); f.args.prepared.missing.push('school.logo');
  await assert.rejects(issuePreparedNativeCard(f.args), /CARD_DATA_INCOMPLETE/);
  assert.equal(f.calls(), 0);
});

test('mismatched school or student never invokes the issuer', async () => {
  for (const field of ['schoolId', 'studentId']) {
    const f = fixture(); f.args.context[field] = 'other';
    await assert.rejects(issuePreparedNativeCard(f.args), /CARD_ISSUER_CONTEXT_INVALID/);
    assert.equal(f.calls(), 0);
  }
});

test('central access must be explicitly true', async () => {
  for (const value of [false, undefined, null]) {
    const f = fixture(); f.args.assertSchoolAccess = async () => value;
    await assert.rejects(issuePreparedNativeCard(f.args), /SCHOOL_ACCESS_DENIED/);
    assert.equal(f.calls(), 0);
  }
});

test('missing native service fails closed', async () => {
  const f = fixture(); delete f.args.service;
  await assert.rejects(issuePreparedNativeCard(f.args), /NATIVE_CARD_ISSUER_REQUIRED/);
});

test('native permission or transaction failures propagate unchanged', async () => {
  const f = fixture(); const error = new Error('NATIVE_PERMISSION_DENIED');
  f.args.service.issueCardCredential = async () => { throw error; };
  await assert.rejects(issuePreparedNativeCard(f.args), e => e === error);
});
