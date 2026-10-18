"use strict";

function mapCycle(cycleKey) {
  if (cycleKey === "nursery" || cycleKey === "maternelle") return "maternelle";
  if (cycleKey === "primary" || cycleKey === "primaire") return "primaire";
  if (cycleKey === "secondary" || cycleKey === "secondaire" || cycleKey === "humanites") return "secondaire";
  return cycleKey || "";
}

function officialQr(value) {
  return /^schoolsafe:\/\/card\/[^/]+\/[^/]+$/.test(value || "") ? value : "";
}

function foldText(value) {
  return String(value || "")
    .trim()
    .toLowerCase()
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "");
}

function officialAddress(contact) {
  const source = contact || {};
  const kept = [];
  [source.address, source.city, source.province, source.country].forEach((value) => {
    const text = String(value || "").trim();
    if (!text) return;
    if (foldText(kept.join(", ")).includes(foldText(text))) return;
    kept.push(text);
  });
  return kept.join(", ");
}

function adaptRosarioCard(input) {
  const source = input || {};
  const record = source.record || {};
  const schooling = record.schooling || {};
  const family = Array.isArray(source.family) ? source.family : [];
  const phoneById = new Map(
    (Array.isArray(source.guardianPhones) ? source.guardianPhones : [])
      .filter((item) => item && item.id)
      .map((item) => [item.id, item.phone || ""]),
  );
  const phoneFor = (item) => (item && phoneById.has(item.id) ? phoneById.get(item.id) || "" : (item && item.phone) || "");
  const years = Array.isArray(source.academicYears) ? source.academicYears : [];
  const assignments = Array.isArray(source.assignments) ? source.assignments : [];
  const settings = source.schoolSettings || {};
  const identity = settings.identity || {};
  const brand = settings.brand || {};
  const contact = settings.contact || {};
  const classRow = source.classRow || {};
  const config = source.cardConfig || {};
  const credential = source.credential || {};
  const yearId = classRow.academic_year_id || schooling.academic_year_id || null;
  const explicitYear = String(source.academicYearLabel || "").trim();
  const year = years.find((item) => item.id === yearId);
  const tutor = classRow.id ? assignments.find((item) => item.class_id === classRow.id && item.is_tutor === true) : null;
  const active = family.filter((item) => item.is_active !== false);
  const primary = active.find((item) => item.is_primary === true);
  const authorizedPeople = active
    .filter((item) => item.is_authorized_pickup === true)
    .map((item) => ({
      id: item.id,
      name: item.full_name,
      phone: phoneFor(item),
      relation: item.guardian_type || "",
    }));

  return {
    student: {
      id: record.id,
      last_name: record.last_name || "",
      middle_name: record.middle_name || "",
      first_name: record.first_name || "",
      mat: record.matricule || "",
      dob: record.date_of_birth || "",
      photo: record.photo_path || "",
      parent_name: primary ? primary.full_name : "",
      parent_phone: phoneFor(primary),
      authorized_name: "",
      authorized_phone: "",
      qr: credential.status === "active" ? officialQr(credential.qr_payload) : "",
    },
    classData: {
      id: classRow.id,
      name: classRow.name || schooling.class_name || "",
      cycle: mapCycle(classRow.cycle_key),
      option: classRow.option || "",
      format: source.format === "badge" || source.format === "carte" ? source.format : undefined,
      card_color: config.card_color,
      card_color_soft: config.card_color_soft,
      card_color_dark: config.card_color_dark,
      card_pat: config.card_pat,
      card_family: config.card_family,
      card_variant: config.card_variant,
      card_pat_style: config.card_pat_style,
    },
    teacher: tutor && tutor.teacher_name ? { name: tutor.teacher_name } : null,
    year: explicitYear || (year && year.label ? year.label : ""),
    school: {
      name: identity.name || "",
      motto: source.cardMotto || "",
      street: String(contact.address || "").trim(),
      address: officialAddress(contact),
      city: String(contact.city || "").trim(),
      province: String(contact.province || "").trim(),
      country: String(contact.country || "").trim(),
      phone: contact.phone || "",
      email: contact.email || "",
      website: contact.website_url || "",
      logo: brand.logo_path || "",
    },
    academicYearId: yearId,
    authorizedPeople,
  };
}

if (typeof module !== "undefined" && module.exports) {
  module.exports = { adaptRosarioCard, mapCycle, officialQr };
}
if (typeof window !== "undefined") {
  window.SchoolSafeCardAdapter = { adaptRosarioCard: adaptRosarioCard, mapCycle: mapCycle, officialQr: officialQr };
}
