'use strict';
const uuid = /^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i;
// Call only with a persisted event read after the business transaction commits.
function planScanNotification(event, primaryParents) {
  if (!event || event.committed !== true || event.signatureValid !== true
      || !['id', 'schoolId', 'studentId'].every(key => uuid.test(event[key] || ''))) return null;
  if (!Array.isArray(primaryParents)) throw new Error('PRIMARY_PARENT_REQUIRED');
  const parents = primaryParents.filter(parent => parent.schoolId === event.schoolId
    && parent.studentId === event.studentId && parent.isPrimary === true
    && parent.active === true && uuid.test(parent.profileId || ''));
  const ids = [...new Set(parents.map(parent => parent.profileId))];
  if (ids.length !== 1) throw new Error('PRIMARY_PARENT_REQUIRED');
  // A caller must provide the snapshot captured in the event transaction.
  // Today's principal list can validate that snapshot, never replace it.
  if (!uuid.test(event.recipientProfileId || '') || event.recipientBindingVersion !== 1
      || ids[0] !== event.recipientProfileId) throw new Error('EVENT_RECIPIENT_BINDING_REQUIRED');
  let type;
  const payload = {};
  if (event.mode === 'entry' && event.result === 'PRESENT' && event.decision === 'allowed') type = 'student.arrival';
  if (event.mode === 'pickup' && event.result === 'CONFIRMED' && event.decision === 'allowed') type = 'student.pickup';
  if (event.mode === 'fees' && event.result === 'FEE_STATUS' && event.decision === 'allowed'
      && ['paid', 'partial', 'due', 'exempt', 'no_data'].includes(event.balanceStatus)) {
    type = 'student.fee_control';
    payload.balanceStatus = event.balanceStatus;
  }
  if (!type) return null;
  return { type, schoolId: event.schoolId, studentId: event.studentId,
    recipientProfileId: ids[0], eventId: event.id,
    deduplicationKey: `${event.id}:${type}`, payload };
}
module.exports = { planScanNotification };
