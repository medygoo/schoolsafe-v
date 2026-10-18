'use strict';

function toRestoredStudioData(result) {
  const v = result?.view;
  const ref = result?.targetReference;
  if (result?.issuanceAllowed !== false || !v || !ref
      || v.student?.id !== ref.studentId || !v.classData?.id
      || !v.classData.cycle || result.missing?.includes('class.mapping')) {
    throw new Error('RESTORED_STUDIO_DATA_INVALID');
  }
  const student = v.student;
  const classData = { ...v.classData };
  const users = [];
  if (v.teacher?.name) {
    classData.titulaire_id = 'display-teacher:' + classData.id;
    users.push({ id: classData.titulaire_id, name: v.teacher.name, phone: '' });
  }
  let parentId;
  if (student.parent_name) {
    parentId = 'display-primary:' + student.id;
    users.push({ id: parentId, name: student.parent_name, phone: student.parent_phone || '' });
  }
  return {
    settings: { year: v.year, school: { ...v.school } },
    classes: [classData],
    students: [{ id: student.id, cid: classData.id,
      name: [student.last_name, student.middle_name, student.first_name].filter(Boolean).join(' '),
      mat: student.mat, dob: student.dob, photo: student.photo, pid: parentId,
      official_qr: /^schoolsafe:\/\/card\/[^/\s]+\/[^/\s]+$/.test(student.qr || '') ? student.qr : '' }],
    users, aps: [],
  };
}

if (typeof module !== 'undefined' && module.exports) module.exports = { toRestoredStudioData };
else window.toRestoredStudioData = toRestoredStudioData;
