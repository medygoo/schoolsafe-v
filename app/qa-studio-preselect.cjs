"use strict";

const assert = require("node:assert/strict");
const path = require("node:path");
const { pathToFileURL } = require("node:url");

const STUDENT = "aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa";
const OTHER_STUDENT = "dddddddd-dddd-4ddd-8ddd-dddddddddddd";
const CLASS_ID = "bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb";
const SCHOOL = "cccccccc-cccc-4ccc-8ccc-cccccccccccc";
const OTHER_SCHOOL = "eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee";

function Element(id) {
  this.id = id;
  this.hidden = false;
  this.value = "";
  this._html = "";
  this.style = { setProperty: function () {} };
  this.children = [];
  this.listeners = {};
}
Object.defineProperty(Element.prototype, "innerHTML", {
  get: function () { return this._html; },
  set: function (value) { this._html = String(value); this.children = []; }
});
Element.prototype.setAttribute = function () {};
Element.prototype.addEventListener = function (type, fn) { this.listeners[type] = fn; };
Element.prototype.appendChild = function (child) { this.children.push(child); return child; };
Element.prototype.dispatch = function (type) {
  if (this.listeners[type]) this.listeners[type]({ target: this, preventDefault: function () {} });
};

const elements = {};
function element(id) {
  if (!elements[id]) elements[id] = new Element(id);
  return elements[id];
}
["cardsStudio", "closeCardsStudio", "cardsClassSelect", "cardsRenderBtn", "cardsRequestPrintBtn", "cardsSelectAll", "cardsStudentList", "cardsPreview", "cardsStatus", "cardsAuthorizedSelect", "cardsPatStyle", "cardsBuildBatchBtn"].forEach(element);

const session = { schoolId: SCHOOL, profileId: "ffffffff-ffff-4fff-8fff-ffffffffffff" };
const calls = [];
let release = {};

function json(status, body) {
  return { ok: status >= 200 && status < 300, status: status, json: async function () { return body; } };
}

function route(url) {
  if (url.indexOf("/academic-year") >= 0) return "year";
  if (url.indexOf("/record") >= 0) return "record";
  if (url.indexOf("/credential") >= 0) return "credential";
  if (url.indexOf("/family") >= 0) return "family";
  if (url.indexOf("guardian-phones") >= 0) return "phones";
  if (url.indexOf("/pedagogy/classes") >= 0) return "classes";
  if (url.indexOf("class-visual") >= 0) return "visual";
  if (url.indexOf("/students?") >= 0) return "students";
  if (url.indexOf("/cards/school-identity") >= 0) return "settings";
  if (url.indexOf("/school/settings") >= 0) return "settings";
  if (url.indexOf("school-motto") >= 0) return "motto";
  if (url.indexOf("academic-years") >= 0) return "years";
  if (url.indexOf("teacher-assignments") >= 0) return "assignments";
  return url;
}

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
global.SchoolSafeCardAdapter = {
  adaptRosarioCard: function (input) {
    const credential = input.credential || {};
    const qr = /^schoolsafe:\/\/card\/[^/]+\/[^/]+$/.test(credential.qr_payload || "") ? credential.qr_payload : "";
    return {
      student: { id: (input.record || {}).id, qr: qr, first_name: (input.record || {}).first_name || "", last_name: (input.record || {}).last_name || "" },
      classData: {},
      teacher: null,
      year: "",
      school: { logo: "" },
      authorizedPeople: []
    };
  }
};
global.fetch = function (url, options) {
  const entry = { url: String(url), method: (options && options.method) || "GET" };
  calls.push(entry);
  const key = route(entry.url);
  if (release[key]) {
    return new Promise(function (resolve) { release[key].push(function () { resolve(respond(key, entry.url)); }); });
  }
  return Promise.resolve(respond(key, entry.url));
};

function recordFor(id, schoolId) {
  return json(200, { data: { id: id, school_id: schoolId, first_name: "Amina", last_name: "Kabila", matricule: "MAT-A", schooling: { class_id: CLASS_ID, class_name: "6e A" } } });
}

function respond(key, url) {
  if (key === "year") return json(200, { data: { label: "2026-2027" } });
  if (key === "record") {
    if (url.indexOf(OTHER_STUDENT) >= 0) return json(404, { message: "Élève introuvable" });
    return recordFor(STUDENT, SCHOOL);
  }
  if (key === "classes") return json(200, { data: [{ id: CLASS_ID, name: "6e A", cycle_key: "secondary" }] });
  if (key === "visual") return json(200, { data: [] });
  if (key === "students") return json(200, { data: { rows: [{ id: STUDENT, first_name: "Amina", last_name: "Kabila", matricule: "MAT-A" }] } });
  if (key === "credential") return json(200, { data: { card_number: null, qr_payload: "" } });
  if (key === "family" || key === "phones") return json(200, { data: [] });
  if (key === "settings") return json(200, { data: { identity: { name: "Ecole Lumiere" }, brand: {}, contact: { address: "Avenue de la Paix", phone: "+243800", email: "ecole@example.test" } } });
  if (key === "motto") return json(200, { data: { motto: "" } });
  if (key === "years") return json(200, []);
  if (key === "assignments") return json(200, { data: [] });
  return json(500, { message: "route inattendue" });
}

function selectedIds() {
  return element("cardsStudentList").children.filter(function (label) {
    return label.children && label.children[0] && label.children[0].checked;
  }).map(function (label) { return label.children[0].value; });
}

async function flush() {
  await new Promise(function (resolve) { setTimeout(resolve, 0); });
  await new Promise(function (resolve) { setTimeout(resolve, 0); });
}

(async function main() {
  await import(pathToFileURL(path.resolve(__dirname, "modules/cards/cards-module.js")).href);
  assert.equal(typeof global.SchoolSafeCards.open, "function");

  calls.length = 0;
  await global.SchoolSafeCards.open();
  await flush();
  assert.equal(element("cardsClassSelect").value, "");
  assert.deepEqual(selectedIds(), []);
  assert.equal(calls.some(function (call) { return call.method === "POST"; }), false);

  calls.length = 0;
  await global.SchoolSafeCards.open({ studentId: STUDENT, classId: CLASS_ID });
  await flush();
  assert.equal(element("cardsClassSelect").value, CLASS_ID);
  assert.deepEqual(selectedIds(), [STUDENT]);
  assert.equal(calls.some(function (call) { return call.method === "POST"; }), false);
  assert.ok(calls.some(function (call) { return route(call.url) === "credential" && call.method === "GET"; }));
  assert.match(element("cardsStatus").innerHTML, /Carte non encore émise|Aucun aperçu|Aperçu/);

  const originalRespond = respond;
  global.fetch = function (url, options) {
    const entry = { url: String(url), method: (options && options.method) || "GET" };
    calls.push(entry);
    const key = route(entry.url);
    if (key === "record" && entry.url.indexOf(STUDENT) >= 0 && global.__foreign) return Promise.resolve(recordFor(STUDENT, OTHER_SCHOOL));
    if (key === "credential" && global.__withCard) return Promise.resolve(json(200, { data: { card_number: "SS-SERVEUR", qr_payload: "schoolsafe://card/SS-SERVEUR/sig", status: "active" } }));
    if (key === "record" && global.__denied) return Promise.resolve(json(403, { message: "Capacité non déléguée" }));
    if (key === "students" && global.__absent) return Promise.resolve(json(200, { data: { rows: [{ id: OTHER_STUDENT, first_name: "Autre", last_name: "Eleve", matricule: "MAT-X" }] } }));
    if (release[key]) return new Promise(function (resolve) { release[key].push(function () { resolve(originalRespond(key, entry.url)); }); });
    return Promise.resolve(originalRespond(key, entry.url));
  };

  calls.length = 0;
  element("cardsStudentList").children = [];
  element("cardsClassSelect").value = "";
  await global.SchoolSafeCards.open({ studentId: OTHER_STUDENT });
  await flush();
  assert.deepEqual(selectedIds(), []);
  assert.match(element("cardsStatus").innerHTML, /introuvable/i);
  assert.equal(calls.some(function (call) { return call.method === "POST"; }), false);

  global.__foreign = true;
  calls.length = 0;
  element("cardsClassSelect").value = "";
  await global.SchoolSafeCards.open({ studentId: STUDENT, classId: CLASS_ID });
  await flush();
  global.__foreign = false;
  assert.equal(element("cardsClassSelect").value, "");
  assert.deepEqual(selectedIds(), []);
  assert.match(element("cardsStatus").innerHTML, /école active/);

  global.__denied = true;
  await global.SchoolSafeCards.open({ studentId: STUDENT });
  await flush();
  global.__denied = false;
  assert.deepEqual(selectedIds(), []);
  assert.match(element("cardsStatus").innerHTML, /Capacité non déléguée|Accès refusé|refus/i);

  release.record = [];
  const late = global.SchoolSafeCards.open({ studentId: STUDENT, classId: CLASS_ID });
  await flush();
  session.schoolId = OTHER_SCHOOL;
  release.record.shift()();
  await late;
  await flush();
  session.schoolId = SCHOOL;
  assert.deepEqual(selectedIds(), []);
  release = {};

  release.students = [];
  const first = global.SchoolSafeCards.open({ studentId: STUDENT, classId: CLASS_ID });
  await flush();
  const second = global.SchoolSafeCards.open({ studentId: STUDENT, classId: CLASS_ID });
  await flush();
  release.students.forEach(function (done) { done(); });
  release = {};
  await first;
  await second;
  await flush();
  assert.deepEqual(selectedIds(), [STUDENT]);
  assert.equal(calls.filter(function (call) { return call.method === "POST"; }).length, 0);

  global.__withCard = true;
  calls.length = 0;
  await global.SchoolSafeCards.open({ studentId: STUDENT, classId: CLASS_ID });
  await flush();
  assert.deepEqual(selectedIds(), [STUDENT]);
  assert.equal(calls.some(function (call) { return route(call.url) === "credential" && call.method === "POST"; }), false);
  assert.ok(calls.some(function (call) { return route(call.url) === "credential" && call.method === "GET"; }));
  global.__withCard = false;

  calls.length = 0;
  await global.SchoolSafeCards.open({ studentId: STUDENT, classId: "99999999-9999-4999-8999-999999999999" });
  await flush();
  assert.equal(element("cardsClassSelect").value, "");
  assert.deepEqual(selectedIds(), []);
  assert.match(element("cardsStatus").innerHTML, /classe/i);

  release.students = [];
  const duringClassChange = global.SchoolSafeCards.open({ studentId: STUDENT, classId: CLASS_ID });
  await flush();
  element("cardsClassSelect").value = "";
  element("cardsClassSelect").dispatch("change");
  await flush();
  release.students.forEach(function (done) { done(); });
  release = {};
  await duringClassChange;
  await flush();
  assert.deepEqual(selectedIds(), []);
  assert.equal(calls.some(function (call) { return call.method === "POST"; }), false);

  global.__absent = true;
  calls.length = 0;
  await global.SchoolSafeCards.open({ studentId: STUDENT, classId: CLASS_ID });
  await flush();
  global.__absent = false;
  assert.equal(element("cardsClassSelect").value, CLASS_ID);
  assert.deepEqual(selectedIds(), []);
  assert.match(element("cardsStatus").innerHTML, /pas dans la classe autorisée/);
  assert.equal(calls.some(function (call) { return call.method === "POST"; }), false);

  console.log("qa-studio-preselect: classe, élève et aucune émission automatique");
})().catch(function (error) {
  console.error(error.stack || error.message);
  process.exitCode = 1;
});
