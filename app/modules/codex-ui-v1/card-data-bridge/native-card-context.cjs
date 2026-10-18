'use strict';

const { randomUUID } = require('node:crypto');
const { verifyNativeEnvelope } = require('./native-envelope.cjs');
const { resolveCardContext } = require('./resolve-card-context.cjs');

// This boundary is for card preparation only, not issuance or scan authorization.
async function withNativeCardContext(envelope, dependencies, operation) {
  const { nativeVerification, mappingPool, targetPool, assertSchoolAccess } = dependencies;
  if (typeof assertSchoolAccess !== 'function' || typeof operation !== 'function') {
    throw new Error('CARD_BOUNDARY_CONFIGURATION_REQUIRED');
  }
  const claim = await verifyNativeEnvelope(envelope, nativeVerification);
  const client = await targetPool.connect();
  try {
    await client.query('BEGIN');
    const identity = await resolveCardContext(claim, mappingPool, client);
    // A trusted adapter must perform the existing central school-access check.
    // Returning undefined or an unavailable check never grants access.
    if (await assertSchoolAccess(identity.school_id) !== true) {
      throw new Error('SCHOOL_ACCESS_DENIED');
    }
    const context = { userId: identity.user_id, profileId: identity.profile_id,
      schoolId: identity.school_id, studentId: identity.student_id, requestId: randomUUID() };
    await client.query('select api.set_request_context($1, $2, $3, $4)',
      [context.userId, context.profileId, context.schoolId, context.requestId]);
    await client.query('select iam.require_access($1, NULL, $2)',
      ['cards.request.print', context.studentId]);
    const result = await operation({ client, context, claim });
    await client.query('COMMIT');
    return result;
  } catch (error) {
    await client.query('ROLLBACK').catch(() => {});
    throw error;
  } finally {
    client.release();
  }
}

module.exports = { withNativeCardContext };
