'use strict';
const {test}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const {spawnSync}=require('node:child_process');
const runtime=path.resolve(__dirname,'../extensions-test-runtime');
const {normalizeNotifications:n}=require(path.join(runtime,'schoolsafe-card-bridge/parent-notification-data.js'));
const remediation=()=>({id:'remediation:1',student_id:'12',student_name:'TEST',event_type:'remediation.payment_confirmed',created_at:'2026-10-12 10:00:00',payload:{message:'TEST',course:'TEST',case_id:1}});
test('runtime preserves all fourteen remediation kinds and native SQL dates',()=>{
  for(const kind of ['detected','approved','refused','session_scheduled','session_held','session_postponed','session_cancelled','reassessment_passed','reassessment_repeat','reassessment_closed','billing_configured','payment_recorded','payment_confirmed','payment_review_required']) {
    const row=remediation();row.event_type='remediation.'+kind;assert.deepEqual(n([row]),[row]);
  }
});
test('runtime projects unknown fields away without dropping remediation',()=>{
  const row=remediation();row.internal='PRIVATE';row.payload.recipient='PRIVATE';
  const [safe]=n([row]);assert.equal(safe.internal,undefined);assert.equal(safe.payload.recipient,undefined);assert.equal(safe.payload.case_id,1);
});
test('runtime fails closed on unknown types, malformed remediation and bounded rows/strings',()=>{
  for(const patch of [{event_type:'remediation.unknown'},{student_id:'0'},{id:'remediation:0'},{payload:{message:'TEST',course:'TEST',case_id:'1'}},{student_name:'x'.repeat(251)},{created_at:'invalid'}]) assert.throws(()=>n([{...remediation(),...patch}]));
  assert.throws(()=>n(Array(201).fill(remediation())));assert.throws(()=>n([remediation(),remediation()]));
});
test('runtime preserves three scanner types alongside remediation',()=>{
  const rows=['student.arrival','student.pickup','student.fee_control'].map((event_type,i)=>({id:`00000000-0000-0000-0000-00000000000${i}`,student_id:'00000000-0000-0000-0000-000000000010',student_name:'TEST',event_type,created_at:'2026-10-12T10:00:00Z',payload:{occurredAt:'2026-10-12T10:00:00Z',...(i===2?{balanceStatus:'paid'}:{})}}));
  assert.equal(n([...rows,remediation()]).length,4);
});
test('actual runtime PHP callback accepts exact byte limit and rejects overflow before append',()=>{
  const php=fs.readFileSync(path.join(runtime,'parent-notifications.php'),'utf8');
  const match=php.match(/CURLOPT_WRITEFUNCTION=>(function \(\$handle, \$chunk\) use \(&\$response\) \{[\s\S]*?\n        \})/);assert.ok(match);
  const code='<?php $response="";$writer='+match[1]+'; if($writer(null,str_repeat("x",300000))!==300000)exit(1);if($writer(null,"x")!==0||strlen($response)!==300000)exit(2);echo "PASS bounded callback 300000/300001 bytes\\n";';
  const run=spawnSync('docker',['exec','-i','codex-addons-web','php'],{input:code,encoding:'utf8'});assert.equal(run.status,0,run.stderr);assert.match(run.stdout,/PASS bounded callback/);
});
test('runtime page loads strict normalizer before view; no foreground push substitute',()=>{
  const php=fs.readFileSync(path.join(runtime,'parent-notifications-view.php'),'utf8');
  assert.ok(php.indexOf('parent-notification-data.js')<php.indexOf('parent-notifications-view.js'));assert.doesNotMatch(php,/notifications-push/);
  const view=fs.readFileSync(path.join(runtime,'schoolsafe-card-bridge/parent-notifications-view.js'),'utf8');assert.match(view,/normalizeNotifications\(result.data.notifications\)/);
});
