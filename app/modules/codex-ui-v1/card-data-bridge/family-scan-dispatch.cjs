'use strict';
// Internal dispatch only: context must come from native identity resolution.
// Existing services and SQL functions remain the sole authorization authority.
const uuid = /^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i;
function identifier(value) {
  if (!uuid.test(value || '')) throw new Error('INVALID_ENTITY_ID');
  return value;
}
function reason(value) {
  if (typeof value !== 'string' || !value.trim() || value.length > 500) throw new Error('REASON_REQUIRED');
  return value.trim();
}
function exact(input, keys) {
  if (!input || typeof input !== 'object' || Array.isArray(input)
      || Object.keys(input).some(key => !keys.includes(key))) throw new Error('INVALID_OPERATION_INPUT');
}
async function dispatchFamilyScan({ context, studentId, operation, input = {}, family, scanner }) {
  if (!context || !uuid.test(context.userId || '') || !uuid.test(context.profileId || '')
      || !uuid.test(context.schoolId || '') || !context.requestId) throw new Error('RESOLVED_CONTEXT_REQUIRED');
  identifier(studentId);
  switch (operation) {
    case 'parent-list':
      exact(input, []);
      return family.parentAuthorizationList(context, studentId);
    case 'school-list':
      exact(input, []);
      return family.schoolAuthorizationList(context, studentId);
    case 'parent-suspend':
      exact(input, ['authorizationId', 'reason']);
      return family.parentSuspend(context, identifier(input.authorizationId), reason(input.reason));
    case 'parent-family-suspend':
      exact(input, ['guardianId', 'reason']);
      return family.parentFamilySuspend(context, identifier(input.guardianId), reason(input.reason));
    case 'parent-reactivation-request':
      exact(input, ['authorizationId', 'reason']);
      return family.parentReactivationRequest(context, identifier(input.authorizationId), reason(input.reason));
    case 'parent-family-reactivation-request':
      exact(input, ['guardianId', 'reason']);
      return family.parentFamilyReactivationRequest(context, identifier(input.guardianId), reason(input.reason));
    case 'scan': {
      exact(input, ['mode', 'qr_payload', 'location_id', 'authorized_person_id', 'campaign_id',
        'justification', 'note', 'human_confirmed', 'proof_token']);
      if (!['entry', 'pickup', 'fees', 'verify'].includes(input.mode)) throw new Error('INVALID_SCAN_MODE');
      if (typeof input.qr_payload !== 'string' || input.qr_payload.length > 2000
          || !/^schoolsafe:\/\/card\/[^/\s]+\/[^/\s]+$/.test(input.qr_payload)) throw new Error('OFFICIAL_QR_REQUIRED');
      for (const key of ['location_id', 'authorized_person_id', 'campaign_id']) {
        if (input[key] !== undefined) identifier(input[key]);
      }
      if (input.human_confirmed !== undefined && typeof input.human_confirmed !== 'boolean') throw new Error('INVALID_CONFIRMATION');
      if (input.human_confirmed === true && (typeof input.proof_token !== 'string'
          || input.proof_token.length < 16 || input.proof_token.length > 80)) throw new Error('PICKUP_PROOF_REQUIRED');
      for (const key of ['note', 'justification']) {
        if (input[key] !== undefined && (typeof input[key] !== 'string' || input[key].length > 500)) throw new Error('INVALID_SCAN_TEXT');
      }
      return scanner.scan(context, input);
    }
    default: throw new Error('UNSUPPORTED_FAMILY_SCAN_OPERATION');
  }
}
module.exports = { dispatchFamilyScan };
