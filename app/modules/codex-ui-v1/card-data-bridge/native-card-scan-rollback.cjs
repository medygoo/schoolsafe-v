'use strict';
const {createRequire}=require('node:module'),{readFileSync}=require('node:fs'),{randomUUID}=require('node:crypto'),assert=require('node:assert/strict');
const load=createRequire('/deps/package.json'),{Pool}=load('pg');
const config=JSON.parse(readFileSync('/private/http-config.json','utf8'));
if(config.environment!=='local-isolated-test-only'||config.target.database!=='schoolsafe_cards_isolated')throw new Error('ISOLATED_ONLY');
const pool=new Pool(config.target);
(async()=>{
 const client=await pool.connect();
 try{
  await client.query('BEGIN');
  const profile='04286836-0c33-4744-aec2-dfab27c15f22',school='8f1c3850-6568-4a26-8312-ed582c95891b',student='23b7054e-3e9d-4141-b1ca-fbf175a94647';
  const identity=await client.query('select * from card_bridge_local.resolve_operator($1,$2,$3)',[profile,school,'codex-addons-local:1']);
  assert.equal(identity.rows.length,1);
  const context={userId:identity.rows[0].user_id,profileId:profile,schoolId:school,requestId:randomUUID()};
  // Test harness alone retains the outer transaction across native service calls.
  const harness={connect:async()=>({release(){},query:(sql,args)=>['BEGIN','COMMIT','ROLLBACK'].includes(sql.trim().toUpperCase())?Promise.resolve({rows:[]}):client.query(sql,args)})};
  process.env.CARD_HMAC_SECRET=config.cardSecretHex;
  const issuer=load('./cards-native-service.cjs').createCardsNativeService(harness);
  const scanner=load('./scan-control-service.cjs').createScanControlService(harness,{cards:issuer});
  const credential=await issuer.issueCardCredential(context,student);
  assert.ok(credential.qr_payload);
  const verified=await scanner.scan(context,{mode:'verify',qr_payload:credential.qr_payload});
  assert.equal(verified.result,'VALID');assert.equal(verified.signature_valid,true);assert.equal(verified.student_id,student);
  const invalid=await scanner.scan(context,{mode:'verify',qr_payload:credential.qr_payload+'tampered'});
  assert.equal(invalid.result,'INVALID');assert.equal(invalid.signature_valid,false);
  const print=await issuer.createPrintRequest(context,{student_id:student,format:'badge',metadata:{source:'TEST CODEX ROLLBACK'}});
  assert.ok(print.id);assert.ok(print.version>0);
  console.log('PASS real native issuer -> official QR -> native scanner VALID, tampering INVALID and native print request. Outer transaction rolled back; not a physical camera or printer test.');
 } finally {await client.query('ROLLBACK');client.release();}
})().catch(e=>{console.error(e.message);process.exitCode=1;}).finally(()=>pool.end());
