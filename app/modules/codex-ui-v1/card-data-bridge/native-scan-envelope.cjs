'use strict';
const {createHmac,timingSafeEqual} = require('node:crypto');
// Operator session envelope: deliberately no student chosen to resolve identity.
// Signature establishes origin only; SQL capabilities still govern every scan.
async function verifyNativeScanEnvelope(envelope, {secret,sourceInstance,consumeNonce,
  now=()=>Math.floor(Date.now()/1000)}) {
  if (!Buffer.isBuffer(secret) || secret.length<32 || typeof consumeNonce!=='function') throw new Error('SCAN_CONFIG_REQUIRED');
  if (!envelope || Object.keys(envelope).sort().join(',')!=='body,signature'
      || typeof envelope.body!=='string' || Buffer.byteLength(envelope.body)>16384
      || !/^[a-f0-9]{64}$/.test(envelope.signature||'')) throw new Error('INVALID_SCAN_ENVELOPE');
  const expected=createHmac('sha256',secret).update(envelope.body).digest();
  if (!timingSafeEqual(expected,Buffer.from(envelope.signature,'hex'))) throw new Error('INVALID_SIGNATURE');
  const claim=JSON.parse(envelope.body),time=now();
  const keys=['version','audience','sourceInstance','nativeUserId','nativeSchoolId','nativeYear',
    'operation','input','issuedAt','expiresAt','nonce'];
  if (Object.keys(claim).some(key=>!keys.includes(key)) || claim.version!==1
      || claim.audience!=='schoolsafe-scan-bridge' || claim.sourceInstance!==sourceInstance
      || !['capabilities','locations','scan','notifications','management','fees','location-management'].includes(claim.operation)
      || !claim.input || typeof claim.input!=='object' || Array.isArray(claim.input)
      || ['nativeUserId','nativeSchoolId','nativeYear'].some(key=>!/^[1-9][0-9]{0,18}$/.test(claim[key]||'') || typeof claim[key]!=='string')
      || !Number.isSafeInteger(claim.issuedAt) || !Number.isSafeInteger(claim.expiresAt)
      || claim.issuedAt>time || claim.expiresAt<=time || claim.expiresAt<=claim.issuedAt
      || claim.expiresAt-claim.issuedAt>30 || !/^[a-f0-9]{64}$/.test(claim.nonce||'')) throw new Error('INVALID_SCAN_CLAIM');
  if (!await consumeNonce(sourceInstance,claim.nonce,claim.expiresAt)) throw new Error('REPLAY_REJECTED');
  return claim;
}
module.exports={verifyNativeScanEnvelope};
