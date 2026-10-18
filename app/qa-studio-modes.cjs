"use strict";

const assert = require("node:assert/strict");
const fs = require("node:fs");
const path = require("node:path");
const { pathToFileURL } = require("node:url");

const CARD = "aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa";
const FIRST = "11111111-1111-4111-8111-111111111111";
const NOCAP = "55555555-5555-4555-8555-555555555555";
const DENY = "66666666-6666-4666-8666-666666666666";
const LATE = "77777777-7777-4777-8777-777777777777";
const RACE = "88888888-8888-4888-8888-888888888888";
const PLAIN = "22222222-2222-4222-8222-222222222222";
const FAKE = "44444444-4444-4444-8444-444444444444";
const BATCH_CARD = "abababab-abab-4aba-8aba-abababababab";
const BATCH_FIRST = "bcbcbcbc-bcbc-4bcb-8bcb-bcbcbcbcbcbc";
const BATCH_PLAIN = "cdcdcdcd-cdcd-4cdc-8cdc-cdcdcdcdcdcd";
const BATCH_BROKEN = "dededede-dede-4ded-8ded-dededededede";
const CLASS_ID = "bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb";
const EMPTY = "12121212-1212-4121-8121-121212121212";
const CLASS_B = "99999999-9999-4999-8999-999999999999";
const SCHOOL = "cccccccc-cccc-4ccc-8ccc-cccccccccccc";
const OTHER_SCHOOL = "eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee";
const PARENT = "13131313-1313-4131-8131-131313131313";
const PICKUP = "14141414-1414-4141-8141-141414141414";
const YEAR = "15151515-1515-4151-8151-151515151515";

let checks = 0;
function eq(actual, expected, message) { checks += 1; assert.equal(actual, expected, message); }
function ok(value, message) { checks += 1; assert.ok(value, message); }
function match(value, pattern, message) { checks += 1; assert.match(String(value), pattern, message); }

function Element(id) {
  this.id = id;
  this.hidden = false;
  this.value = "";
  this.checked = false;
  this.disabled = false;
  this._html = "";
  this._text = "";
  this.style = { setProperty: function () {} };
  this.children = [];
  this.listeners = {};
}
Object.defineProperty(Element.prototype, "innerHTML", {
  get: function () { return this._html; },
  set: function (value) { this._html = String(value); this.children = []; }
});
Object.defineProperty(Element.prototype, "textContent", {
  get: function () { return this._text; },
  set: function (value) { this._text = String(value); }
});
Element.prototype.setAttribute = function () {};
Element.prototype.querySelector = function () { return new Element("captured"); };
Element.prototype.addEventListener = function (type, fn) { this.listeners[type] = fn; };
Element.prototype.appendChild = function (child) { this.children.push(child); return child; };
Element.prototype.dispatch = function (type) {
  if (this.listeners[type]) return this.listeners[type]({ target: this, preventDefault: function () {} });
};

const elements = {};
function element(id) {
  if (!elements[id]) elements[id] = new Element(id);
  return elements[id];
}
["cardsStudio", "closeCardsStudio", "cardsClassSelect", "cardsRenderBtn", "cardsRequestPrintBtn", "cardsSelectAll", "cardsStudentList", "cardsPreview", "cardsStatus", "cardsAuthorizedSelect", "cardsPatStyle", "cardsBuildBatchBtn", "cardsModeStudent", "cardsModeClass", "cardsModeSchool", "cardsPrecontrol", "cardsProductionConfirm", "cardsLaunchBtn"].forEach(element);
element("cardsPatStyle").value = "both";

const session = { schoolId: SCHOOL, profileId: "ffffffff-ffff-4fff-8fff-ffffffffffff" };
const calls = [];
const apiCalls = [];
const issued = new Set();
let release = {};
let roster = "one";
let focus = CARD;
let caps = ["CARD_CREATE"];

function json(status, body) {
  return { ok: status >= 200 && status < 300, status: status, json: async function () { return body; } };
}
function route(url) {
  if (url.indexOf("/cards/issue") >= 0) return "issue";
  if (url.indexOf("/academic-year") >= 0) return "year";
  if (url.indexOf("/record") >= 0) return "record";
  if (url.indexOf("/credential") >= 0) return "credential";
  if (url.indexOf("/family") >= 0) return "family";
  if (url.indexOf("guardian-phones") >= 0) return "phones";
  if (url.indexOf("/pedagogy/classes") >= 0) return "classes";
  if (url.indexOf("class-visual") >= 0) return "visual";
  if (url.indexOf("/students?") >= 0) return "students";
  if (url.indexOf("/capabilities") >= 0) return "capabilities";
  if (url.indexOf("/cards/school-identity") >= 0) return "settings";
  if (url.indexOf("/school/settings") >= 0) return "settings";
  if (url.indexOf("school-motto") >= 0) return "motto";
  if (url.indexOf("academic-years") >= 0) return "years";
  if (url.indexOf("teacher-assignments") >= 0) return "assignments";
  return url;
}
function queryValue(url, key) {
  const found = new RegExp("[?&]" + key + "=([^&]+)").exec(url);
  return found ? decodeURIComponent(found[1]) : "";
}
function listed(ids, classId) {
  return ids.map(function (id, index) {
    return {
      id: id,
      first_name: "Amina",
      last_name: "Kabila",
      matricule: "MAT-" + index,
      school_id: SCHOOL,
      class_id: classId,
      lifecycle_status: "active"
    };
  });
}
function pageRows(count, offset, classId) {
  const rows = [];
  for (let i = 0; i < count; i++) {
    rows.push({ id: "row-" + (offset + i), first_name: "Eleve", last_name: String(offset + i), matricule: "M" + (offset + i), school_id: SCHOOL, class_id: classId, lifecycle_status: "active" });
  }
  return rows;
}
function studentsFor(url) {
  const classId = queryValue(url, "class_id");
  const offset = Number(queryValue(url, "offset") || 0);
  if (roster === "school") {
    if (classId === CLASS_B) return json(403, { message: "Capacité non déléguée" });
    if (classId === EMPTY) return json(200, { data: { total: 0, rows: [] } });
    if (offset === 0) return json(200, { data: { total: 250, rows: pageRows(200, 0, CLASS_ID) } });
    const tail = pageRows(49, 200, CLASS_ID);
    tail.push({ id: "foreign-1", first_name: "Hors", last_name: "Ecole", matricule: "HX", school_id: OTHER_SCHOOL, class_id: CLASS_ID, lifecycle_status: "active" });
    return json(200, { data: { total: 250, rows: tail } });
  }
  if (classId === EMPTY) return json(200, { data: { total: 0, rows: [] } });
  if (roster === "batch") return json(200, { data: { total: 4, rows: listed([BATCH_CARD, BATCH_FIRST, BATCH_PLAIN, BATCH_BROKEN], CLASS_ID) } });
  return json(200, { data: { total: 1, rows: listed([focus], CLASS_ID) } });
}
function credentialFor(url) {
  const id = (url.split("/students/")[1] || "").split("/")[0];
  if (id === FAKE) return { card_number: "SS-FAUX", qr_payload: "schoolsafe://student/MAT-9", status: "active" };
  if (id === CARD || id === DENY || id === BATCH_CARD || issued.has(id)) {
    const number = "SS-" + id.slice(0, 8);
    return { card_number: number, qr_payload: "schoolsafe://card/" + number + "/sig", status: "active" };
  }
  return { card_number: null, qr_payload: "" };
}
function recordFor(url) {
  const id = (url.split("/students/")[1] || "").split("/")[0];
  if (id === BATCH_BROKEN) return json(500, { message: "Lecture refusée" });
  const record = {
    id: id,
    school_id: SCHOOL,
    first_name: "Amina",
    middle_name: "Grace",
    last_name: "Kabila",
    matricule: "MAT-" + id.slice(0, 4),
    photo_path: "photos/amina.jpg",
    date_of_birth: "2014-01-02",
    schooling: { class_id: CLASS_ID, class_name: "6e A", academic_year_id: YEAR }
  };
  if (id === PLAIN || id === BATCH_PLAIN) delete record.photo_path;
  return json(200, { data: record });
}
function respond(key, url, options) {
  if (key === "issue") {
    const body = JSON.parse(options.body || "{}");
    issued.add(body.student_id);
    return json(200, { data: { student_id: body.student_id } });
  }
  if (key === "year") return json(200, { data: { academic_year_id: YEAR, label: "2026-2027" } });
  if (key === "record") return recordFor(url);
  if (key === "credential") return json(200, { data: credentialFor(url) });
  if (key === "capabilities") return json(200, { data: { capabilities: caps } });
  if (key === "classes") {
    const rows = roster === "school"
      ? [{ id: CLASS_ID, name: "6e A", cycle_key: "secondary", academic_year_id: YEAR }, { id: EMPTY, name: "Classe vide", cycle_key: "secondary", academic_year_id: YEAR }, { id: CLASS_B, name: "5e B", cycle_key: "secondary", academic_year_id: YEAR }]
      : [{ id: CLASS_ID, name: "6e A", cycle_key: "secondary", academic_year_id: YEAR }, { id: EMPTY, name: "Classe vide", cycle_key: "secondary", academic_year_id: YEAR }];
    return json(200, { data: rows });
  }
  if (key === "visual") return json(200, { data: [] });
  if (key === "students") return studentsFor(url);
  if (key === "family") return json(200, { data: [
    { id: PARENT, full_name: "Grace Kabila", is_primary: true, is_active: true, is_authorized_pickup: false, guardian_type: "mother" },
    { id: PICKUP, full_name: "Paul Kabila", is_primary: false, is_active: true, is_authorized_pickup: true, guardian_type: "uncle" }
  ] });
  if (key === "phones") return json(200, { data: [{ id: PARENT, phone: "+243810" }, { id: PICKUP, phone: "+243811" }] });
  if (key === "settings") return json(200, { data: { identity: { name: "Ecole Lumiere" }, brand: { logo_path: "/logo.png" }, contact: { address: "Avenue de la Paix", phone: "+243800", email: "ecole@example.test", website_url: "https://ecole.example" } } });
  if (key === "motto") return json(200, { data: { motto: "Travail" } });
  if (key === "years") return json(200, [{ id: YEAR, label: "2026-2027" }]);
  if (key === "assignments") return json(200, { data: [{ class_id: CLASS_ID, is_tutor: true, teacher_name: "Mireille Ilunga" }] });
  return json(500, { message: "route inattendue " + url });
}
function fetchImpl(url, options) {
  const entry = { url: String(url), method: (options && options.method) || "GET", options: options || {} };
  calls.push(entry);
  const key = route(entry.url);
  if (release[key]) return new Promise(function (resolve) { release[key].push(function () { resolve(respond(key, entry.url, entry.options)); }); });
  return Promise.resolve(respond(key, entry.url, entry.options));
}
function posts(kind) {
  return calls.filter(function (call) { return call.method === "POST" && route(call.url) === kind; });
}
function prints() { return apiCalls.filter(function (call) { return call.type === "print"; }); }

global.window = global;
global.document = {
  getElementById: function (id) { return elements[id] || null; },
  createElement: function () { return new Element(""); },
  querySelector: function () { return { style: {} }; },
  body: new Element("body")
};
global.sessionStorage = { getItem: function () { return JSON.stringify(session); } };
global.schoolSafeApiBase = "http://api.test";
global.ssState = function (state) { return state.title + " " + (state.message || ""); };
global.html2canvas = async function () { return { toDataURL: function () { return "data:image/png;base64," + "A".repeat(80); } }; };
global.fetch = fetchImpl;
global.SchoolSafeCardsNativeAPI = {
  submitPrintRequest: async function (input) {
    apiCalls.push({ type: "print", input: input });
    return { data: { status: "submitted", requestId: "req-" + input.student_id, studentId: input.student_id } };
  },
  buildBatch: async function (input) {
    apiCalls.push({ type: "batch", input: input });
    return { data: { batch_id: "batch-1", version: 1, card_count: input.request_ids.length } };
  }
};
require("./modules/cards/card-adapter.js");

function arm(id) {
  focus = id;
  roster = "one";
  element("cardsAuthorizedSelect").value = PICKUP;
}
async function settle() {
  for (let i = 0; i < 8; i++) await new Promise(function (resolve) { setTimeout(resolve, 0); });
}

(async function main() {
  await import(pathToFileURL(path.resolve(__dirname, "modules/cards/cards-module.js")).href);
  const studio = global.SchoolSafeCards;
  calls.length = 0;
  await studio.open();
  await settle();
  eq(posts("issue").length, 0, "ouverture sans émission");
  eq(prints().length, 0, "ouverture sans impression");
  eq(calls.some(function (call) { return call.url.indexOf("/native/cards/school-identity") >= 0; }), true, "identité limitée lue");
  eq(calls.some(function (call) { return call.url.indexOf("/school/settings") >= 0; }), false, "paramètres administratifs non lus");

  arm(CARD);
  calls.length = 0;
  apiCalls.length = 0;
  await studio.open({ studentId: CARD, classId: CLASS_ID });
  await settle();
  element("cardsAuthorizedSelect").value = PICKUP;
  const ready = await studio.precontrol({ mode: "student" });
  eq(ready.active, 1);
  eq(ready.firstIssue, 0);
  eq(ready.incomplete, 0);
  const unconfirmed = await studio.launch({ mode: "student", confirmed: false });
  eq(unconfirmed.reason, "Confirmation requise");
  eq(posts("issue").length, 0);
  eq(prints().length, 0);
  element("cardsProductionConfirm").checked = false;
  element("cardsLaunchBtn").dispatch("click");
  await settle();
  eq(prints().length, 0, "le bouton exige la case");
  const printed = await studio.launch({ mode: "student", confirmed: true });
  eq(printed.issued, 0, "carte active sans nouvelle émission");
  eq(printed.submitted, 1);
  eq(posts("issue").length, 0);
  eq(prints().length, 1);
  ok(prints()[0].input.front_image_base64);
  eq(JSON.stringify(prints()[0].input).indexOf("schoolsafe://student/"), -1);
  const again = await studio.launch({ mode: "student", confirmed: true });
  eq(again.results[0].status, "already");
  eq(prints().length, 1, "reprise sans seconde impression");

  arm(NOCAP);
  caps = [];
  calls.length = 0;
  apiCalls.length = 0;
  await studio.open({ studentId: NOCAP, classId: CLASS_ID });
  await settle();
  element("cardsAuthorizedSelect").value = PICKUP;
  const missingCap = await studio.precontrol({ mode: "student" });
  eq(missingCap.firstIssue, 1);
  const refusedCap = await studio.launch({ mode: "student", confirmed: true });
  eq(refusedCap.results[0].status, "refused");
  match(refusedCap.results[0].error, /Capacité non déléguée/);
  eq(posts("issue").length, 0);
  eq(prints().length, 0);

  arm(FIRST);
  caps = ["CARD_CREATE"];
  calls.length = 0;
  apiCalls.length = 0;
  await studio.open({ studentId: FIRST, classId: CLASS_ID });
  await settle();
  element("cardsAuthorizedSelect").value = PICKUP;
  await studio.precontrol({ mode: "student" });
  const created = await studio.launch({ mode: "student", confirmed: true });
  eq(created.issued, 1);
  eq(created.submitted, 1);
  eq(posts("issue").length, 1);
  eq(JSON.parse(posts("issue")[0].options.body).student_id, FIRST);
  const createdAgain = await studio.launch({ mode: "student", confirmed: true });
  eq(createdAgain.results[0].status, "already");
  eq(posts("issue").length, 1, "reprise sans seconde émission");

  arm(PLAIN);
  calls.length = 0;
  apiCalls.length = 0;
  await studio.open({ studentId: PLAIN, classId: CLASS_ID });
  await settle();
  element("cardsAuthorizedSelect").value = PICKUP;
  const incomplete = await studio.precontrol({ mode: "student" });
  eq(incomplete.incomplete, 1);
  match(incomplete.errors[0], /Photo/);
  const blockedPlain = await studio.launch({ mode: "student", confirmed: true });
  eq(blockedPlain.submitted, 0);
  eq(posts("issue").length, 0);
  eq(prints().length, 0);

  arm(FAKE);
  calls.length = 0;
  apiCalls.length = 0;
  await studio.open({ studentId: FAKE, classId: CLASS_ID });
  await settle();
  element("cardsAuthorizedSelect").value = PICKUP;
  const fake = await studio.precontrol({ mode: "student" });
  match(fake.errors.join(" "), /QR non officiel/);
  const fakeLaunch = await studio.launch({ mode: "student", confirmed: true });
  eq(fakeLaunch.submitted, 0);
  eq(posts("issue").length, 0);
  eq(prints().length, 0);

  arm(DENY);
  calls.length = 0;
  apiCalls.length = 0;
  global.SchoolSafeAppContext = { getCurrentUser: function () { return { id: "user" }; } };
  global.SchoolSafeAccess = { explicitDeny: function () { return false; }, canAccess: function () { return false; } };
  await studio.open({ studentId: DENY, classId: CLASS_ID });
  await settle();
  element("cardsAuthorizedSelect").value = PICKUP;
  await studio.precontrol({ mode: "student" });
  const denied = await studio.launch({ mode: "student", confirmed: true });
  eq(denied.reason, "Impression non autorisée");
  eq(prints().length, 0);
  delete global.SchoolSafeAccess;
  delete global.SchoolSafeAppContext;

  arm(LATE);
  calls.length = 0;
  apiCalls.length = 0;
  await studio.open({ studentId: LATE, classId: CLASS_ID });
  await settle();
  element("cardsAuthorizedSelect").value = PICKUP;
  await studio.precontrol({ mode: "student" });
  release.credential = [];
  const late = studio.launch({ mode: "student", confirmed: true });
  await settle();
  session.schoolId = OTHER_SCHOOL;
  release.credential.shift()();
  const lateResult = await late;
  session.schoolId = SCHOOL;
  release = {};
  eq(lateResult.results[0].status, "stale");
  eq(posts("issue").length, 0);
  eq(prints().length, 0);

  arm(RACE);
  calls.length = 0;
  apiCalls.length = 0;
  await studio.open({ studentId: RACE, classId: CLASS_ID });
  await settle();
  element("cardsAuthorizedSelect").value = PICKUP;
  await studio.precontrol({ mode: "student" });
  release.credential = [];
  const firstRace = studio.launch({ mode: "student", confirmed: true });
  const secondRace = await studio.launch({ mode: "student", confirmed: true });
  eq(secondRace.reason, "Production déjà en cours");
  release.credential.forEach(function (done) { done(); });
  release = {};
  await firstRace;
  await settle();
  eq(posts("issue").length, 1, "un double clic n'émet qu'une fois");

  roster = "one";
  focus = CARD;
  element("cardsClassSelect").value = EMPTY;
  element("cardsClassSelect").dispatch("change");
  await settle();
  calls.length = 0;
  apiCalls.length = 0;
  const empty = await studio.precontrol({ mode: "class" });
  eq(empty.total, 0);
  const emptyLaunch = await studio.launch({ mode: "class", confirmed: true });
  eq(emptyLaunch.submitted, 0);
  eq(posts("issue").length, 0);
  eq(prints().length, 0);

  roster = "batch";
  element("cardsClassSelect").value = CLASS_ID;
  element("cardsClassSelect").dispatch("change");
  await settle();
  element("cardsAuthorizedSelect").value = PICKUP;
  calls.length = 0;
  apiCalls.length = 0;
  const batch = await studio.precontrol({ mode: "class" });
  eq(batch.total, 4);
  eq(batch.active, 1);
  eq(batch.firstIssue, 1);
  eq(batch.incomplete, 1);
  eq(batch.printable, 2);
  ok(batch.errors.some(function (line) { return line.indexOf("Lecture refusée") >= 0; }));
  const batchRun = await studio.launch({ mode: "class", confirmed: true });
  eq(batchRun.issued, 1);
  eq(batchRun.submitted, 2);
  eq(posts("issue").length, 1);
  eq(prints().length, 2);
  eq(apiCalls.filter(function (call) { return call.type === "batch"; }).length, 1);
  eq(apiCalls.filter(function (call) { return call.type === "batch"; })[0].input.request_ids.length, 2);
  const batchAgain = await studio.launch({ mode: "class", confirmed: true });
  eq(batchAgain.submitted, 0);
  eq(prints().length, 2, "reprise de classe sans doublon");

  roster = "school";
  calls.length = 0;
  apiCalls.length = 0;
  const school = await studio.precontrol({ mode: "school" });
  eq(school.blocked, true);
  eq(school.total, 249);
  eq(school.foreign, 1);
  eq(school.classes, 3);
  eq(school.active, null);
  match(school.reason, /contrat serveur manquant/);
  match(school.errors.join(" "), /5e B : Capacité non déléguée/);
  eq(calls.some(function (call) { return route(call.url) === "credential"; }), false);
  const schoolLaunch = await studio.launch({ mode: "school", confirmed: true });
  eq(schoolLaunch.blocked, true);
  eq(schoolLaunch.submitted, 0);
  eq(schoolLaunch.issued, 0);
  eq(posts("issue").length, 0);
  eq(prints().length, 0);
  eq(calls.some(function (call) { return call.method === "POST"; }), false);
  calls.length = 0;
  apiCalls.length = 0;
  const directSchool = await studio.launch({ mode: "school", confirmed: true });
  eq(directSchool.blocked, true);
  eq(directSchool.submitted, 0);
  eq(directSchool.issued, 0);
  eq(calls.length, 0, "l'appel direct ne contacte aucun service");
  eq(prints().length, 0);
  const schoolButton = element("cardsModeSchool");
  schoolButton.dispatch("click");
  eq(schoolButton.disabled, true);
  match(schoolButton.textContent, /Non disponible/);
  match(element("cardsStatus").innerHTML, /à venir/);
  eq(calls.length, 0, "le bouton école ne lance rien");
  const page = fs.readFileSync(path.join(__dirname, "index.html"), "utf8");
  match(page, /id="cardsModeSchool"[^>]*disabled/);
  match(page, /Toute l'école — Non disponible/);

  console.log("qa-studio-modes: élève, classe et école bloquée assertions=" + checks);
})().catch(function (error) {
  console.error(error.stack || error.message);
  process.exitCode = 1;
});
