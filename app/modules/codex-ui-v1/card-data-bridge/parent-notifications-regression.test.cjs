'use strict';
const test = require('node:test');
const assert = require('node:assert/strict');
const { normalizeNotifications: normalize } = require('./parent-notification-data.js');
const { createNotificationPush, createBrowserTransport } = require('./parent-notifications-push.js');
const id = '04286836-0c33-4744-aec2-dfab27c15f22';
const other = '04286836-0c33-4744-aec2-dfab27c15f23';
const at = '2026-10-12T12:00:00Z';
function item(type = 'student.arrival', eventId = id, payload = {}) {
  return { id: eventId, student_id: id, student_name: 'TEST ONLY', event_type: type,
    created_at: at, payload: { occurredAt: at, ...payload } };
}
test('three notifying types and five financial statuses normalize', () => {
  assert.equal(normalize([item()])[0].event_type, 'student.arrival');
  assert.equal(normalize([item('student.pickup')])[0].event_type, 'student.pickup');
  for (const balanceStatus of ['paid','partial','due','exempt','no_data']) {
    assert.equal(normalize([item('student.fee_control', id, { balanceStatus })])[0].payload.balanceStatus, balanceStatus);
  }
  for (const feeColor of ['blue','yellow','red']) {
    assert.equal(normalize([item('student.fee_control', id, { feeColor, feeLabel: 'TEST' })])[0].payload.feeColor, feeColor);
  }
});
test('verify, malformed payloads, duplicates and scope escape are rejected', () => {
  for (const row of [item('student.verify'), { ...item(), id: 42 },
    { ...item(), payload: [] }, { ...item(), created_at: 'invalid' },
    item('student.fee_control', id, { balanceStatus: true }),
    item('student.fee_control', id, { feeColor: 'yellow', feeLabel: {} }),
    item('student.fee_control', id, { feeColor: 'red', feeLabel: 'TEST', balanceStatus: 'paid' })]) {
    assert.throws(() => normalize([row]), /INVALID_NOTIFICATIONS/);
  }
  assert.throws(() => normalize([item(), item()]), /INVALID_NOTIFICATIONS/);
  assert.throws(() => normalize([item()], new Set([other])), /INVALID_NOTIFICATIONS/);
  assert.throws(() => normalize(Array(101).fill(item())), /INVALID_NOTIFICATIONS/);
});
test('projection removes recipient and financial details from client payload', () => {
  const row = { ...item(), recipient_profile_id: other, payload: { occurredAt: at, amount: 999 } };
  assert.deepEqual(normalize([row]), [item()]);
});
test('push disabled by default even if a transport is supplied', () => {
  let calls = 0; const push = createNotificationPush({ deliver: () => { calls++; } });
  push.observe([item()]); assert.deepEqual(push.observe([item('student.pickup', other)]), { delivered: 0, pending: 1 });
  assert.equal(calls, 0);
});
test('opt-in simulated transport suppresses initial history and repeated polls', () => {
  const calls = []; const push = createNotificationPush({ enabled: true, deliver: message => calls.push(message) });
  assert.equal(push.observe([item()]).delivered, 0);
  assert.equal(push.observe([item('student.pickup', other), item()]).delivered, 1);
  assert.equal(push.observe([item('student.pickup', other), item()]).delivered, 0);
  assert.equal(calls.length, 1); assert.ok(!JSON.stringify(calls).includes('TEST ONLY'));
  assert.equal(calls[0].url, '/parent-notifications-view.php');
  push.reset(); assert.equal(push.observe([item('student.pickup', other)]).delivered, 0);
});
test('push refuses verify and sanitizes failed simulated transport', () => {
  const push = createNotificationPush({ enabled: true, deliver: () => { throw new Error('private information'); } });
  push.observe([item()]); assert.equal(push.observe([item('student.pickup', other)]).delivered, 0);
  assert.throws(() => push.observe([item('student.verify')]), /INVALID_NOTIFICATIONS/);
});
test('failed simulated delivery is retried on the next observation', () => {
  let calls=0;
  const push=createNotificationPush({enabled:true,deliver:()=>{if(++calls===1)throw new Error('unavailable');}});
  push.observe([item()]);assert.equal(push.observe([item('student.pickup',other)]).delivered,0);
  assert.equal(push.observe([item('student.pickup',other)]).delivered,1);assert.equal(calls,2);
});
test('foreground native transport checks existing permission and reveals no child details', () => {
  assert.throws(()=>createBrowserTransport({isSecureContext:false}),/NATIVE_PUSH_UNAVAILABLE/);
  const calls=[];let notice,visited;
  function FakeNotification(title,options){calls.push({title,options});notice=this;this.close=()=>{};}
  FakeNotification.permission='default';
  const browser={isSecureContext:true,Notification:FakeNotification,focus(){},location:{assign:url=>{visited=url;}}};
  assert.throws(()=>createBrowserTransport(browser),/NATIVE_PUSH_UNAVAILABLE/);
  FakeNotification.permission='granted';const send=createBrowserTransport(browser);
  send({id,body:'PRIVATE CHILD INFORMATION',url:'https://test.invalid'});
  assert.equal(calls.length,1);assert.ok(!JSON.stringify(calls).includes('PRIVATE'));
  notice.onclick();assert.equal(visited,'/parent-notifications-view.php');
  FakeNotification.permission='denied';assert.throws(()=>send({id}),/NATIVE_PUSH_UNAVAILABLE/);
});
