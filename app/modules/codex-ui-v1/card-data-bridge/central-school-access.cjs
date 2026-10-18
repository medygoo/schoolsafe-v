'use strict';

// Match the existing auth service: no central record does not mean suspension.
async function assertCentralSchoolAccess(authDb, schoolId) {
  if (typeof schoolId !== 'string' || !/^[a-f0-9-]{36}$/i.test(schoolId)) {
    throw new Error('INVALID_TARGET_SCHOOL');
  }
  const result = await authDb.query(
    'select api.master_school_access_status($1) as status', [schoolId]);
  if (result.rows.length !== 1 || !Object.hasOwn(result.rows[0], 'status')) {
    throw new Error('SCHOOL_ACCESS_RESULT_INVALID');
  }
  const status = result.rows[0].status;
  if (status === null || status === 'active') return true;
  if (status === 'suspended' || status === 'revoked') return false;
  throw new Error('SCHOOL_ACCESS_RESULT_INVALID');
}

module.exports = { assertCentralSchoolAccess };
