'use strict';

// Authorization belongs to the native session boundary, not to this registry.
async function ensureStudentMatricule(db, { sourceInstance, nativeSchoolId, nativeStudentId, existingMatricule = null }) {
  if (typeof sourceInstance !== 'string' || !sourceInstance.trim()) throw new TypeError('Invalid source');
  for (const id of [nativeSchoolId, nativeStudentId]) {
    if (!/^[1-9][0-9]*$/.test(String(id))) throw new TypeError('Invalid native identity');
  }
  if (existingMatricule !== null && (typeof existingMatricule !== 'string' || !existingMatricule.trim() || existingMatricule !== existingMatricule.trim())) {
    throw new TypeError('Invalid existing matricule');
  }
  const result = await db.query(
    'SELECT card_bridge.ensure_student_matricule($1::text, $2::bigint, $3::bigint, $4::text) AS matricule',
    [sourceInstance, String(nativeSchoolId), String(nativeStudentId), existingMatricule]
  );
  if (!result.rows?.[0]?.matricule) throw new Error('Missing matricule registry response');
  return result.rows[0].matricule;
}

module.exports = { ensureStudentMatricule };
