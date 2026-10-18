// Both databases are server-owned dependencies. No context IDs are accepted from the browser.
async function resolveCardContext(claim, mappingPool, targetPool) {
  const result = await mappingPool.query(
    `SELECT school.target_school_id, staff.target_entity_id AS target_profile_id,
            student.target_entity_id AS target_student_id
     FROM card_bridge.school_map school
     JOIN card_bridge.entity_map staff USING (source_instance, native_school_id)
     JOIN card_bridge.entity_map student USING (source_instance, native_school_id)
     WHERE school.source_instance = $1 AND school.native_school_id = $2
       AND staff.entity_type = 'staff' AND staff.native_entity_id = $3
       AND student.entity_type = 'student' AND student.native_entity_id = $4`,
    [claim.sourceInstance, claim.nativeSchoolId, claim.nativeUserId, claim.input.enrollment.STUDENT_ID],
  );
  if (result.rows.length !== 1) throw new Error('NATIVE_MAPPING_REQUIRED');
  const mapping = result.rows[0];
  // Staff mapping deliberately points to a reviewed target profile, not a user ID.
  const target = await targetPool.query(
    `SELECT * FROM card_bridge_local.resolve_identity($1, $2, $3, $4)`,
    [mapping.target_profile_id, mapping.target_school_id, mapping.target_student_id,
      `${claim.sourceInstance}:${claim.nativeUserId}`],
  );
  if (target.rows.length !== 1) throw new Error('TARGET_RECORDS_REQUIRED');
  // The caller must additionally enforce Access_Law and school access status.
  return target.rows[0];
}
module.exports = { resolveCardContext };
