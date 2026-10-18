const test = require('node:test');
const assert = require('node:assert/strict');
const { checkCardIssuance } = require('./card-issuance-check.cjs');
const source = { issuanceAllowed: false, missing: ['student.photo', 'class.mapping', 'card.official_qr'],
  input: { credential: { qr_payload: '' } } };
test('missing QR is expected before issuance, missing record fields remain blockers', () => {
  const result = checkCardIssuance(source);
  assert.deepEqual(result.blockers, ['student.photo', 'class.mapping']);
  assert.equal(result.dataComplete, false);
  assert.equal(result.operationPerformed, false);
});
test('complete data does not claim an issuer or permission is connected', () => {
  const result = checkCardIssuance({ ...source, missing: ['card.official_qr'] });
  assert.equal(result.dataComplete, true);
  assert.equal(result.issuanceAllowed, false);
  assert.equal(result.issuerConnected, false);
});
test('existing QR does not authorize another issuance', () => {
  const result = checkCardIssuance({ ...source, missing: [], input: { credential: { qr_payload: 'schoolsafe://card/TEST/TEST' } } });
  assert.equal(result.hasActiveCredential, true);
  assert.equal(result.issuanceAllowed, false);
});
test('untrusted or malformed prepared response is rejected', () => {
  assert.throws(() => checkCardIssuance({ ...source, issuanceAllowed: true }), /CARD_PREFLIGHT_INPUT_INVALID/);
  assert.throws(() => checkCardIssuance(null), /CARD_PREFLIGHT_INPUT_INVALID/);
});
