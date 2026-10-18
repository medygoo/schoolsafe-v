'use strict';
const {createRequire}=require('node:module'),{readFileSync}=require('node:fs'),{randomUUID,randomBytes,createHmac}=require('node:crypto'),assert=require('node:assert/strict');
const load=createRequire('/deps/package.json'),{Pool}=load('pg'),{registerNativeFeeRoutes}=require('./native-fee-routes.cjs');
const config=JSON.parse(readFileSync('/private/http-config.json','utf8'));
if(config.environment!=='local-isolated-test-only')throw new Error('ISOLATED_ONLY');
const pool=new Pool(config.target),mappingPool=new Pool(config.mapping);
(async()=>{
 const client=await pool.connect();
 try{
  await client.query('BEGIN');
  const profile='04286836-0c33-4744-aec2-dfab27c15f22',school='8f1c3850-6568-4a26-8312-ed582c95891b',student='23b7054e-3e9d-4141-b1ca-fbf175a94647';
  const identity=(await client.query('select * from card_bridge_local.resolve_operator($1,$2,$3)',[profile,school,'codex-addons-local:1'])).rows[0];
  const context={userId:identity.user_id,profileId:profile,schoolId:school,requestId:randomUUID()};
  await client.query('select api.set_request_context($1,$2,$3,$4)',Object.values(context));
  const harness={connect:async()=>({release(){},query:(sql,args)=>['BEGIN','COMMIT','ROLLBACK'].includes(sql.trim().toUpperCase())?Promise.resolve({rows:[]}):client.query(sql,args)})};
  process.env.CARD_HMAC_SECRET=config.cardSecretHex;
  const issuer=load('./cards-native-service.cjs').createCardsNativeService(harness),credential=await issuer.issueCardCredential(context,student);
  await client.query("select card_bridge_fees.manage(2026,'save',$1::jsonb)",[JSON.stringify({label:'TEST CODEX QR ROLLBACK',threshold:'150',tolerance:10})]);
  const models=(await client.query("select card_bridge_fees.manage(2026,'list','{}') as result")).rows[0].result;
  const model=models.find(m=>m.label==='TEST CODEX QR ROLLBACK');
  await client.query("select card_bridge_fees.manage(2026,'activate',$1::jsonb)",[JSON.stringify({id:model.id})]);
  let handler;registerNativeFeeRoutes({post:(path,options,fn)=>{handler=fn;}},{targetPool:harness,mappingPool,assertSchoolAccess:async()=>true,onFeeTestError:error=>console.error('TEST SQL DIAGNOSTIC',error.code||'',error.message),nativeVerification:{secret:Buffer.from(config.secretHex,'hex'),sourceInstance:'codex-addons-local',consumeNonce:async()=>true}});
  async function invoke(input){
   const now=Math.floor(Date.now()/1000),body=JSON.stringify({version:1,audience:'schoolsafe-scan-bridge',sourceInstance:'codex-addons-local',nativeUserId:'1',nativeSchoolId:'1',nativeYear:'2026',operation:'fees',input,issuedAt:now,expiresAt:now+30,nonce:randomBytes(32).toString('hex')});
   const reply={status:200,header(){return this;},code(code){this.status=code;return this;},send(value){return value;}};
   const output=await handler({headers:{},query:{},body:{body,signature:createHmac('sha256',Buffer.from(config.secretHex,'hex')).update(body).digest('hex')}},reply);
   assert.equal(reply.status,200,JSON.stringify(output));return output.data;
  }
  const resolve=await invoke({action:'resolve',qr_payload:credential.qr_payload});assert.equal(resolve.nativeStudentId,'2');assert.equal(resolve.modelId,model.id);
  for(const [paid,color] of [['150','blue'],['135','yellow'],['134.99','red']]){
   const result=await invoke({action:'result',qr_payload:credential.qr_payload,modelId:model.id,paid,hasData:true});assert.equal(result.color,color);
   assert.ok(!Object.keys(result).some(k=>/paid|amount|threshold|tolerance/i.test(k)));
  }
  const invalid=await invoke({action:'resolve',qr_payload:credential.qr_payload+'tamper'});assert.equal(invalid.result,'INVALID');
  console.log('PASS official native QR, mapped Rosario student, real SQL policy, three colors and no amount disclosure. Payment values and auth harness are synthetic; no PHP billing or physical camera certification. Outer rollback.');
 }finally{await client.query('ROLLBACK');client.release();}
})().catch(e=>{console.error(e.message);process.exitCode=1;}).finally(()=>Promise.all([pool.end(),mappingPool.end()]));
