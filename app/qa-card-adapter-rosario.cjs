"use strict";

const assert = require("node:assert/strict");
const fs = require("node:fs");
const path = require("node:path");
const { createHmac } = require("node:crypto");
const { pathToFileURL } = require("node:url");
const { adaptRosarioCard } = require("./modules/cards/card-adapter.js");

const root = path.resolve(__dirname, "..");
const read = (rel) => fs.readFileSync(path.join(root, rel), "utf8");

const cards = read("app/modules/cards/cards-module.js");
const renderer = read("app/modules/cards/card-renderer.js");
const schoolService = read("server/src/school/service.ts");
const schoolSchema = read("server/src/school/schema.ts");
const replaceService = read("server/src/cardsnative/service.ts");
const familySql = read("database/studentrecord/v2/01_family_phone_and_photo.sql");
const cardSql = read("database/cards/v2/01_class_config_and_credential.sql");

assert.equal(cards.includes("'/school/info'"), false);
assert.equal(cards.includes("/school/settings"), false);
assert.equal(cards.includes("/cards/school-identity"), true);
assert.equal(cards.includes("/academic-year"), true);
assert.equal(cards.includes("/school/academic-years"), false);
assert.equal(cards.includes("school.manage"), false);
assert.equal(/new Date\(\)\.getFullYear\(\)/.test(cards), false);
assert.equal(/name:\s*'—'/.test(cards), false);
assert.equal(cards.includes("adaptRosarioCard"), true);
assert.equal(cards.includes("is_tutor === true"), true);
assert.equal(cards.includes("cardsAuthorizedSelect"), true);

assert.equal(renderer.includes("schoolsafe://student/"), false);
assert.equal(renderer.includes("schoolsafe://card/"), true);
assert.equal(renderer.includes("schoolsafe1@gmail.com"), false);
assert.equal(renderer.includes("www.schoolsafe.cd"), false);
assert.equal(renderer.includes("NOM DE L'ÉCOLE"), false);
assert.equal(renderer.includes("Adresse de l'école"), false);
assert.equal(renderer.includes("N° Direction"), false);
assert.equal(renderer.includes("LOGO<br>ÉCOLE"), false);
assert.equal(renderer.includes("https://shoolsafe.tech/"), true);
assert.equal(renderer.includes("app.shoolsafe.tech"), false);
assert.equal(renderer.includes("COORDONNÉES DE L'ÉCOLE"), true);
assert.equal(renderer.includes("s.last_name"), true);

assert.equal(schoolSchema.includes("motto"), false);
assert.equal(schoolService.includes("motto"), false);
assert.equal(cards.includes("/cards/class-visual"), true);
assert.equal(cards.includes("/cards/class-card-config"), false);
assert.equal(cards.includes("/cards/school-motto"), true);
assert.equal(cards.includes("guardian-phones"), true);
assert.equal(cards.includes("guardianPhones"), true);
assert.equal(cards.includes("cardMotto"), true);

const replaceFn = replaceService.slice(replaceService.indexOf("async replaceCard"), replaceService.indexOf("async reprintAuthorize"));
assert.equal(replaceFn.includes("signCardNumber"), true);
assert.equal(replaceFn.includes('digest("hex")'), false);
const issueFn = replaceService.slice(replaceService.indexOf("async issueCardCredential"), replaceService.indexOf("function credentialPayload"));
assert.equal(issueFn.includes("signCardNumber"), true);
assert.equal(issueFn.includes('digest("hex")'), false);
assert.equal(replaceService.includes("api.class_card_config_list()"), true);
assert.equal(replaceService.includes("api.class_card_visual_list()"), true);
assert.equal(replaceService.includes("api.school_card_motto()"), true);
assert.equal(replaceService.includes("api.student_card_guardian_phones"), true);

assert.equal(familySql.includes("api.student_family_read"), false);
assert.equal(familySql.includes("api.student_card_guardian_phones"), true);
assert.equal(familySql.includes("'phone', g.phone"), true);
assert.equal(familySql.includes("api.student_photo_set"), true);
assert.equal(familySql.includes("create table"), false);

assert.equal(cardSql.includes("api.class_card_config_list"), false);
assert.equal(cardSql.includes("api.class_card_visual_list"), true);
assert.equal(cardSql.includes("api.school_card_motto"), true);
assert.equal(cardSql.includes("app.schools"), true);
assert.equal(cardSql.includes("c.teacher_profile_id"), true);
assert.equal(cardSql.includes("c.teacher_id"), false);
assert.equal(cardSql.includes("api.student_card_issue"), true);
assert.equal(cardSql.includes("card_secret"), true);
assert.equal(/'card_secret'/.test(cardSql), false);

const identitySql = read("database/cards/v2/02_school_card_identity.sql");
assert.equal(identitySql.includes("api.school_card_identity()"), true);
assert.equal(identitySql.includes("iam.require_access('cards.request.print', null, null, null)"), true);
assert.equal(identitySql.includes("iam.current_school_id()"), true);
assert.equal(identitySql.includes("school.manage"), false);
assert.equal(identitySql.includes("p_school_id"), false);
assert.equal(identitySql.includes("website_mode"), false);
assert.equal(identitySql.includes("public_news"), false);
assert.equal(identitySql.includes("approval_code"), false);
assert.equal(replaceService.includes("api.school_card_identity()"), true);
assert.equal(read("server/src/school/routes.ts").includes('requirePermission(access, "school.manage")'), true);

const view = adaptRosarioCard({
  record: {
    id: "stu-1",
    last_name: "Kabila",
    middle_name: "Wa",
    first_name: "Amina",
    matricule: "MAT-9",
    date_of_birth: "2016-04-02",
    photo_path: "/uploads/students/stu-1.jpg",
    schooling: { academic_year_id: "year-1", class_id: "cls-1", class_name: "3e Primaire" },
  },
  family: [
    { id: "g1", full_name: "Marie Kabila", guardian_type: "mere", is_primary: true, is_authorized_pickup: true, is_active: true },
    { id: "g2", full_name: "Paul Ilunga", guardian_type: "tuteur", is_primary: false, is_authorized_pickup: true, is_active: true },
  ],
  guardianPhones: [
    { id: "g1", phone: "+243810000001" },
    { id: "g2", phone: "+243810000002" },
  ],
  cardMotto: "Grandir ensemble",
  schoolSettings: {
    identity: { name: "École Lumière", motto: "Ne pas lire cette devise" },
    brand: { logo_path: "/uploads/logos/ecole.png" },
    contact: { address: "Limete", phone: "+243810000099", email: "ecole@example.test", website_url: "https://ecole.example.test" },
  },
  academicYears: [{ id: "year-1", label: "2026-2027", is_active: true }],
  assignments: [{ class_id: "cls-1", is_tutor: true, teacher_name: "Jeanne Mbayo", profile_id: "tch-1" }],
  classRow: { id: "cls-1", name: "3e Primaire", cycle_key: "primary", option: "", academic_year_id: "year-1" },
  cardConfig: { card_pat: "rdc-okapi", card_color: "#1446aa" },
  credential: { qr_payload: "schoolsafe://card/SS-1/abcdef" },
  selectedAuthorizedId: "",
});

assert.equal(view.student.last_name, "Kabila");
assert.equal(view.student.middle_name, "Wa");
assert.equal(view.student.first_name, "Amina");
assert.equal(view.student.mat, "MAT-9");
assert.equal(view.student.photo, "/uploads/students/stu-1.jpg");
assert.equal(view.student.parent_name, "Marie Kabila");
assert.equal(view.student.parent_phone, "+243810000001");
assert.equal(view.student.authorized_name, "");
assert.equal(view.year, "2026-2027");
assert.equal(view.teacher.name, "Jeanne Mbayo");
assert.equal(view.school.motto, "Grandir ensemble");
assert.equal(view.school.logo, "/uploads/logos/ecole.png");
assert.equal(view.classData.cycle, "primaire");
assert.equal(view.student.qr, "schoolsafe://card/SS-1/abcdef");
assert.equal(view.authorizedPeople.length, 2);

const chosen = adaptRosarioCard({
  record: { id: "stu-1", last_name: "Kabila", first_name: "Amina", middle_name: "Wa" },
  family: [
    { id: "g2", full_name: "Paul Ilunga", guardian_type: "tuteur", is_primary: false, is_authorized_pickup: true, is_active: true },
  ],
  guardianPhones: [{ id: "g2", phone: "+243810000002" }],
  academicYears: [],
  assignments: [],
  classRow: { id: "cls-1", name: "5e", cycle_key: "secondary" },
  credential: { qr_payload: "schoolsafe://student/MAT-9" },
  selectedAuthorizedId: "g2",
});
assert.equal(chosen.student.authorized_name, "");
assert.equal(chosen.student.authorized_phone, "");
assert.equal(chosen.authorizedPeople[0].name, "Paul Ilunga");
assert.equal(cards.includes("gaps.push('Personne autorisée')"), false);
assert.equal(chosen.student.qr, "");
const repeated = adaptRosarioCard({
  record: { id: "stu-1", last_name: "Kabila", first_name: "Amina" },
  schoolSettings: {
    identity: { name: "Ecole Lumiere" },
    contact: { address: "12 Avenue de la Paix, Kinshasa", city: "Kinshasa", province: "Kinshasa", country: "République démocratique du Congo", phone: "+243800", email: "ecole@example.test", website_url: "https://ecole.example" },
    brand: { logo_path: "/logo.png" }
  }
});
assert.equal(repeated.school.address, "12 Avenue de la Paix, Kinshasa, République démocratique du Congo");
assert.equal(repeated.school.street, "12 Avenue de la Paix, Kinshasa");
const moved = adaptRosarioCard({
  record: { id: "stu-1", last_name: "Kabila", first_name: "Amina" },
  schoolSettings: {
    identity: { name: "Ecole Lumiere" },
    contact: { address: "45 Boulevard du 30 Juin", city: "Lubumbashi", province: "Haut-Katanga", country: "République démocratique du Congo" },
    brand: {}
  }
});
assert.equal(moved.school.address, "45 Boulevard du 30 Juin, Lubumbashi, Haut-Katanga, République démocratique du Congo");
const combined = adaptRosarioCard({
  record: { id: "stu-1", name: "Kabila Wa Amina" },
  schoolSettings: { identity: {}, contact: {}, brand: {} }
});
assert.equal(combined.student.last_name, "");
assert.equal(combined.student.middle_name, "");
assert.equal(combined.student.first_name, "");
assert.equal(chosen.classData.cycle, "secondaire");
assert.equal(chosen.year, "");

function signCardNumber(cardNumber, secret) {
  return createHmac("sha256", secret).update(cardNumber).digest("base64url").slice(0, 32);
}
const number = "SS-REPL-abcdef12-1";
const secret = "test-card-secret";
const signature = signCardNumber(number, secret);
assert.match(`schoolsafe://card/${number}/${signature}`, /^schoolsafe:\/\/card\/([^/]+)\/([^/]+)$/);
assert.equal(signature.includes(" "), false);
assert.notEqual(createHmac("sha256", secret).update(number).digest("hex"), signature);

function Element() {}
Object.defineProperty(Element.prototype, "textContent", {
  set: function (value) {
    this._text = String(value);
    this._html = this._text.replace(/[&<>]/g, function (character) {
      return { "&": "&amp;", "<": "&lt;", ">": "&gt;" }[character];
    });
  },
  get: function () { return this._text || ""; }
});
Object.defineProperty(Element.prototype, "innerHTML", {
  get: function () { return this._html || ""; },
  set: function (value) { this._html = String(value); }
});
global.document = { createElement: function () { return new Element(); } };

(async function renderOfficialCard() {
  const rendererModule = await import(pathToFileURL(path.join(__dirname, "modules/cards/card-renderer.js")).href);
  const pattern = { v: "okapi", n: "Okapi" };
  const empty = rendererModule.ssBuildCarte(
    { last_name: "Kabila", first_name: "Amina", qr: "schoolsafe://card/SS-1/sig" },
    { name: "6e A", cycle: "secondaire" },
    null,
    "",
    pattern,
    "none",
    {},
    ""
  );
  const emptyHtml = empty.recto + empty.verso;
  assert.equal(emptyHtml.includes("NOM DE L'ÉCOLE"), false);
  assert.equal(emptyHtml.includes("Adresse de l'école"), false);
  assert.equal(emptyHtml.includes("schoolsafe1@gmail.com"), false);
  assert.equal(emptyHtml.includes("www.schoolsafe.cd"), false);
  assert.equal(emptyHtml.includes("N° Direction"), false);
  assert.equal(empty.recto.includes("Téléphone de l'école"), true);
  assert.equal(empty.recto.includes("https://shoolsafe.tech/"), true);
  assert.equal(empty.verso.includes("https://shoolsafe.tech/"), true);
  assert.equal(empty.recto.includes("app.shoolsafe.tech"), false);
  assert.equal(empty.qr, "schoolsafe://card/SS-1/sig");
  const full = rendererModule.ssBuildCarte(
    {
      last_name: "Kabila", middle_name: "Wa", first_name: "Amina", mat: "MAT-9",
      photo: "/uploads/students/stu-1.jpg", dob: "2016-04-02",
      parent_name: "Marie Kabila", parent_phone: "+243810000001",
      qr: "schoolsafe://card/SS-1/sig"
    },
    { name: "6e A", cycle: "secondaire" },
    { name: "Jeanne Mbayo" },
    "2026-2027",
    pattern,
    "none",
    { name: "Ecole Lumiere", address: "12 Avenue de la Paix, Kinshasa, République démocratique du Congo", phone: "+243800", email: "ecole@example.test", website: "https://ecole.example" },
    "/uploads/logos/ecole.png"
  );
  const fullHtml = full.recto + full.verso;
  assert.equal(fullHtml.includes("Ecole Lumiere"), true);
  assert.equal(fullHtml.includes("Jeanne Mbayo"), true);
  assert.equal(full.verso.includes("COORDONNÉES DE L'ÉCOLE"), true);
  assert.equal(full.verso.includes("12 Avenue de la Paix, Kinshasa, République démocratique du Congo"), true);
  assert.equal(full.verso.includes("Téléphone de l'école"), true);
  assert.equal(full.verso.includes("N° Direction"), false);
  assert.equal(fullHtml.includes("+243800"), true);
  assert.equal(fullHtml.includes("ecole@example.test"), true);
  assert.equal(full.verso.includes("https://ecole.example"), true);
  assert.equal(full.recto.includes("https://shoolsafe.tech/"), true);
  assert.equal(full.verso.includes("https://shoolsafe.tech/"), true);
  assert.equal(fullHtml.includes("app.shoolsafe.tech"), false);
  assert.equal(fullHtml.includes("Marie Kabila"), true);
  assert.equal(fullHtml.includes("Paul Ilunga"), false);
  assert.equal(fullHtml.includes("Personne autorisée"), true);
  assert.equal(full.qr, "schoolsafe://card/SS-1/sig");
  const badge = rendererModule.ssBuildBadge(
    { last_name: "Kabila", middle_name: "Wa", first_name: "Amina", mat: "MAT-9", qr: "schoolsafe://card/SS-1/sig" },
    { name: "1re", cycle: "primaire" },
    { name: "Jeanne Mbayo" },
    "2026-2027",
    pattern,
    "none",
    { name: "Ecole Lumiere", address: "12 Avenue de la Paix, Kinshasa, République démocratique du Congo", phone: "+243800", email: "ecole@example.test", website: "https://ecole.example" },
    "/uploads/logos/ecole.png"
  );
  assert.equal(badge.recto.includes("https://shoolsafe.tech/"), true);
  assert.equal(badge.verso.includes("https://shoolsafe.tech/"), true);
  assert.equal(badge.verso.includes("COORDONNÉES DE L'ÉCOLE"), true);
  assert.equal(badge.verso.includes("https://ecole.example"), true);
  assert.equal(badge.verso.includes("app.shoolsafe.tech"), false);
  assert.equal(badge.recto.includes("Kabila"), true);
  assert.equal(badge.recto.includes("Wa"), true);
  assert.equal(badge.recto.includes("Amina"), true);
  const unsplit = rendererModule.ssBuildCarte(
    { name: "Kabila Wa Amina", qr: "schoolsafe://card/SS-1/sig" },
    { name: "6e A", cycle: "secondaire" },
    null,
    "2026-2027",
    pattern,
    "none",
    { name: "Ecole Lumiere" },
    ""
  );
  assert.equal(unsplit.recto.includes(">Kabila<"), false);
  assert.equal(unsplit.recto.includes(">Wa<"), false);
  console.log("qa-card-adapter-rosario: adaptateur, contrat et QR canonique");
})().catch(function (error) {
  console.error(error.stack || error.message);
  process.exitCode = 1;
});
