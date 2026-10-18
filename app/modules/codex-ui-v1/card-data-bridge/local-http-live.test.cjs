const { readFileSync } = require('node:fs');
const { createHmac, randomBytes } = require('node:crypto');
const assert = require('node:assert/strict');
const config = JSON.parse(readFileSync('/private/http-config.json', 'utf8'));
const secret = Buffer.from(config.secretHex, 'hex');
function sign(user = '1', operation) {
  const now = Math.floor(Date.now() / 1000);
  const body = JSON.stringify({ version: 1, audience: 'schoolsafe-card-bridge', sourceInstance: 'codex-addons-local',
    nativeUserId: user, nativeSchoolId: '1', nativeYear: '2026', issuedAt: now, expiresAt: now + 30, operation,
    nonce: randomBytes(32).toString('hex'), input: { authorized: true,
      student: { STUDENT_ID: '1', FIRST_NAME: 'TEST', LAST_NAME: 'FIXTURE' },
      school: { ID: '1', SYEAR: '2026', TITLE: 'TEST FIXTURE' },
      enrollment: { STUDENT_ID: '1', SCHOOL_ID: '1', SYEAR: '2026' }, verified: { matricule: 'SCH0023-000001' } } });
  return { body, signature: createHmac('sha256', secret).update(body).digest('hex') };
}
async function send(envelope, resource = 'prepare') {
  return fetch('http://127.0.0.1:8080/bridge/cards/' + resource, { method: 'POST',
    headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(envelope), signal: AbortSignal.timeout(12000) });
}
(async () => {
  const envelope = sign();
  let response = await send(envelope);
  const result = await response.json();
  assert.equal(response.status, 200, JSON.stringify(result));
  assert.equal(result.data.issuanceAllowed, false);
  assert.ok(result.data.missing.includes('card.official_qr'));
  assert.equal((await send(envelope)).status, 401);
  assert.equal((await send(sign('3'))).status, 403);
  assert.equal((await send({ ...sign(), signature: '0'.repeat(64) })).status, 401);
  const metadataResponse = await send(sign(), 'metadata');
  const metadata = await metadataResponse.json();
  assert.equal(metadataResponse.status, 200, JSON.stringify(metadata));
  assert.equal(metadata.data.studentId, '46141a61-9270-4164-bce4-655ac96a5dd1');
  assert.equal(metadata.data.credential.card_number, null);
  assert.equal(metadata.data.credential.signature, null);
  assert.equal((await send(sign('3'), 'metadata')).status, 403);
  const studioResponse = await send(sign(), 'studio-input');
  const studio = await studioResponse.json();
  assert.equal(studioResponse.status, 200, JSON.stringify(studio));
  assert.equal(studio.data.input.record.id, metadata.data.studentId);
  assert.equal(studio.data.nativeReference.studentId, '1');
  assert.equal(studio.data.input.credential.qr_payload, '');
  assert.equal(studio.data.issuanceAllowed, false);
  assert.equal((await send(sign(), 'issue')).status, 400);
  const incompleteIssue = await send(sign('1', 'issue-card'), 'issue');
  assert.equal(incompleteIssue.status, 409);
  assert.equal((await incompleteIssue.json()).operationPerformed, false);
  assert.equal((await send(sign('3', 'issue-card'), 'issue')).status, 403);
  const unchanged = await (await send(sign(), 'metadata')).json();
  assert.equal(unchanged.data.credential.card_number, null);
  console.log('PASS live TCP + real SQL: preparation, replay denied, parent denied, bad signature denied. Native envelope is a synthetic test fixture; browser authentication not tested.');
})().catch(error => { console.error(error.message); process.exitCode = 1; });
