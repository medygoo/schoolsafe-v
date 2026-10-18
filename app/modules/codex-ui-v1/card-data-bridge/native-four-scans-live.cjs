'use strict';
const {readFileSync}=require('node:fs'),{createHmac,randomBytes}=require('node:crypto'),assert=require('node:assert/strict');
const config=JSON.parse(readFileSync('/private/http-config.json','utf8'));
if(config.environment!=='local-isolated-test-only')throw new Error('ISOLATED_ONLY');
function envelope(input,user='1') {
 const now=Math.floor(Date.now()/1000),body=JSON.stringify({version:1,audience:'schoolsafe-scan-bridge',sourceInstance:'codex-addons-local',
 nativeUserId:user,nativeSchoolId:'1',nativeYear:'2026',operation:'scan',input,issuedAt:now,expiresAt:now+30,nonce:randomBytes(32).toString('hex')});
 return {body,signature:createHmac('sha256',Buffer.from(config.secretHex,'hex')).update(body).digest('hex')};
}
function send(e){return fetch('http://127.0.0.1:8080/bridge/scanner/scan',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(e)});}
(async()=>{
 for(const mode of ['entry','pickup','fees','verify']) {
  const e=envelope({mode,qr_payload:'schoolsafe://card/TEST-AUDIT-NOT-A-CARD/invalid-signature'});
  const r=await send(e),body=await r.json();assert.equal(r.status,200,JSON.stringify(body));
  assert.equal(body.data.result,'INVALID');assert.equal(body.data.signature_valid,false);
  assert.equal((await send(e)).status,401);
 }
 assert.equal((await send(envelope({mode:'pickup',qr_payload:'schoolsafe://card/TEST/invalid',human_confirmed:true}))).status,400);
 assert.equal((await send(envelope({mode:'verify',qr_payload:'schoolsafe://card/TEST/invalid'},'999999'))).status,403);
 console.log('PASS four real engine rejection paths, replay, missing pickup proof and unmapped identity. Invalid QR test only; refusal audit notes may be recorded locally, no arrival or pickup.');
})().catch(e=>{console.error(e.message);process.exitCode=1;});
