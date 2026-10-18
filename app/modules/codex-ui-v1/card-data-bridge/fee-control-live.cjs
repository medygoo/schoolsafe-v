'use strict';
const {readFileSync}=require('node:fs'),{createHmac,randomBytes}=require('node:crypto'),assert=require('node:assert/strict');
const config=JSON.parse(readFileSync('/private/http-config.json','utf8'));
if(config.environment!=='local-isolated-test-only')throw new Error('ISOLATED_ONLY');
function sign(input,user='1'){
 const now=Math.floor(Date.now()/1000),body=JSON.stringify({version:1,audience:'schoolsafe-scan-bridge',sourceInstance:'codex-addons-local',nativeUserId:user,nativeSchoolId:'1',nativeYear:'2026',operation:'fees',input,issuedAt:now,expiresAt:now+30,nonce:randomBytes(32).toString('hex')});
 return {body,signature:createHmac('sha256',Buffer.from(config.secretHex,'hex')).update(body).digest('hex')};
}
const send=body=>fetch('http://127.0.0.1:8080/bridge/scanner/fees',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)});
(async()=>{
 const envelope=sign({action:'list'}),response=await send(envelope);
 assert.equal(response.status,200);assert.ok(Array.isArray((await response.json()).data));
 assert.equal((await send(sign({action:'list'},'3'))).status,403);
 assert.equal((await send(sign({action:'list',schoolId:'override'}))).status,400);
 assert.notEqual((await send(envelope)).status,200);
 const empty=await send(sign({action:'resolve',qr_payload:'TEST INVALID QR'}));
 assert.equal(empty.status,200);const result=await empty.json();assert.equal(result.data.color,'yellow');
 assert.equal(result.data.reason,'no_active_policy');assert.ok(!JSON.stringify(result).includes('amount_paid'));
 console.log('PASS real policy read, parent denied, override and replay rejected, inactive control yellow without amounts. No policy activation or payment write.');
})().catch(error=>{console.error(error.message);process.exitCode=1;});
