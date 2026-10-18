const { createHmac, timingSafeEqual } = require('node:crypto');

// Dependencies are trusted server configuration, never request parameters.
async function verifyNativeEnvelope(envelope, { secret, sourceInstance, consumeNonce, now = () => Math.floor(Date.now() / 1000) }) {
  if (!Buffer.isBuffer(secret) || secret.length < 32 || typeof consumeNonce !== 'function') {
    throw new Error('BRIDGE_CONFIGURATION_REQUIRED');
  }
  if (!envelope || typeof envelope.body !== 'string' || Buffer.byteLength(envelope.body) > 262144
      || !/^[a-f0-9]{64}$/.test(envelope.signature || '')) throw new Error('INVALID_ENVELOPE');
  const expected = createHmac('sha256', secret).update(envelope.body).digest();
  if (!timingSafeEqual(expected, Buffer.from(envelope.signature, 'hex'))) throw new Error('INVALID_SIGNATURE');
  const claim = JSON.parse(envelope.body);
  const time = now();
  const positiveId = value => typeof value === 'string' && /^[1-9][0-9]{0,18}$/.test(value);
  if (claim.version !== 1 || claim.audience !== 'schoolsafe-card-bridge' || claim.sourceInstance !== sourceInstance
      || !positiveId(claim.nativeUserId) || !positiveId(claim.nativeSchoolId) || !positiveId(claim.nativeYear)
      || !Number.isSafeInteger(claim.issuedAt) || !Number.isSafeInteger(claim.expiresAt)
      || claim.issuedAt > time || claim.expiresAt <= time || claim.expiresAt <= claim.issuedAt
      || claim.expiresAt - claim.issuedAt > 30 || !/^[a-f0-9]{64}$/.test(claim.nonce || '')) {
    throw new Error('INVALID_CLAIM');
  }
  const enrollment = claim.input?.enrollment;
  if (claim.input?.authorized !== true || String(enrollment?.SCHOOL_ID) !== claim.nativeSchoolId
      || String(enrollment?.SYEAR) !== claim.nativeYear
      || !positiveId(String(enrollment?.STUDENT_ID))
      || String(claim.input?.student?.STUDENT_ID) !== String(enrollment.STUDENT_ID)) {
    throw new Error('INVALID_NATIVE_SCOPE');
  }
  if (!await consumeNonce(sourceInstance, claim.nonce, claim.expiresAt)) throw new Error('REPLAY_REJECTED');
  // This proves native provenance only. Target profile, permissions and records still need resolution.
  return claim;
}
module.exports = { verifyNativeEnvelope };
