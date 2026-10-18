'use strict';
const test=require('node:test');
const assert=require('node:assert/strict');
const {startNotificationCollector}=require('./notification-collector.cjs');
test('readiness fingerprint matches the prepared zero collector body',()=>{
 const {readFileSync}=require('node:fs');
 const {safeCollectorBody}=require('./notification-collector.cjs');
 const sql=readFileSync(require('node:path').join(__dirname,'local-notification-event-binding.sql'),'utf8');
 const body=sql.split('create or replace function card_bridge_notifications.collect() returns integer')[1]
  .split('as $$')[1].split('$$;')[0];
 assert.equal(body.replace(/\s/g,''),safeCollectorBody.replace(/\s/g,''));
});
test('immediate collection and graceful stop',async()=>{
 const states=[];
 let calls=0;
 const stop=startNotificationCollector({query:async sql=>{
  calls++;if(calls===1)assert.ok(!/^select card_bridge_notifications\.collect\(/.test(sql));
  return {rows:[calls===1?{binding_table:true,binding_triggers:3,safe_collector:true}:{collected:0}]};
 }},{onState:s=>states.push(s)});
 await stop();assert.deepEqual(states,[{status:'ready',collected:0}]);assert.equal(calls,2);
});
test('errors are sanitized and do not crash service',async()=>{
 const states=[];
 const stop=startNotificationCollector({query:async()=>{throw new Error('private database information');}},{onState:s=>states.push(s)});
 await stop();assert.deepEqual(states,[{status:'unavailable'}]);
});
test('no overlap and stop waits for running collection',async()=>{
 let resolve,calls=0;
 const stop=startNotificationCollector({query:()=>{calls++;return new Promise(r=>{resolve=r;});}},{intervalMs:5});
 await new Promise(r=>setTimeout(r,25));assert.equal(calls,1);
 resolve({rows:[{binding_table:false}]});await stop();
 await new Promise(r=>setTimeout(r,15));assert.equal(calls,1);
});
test('unreviewed or incomplete binding never invokes legacy collection',async()=>{
 for(const row of [{binding_table:false},{binding_table:true,binding_triggers:2},
  {binding_table:true,binding_triggers:3,safe_collector:false}]) {
  const states=[];let calls=0;
  const stop=startNotificationCollector({query:async sql=>{
   calls++;assert.ok(!/^select card_bridge_notifications\.collect\(/.test(sql));return {rows:[row]};
  }},{onState:s=>states.push(s)});
  await stop();assert.equal(calls,1);assert.deepEqual(states,[{status:'awaiting_binding_review',collected:0}]);
 }
});
test('inactive binding and invalid interval fail closed',async()=>{
 let calls=0;const states=[];
 const stop=startNotificationCollector({query:async()=>{
  if(++calls===1)return {rows:[{binding_table:true,binding_triggers:3,safe_collector:true}]};
  throw new Error('NOTIFICATION_BINDING_INACTIVE');
 }},{onState:s=>states.push(s)});
 await stop();assert.deepEqual(states,[{status:'unavailable'}]);
 assert.throws(()=>startNotificationCollector({}, {intervalMs:0}),/INVALID_INTERVAL/);
});
