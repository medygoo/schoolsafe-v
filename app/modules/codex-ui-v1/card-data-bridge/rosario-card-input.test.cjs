"use strict";
const assert = require("node:assert/strict");
const { toStudioInput } = require("./rosario-card-input.cjs");
const { adaptRosarioCard } = require("../../cards/card-adapter.js");
const source = {
  authorized: true,
  student: { STUDENT_ID: 12, LAST_NAME: "TEST", FIRST_NAME: "EXAMPLE", PASSWORD: "must-not-export" },
  school: { ID: 1, SYEAR: 2026, TITLE: "TEST SCHOOL" },
  enrollment: { STUDENT_ID: 12, SCHOOL_ID: 1, SYEAR: 2026 },
};
const result = toStudioInput(source);
assert.equal(result.input.record.id, "12");
assert.equal(result.issuanceAllowed, false);
assert(result.missing.includes("class.mapping"));
assert(result.missing.includes("card.official_qr"));
assert(!JSON.stringify(result).includes("must-not-export"));
assert.equal(adaptRosarioCard(result.input).student.last_name, "TEST");
assert.throws(() => toStudioInput({ ...source, authorized: false }), /NATIVE_ACCESS_REQUIRED/);
assert.throws(() => toStudioInput({ ...source, enrollment: { ...source.enrollment, SCHOOL_ID: 2 } }), /NATIVE_CONTEXT_MISMATCH/);
assert.throws(() => toStudioInput({ ...source, verified: { family: [{ full_name: "TEST CONTACT" }] } }), /PICKUP_DECISION_REQUIRED/);
assert.equal(adaptRosarioCard(result.input).authorizedPeople.length, 0);
const withSections = toStudioInput({ ...source, schoolingSource: {
  grade: { id: 7, title: 'TEST GRADE', shortName: 'TG' }, scheduleAccess: true,
  courseSections: [{ id: 10, title: 'TEST SECTION', courseId: 4, teacherId: 2, password: 'must-not-export' }],
} });
assert.equal(withSections.schoolingSource.grade.id, '7');
assert.equal(withSections.schoolingSource.courseSections[0].teacherId, '2');
assert(!JSON.stringify(withSections).includes('must-not-export'));
assert.equal(withSections.input.classRow.id, undefined);
assert.equal(withSections.input.assignments.length, 0);
assert(withSections.missing.includes('class.cycle'));
for (const cycle of [undefined, '', 'unknown']) {
  const incomplete = toStudioInput({ ...source, verified: { classRow: { id: 'TEST-CLASS', name: 'TEST CLASS', cycle_key: cycle } } });
  assert(incomplete.missing.includes('class.cycle'));
}
const validCycle = toStudioInput({ ...source, verified: { classRow: { id: 'TEST-CLASS', name: 'TEST CLASS', cycle_key: 'primary' } } });
assert(!validCycle.missing.includes('class.cycle'));
const deniedSections = toStudioInput({ ...source, schoolingSource: {
  scheduleAccess: false, courseSections: [{ id: 10, teacherId: 2 }],
} });
assert.deepEqual(deniedSections.schoolingSource.courseSections, []);
console.log("PASS: simulated native-to-Studio mapping and schooling provenance; no business writes");
