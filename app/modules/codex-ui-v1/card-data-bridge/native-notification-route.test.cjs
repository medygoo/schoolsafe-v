'use strict';
const test=require('node:test');
const assert=require('node:assert/strict');
const {createHmac,randomBytes}=require('node:crypto');
const {registerNativeNotificationRoute}=require('./native-notification-route.cjs');
const id='04286836-0c33-4744-aec2-dfab27c15f22';
function fixture(notifications=[]) {
 const secret=randomBytes(32),used=new Set(),queries=[];let handler;
 const client={query:async(sql)=>{
  queries.push(sql);
  if(sql.includes('resolve_operator'))return {rows:[{user_id:id,profile_id:id,school_id:id}]};
  if(sql.includes('principal_scope'))return {rows:[{ids:[id]}]};
  if(sql.includes('read_own'))return {rows:[{notifications}]};
  return {rows:[]};
 },release(){}};
 registerNativeNotificationRoute({post(_url,_options,callback){handler=callback;}},{
  nativeVerification:{secret,sourceInstance:'codex-addons-local',consumeNonce:async(_source,nonce)=>{
   if(used.has(nonce))return false;used.add(nonce);return true;
  }},targetPool:{connect:async()=>client},assertSchoolAccess:async()=>true,
  mappingPool:{query:async(sql)=>({rows:sql.includes('school_map')
   ?[{target_school_id:id,profile_id:id}]:[{native_entity_id:'1',target_entity_id:id}]})}
 });
 function envelope(input={nativeStudentIds:['1']}) {
  const now=Math.floor(Date.now()/1000),body=JSON.stringify({version:1,audience:'schoolsafe-scan-bridge',
   sourceInstance:'codex-addons-local',nativeUserId:'3',nativeSchoolId:'1',nativeYear:'2026',
   operation:'notifications',input,issuedAt:now,expiresAt:now+30,nonce:randomBytes(32).toString('hex')});
  return {body,signature:createHmac('sha256',secret).update(body).digest('hex')};
 }
 async function send(body,headers={}) {
  const reply={status:200,header(){return this;},code(value){this.status=value;return this;},send(value){return value;}};
  return {data:await handler({body,headers,query:{}},reply),status:reply.status};
 }
 return {envelope,send,queries};
}
test('native notification read remains scoped and read-only with an empty inbox',async()=>{
 const f=fixture();const r=await f.send(f.envelope());assert.equal(r.status,200);
 assert.deepEqual(r.data.data,{notifications:[],principalStudentIds:['1']});
 assert.ok(f.queries.some(sql=>sql.includes('principal_scope')));
 assert.ok(!f.queries.some(sql=>/collect\(|insert|update|delete/i.test(sql)));
});
test('origins, duplicate scope and target overrides are refused',async()=>{
 const f=fixture();assert.equal((await f.send(f.envelope(),{origin:'https://test.invalid'})).status,400);
 for(const input of [{nativeStudentIds:['1','1']},{nativeStudentIds:['1'],profileId:id}]) {
  assert.equal((await f.send(f.envelope(input))).status,400);
 }
});
test('signature and replay rejection remain enforced',async()=>{
 const f=fixture(),e=f.envelope();assert.equal((await f.send({...e,signature:'0'.repeat(64)})).status,401);
 assert.equal((await f.send(e)).status,200);assert.equal((await f.send(e)).status,401);
});
test('malformed or cross-student SQL results rollback without leaking diagnostics',async()=>{
 const row={id,student_id:id,student_name:'TEST ONLY',event_type:'student.arrival',
  created_at:'2026-10-12T12:00:00Z',payload:{occurredAt:'2026-10-12T12:00:00Z'}};
 for(const bad of [{...row,event_type:'student.verify'},{...row,student_id:'04286836-0c33-4744-aec2-dfab27c15f23'}]) {
  const f=fixture([bad]),r=await f.send(f.envelope());assert.equal(r.status,503);
  assert.deepEqual(r.data,{error:'NOTIFICATIONS_UNAVAILABLE'});assert.ok(f.queries.includes('ROLLBACK'));
 }
});
