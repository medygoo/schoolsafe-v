'use strict';
const {readFileSync}=require('node:fs');
const {createHmac,randomBytes}=require('node:crypto');
const assert=require('node:assert/strict');
const config=JSON.parse(readFileSync('/private/http-config.json','utf8'));
if(config.environment!=='local-isolated-test-only') throw new Error('ISOLATED_ONLY');
function sign(operation,user='1') {
  const now=Math.floor(Date.now()/1000);
  const body=JSON.stringify({version:1,audience:'schoolsafe-scan-bridge',sourceInstance:'codex-addons-local',
    nativeUserId:user,nativeSchoolId:'1',nativeYear:'2026',operation,input:{},issuedAt:now,expiresAt:now+30,
    nonce:randomBytes(32).toString('hex')});
  return {body,signature:createHmac('sha256',Buffer.from(config.secretHex,'hex')).update(body).digest('hex')};
}
async function send(operation,envelope) {
  return fetch('http://127.0.0.1:8080/bridge/scanner/'+operation,{method:'POST',
    headers:{'Content-Type':'application/json'},body:JSON.stringify(envelope)});
}
(async()=>{
  const envelope=sign('capabilities');
  const response=await send('capabilities',envelope);
  const result=await response.json();
  assert.equal(response.status,200,JSON.stringify(result));
  assert.equal(result.data.schoolId,'8f1c3850-6568-4a26-8312-ed582c95891b');
  assert.equal((await send('capabilities',envelope)).status,401);
  assert.equal((await send('capabilities',sign('capabilities','999999'))).status,403);
  const locations=await send('locations',sign('locations'));
  assert.equal(locations.status,200,await locations.text());
  console.log('PASS real SQL operator context, capabilities, locations, replay and unmapped identity refusal. Synthetic provenance fixture; no browser login or scan event.');
})().catch(error=>{console.error(error.message);process.exitCode=1;});
