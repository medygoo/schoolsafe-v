'use strict';
const { randomUUID } = require('node:crypto');
const { verifyNativeEnvelope } = require('./native-envelope.cjs');
const { resolveCardContext } = require('./resolve-card-context.cjs');

// Family access never relies on cards.request.print. The native family RPC
// enforces principal-parent ownership using the resolved profile in SQL.
async function readNativeParentFamily(envelope, dependencies) {
  const claim = await verifyNativeEnvelope(envelope, dependencies.nativeVerification);
  if (claim.operation !== 'parent-family-read') throw new Error('FAMILY_OPERATION_REQUIRED');
  const client = await dependencies.targetPool.connect();
  try {
    await client.query('BEGIN');
    const identity = await resolveCardContext(claim, dependencies.mappingPool, client);
    if (await dependencies.assertSchoolAccess(identity.school_id) !== true) throw new Error('SCHOOL_ACCESS_DENIED');
    await client.query('select api.set_request_context($1,$2,$3,$4)',
      [identity.user_id,identity.profile_id,identity.school_id,randomUUID()]);
    const result = await client.query('select api.pickup_parent_authorization_list($1) as result', [identity.student_id]);
    if (result.rows.length !== 1 || !result.rows[0].result) throw new Error('FAMILY_RESULT_INVALID');
    await client.query('COMMIT');
    return result.rows[0].result;
  } catch(error) {
    await client.query('ROLLBACK').catch(()=>{}); throw error;
  } finally { client.release(); }
}
module.exports = { readNativeParentFamily };
