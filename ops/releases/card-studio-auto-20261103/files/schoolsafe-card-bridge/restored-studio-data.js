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
      parts: [student.last_name || '', student.middle_name || '', student.first_name || ''],
      mat: student.mat, dob: student.dob, photo: student.photo, addr: student.addr || '', pid: parentId,
      official_qr: /^schoolsafe:\/\/card\/[^/\s]+\/[^/\s]+$/.test(student.qr || '') ? student.qr : '' }],
    users,
    // Personne autorisee (accreditation place 1) imprimee au verso.
    aps: (v.authorizedPeople || []).slice(0, 1).map(p => ({ id: 'display-ap:' + student.id, sid: student.id,
      name: p.name || '', phone: p.phone || '', active: true })),
  };
}

// Toutes les cartes pretes de l'ecole dans un seul jeu de donnees du Studio.
function mergeRestoredStudioData(list) {
  const merged = { settings: list[0].settings, classes: [], students: [], users: [], aps: [] };
  for (const data of list) {
    for (const cl of data.classes) if (!merged.classes.some(c => c.id === cl.id)) merged.classes.push(cl);
    for (const user of data.users) if (!merged.users.some(u => u.id === user.id)) merged.users.push(user);
    merged.students.push(...data.students); merged.aps.push(...data.aps);
  }
  merged.students.sort((a, b) => a.name.localeCompare(b.name, 'fr'));
  return merged;
}

if (typeof module !== 'undefined' && module.exports) module.exports = { toRestoredStudioData, mergeRestoredStudioData };
else { window.toRestoredStudioData = toRestoredStudioData; window.mergeRestoredStudioData = mergeRestoredStudioData; }
