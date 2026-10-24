'use strict';
// Pure real runtime normalizer, synthetic data only; not HTTP/DB/provider proof.
const test=require('node:test'),assert=require('node:assert/strict');
const {normalizeNotifications}=require('../app/modules/codex-ui-v1/extensions-test-runtime/schoolsafe-card-bridge/parent-notification-data.js');
const at='2026-10-23 10:30:00+00';
const arrival={id:'arrival:17',student_id:'11',student_name:'Synthetic child',event_type:'security.entry',created_at:at,payload:{message:'Arrivée physique enregistrée ; pas présence en classe.',record_id:17}};
const pickup={...arrival,id:'departure:18',event_type:'student.pickup',payload:{message:'Remise confirmée à la personne réellement vérifiée.',record_id:18}};
const financial={...arrival,id:'financial-control:19',event_type:'financial.control',payload:{message:'Contrôle financier — état observé, pas un reçu.\nNet 75.00 SYN ; versé net 70.00 SYN ; plancher 67.50 SYN.',consult_id:23,delivery_status:'pending',unexpected:{qr_payload:'SYNTHETIC-NOT-FORWARDED'}}};
for(const row of [arrival,pickup,financial])test('real runtime normalizer accepts native '+row.event_type,()=>{
 const normalized=normalizeNotifications([row]);assert.equal(normalized.length,1);assert.equal(normalized[0].payload.message,row.payload.message);assert.equal(normalized[0].student_id,'11');
});
test('financial normalization never converts observation into receipt or forwards extra JSON',()=>{
 const p=normalizeNotifications([financial])[0].payload;assert.equal(p.consult_id,23);assert.equal(p.receipt_id,undefined);assert.equal(p.unexpected,undefined);assert.equal(p.balance,undefined);
});
test('native physical identifier cannot alias a different direction or record',()=>{
 for(const row of [{...arrival,id:'departure:17'},{...pickup,payload:{...pickup.payload,record_id:19}},{...arrival,student_id:'not-a-native-id'}])assert.throws(()=>normalizeNotifications([row]),/INVALID_NOTIFICATIONS/);
});
test('malformed monetary notice is rejected rather than displayed as payment',()=>{
 for(const payload of [{...financial.payload,consult_id:0},{...financial.payload,message:{amount:0}},{...financial.payload,message:'x'.repeat(8001)}])assert.throws(()=>normalizeNotifications([{...financial,payload}]),/INVALID_NOTIFICATIONS/);
});
test('mixed native timeline is normalized atomically without inventing repeated events',()=>{
 assert.equal(normalizeNotifications([arrival,pickup,financial]).length,3);assert.throws(()=>normalizeNotifications([arrival,arrival]),/INVALID_NOTIFICATIONS/);
});
test('existing isolated UUID bridge remains compatible',()=>{
 const id='04286836-0c33-4744-aec2-dfab27c15f22';const row={...arrival,id,student_id:id,event_type:'student.arrival',payload:{occurredAt:'2026-10-23T10:30:00Z'}};
 assert.equal(normalizeNotifications([row])[0].event_type,'student.arrival');
});
test('actual payment receipt remains a separate supported type',()=>{
 const row={...arrival,id:'receipt:31',event_type:'payment.receipt',payload:{receipt_id:31,message:'Reçu réel existant',status:'valid'}};
 assert.equal(normalizeNotifications([row])[0].payload.receipt_id,31);
});
