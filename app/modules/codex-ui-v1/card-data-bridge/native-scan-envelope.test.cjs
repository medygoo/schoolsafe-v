const test=require('node:test');
const assert=require('node:assert/strict');
const {createHmac}=require('node:crypto');
const {verifyNativeScanEnvelope}=require('./native-scan-envelope.cjs');
const secret=Buffer.alloc(32,9);
function sign(extra={}) {
  const body=JSON.stringify({version:1,audience:'schoolsafe-scan-bridge',sourceInstance:'local',
    nativeUserId:'5',nativeSchoolId:'1',nativeYear:'2026',operation:'scan',input:{mode:'verify'},
    issuedAt:100,expiresAt:125,nonce:'a'.repeat(64),...extra});
  return {body,signature:createHmac('sha256',secret).update(body).digest('hex')};
}
function config(consumeNonce=async()=>true){return {secret,sourceInstance:'local',now:()=>110,consumeNonce};}
test('operator provenance does not require an invented student identity',async()=>{
  assert.equal((await verifyNativeScanEnvelope(sign(),config())).nativeUserId,'5');
});
test('card audience and target profile overrides are rejected',async()=>{
  for(const extra of [{audience:'schoolsafe-card-bridge'},{profileId:'target-id'}])
    await assert.rejects(verifyNativeScanEnvelope(sign(extra),config()),/INVALID_SCAN_CLAIM/);
});
test('tampering, expiry and replay are rejected',async()=>{
  await assert.rejects(verifyNativeScanEnvelope({...sign(),signature:'0'.repeat(64)},config()),/INVALID_SIGNATURE/);
  await assert.rejects(verifyNativeScanEnvelope(sign({expiresAt:105}),config()),/INVALID_SCAN_CLAIM/);
  await assert.rejects(verifyNativeScanEnvelope(sign(),config(async()=>false)),/REPLAY_REJECTED/);
});
