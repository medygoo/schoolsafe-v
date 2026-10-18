'use strict';

async function resolveStudioSchooling(claim, metadata, mappingPool, client) {
  const source = claim.input.schoolingSource;
  if (!source?.scheduleAccess || !Array.isArray(source.courseSections) || !source.courseSections.length) return claim.input;
  const { rows } = await client.query('select card_bridge_local.student_schooling($1) as result', [metadata.studentId]);
  const schooling = rows[0]?.result;
  if (!schooling) return claim.input;
  const matches = [];
  for (const section of source.courseSections) {
    const mapped = await mappingPool.query(`SELECT entity_type,native_entity_id,target_entity_id
      FROM card_bridge.entity_map WHERE source_instance=$1 AND native_school_id=$2
      AND ((entity_type='class' AND native_entity_id=$3)
        OR (entity_type='academic_year' AND native_entity_id=$4)
        OR (entity_type='staff' AND native_entity_id=$5))`,
    [claim.sourceInstance, claim.nativeSchoolId, section.id, claim.input.enrollment.SYEAR, section.teacherId]);
    const find = type => mapped.rows.find(row => row.entity_type === type)?.target_entity_id;
    const classId = find('class');
    if (!classId || classId !== schooling.classId || find('academic_year') !== schooling.yearId) continue;
    const classRow = metadata.classVisuals.find(row => row.id === classId && row.is_active === true && row.academic_year_id === schooling.yearId);
    if (!classRow) continue;
    const tutor = schooling.teacherId && find('staff') === schooling.teacherId
      && classRow.teacher_profile_id === schooling.teacherId && schooling.teacherName;
    matches.push({ classRow, assignments: tutor
      ? [{ class_id: classId, is_tutor: true, teacher_name: schooling.teacherName }] : [] });
  }
  if (matches.length !== 1) return claim.input;
  return { ...claim.input, verified: { ...claim.input.verified,
    classRow: matches[0].classRow, cardConfig: matches[0].classRow, assignments: matches[0].assignments } };
}
module.exports = { resolveStudioSchooling };
