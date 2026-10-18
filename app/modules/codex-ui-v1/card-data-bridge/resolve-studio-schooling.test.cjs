const test = require('node:test');
const assert = require('node:assert/strict');
const { resolveStudioSchooling } = require('./resolve-studio-schooling.cjs');
const claim = { sourceInstance: 'TEST', nativeSchoolId: '1', input: {
  enrollment: { SYEAR: 2026 }, verified: {}, schoolingSource: { scheduleAccess: true,
    courseSections: [{ id: '1', teacherId: '2' }] } } };
const metadata = { studentId: 'student', classVisuals: [{ id: 'class', academic_year_id: 'year',
  teacher_profile_id: 'teacher', is_active: true, name: 'TEST CLASS', cycle_key: 'nursery' }] };
const mapping = { query: async () => ({ rows: [
  { entity_type: 'class', target_entity_id: 'class' },
  { entity_type: 'academic_year', target_entity_id: 'year' },
  { entity_type: 'staff', target_entity_id: 'teacher' },
] }) };
const client = { query: async () => ({ rows: [{ result: { classId: 'class', yearId: 'year',
  teacherId: 'teacher', teacherName: 'TEST TEACHER' } }] }) };
test('reviewed native class/year/teacher matches active target enrollment', async () => {
  const result = await resolveStudioSchooling(claim, metadata, mapping, client);
  assert.equal(result.verified.classRow.id, 'class');
  assert.equal(result.verified.assignments[0].teacher_name, 'TEST TEACHER');
});
test('no source permission or no active enrollment never creates class', async () => {
  const denied = { ...claim, input: { ...claim.input, schoolingSource: { scheduleAccess: false } } };
  assert.equal(await resolveStudioSchooling(denied, metadata, mapping, client), denied.input);
  assert.equal(await resolveStudioSchooling(claim, metadata, mapping,
    { query: async () => ({ rows: [{ result: null }] }) }), claim.input);
});
test('wrong year, inactive class and ambiguous sections remain unassigned', async () => {
  for (const row of [{ ...metadata.classVisuals[0], academic_year_id: 'wrong' }, { ...metadata.classVisuals[0], is_active: false }]) {
    assert.equal(await resolveStudioSchooling(claim, { ...metadata, classVisuals: [row] }, mapping, client), claim.input);
  }
  const ambiguous = { ...claim, input: { ...claim.input, schoolingSource: {
    ...claim.input.schoolingSource, courseSections: [{ id: '1', teacherId: '2' }, { id: '3', teacherId: '2' }] } } };
  assert.equal(await resolveStudioSchooling(ambiguous, metadata, mapping, client), ambiguous.input);
});
test('mismatched teacher cannot become tutor', async () => {
  const wrong = { query: async () => ({ rows: [{ result: { classId: 'class', yearId: 'year',
    teacherId: 'other-teacher', teacherName: 'TEST WRONG' } }] }) };
  const result = await resolveStudioSchooling(claim, metadata, mapping, wrong);
  assert.deepEqual(result.verified.assignments, []);
});
