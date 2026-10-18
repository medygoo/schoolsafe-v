'use strict';

const { checkCardIssuance } = require('./card-issuance-check.cjs');

// Called only with a fresh server-resolved context, after the read transaction commits.
// The existing service owns the write transaction, capability check and QR signature.
async function issuePreparedNativeCard({ context, prepared, service, assertSchoolAccess }) {
  const preflight = checkCardIssuance(prepared);
  if (!preflight.dataComplete) throw new Error('CARD_DATA_INCOMPLETE');
  const reference = prepared.targetReference;
  if (!context || !reference || context.studentId !== reference.studentId
      || context.schoolId !== reference.schoolId
      || !context.userId || !context.profileId || !context.requestId) {
    throw new Error('CARD_ISSUER_CONTEXT_INVALID');
  }
  if (typeof assertSchoolAccess !== 'function'
      || await assertSchoolAccess(context.schoolId) !== true) {
    throw new Error('SCHOOL_ACCESS_DENIED');
  }
  if (typeof service?.issueCardCredential !== 'function') {
    throw new Error('NATIVE_CARD_ISSUER_REQUIRED');
  }
  return service.issueCardCredential(context, context.studentId);
}

module.exports = { issuePreparedNativeCard };
