'use strict';
const {test}=require('node:test');const assert=require('node:assert/strict');const vm=require('node:vm');const fs=require('node:fs');const path=require('node:path');
const {createNativeWebPushTransport:create}=require('./native-webpush-transport.cjs');
const {createNativeWebPushWorker}=require('./native-webpush-worker.cjs');
const runtime=path.resolve(__dirname,'../extensions-test-runtime');
const job=()=>({recipientBindingVersion:1,recipientProfileId:'00000000-0000-0000-0000-000000000001',nativeMappingCount:1,nativeRecipientId:1,subscriptionOwnerId:1,deliveryClaimed:true,eventType:'student.arrival',subscription:{endpoint:'https://fcm.googleapis.com/test',keys:{p256dh:'x'.repeat(87),auth:'x'.repeat(22)}}});
const options=sender=>({enabled:true,webPush:{sendNotification:sender},publicKey:'TEST',privateKey:'TEST',subject:'mailto:test@example.test'});
test('disabled transport and worker perform no storage or network work',async()=>{
  assert.deepEqual(await create({}).deliver(),{status:'disabled'});assert.equal((await createNativeWebPushWorker({}).runOnce()).processed,0);
});
test('bound transport uses existing web-push interface with generic payload only (mock)',async()=>{
  let count=0;const t=create(options(async(s,p,o)=>{count++;assert.deepEqual(JSON.parse(p),{version:1});assert.equal(o.TTL,300);}));
  assert.equal((await t.deliver(job())).status,'sent');assert.equal(count,1);
});
test('unbound, ambiguous, transferred owner, verify and unsafe endpoint fail closed before send',async()=>{
  let count=0;const t=create(options(async()=>{count++;}));
  for(const patch of [{recipientBindingVersion:0},{nativeMappingCount:2},{subscriptionOwnerId:2},{deliveryClaimed:false},{eventType:'verify'},{subscription:{...job().subscription,endpoint:'https://127.0.0.1/test'}}]) await assert.rejects(t.deliver({...job(),...patch}));assert.equal(count,0);
});
test('worker claims once and records expired delivery without leaking provider errors (mock)',async()=>{
  const statuses=[];const worker=createNativeWebPushWorker({...options(async()=>{throw {statusCode:410,message:'PRIVATE'};}),store:{claimBoundDelivery:async()=>job(),finishDelivery:async(j,s)=>statuses.push(s)}});
  assert.equal((await worker.runOnce()).status,'expired');assert.deepEqual(statuses,['expired']);
});
test('birthday uses captured recipient and generic payload without birthdate or child identity (mock)',async()=>{
  let count=0;
  const transport=create(options(async(s,p)=>{count++;assert.deepEqual(JSON.parse(p),{version:1});}));
  const birthday={...job(),eventType:'student.birthday'};
  assert.equal((await transport.deliver(birthday)).status,'sent');
  for(const patch of [{recipientBindingVersion:0},{nativeMappingCount:0},{subscriptionOwnerId:2},{deliveryClaimed:false}]) {
    await assert.rejects(transport.deliver({...birthday,...patch}),/BOUND_DELIVERY_REQUIRED/);
  }
  assert.equal(count,1);
});
test('birthday retries temporary provider failures without exposing their contents (mock)',async()=>{
  const transport=create(options(async()=>{throw {statusCode:503,message:'private provider data'};}));
  assert.deepEqual(await transport.deliver({...job(),eventType:'student.birthday'}),{status:'retry'});
});
test('service worker ignores identifying delivery body and arbitrary URLs (mock)',async()=>{
  const handlers={},seen=[];vm.runInNewContext(fs.readFileSync(path.join(runtime,'notification-push-sw.js'),'utf8'),{self:{addEventListener:(n,f)=>handlers[n]=f,registration:{showNotification:async(t,o)=>seen.push([t,o])},clients:{openWindow:async url=>seen.push(url)}}});
  await handlers.push({data:{json:()=>{throw Error('must not parse');}},waitUntil:p=>p});
  await handlers.notificationclick({notification:{close(){},data:{url:'https://evil.test'}},waitUntil:p=>p});
  assert.equal(seen[0][1].body,'Une nouvelle notification est disponible.');assert.equal(seen[1],'/parent-notifications-view.php');
});
test('subscription script does nothing until explicit click; endpoint requires native session CSRF and opt-in',()=>{
  let attached=0;vm.runInNewContext(fs.readFileSync(path.join(runtime,'schoolsafe-card-bridge/notification-push-subscribe.js'),'utf8'),{document:{getElementById:()=>({addEventListener:()=>attached++})}});assert.equal(attached,1);
  const php=fs.readFileSync(path.join(runtime,'notification-push-subscription.php'),'utf8');
  for(const pattern of [/User\('PROFILE'\)!=='parent'/,/hash_equals/,/SCHOOLSAFE_NATIVE_WEBPUSH_ENABLED/,/pg_query_params/,/8193/,/subscription.*\]/]) assert.match(php,pattern);
});
