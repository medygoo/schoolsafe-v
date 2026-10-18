'use strict';
const { readFileSync } = require('node:fs');
const { createHmac, randomBytes } = require('node:crypto');
const assert = require('node:assert/strict');
const config = JSON.parse(readFileSync('/private/http-config.json','utf8'));
if (config.environment !== 'local-isolated-test-only') throw new Error('ISOLATED_ONLY');
function sign(input, user = '3') {
  const now = Math.floor(Date.now()/1000);
  const body = JSON.stringify({version:1,audience:'schoolsafe-scan-bridge',sourceInstance:'codex-addons-local',
    nativeUserId:user,nativeSchoolId:'1',nativeYear:'2026',operation:'notifications',input,
    issuedAt:now,expiresAt:now+30,nonce:randomBytes(32).toString('hex')});
  return {body,signature:createHmac('sha256',Buffer.from(config.secretHex,'hex')).update(body).digest('hex')};
}
async function send(envelope) {
  return fetch('http://127.0.0.1:8080/bridge/scanner/notifications', {method:'POST',
    headers:{'Content-Type':'application/json'},body:JSON.stringify(envelope)});
}
(async()=>{
  const envelope=sign({nativeStudentIds:['1']});
  const response=await send(envelope);
  const result=await response.json();
  assert.equal(response.status,200,JSON.stringify(result));
  assert.deepEqual(result.data.notifications,[]);
  assert.deepEqual(result.data.principalStudentIds,['1']);
  const otherChild=await send(sign({nativeStudentIds:['2']}));
  assert.equal(otherChild.status,200);
  assert.deepEqual((await otherChild.json()).data.principalStudentIds,[]);
  assert.equal((await send(envelope)).status,401);
  assert.equal((await send(sign({nativeStudentIds:['1'],profileId:'override'}))).status,400);
  assert.equal((await send(sign({nativeStudentIds:['1']},'999999'))).status,403);
  console.log('PASS isolated SQL parent read, replay rejection, context override rejection, unmapped user rejection. Empty inbox only; synthetic signed fixture, not a browser login.');
})().catch(error=>{console.error(error.message);process.exitCode=1;});
