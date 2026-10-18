"use strict";

const assert = require("node:assert/strict");
const fs = require("node:fs");
const path = require("node:path");

const root = path.resolve(__dirname, "..");

function read(relativePath) {
  const file = path.join(root, relativePath);
  return fs.existsSync(file) ? fs.readFileSync(file, "utf8") : "";
}

const app = read("app/app.js");
const cards = read("app/modules/cards/cards-module.js");
const wiring = read("app/modules/core/visual-wiring.js");
const css = read("app/styles/modules/visual-wiring.css");
const html = read("app/index.html");
const failures = [];

function check(name, fn) {
  try { fn(); } catch (error) { failures.push(name + ": " + error.message); }
}

const expected = {
  "school|Import massif": "students-import",
  "school|Créer l’élève": "students-create",
  "school|Vérifier les dossiers": "students-drafts",
  "school|Accepter ou refuser": "students-drafts",
  "school|Préinscriptions": "students-drafts",
  "school|Inscriptions": "students-active",
  "school|Inscriptions et réinscriptions": "students-active",
  "school|Parent principal et tuteurs": "guardians",
  "school|Parents et tuteurs": "guardians",
  "school|Affectations": "classes",
  "school|Importer des dossiers": "students-import",
  "school|Présence et passages": "events",
  "school|Emploi du temps": "gap",
  "school|Justifier une absence": "gap",
  "school|Documents": "documents",
  "people|Enseignants": "staff",
  "people|Personnel": "staff",
  "pedagogy|6e A": "classes",
  "pedagogy|5e A": "classes",
  "pedagogy|Classes": "classes",
  "pedagogy|Affectations": "teacher-assignments",
  "pilotage|Approbations": "approvals",
  "security|Rechercher": "events",
  "administration|Documents R2": "documents",
  "administration|Archives": "documents",
  "administration|Mode hors ligne": "sync",
  "administration|Attestations": "gap",
  "administration|Certificats": "gap",
  "administration|Convocations": "gap",
  "administration|Dossiers ENAFEP, TENASOSP et EXETAT": "gap",
  "administration|Contrôle des identités candidats": "gap",
  "administration|Rechercher": "students-search",
  "administration|Français et anglais": "gap",
  "administration|Séparation public et privé": "gap",
  "security|Lockdown": "lockdown",
  "security|Recherche de passage": "events",
  "school|Personnes autorisées de mes enfants": "authorized-persons"
};

check("WRONG_TARGETS", function () {
  const mapStart = wiring.indexOf("var VISUAL_WIRING");
  const mapEnd = wiring.indexOf("function allowed");
  assert.ok(mapStart >= 0 && mapEnd > mapStart, "carte de câblage absente");
  const mapBlock = wiring.slice(mapStart, mapEnd);
  const found = {};
  for (const match of mapBlock.matchAll(/"([^"]+)": "([^"]+)"/g)) found[match[1]] = match[2];
  assert.equal(Object.keys(expected).length, 37);
  assert.deepEqual(found, expected);
});

check("WRONG_TARGETS_COUNT", function () {
  const wrong = Object.entries(expected).filter(function (entry) {
    return entry[0] !== "security|Lockdown" && entry[0] !== "security|Recherche de passage" && entry[0] !== "school|Personnes autorisées de mes enfants";
  });
  assert.equal(wrong.length, 34);
});

check("BUTTONS_MISSING", function () {
  assert.equal(app.includes('["Lockdown","shield-alert"]'), true);
  assert.equal(app.includes('["Recherche de passage","search"]'), true);
  assert.equal(app.includes('["Personnes autorisées de mes enfants","user-round-check"]'), true);
  assert.equal(app.includes("SchoolSafeVisualWiring.open(branchKey, actionName"), true);
});

check("CARD_PROMPT_CONFIRM", function () {
  assert.equal(cards.includes("window.prompt"), false);
  assert.equal(cards.includes("window.confirm"), false);
});
check("CARD_MODALS", function () {
  for (const id of ["loss", "replace", "reprint", "distribute"]) {
    assert.match(cards, new RegExp('data-card-modal="' + id + '"'));
  }
  assert.match(cards, /ssModal/);
  assert.match(cards, /lossReport\(/);
  assert.match(cards, /replaceCard\(/);
  assert.match(cards, /reprintAuthorize\(/);
  assert.match(cards, /markDistributed\(/);
  assert.match(cards, /explicitDeny/);
  assert.match(cards, /setLoading/);
  assert.match(cards, /setError/);
});

check("PERMISSIONS_AND_SURFACES", function () {
for (const code of [
  "school.student.read",
  "school.student.create",
  "school.guardian.read",
  "staff.read",
  "school.class.read",
  "school.structure.manage",
  "pedagogy.assignment.read",
  "pilotage.approvals.read",
  "security.events.read",
  "security.lockdown.manage",
  "security.pickup.read",
  "file.download",
  "sync.submit"
]) {
  assert.match(wiring, new RegExp(code.replace(/\./g, "\\.")));
}
assert.match(wiring, /explicitDeny/);
assert.match(wiring, /canAccess/);
assert.match(wiring, /BACKEND_GAP/);
assert.match(wiring, /LOCKDOWN_ON_CONFIRM_ONLY/);
assert.ok(wiring.indexOf("setLockdown") > wiring.indexOf("LOCKDOWN_ON_CONFIRM_ONLY"));
assert.equal(wiring.includes("renderScanner"), false);
assert.match(wiring, /listEvents/);
assert.match(wiring, /getParentChildren/);
assert.match(wiring, /pickup-authorizations/);
assert.match(wiring, /listTeacherAssignments/);
assert.match(wiring, /listApprovals/);
assert.match(wiring, /own_children/);
assert.equal(wiring.includes("CREATE TABLE"), false);
assert.equal(wiring.includes("Rattrapage pédagogique"), false);
assert.equal(wiring.includes("Versement de rattrapage"), false);
assert.equal(wiring.includes("Présences repas"), false);

});

check("VIEWPORTS", function () {
  assert.match(css, /@media \(min-width: 1440px\)/);
  assert.match(css, /@media \(min-width: 769px\) and \(max-width: 1024px\)/);
  assert.match(css, /@media \(max-width: 390px\)/);
  assert.match(css, /\.wiring-surface/);
  assert.match(html, /visual-wiring\.js/);
  assert.match(html, /visual-wiring\.css/);
  assert.match(html, /id="wiringSurface"/);
});

if (failures.length) {
  console.error(failures.join("\n"));
  process.exit(1);
}

console.log("qa-rosario-visual-wiring: 34 cibles, 3 boutons, 4 modales, 3 viewports");
