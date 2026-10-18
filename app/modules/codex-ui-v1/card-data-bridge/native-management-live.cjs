'use strict';
const {readFileSync}=require('node:fs'),{createHmac,randomBytes}=require('node:crypto'),assert=require('node:assert/strict');
const config=JSON.parse(readFileSync('/private/http-config.json','utf8'));
if(config.environment!=='local-isolated-test-only')throw new Error('ISOLATED_ONLY');
function sign(input,user='3'){
 const now=Math.floor(Date.now()/1000),body=JSON.stringify({version:1,audience:'schoolsafe-scan-bridge',sourceInstance:'codex-addons-local',nativeUserId:user,nativeSchoolId:'1',nativeYear:'2026',operation:'management',input,issuedAt:now,expiresAt:now+30,nonce:randomBytes(32).toString('hex')});
 return {body,signature:createHmac('sha256',Buffer.from(config.secretHex,'hex')).update(body).digest('hex')};
}
function send(body){return fetch('http://127.0.0.1:8080/bridge/scanner/management',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)});}
(async()=>{
 const e=sign({action:'parent-list',nativeStudentId:'1'}),response=await send(e),data=await response.json();
 assert.equal(response.status,200,JSON.stringify(data));assert.ok(Array.isArray(data.data.family));
 assert.equal((await send(e)).status,401);
 assert.equal((await send(sign({action:'parent-list',nativeStudentId:'2'}))).status,403);
 assert.equal((await send(sign({action:'parent-list',nativeStudentId:'1',schoolId:'override'}))).status,400);
 assert.equal((await send(sign({action:'grants'}))).status,403);
 const adminFamily=await send(sign({action:'school-list',nativeStudentId:'1'},'1'));
 assert.equal(adminFamily.status,200);assert.ok(Array.isArray((await adminFamily.json()).data.family));
 const adminGrants=await send(sign({action:'grants'},'1'));
 assert.equal(adminGrants.status,200);
 const primary='f91ea2ab-4350-490d-94c0-b26a717bb2dd';
 const retire={action:'school-retire',nativeStudentId:'1',guardianId:primary,reason:'TEST REFUS UNIQUEMENT',consent:'TEST REFUS UNIQUEMENT'};
 assert.equal((await send(sign(retire))).status,403);
 assert.equal((await send(sign(retire,'1'))).status,400);
 assert.equal((await send(sign({action:'parent-activate',nativeStudentId:'2',guardianId:primary,reason:'TEST REFUS'}))).status,403);
 console.log('PASS real SQL principal-family read, unrelated student refused, replay/context override refused and parent delegation-management refused. No family or permission write performed.');
})().catch(error=>{console.error(error.message);process.exitCode=1;});
