'use strict';
const { test } = require('node:test');
const assert = require('node:assert/strict');
const { createHmac } = require('node:crypto');
const { prepareStudentCard } = require('./prepare-student-card.cjs');
const secret = Buffer.alloc(32, 7);
function fixture() {
  const claim = {
    version: 1, audience: 'schoolsafe-card-bridge', sourceInstance: 'test',
    nativeUserId: '3', nativeSchoolId: '1', nativeYear: '2026',
    issuedAt: 100, expiresAt: 130, nonce: 'a'.repeat(64),
    input: { authorized: true, student: { STUDENT_ID: '1', FIRST_NAME: 'Test', LAST_NAME: 'Child' },
      school: { ID: '1', SYEAR: '2026', TITLE: 'Test school' },
      enrollment: { STUDENT_ID: '1', SCHOOL_ID: '1', SYEAR: '2026' }, verified: {} },
  };
  const sign = () => {
    const body = JSON.stringify(claim);
    return { body, signature: createHmac('sha256', secret).update(body).digest('hex') };
  };
  let writes = 0;
  let values;
  const deps = {
    nativeVerification: { secret, sourceInstance: 'test', consumeNonce: async () => true, now: () => 110 },
    authorizePreparation: async () => true,
    registryDb: { query: async (sql, params) => {
      writes++; values = params;
      assert.match(sql, /\$1::text/);
      return { rows: [{ matricule: params[3] || 'SCH0023-000001' }] };
    } },
  };
  return { claim, sign, deps, writes: () => writes, values: () => values };
}
test('signed native data supplies matricule to Studio without issuing a card', async () => {
  const f = fixture();
  const result = await prepareStudentCard(f.sign(), f.deps);
  assert.equal(result.input.record.matricule, 'SCH0023-000001');
  assert.equal(result.issuanceAllowed, false);
  assert.equal(result.input.credential.qr_payload, undefined);
  assert.ok(!result.missing.includes('student.matricule'));
  assert.ok(result.missing.includes('card.official_qr'));
  assert.equal(f.claim.input.verified.matricule, undefined);
});
test('preserves verified legacy matricule', async () => {
  const f = fixture(); f.claim.input.verified.matricule = 'LEGACY-42';
  assert.equal((await prepareStudentCard(f.sign(), f.deps)).input.record.matricule, 'LEGACY-42');
  assert.equal(f.values()[3], 'LEGACY-42');
});
test('denied permission causes zero registry writes', async () => {
  const f = fixture(); f.deps.authorizePreparation = async () => false;
  await assert.rejects(prepareStudentCard(f.sign(), f.deps), /ACCESS_DENIED/);
  assert.equal(f.writes(), 0);
});
test('tampered signature causes zero registry writes', async () => {
  const f = fixture(); const e = f.sign(); e.body += ' ';
  await assert.rejects(prepareStudentCard(e, f.deps), /INVALID_SIGNATURE/);
  assert.equal(f.writes(), 0);
});
test('replayed envelope causes zero registry writes', async () => {
  const f = fixture(); f.deps.nativeVerification.consumeNonce = async () => false;
  await assert.rejects(prepareStudentCard(f.sign(), f.deps), /REPLAY_REJECTED/);
  assert.equal(f.writes(), 0);
});
test('database failure propagates without a fabricated matricule', async () => {
  const f = fixture(); f.deps.registryDb.query = async () => { throw new Error('DATABASE_UNAVAILABLE'); };
  await assert.rejects(prepareStudentCard(f.sign(), f.deps), /DATABASE_UNAVAILABLE/);
});
