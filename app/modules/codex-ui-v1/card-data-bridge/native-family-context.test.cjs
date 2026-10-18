const test = require('node:test');
const assert = require('node:assert/strict');
const {createHmac} = require('node:crypto');
const {readNativeParentFamily} = require('./native-family-context.cjs');
test('unsigned family access is refused before a database connection', async()=>{
  let connections=0;
  await assert.rejects(readNativeParentFamily({body:'{}',signature:'0'.repeat(64)}, {
    nativeVerification:{secret:Buffer.alloc(32,7),sourceInstance:'local',consumeNonce:async()=>true},
    targetPool:{connect:async()=>{connections++;}}
  }),/INVALID_SIGNATURE/);
  assert.equal(connections,0);
});
test('a signed card envelope cannot be reused for family consultation', async()=>{
  const secret=Buffer.alloc(32,7),now=Math.floor(Date.now()/1000);
  const body=JSON.stringify({version:1,audience:'schoolsafe-card-bridge',sourceInstance:'local',
    nativeUserId:'3',nativeSchoolId:'1',nativeYear:'2026',issuedAt:now,expiresAt:now+30,nonce:'a'.repeat(64),
    input:{authorized:true,student:{STUDENT_ID:1},enrollment:{STUDENT_ID:1,SCHOOL_ID:1,SYEAR:2026}}});
  await assert.rejects(readNativeParentFamily({body,signature:createHmac('sha256',secret).update(body).digest('hex')}, {
    nativeVerification:{secret,sourceInstance:'local',consumeNonce:async()=>true}
  }),/FAMILY_OPERATION_REQUIRED/);
});
