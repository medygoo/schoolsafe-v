"use strict";

// Consume only records already selected by native Rosario permission checks.
function toStudioInput(source) {
  if (!source || source.authorized !== true) throw new Error("NATIVE_ACCESS_REQUIRED");
  const { student = {}, school = {}, enrollment = {}, verified = {} } = source;
  if (!student.STUDENT_ID || !school.ID ||
      String(enrollment.STUDENT_ID) !== String(student.STUDENT_ID) ||
      String(enrollment.SCHOOL_ID) !== String(school.ID) ||
      String(enrollment.SYEAR) !== String(school.SYEAR)) {
    throw new Error("NATIVE_CONTEXT_MISMATCH");
  }
  const text = value => value == null ? "" : String(value);
  const missing = [];
  const required = (name, value) => {
    if (!value) missing.push(name);
    return value || "";
  };
  // Integer IDs remain native references, never invented Node UUIDs.
  const input = {
    record: {
      id: text(student.STUDENT_ID),
      last_name: required("student.last_name", text(student.LAST_NAME)),
      first_name: required("student.first_name", text(student.FIRST_NAME)),
      middle_name: text(student.MIDDLE_NAME),
      matricule: required("student.matricule", text(verified.matricule)),
      date_of_birth: text(student.CUSTOM_200000004),
      photo_path: required("student.photo", text(verified.photoUrl)),
    },
    schoolSettings: {
      identity: { name: required("school.name", text(school.TITLE)) },
      brand: { logo_path: required("school.logo", text(verified.logoUrl)) },
      contact: {
        address: required("school.address", text(school.ADDRESS)),
        postal_code: text(school.ZIPCODE),
        city: text(school.CITY), province: text(school.STATE),
        country: text(verified.country),
        phone: required("school.phone", text(school.PHONE)),
        email: required("school.email", text(verified.schoolEmail)),
        website_url: required("school.website", text(school.WWW_ADDRESS)),
      },
      principal: { name: text(school.PRINCIPAL) },
      registration: { school_number: text(school.SCHOOL_NUMBER) },
    },
    cardMotto: text(verified.motto),
    academicYearLabel: required("school.year", text(verified.academicYearLabel)),
    classRow: verified.classRow || {},
    assignments: verified.assignments || [],
    family: verified.family || [],
    guardianPhones: verified.guardianPhones || [],
    cardConfig: verified.cardConfig || {},
    credential: verified.credential || {},
  };
  if (!input.classRow.id || !input.classRow.name) missing.push("class.mapping");
  if (!["nursery", "maternelle", "primary", "primaire", "secondary", "secondaire", "humanites"].includes(input.classRow.cycle_key)) {
    missing.push("class.cycle");
  }
  if (!input.assignments.some(a => a.class_id === input.classRow.id && a.is_tutor === true && a.teacher_name)) {
    missing.push("class.tutor");
  }
  if (input.family.some(p => typeof p.is_authorized_pickup !== "boolean")) {
    throw new Error("PICKUP_DECISION_REQUIRED");
  }
  if (!input.family.some(p => p.is_active !== false && p.is_primary === true && p.full_name && p.phone)) {
    missing.push("family.primary_contact");
  }
  if (!/^schoolsafe:\/\/card\/[^/]+\/[^/]+$/.test(input.credential.qr_payload || "")) missing.push("card.official_qr");
  return { input, missing, nativeReference: {
    studentId: text(student.STUDENT_ID), schoolId: text(school.ID), year: text(enrollment.SYEAR),
  }, schoolingSource: source.schoolingSource ? {
    grade: source.schoolingSource.grade ? {
      id: text(source.schoolingSource.grade.id),
      title: text(source.schoolingSource.grade.title),
      shortName: text(source.schoolingSource.grade.shortName),
    } : null,
    scheduleAccess: source.schoolingSource.scheduleAccess === true,
    courseSections: source.schoolingSource.scheduleAccess === true
      && Array.isArray(source.schoolingSource.courseSections)
      ? source.schoolingSource.courseSections.map(row => ({
        id: text(row.id), title: text(row.title), courseId: text(row.courseId),
        teacherId: row.teacherId == null ? null : text(row.teacherId),
      })) : [],
  } : null, issuanceAllowed: false };
}

module.exports = { toStudioInput };
