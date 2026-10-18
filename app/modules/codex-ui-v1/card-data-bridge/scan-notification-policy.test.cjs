'use strict';
const test = require('node:test');
const assert = require('node:assert/strict');
const { planScanNotification: plan } = require('./scan-notification-policy.cjs');
const id = '04286836-0c33-4744-aec2-dfab27c15f22';
const event = { id, schoolId: id, studentId: id, committed: true, signatureValid: true,
  mode: 'entry', result: 'PRESENT', decision: 'allowed', recipientProfileId: id, recipientBindingVersion: 1 };
const parents = [{ schoolId: id, studentId: id, profileId: id, isPrimary: true, active: true }];
test('three notification types and all financial statuses', () => {
  assert.equal(plan(event, parents).type, 'student.arrival');
  assert.equal(plan({ ...event, mode: 'pickup', result: 'CONFIRMED' }, parents).type, 'student.pickup');
  for (const balanceStatus of ['paid', 'partial', 'due', 'exempt', 'no_data']) {
    assert.equal(plan({ ...event, mode: 'fees', result: 'FEE_STATUS', balanceStatus }, parents).payload.balanceStatus, balanceStatus);
  }
});
test('no notification before commit or for invalid signatures', () => {
  assert.equal(plan({ ...event, committed: false }, parents), null);
  assert.equal(plan({ ...event, signatureValid: false }, parents), null);
});
test('prepared, refused and verification scans do not notify success', () => {
  for (const result of ['NEED_HUMAN_CONFIRMATION', 'PICKUP_CHOICE', 'DENIED']) {
    assert.equal(plan({ ...event, mode: 'pickup', result }, parents), null);
  }
  assert.equal(plan({ ...event, mode: 'verify' }, parents), null);
});
test('recipient is primary parent regardless of pickup authorization', () => {
  assert.equal(plan(event, [{ ...parents[0], isAuthorizedPickup: false }]).recipientProfileId, id);
  assert.throws(() => plan(event, [{ ...parents[0], isPrimary: false }]), /PRIMARY_PARENT_REQUIRED/);
  assert.throws(() => plan(event, [{ ...parents[0], schoolId: 'wrong' }]), /PRIMARY_PARENT_REQUIRED/);
});
test('stable deduplication key, without claiming durable delivery', () => {
  assert.equal(plan(event, parents).deduplicationKey, plan(event, parents).deduplicationKey);
});
test('current principal cannot replace the event-time recipient', () => {
  const other='04286836-0c33-4744-aec2-dfab27c15f23';
  assert.throws(()=>plan({...event,recipientProfileId:other},parents),/EVENT_RECIPIENT_BINDING_REQUIRED/);
  assert.throws(()=>plan({...event,recipientBindingVersion:undefined},parents),/EVENT_RECIPIENT_BINDING_REQUIRED/);
  assert.equal(plan({...event,mode:'fees',result:'FEE_STATUS',balanceStatus:'paid',decision:'denied'},parents),null);
});
