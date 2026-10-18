'use strict';

// Same read functions as CardsNativeService; no direct business-table queries.
async function readCardMetadata({ client, context }) {
  const reads = [
    ['schoolIdentity', 'select api.school_card_identity() as result', []],
    ['schoolMotto', 'select api.school_card_motto() as result', []],
    ['classVisuals', 'select api.class_card_visual_list() as result', []],
    ['guardianPhones', 'select api.student_card_guardian_phones($1) as result', [context.studentId]],
    ['credential', 'select api.student_card_read($1) as result', [context.studentId]],
  ];
  const data = { studentId: context.studentId, schoolId: context.schoolId };
  for (const [key, sql, parameters] of reads) {
    const result = await client.query(sql, parameters);
    if (result.rows.length !== 1 || !Object.hasOwn(result.rows[0], 'result')) {
      throw new Error('CARD_METADATA_RESULT_INVALID');
    }
    data[key] = result.rows[0].result;
  }
  // The existing credential service owns QR serialization and issuance.
  return data;
}
module.exports = { readCardMetadata };
