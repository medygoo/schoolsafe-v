"use strict";

const assert = require("node:assert/strict");
const fs = require("node:fs");
const path = require("node:path");
const vm = require("node:vm");

const root = path.resolve(__dirname, "..");
const read = (relative) => fs.readFileSync(path.join(root, relative), "utf8");
const STUDENT = "aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa";
const SCHOOL = "bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb";
const OTHER = "cccccccc-cccc-4ccc-8ccc-cccccccccccc";

function slice(source, start, end) {
  const from = source.indexOf(start);
  assert.ok(from >= 0, start);
  const to = source.indexOf(end, from + start.length);
  assert.ok(to > from, end);
  return source.slice(from, to);
}

const cards = read("app/modules/cards/cards-module.js");
const cardView = slice(cards, "async function cardViewFor", "async function renderPreviewForStudent");
assert.equal(cardView.includes("method: 'POST'"), false, "cardViewFor ne doit plus émettre");
assert.match(cardView, /\/credential/);
assert.match(cards, /Carte non encore émise/);
assert.match(cards, /async function openCardsStudio\(options\)/);
assert.match(cards, /window\.SchoolSafeCards = \{ init: initCardsModule, open: openCardsStudio/);
assert.match(read("app/modules/school/student-card-preparation-demo.js"), /id="studentCardOpenStudio"/);
assert.match(read("app/modules/school/student-card-preparation-demo.js"), /SchoolSafeCards\.open\(options\)/);
assert.doesNotMatch(read("app/modules/school/student-dossier-demo.js"), /SchoolSafeCards\.open\(/);

const p2cSource = read("app/modules/core/visual-output-p2c.js");
const p2cContext = {
  console: console,
  SchoolSafeAccess: { explicitDeny: function () { return false; }, canAccess: function () { return true; } },
  fetch: function () { throw new Error("fetch interdit"); }
};
p2cContext.window = p2cContext;
vm.runInNewContext(p2cSource, p2cContext, { filename: "visual-output-p2c.js" });
assert.equal(p2cContext.SchoolSafeVisualOutputP2C.open("school", "Studio des cartes", { user: {}, live: true }), false);
assert.match(read("app/app.js"), /SchoolSafeCards\.open\(\)/);

const scan = read("app/modules/security/scan-control.js");
for (const route of [
  "/native/scan-control/cards/issue",
  "/native/scan-control/cards/loss",
  "/canonical-replace",
  "/reprint",
  "/revoke"
]) assert.match(scan, new RegExp(route.replace(/[.*+?^${}()|[\]\\]/g, "\\$&")));

function Host() { this._html = ""; this._nodes = {}; }
Object.defineProperty(Host.prototype, "innerHTML", {
  get: function () { return this._html; },
  set: function (value) { this._html = String(value); this._nodes = {}; }
});
Host.prototype.node = function (id) {
  if (this._nodes[id]) return this._nodes[id];
  const matchers = {
    "data-card-state": /data-card-state>([^<]*)</,
    "data-card-number": /data-card-number>([^<]*)</,
    studentCardMessage: /id="studentCardMessage"[^>]*>([^<]*)</
  };
  const found = matchers[id] ? matchers[id].exec(this._html) : null;
  let text = found ? found[1] : "";
  const node = {
    id: id,
    disabled: /id="studentCardIssue"[^>]*disabled/.test(this._html),
    checked: false,
    listeners: {},
    addEventListener: function (type, fn) { this.listeners[type] = fn; },
    click: function () { if (this.listeners.click) this.listeners.click({ preventDefault: function () {} }); },
    dispatch: function (type) { if (this.listeners[type]) this.listeners[type]({ target: this }); }
  };
  Object.defineProperty(node, "textContent", { get: function () { return text; }, set: function (value) { text = String(value); } });
  this._nodes[id] = node;
  return node;
};
Host.prototype.querySelector = function (selector) {
  if (selector === "[data-card-state]") return this._html.includes("data-card-state") ? this.node("data-card-state") : null;
  if (selector === "[data-card-number]") return this._html.includes("data-card-number") ? this.node("data-card-number") : null;
  const id = /#([A-Za-z0-9_-]+)/.exec(selector);
  if (!id || this._html.indexOf('id="' + id[1] + '"') < 0) return null;
  return this.node(id[1]);
};

function boot(fetchImpl) {
  const calls = [];
  const session = { schoolId: SCHOOL };
  const context = {
    console: console,
    schoolSafeApiBase: "http://api.test",
    navigator: { onLine: true },
    sessionStorage: { getItem: function () { return JSON.stringify(session); } },
    SchoolSafeAccess: {
      explicitDeny: function () { return false; },
      canAccess: function (user, permission) { return Array.isArray(user.permissions) && user.permissions.indexOf(permission) >= 0; }
    },
    ssBadge: function (badge) { return "<b>" + badge.label + "</b>"; },
    ssModal: function (options) {
      const content = new Host();
      content.innerHTML = options.content;
      return { content: content, isOpen: function () { return true; } };
    },
    fetch: function (url, options) {
      calls.push({ url: String(url), options: options || {} });
      return Promise.resolve(fetchImpl(String(url), options || {}, calls));
    }
  };
  context.window = context;
  vm.runInNewContext(read("app/modules/school/student-card-preparation-demo.js"), context, { filename: "student-card-preparation-demo.js" });
  return { context: context, calls: calls, session: session };
}

function student(patch) {
  return Object.assign({
    id: STUDENT,
    first_name: "Amina",
    last_name: "Kabila",
    matricule: "MAT-A",
    lifecycle_status: "active",
    class_id: "class-1",
    school_id: SCHOOL
  }, patch || {});
}

function user(permissions) {
  return {
    permissions: permissions,
    scopes: permissions.map(function (permission) { return { permission: permission, type: "school" }; })
  };
}

function jsonResponse(status, body) {
  return { ok: status >= 200 && status < 300, status: status, json: async function () { return body; } };
}

function emptyCredential() {
  return jsonResponse(200, { data: { card_number: null, signature: null, qr_payload: "" } });
}

function capabilities(list) {
  return jsonResponse(200, { data: { capabilities: list } });
}

const authorized = user(["school.student.read", "cards.request.print"]);

function route(url) {
  if (url.indexOf("/credential") >= 0) return "credential";
  if (url.indexOf("/capabilities") >= 0) return "capabilities";
  if (url.indexOf("/cards/issue") >= 0) return "issue";
  return url;
}

async function flush() { await new Promise(function (resolve) { setTimeout(resolve, 0); }); }

async function openAuthorized(fetchImpl) {
  const session = boot(fetchImpl || function (url) {
    if (route(url) === "credential") return emptyCredential();
    if (route(url) === "capabilities") return capabilities(["CARD_CREATE"]);
    return jsonResponse(500, { message: "route inattendue" });
  });
  const modal = session.context.SchoolSafeStudentCardPreparation.open(student(), authorized);
  await flush();
  return { session: session, modal: modal };
}

(async function main() {
  const missing = await openAuthorized();
  assert.equal(missing.modal.content.querySelector("[data-card-state]").textContent, "Aucune carte émise");
  assert.equal(missing.session.calls.some(function (call) { return (call.options.method || "GET") === "POST"; }), false);
  assert.ok(missing.modal.content.querySelector("#studentCardIssue"));
  const unconfirmed = missing.modal.content.querySelector("#studentCardIssue");
  unconfirmed.click();
  await flush();
  assert.equal(missing.session.calls.some(function (call) { return route(call.url) === "issue"; }), false);

  const issued = await openAuthorized(function (url, options, calls) {
    if (route(url) === "issue") return jsonResponse(200, { data: { card_number: "SS-NE-PAS-AFFICHER" } });
    if (route(url) === "credential" && (options.method || "GET") === "GET") {
      const prior = calls.filter(function (call) { return route(call.url) === "issue"; }).length;
      if (prior) return jsonResponse(200, { data: { card_number: "SS-SERVEUR", qr_payload: "schoolsafe://card/SS-SERVEUR/sig", status: "active" } });
      return emptyCredential();
    }
    if (route(url) === "capabilities") return capabilities(["CARD_CREATE"]);
    return jsonResponse(500, { message: "route inattendue" });
  });
  const confirm = issued.modal.content.querySelector("#studentCardConfirm");
  const button = issued.modal.content.querySelector("#studentCardIssue");
  confirm.checked = true;
  confirm.dispatch("change");
  button.click();
  button.click();
  await flush();
  await flush();
  const posts = issued.session.calls.filter(function (call) { return route(call.url) === "issue"; });
  assert.equal(posts.length, 1);
  assert.equal(posts[0].options.method, "POST");
  assert.deepEqual(JSON.parse(posts[0].options.body), { student_id: STUDENT });
  assert.match(posts[0].options.headers && posts[0].options.headers["Content-Type"] || "", /application\/json/);
  assert.equal(issued.modal.content.querySelector("[data-card-state]").textContent, "Carte active");
  assert.equal(issued.modal.content.querySelector("[data-card-number]").textContent, "SS-SERVEUR");
  assert.equal(issued.modal.content.innerHTML.includes("SS-NE-PAS-AFFICHER"), false);
  assert.equal(issued.modal.content.innerHTML.includes("schoolsafe://card/"), false);

  const existing = await openAuthorized(function (url) {
    if (route(url) === "credential") return jsonResponse(200, { data: { card_number: "SS-DEJA", qr_payload: "schoolsafe://card/SS-DEJA/sig", status: "active" } });
    if (route(url) === "capabilities") return capabilities(["CARD_CREATE"]);
    return jsonResponse(500, { message: "route inattendue" });
  });
  assert.equal(existing.modal.content.querySelector("[data-card-state]").textContent, "Carte active");
  assert.equal(existing.modal.content.querySelector("#studentCardIssue"), null);
  assert.equal(existing.session.calls.some(function (call) { return route(call.url) === "issue"; }), false);

  const refused = await openAuthorized(function (url, options) {
    if (route(url) === "issue") return jsonResponse(403, { message: "Capacité non déléguée" });
    if (route(url) === "credential") return emptyCredential();
    if (route(url) === "capabilities") return capabilities(["CARD_CREATE"]);
    return jsonResponse(500, { message: "route inattendue" });
  });
  const refusedConfirm = refused.modal.content.querySelector("#studentCardConfirm");
  refusedConfirm.checked = true;
  refusedConfirm.dispatch("change");
  refused.modal.content.querySelector("#studentCardIssue").click();
  await flush();
  await flush();
  assert.match(refused.modal.content.innerHTML, /Capacité non déléguée/);
  assert.equal(refused.modal.content.innerHTML.includes("Première carte émise"), false);
  assert.equal(refused.modal.content.querySelector("[data-card-number]"), null);

  const noCreate = boot(function (url) {
    if (route(url) === "credential") return emptyCredential();
    if (route(url) === "capabilities") return capabilities(["CARD_PRINT"]);
    return jsonResponse(500, {});
  });
  const noCreateModal = noCreate.context.SchoolSafeStudentCardPreparation.open(student(), authorized);
  await flush();
  assert.equal(noCreateModal.content.querySelector("#studentCardIssue"), null);

  const noPrint = boot(function (url) {
    if (route(url) === "credential") return emptyCredential();
    if (route(url) === "capabilities") return capabilities(["CARD_CREATE"]);
    return jsonResponse(500, {});
  });
  const noPrintModal = noPrint.context.SchoolSafeStudentCardPreparation.open(student(), user(["school.student.read"]));
  await flush();
  assert.equal(noPrintModal.content.querySelector("#studentCardIssue"), null);

  const foreign = boot(function () { throw new Error("fetch interdit"); });
  const foreignModal = foreign.context.SchoolSafeStudentCardPreparation.open(student({ school_id: OTHER }), authorized);
  await flush();
  assert.equal(foreign.calls.length, 0);
  assert.match(foreignModal.content.innerHTML, /école active/);
  assert.equal(foreignModal.content.querySelector("#studentCardIssue"), null);

  const offline = boot(function () { throw new Error("réseau"); });
  offline.context.navigator.onLine = false;
  const offlineModal = offline.context.SchoolSafeStudentCardPreparation.open(student(), authorized);
  await flush();
  assert.equal(offline.calls.length, 0);
  assert.match(offlineModal.content.innerHTML, /Réseau absent/);
  assert.equal(offlineModal.content.innerHTML.includes("Première carte émise"), false);

  let releaseIssue;
  const late = boot(function (url, options) {
    if (route(url) === "issue") return new Promise(function (resolve) { releaseIssue = function () { resolve(jsonResponse(200, { data: { card_number: "SS-TARD" } })); }; });
    if (route(url) === "credential") return emptyCredential();
    if (route(url) === "capabilities") return capabilities(["CARD_CREATE"]);
    return jsonResponse(500, {});
  });
  const lateModal = late.context.SchoolSafeStudentCardPreparation.open(student(), authorized);
  await flush();
  const lateConfirm = lateModal.content.querySelector("#studentCardConfirm");
  lateConfirm.checked = true;
  lateConfirm.dispatch("change");
  lateModal.content.querySelector("#studentCardIssue").click();
  late.session.schoolId = OTHER;
  releaseIssue();
  await flush();
  await flush();
  assert.equal(lateModal.content.innerHTML.includes("Première carte émise"), false);
  assert.equal(lateModal.content.innerHTML.includes("SS-TARD"), false);

  const CLASS_ID = "dddddddd-dddd-4ddd-8ddd-dddddddddddd";
  const studio = boot(function (url) {
    if (route(url) === "credential") return emptyCredential();
    if (route(url) === "capabilities") return capabilities(["CARD_CREATE"]);
    return jsonResponse(500, { message: "route inattendue" });
  });
  const opened = [];
  studio.context.SchoolSafeCards = { open: function (options) { opened.push(options); } };
  const studioModal = studio.context.SchoolSafeStudentCardPreparation.open(student({ schooling: { class_id: CLASS_ID } }), authorized);
  await flush();
  const studioButton = studioModal.content.querySelector("#studentCardOpenStudio");
  assert.ok(studioButton);
  studioButton.click();
  studioButton.click();
  assert.equal(opened.length, 2);
  assert.equal(opened[0].studentId, STUDENT);
  assert.equal(opened[0].classId, CLASS_ID);
  assert.equal(opened[1].studentId, STUDENT);
  assert.equal(opened[1].classId, CLASS_ID);
  assert.equal(studio.calls.some(function (call) { return route(call.url) === "issue"; }), false);

  console.log("qa-first-card-ui: dossier, studio réel et aucune émission automatique");
})().catch(function (error) {
  console.error(error.stack || error.message);
  process.exitCode = 1;
});
