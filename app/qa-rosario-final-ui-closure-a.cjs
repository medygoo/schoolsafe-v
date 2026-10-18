"use strict";

const assert = require("node:assert/strict");
const fs = require("node:fs");
const path = require("node:path");
const vm = require("node:vm");

const root = path.resolve(__dirname, "..");
const failures = [];

function read(relativePath) {
  const file = path.join(root, relativePath);
  return fs.existsSync(file) ? fs.readFileSync(file, "utf8") : "";
}

function check(name, fn) {
  try { fn(); } catch (error) { failures.push(name + ": " + error.message); }
}

const closure = read("app/modules/core/visual-output-closure-a.js");
const css = read("app/styles/modules/visual-output-closure-a.css");
const html = read("app/index.html");
const wiring = read("app/modules/core/visual-wiring.js");
const p1 = read("app/modules/core/visual-output-p1.js");
const p2a = read("app/modules/core/visual-output-p2a.js");
const p2b = read("app/modules/core/visual-output-p2b.js");
const p2c = read("app/modules/core/visual-output-p2c.js");

const STUDENT = "aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa";
const OTHER = "99999999-9999-4999-8999-999999999999";
const CLASS_ID = "bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb";
const SUBJECT = "cccccccc-cccc-4ccc-8ccc-cccccccccccc";
const PROFILE = "dddddddd-dddd-4ddd-8ddd-dddddddddddd";
const YEAR = "eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee";
const ROLE = "ffffffff-ffff-4fff-8fff-ffffffffffff";
const FEE = "11111111-1111-4111-8111-111111111111";
const CAMPAIGN = "22222222-2222-4222-8222-222222222222";
const ALERT = "33333333-3333-4333-8333-333333333333";
const DOC = "44444444-4444-4444-8444-444444444444";
const AUTH = "55555555-5555-4555-8555-555555555555";
const ASSIGNMENT = "66666666-6666-4666-8666-666666666666";
const RANKING = "77777777-7777-4777-8777-777777777777";
const PLAN = "88888888-8888-4888-8888-888888888888";

check("MODULE_MISSING", function () {
  assert.ok(closure.length > 0, "visual-output-closure-a.js absent");
  assert.ok(css.length > 0, "visual-output-closure-a.css absent");
});

check("GAP_ROUTES", function () {
  assert.match(closure, /\/native\/finance\/fee-control\/campaigns/);
  assert.match(closure, /\/native\/finance\/fee-control\/scans/);
  assert.match(closure, /\/documents\/create/);
  assert.match(closure, /\/documents\//);
  assert.match(closure, /\/issue/);
  assert.match(closure, /\/school\/academic-years/);
  assert.match(closure, /\/activate/);
  assert.match(closure, /\/school\/settings/);
  assert.match(closure, /\/school\/logo/);
  assert.match(closure, /\/native\/family\/pickup-authorizations/);
  assert.match(closure, /\/validate/);
  assert.match(closure, /\/revoke/);
  assert.match(closure, /\/native\/devicehub\/devices/);
  assert.match(closure, /\/native\/devicehub\/events/);
  assert.match(closure, /\/native\/devicehub\/mappings/);
  assert.match(closure, /\/native\/devicehub\/attendance\/apply/);
  assert.match(closure, /\/native\/pedagogy\/parent\/children/);
  assert.match(closure, /\/native\/pedagogy\/parent\/grades\//);
  assert.match(p2a, /\/native\/pedagogy\/teacher-assignments\/homeroom/);
  assert.match(p2a, /\/school\/staff\/invite/);
  assert.match(p2a, /\/resend-invite/);
  assert.match(p2a, /\/roles/);
  assert.match(p2c, /\/publish/);
  assert.match(p2c, /\/grades\/publish/);
  assert.match(p2c, /\/stars/);
  assert.match(p2c, /method: "PATCH"/);
  assert.match(p2c, /method: "DELETE"/);
  assert.match(p2c, /\/fees"/);
  assert.match(p2c, /\/resolve/);
  assert.match(p2b, /Historique des passages/);
  assert.match(p2c, /Alertes et anomalies/);
  assert.match(closure, /Rapports financiers/);
  assert.equal(p1.includes("Historique du jour"), false);
});

check("NO_INVENTED", function () {
  for (const token of ["create table", "schoolsafe://card/", "syscohada", "rattrapage", "jaspe"]) {
    assert.equal(closure.toLowerCase().includes(token), false, token);
  }
  assert.equal(closure.includes("is_tutor"), false);
  assert.equal(closure.includes("DÉMONSTRATION"), false);
  assert.equal(closure.includes("DONNÉES DOCUMENTAIRES INDISPONIBLES"), false);
  assert.equal(closure.includes("BACKEND_LATER"), false);
  assert.equal(/\bpaie\b/.test(closure.toLowerCase()), false);
});

check("PERMISSIONS", function () {
  for (const code of [
    "finance.control.read",
    "finance.control.manage",
    "finance.control.scan",
    "school.manage",
    "school.guardian.manage",
    "security.pickup.read",
    "school.student.read",
    "school.student.create",
    "security.scan",
    "pedagogy.grade.read",
    "own_children"
  ]) {
    assert.match(closure, new RegExp(code.replace(/\./g, "\\.")));
  }
  assert.match(closure, /explicitDeny/);
  assert.match(closure, /canAccess/);
  assert.match(p2c, /pilotage\.alerts\.manage/);
  assert.match(p2a, /staff\.manage/);
  assert.match(p2a, /school\.structure\.manage/);
});

check("NAVIGATION", function () {
  assert.match(wiring, /SchoolSafeVisualOutputClosureA\.open\(branchKey, actionName/);
  assert.ok(wiring.indexOf("SchoolSafeVisualOutputClosureA.open") > wiring.indexOf("SchoolSafeVisualOutputP2C.open"));
  assert.ok(wiring.indexOf("VISUAL_WIRING[") > wiring.indexOf("SchoolSafeVisualOutputClosureA.open"));
  assert.match(html, /visual-output-closure-a\.js/);
  assert.match(html, /visual-output-closure-a\.css/);
  assert.ok(html.indexOf("visual-output-closure-a.js") < html.indexOf("visual-wiring.js"));
  assert.ok(html.indexOf("visual-output-p2c.js") < html.indexOf("visual-output-closure-a.js"));
});

check("VIEWPORTS", function () {
  assert.match(css, /@media \(min-width: 1440px\)/);
  assert.match(css, /@media \(min-width: 769px\) and \(max-width: 1024px\)/);
  assert.match(css, /@media \(max-width: 390px\)/);
  assert.match(css, /\.cla-screen/);
  assert.match(css, /button \{ width: 100% \}/);
});

function createHost() {
  const host = { id: "wiringSurface", hidden: true, history: [], _html: "", _nodes: {} };
  Object.defineProperty(host, "innerHTML", {
    get: function () { return host._html; },
    set: function (value) {
      host._html = String(value);
      host.history.push(host._html);
      host._nodes = {};
    }
  });
  host.querySelector = function (selector) {
    const match = /#([A-Za-z0-9_-]+)/.exec(selector || "");
    if (!match || host._html.indexOf('id="' + match[1] + '"') < 0) return null;
    if (!host._nodes[match[1]]) {
      host._nodes[match[1]] = {
        id: match[1],
        value: "",
        files: [],
        listeners: {},
        addEventListener: function (type, fn) { this.listeners[type] = fn; },
        getAttribute: function () { return null; }
      };
    }
    return host._nodes[match[1]];
  };
  return host;
}

function boot(scripts, access, fetchImpl) {
  const host = createHost();
  const calls = [];
  const context = {
    console: console,
    setTimeout: setTimeout,
    clearTimeout: clearTimeout,
    URLSearchParams: URLSearchParams,
    FormData: function FormData() { this._parts = []; this.append = function (name, value) { this._parts.push([name, value]); }; },
    schoolSafeApiBase: "http://api.test",
    sessionStorage: { getItem: function () { return JSON.stringify({ token: "live-token" }); } },
    fetch: function (url, options) {
      calls.push({ url: String(url), options: options || {} });
      return Promise.resolve(fetchImpl(String(url), options || {}, calls.length - 1));
    },
    SchoolSafeAccess: access,
    ssState: function (state) {
      return '<p class="ss-state ss-state--' + state.type + '"><b>' + state.title + "</b> " + (state.message || "") + "</p>";
    },
    ssButton: function (button) {
      const id = button.attrs && button.attrs.id ? button.attrs.id : "";
      return '<button type="' + (button.type || "button") + '" id="' + id + '">' + button.label + "</button>";
    }
  };
  context.window = context;
  context.document = {
    getElementById: function (id) {
      if (id === "wiringSurface") return host;
      return host.querySelector("#" + id);
    }
  };
  scripts.forEach(function (source, index) {
    vm.runInNewContext(source, context, { filename: "closure-" + index + ".js" });
  });
  return { host: host, calls: calls, context: context };
}

function allowAll() {
  return {
    explicitDeny: function () { return false; },
    canAccess: function () { return true; },
    allowsScope: function () { return false; },
    scopeFor: function () { return null; }
  };
}

function deny(code) {
  return {
    explicitDeny: function (_user, permission) { return permission === code; },
    canAccess: function () { return true; },
    allowsScope: function () { return false; },
    scopeFor: function () { return null; }
  };
}

function jsonResponse(status, body) {
  return { ok: status >= 200 && status < 300, status: status, json: function () { return Promise.resolve(body); } };
}

function flush() {
  return new Promise(function (resolve) { setTimeout(resolve, 0); });
}

function click(session, id) {
  const node = session.host.querySelector("#" + id);
  assert.ok(node, id);
  const event = { preventDefault: function () {} };
  if (node.listeners.click) node.listeners.click(event);
  if (node.listeners.submit) node.listeners.submit(event);
}

function set(session, id, value) {
  const node = session.host.querySelector("#" + id);
  assert.ok(node, id);
  node.value = value;
}

function live(extra) {
  return Object.assign({ user: { role: "school_head" }, live: true, hideDashboard: function () {}, restoreDashboard: function () {} }, extra || {});
}

async function main() {
  check("FOREIGN_STAYS", function () {
    const session = boot([closure], allowAll(), function () { return jsonResponse(200, { data: [] }); });
    assert.equal(session.context.SchoolSafeVisualOutputClosureA.open("pedagogy", "Rattrapage pédagogique", live()), false);
    assert.equal(session.context.SchoolSafeVisualOutputClosureA.open("school", "Studio des cartes", live()), false);
    assert.equal(session.context.SchoolSafeVisualOutputClosureA.open("security", "Scanner un QR", live()), false);
    assert.equal(session.calls.length, 0);
  });

  const fee = boot([closure], allowAll(), function (url, options) {
    if ((options.method || "GET") === "POST" && url.indexOf("/scans") >= 0) return jsonResponse(201, { data: { id: "scan-1" } });
    if ((options.method || "GET") === "POST") return jsonResponse(201, { data: { id: CAMPAIGN } });
    return jsonResponse(200, { data: [{ id: CAMPAIGN, label: "Minerval octobre", status: "open" }] });
  });
  fee.context.SchoolSafeVisualOutputClosureA.open("finance", "Contrôle des frais", live());
  await flush();
  check("GAP01_CAMPAIGNS", function () {
    assert.match(fee.calls[0].url, /\/native\/finance\/fee-control\/campaigns/);
    assert.ok(fee.host.history.some(function (html) { return html.indexOf("ss-state--loading") >= 0; }));
    assert.match(fee.host.innerHTML, /Minerval octobre/);
    assert.match(fee.host.innerHTML, /open/);
    assert.equal(/d[ée]monstration/i.test(fee.host.innerHTML), false);
    assert.equal(fee.host.innerHTML.includes("schoolsafe://card/"), false);
  });
  set(fee, "claCampaignStructure", FEE);
  set(fee, "claCampaignLabel", "Cantine controle");
  click(fee, "claCampaignCreate");
  click(fee, "claCampaignYes");
  await flush();
  check("GAP01_CAMPAIGN_WRITE", function () {
    const post = fee.calls.find(function (call) { return call.options.method === "POST" && call.url.indexOf("/campaigns") >= 0; });
    const body = JSON.parse(post.options.body);
    assert.equal(body.fee_structure_id, FEE);
    assert.equal(body.label, "Cantine controle");
    assert.match(fee.host.innerHTML, /ss-state--success/);
  });
  fee.context.SchoolSafeVisualOutputClosureA.open("finance", "Contrôle des frais", live());
  await flush();
  set(fee, "claScanCampaign", CAMPAIGN);
  set(fee, "claScanStudent", STUDENT);
  set(fee, "claScanResult", "seen");
  click(fee, "claScanCreate");
  click(fee, "claScanYes");
  await flush();
  check("GAP01_SCAN_WRITE", function () {
    const post = fee.calls.find(function (call) { return call.options.method === "POST" && call.url.indexOf("/scans") >= 0; });
    const body = JSON.parse(post.options.body);
    assert.equal(body.campaign_id, CAMPAIGN);
    assert.equal(body.student_id, STUDENT);
    assert.equal(body.result, "seen");
    assert.match(fee.host.innerHTML, /scan-1/);
  });

  const feeDeny = boot([closure], deny("finance.control.read"), function () { throw new Error("fetch"); });
  feeDeny.context.SchoolSafeVisualOutputClosureA.open("finance", "Contrôle des frais", live());
  check("GAP01_DENY", function () {
    assert.match(feeDeny.host.innerHTML, /finance\.control\.read/);
    assert.equal(feeDeny.calls.length, 0);
  });

  const feeDemo = boot([closure], allowAll(), function () { throw new Error("fetch"); });
  feeDemo.context.SchoolSafeVisualOutputClosureA.open("finance", "Contrôle des frais", { user: { role: "finance_manager" }, live: false });
  check("GAP01_DEMO", function () {
    assert.equal(feeDemo.calls.length, 0);
    assert.match(feeDemo.host.innerHTML, /Session réelle requise/);
  });

  const feeEmpty = boot([closure], allowAll(), function () { return jsonResponse(200, { data: [] }); });
  feeEmpty.context.SchoolSafeVisualOutputClosureA.open("finance", "Contrôle des frais", live());
  await flush();
  check("GAP01_EMPTY", function () {
    assert.match(feeEmpty.host.innerHTML, /ss-state--empty/);
  });

  const feeError = boot([closure], allowAll(), function () { return jsonResponse(500, { message: "echec campagne" }); });
  feeError.context.SchoolSafeVisualOutputClosureA.open("finance", "Contrôle des frais", live());
  await flush();
  check("GAP01_ERROR", function () {
    assert.match(feeError.host.innerHTML, /ss-state--error/);
    assert.match(feeError.host.innerHTML, /echec campagne/);
  });

  const docs = boot([closure], allowAll(), function (url, options) {
    if (url.indexOf("/issue") >= 0) return jsonResponse(200, { status: "ISSUED" });
    return jsonResponse(201, { document_id: DOC, code: "ATT-1", sequence: 1, status: "DRAFT" });
  });
  docs.context.SchoolSafeVisualOutputClosureA.open("administration", "Centre documentaire", live());
  check("GAP02_SURFACE", function () {
    assert.equal(docs.calls.length, 0);
    assert.equal(docs.host.innerHTML.includes("DONNÉES DOCUMENTAIRES INDISPONIBLES"), false);
    assert.equal(docs.host.innerHTML.includes("BACKEND_LATER"), false);
  });
  set(docs, "claDocYear", YEAR);
  set(docs, "claDocType", "ATT");
  click(docs, "claDocCreate");
  click(docs, "claDocYes");
  await flush();
  check("GAP02_CREATE", function () {
    const post = docs.calls.find(function (call) { return call.options.method === "POST" && call.url.indexOf("/documents/create") >= 0; });
    const body = JSON.parse(post.options.body);
    assert.equal(body.academicYearId, YEAR);
    assert.equal(body.typeCode, "ATT");
    assert.match(post.options.headers.Authorization, /Bearer live-token/);
    assert.match(docs.host.innerHTML, /ATT-1/);
    assert.match(docs.host.innerHTML, /ss-state--success/);
  });
  set(docs, "claDocId", DOC);
  click(docs, "claDocIssue");
  click(docs, "claDocIssueYes");
  await flush();
  check("GAP02_ISSUE", function () {
    const post = docs.calls.find(function (call) { return call.url.indexOf("/issue") >= 0; });
    assert.equal(post.options.method, "POST");
    assert.match(post.url, new RegExp("/documents/" + DOC + "/issue"));
    assert.match(docs.host.innerHTML, /ISSUED/);
  });

  const years = boot([closure], allowAll(), function (url, options) {
    if (url.indexOf("/activate") >= 0) return jsonResponse(200, { status: "ok" });
    if (options.method === "POST") return jsonResponse(201, { id: YEAR });
    if (options.method === "PUT") return jsonResponse(200, { status: "ok" });
    if (url.indexOf("/school/settings") >= 0) {
      return jsonResponse(200, { identity: { name: "Ecole Lumiere", legal_name: "Ecole Lumiere ASBL" }, contact: { address: "Avenue", city: "Kinshasa" } });
    }
    return jsonResponse(200, [{ id: YEAR, label: "2025-2026", starts_on: "2025-09-01", ends_on: "2026-06-30", periods: "Trimestres", is_active: false }]);
  });
  years.context.SchoolSafeVisualOutputClosureA.open("school", "Année scolaire", live());
  await flush();
  check("GAP03_YEARS", function () {
    assert.match(years.calls[0].url, /\/school\/academic-years/);
    assert.match(years.host.innerHTML, /2025-2026/);
  });
  set(years, "claYearLabel", "2026-2027");
  set(years, "claYearStart", "2026-09-01");
  set(years, "claYearEnd", "2027-06-30");
  set(years, "claYearPeriods", "Trimestres");
  click(years, "claYearCreate");
  click(years, "claYearYes");
  await flush();
  check("GAP03_YEAR_CREATE", function () {
    const post = years.calls.find(function (call) { return call.options.method === "POST" && call.url.indexOf("/academic-years") >= 0 && call.url.indexOf("activate") < 0; });
    const body = JSON.parse(post.options.body);
    assert.equal(body.label, "2026-2027");
    assert.equal(body.periods, "Trimestres");
    assert.match(years.host.innerHTML, /ss-state--success/);
  });
  years.context.SchoolSafeVisualOutputClosureA.open("school", "Année scolaire", live());
  await flush();
  click(years, "claYearActivate0");
  click(years, "claYearActivateYes");
  await flush();
  check("GAP03_ACTIVATE", function () {
    assert.ok(years.calls.some(function (call) { return call.options.method === "POST" && call.url.indexOf("/activate") >= 0; }));
  });
  years.context.SchoolSafeVisualOutputClosureA.open("administration", "Paramètres", live());
  await flush();
  check("GAP03_IDENTITY", function () {
    assert.ok(years.calls.some(function (call) { return call.url.indexOf("/school/settings") >= 0; }));
    assert.match(years.host.innerHTML, /Ecole Lumiere/);
  });
  set(years, "claSchoolName", "Ecole Lumiere");
  set(years, "claLegalName", "ASBL Lumiere");
  click(years, "claIdentitySave");
  click(years, "claIdentityYes");
  await flush();
  check("GAP03_IDENTITY_WRITE", function () {
    const put = years.calls.find(function (call) { return call.options.method === "PUT" && call.url.indexOf("/school/settings") >= 0; });
    const body = JSON.parse(put.options.body);
    assert.equal(body.identity.name, "Ecole Lumiere");
    assert.equal(body.identity.legal_name, "ASBL Lumiere");
  });

  const yearDeny = boot([closure], deny("school.manage"), function () { throw new Error("fetch"); });
  yearDeny.context.SchoolSafeVisualOutputClosureA.open("school", "Année scolaire", live());
  check("GAP03_DENY", function () {
    assert.match(yearDeny.host.innerHTML, /school\.manage/);
    assert.equal(yearDeny.calls.length, 0);
  });

  const home = boot([p2a], allowAll(), function (url, options) {
    if (url.indexOf("/homeroom") >= 0) return jsonResponse(200, { data: { id: "home-1" } });
    return jsonResponse(200, { data: [{ id: "asg", teacher_name: "Jeanne Mbayo", class_name: "6e A", subject_name: "Mathématiques" }] });
  });
  home.context.SchoolSafeVisualOutputP2A.open("pedagogy", "Affectations", live());
  await flush();
  check("GAP04_BUTTON", function () {
    assert.match(home.host.innerHTML, /Définir le titulaire/);
    assert.equal(home.host.innerHTML.includes("is_tutor"), false);
  });
  const form = home.host.querySelector("#p2aHomeroomForm");
  assert.ok(form);
  form.elements = {
    profile_id: { value: PROFILE },
    subject_id: { value: SUBJECT },
    class_id: { value: CLASS_ID },
    academic_year_id: { value: YEAR }
  };
  form.listeners = home.host.querySelector("#p2aHomeroomForm").listeners;
  if (form.listeners.submit) form.listeners.submit({ preventDefault: function () {}, target: form });
  await flush();
  check("GAP04_WRITE_REFRESH", function () {
    const post = home.calls.find(function (call) { return call.options.method === "POST" && call.url.indexOf("/homeroom") >= 0; });
    const body = JSON.parse(post.options.body);
    assert.deepEqual(body, { profile_id: PROFILE, subject_id: SUBJECT, class_id: CLASS_ID, academic_year_id: YEAR });
    assert.ok(home.calls.filter(function (call) { return call.url.indexOf("/teacher-assignments") >= 0 && (call.options.method || "GET") === "GET"; }).length >= 2);
    assert.equal(home.host.innerHTML.includes("is_tutor"), false);
    assert.match(home.host.innerHTML, /Jeanne Mbayo/);
  });

  const pickup = boot([closure], allowAll(), function (url, options) {
    if ((options.method || "GET") === "POST" && url.indexOf("/validate") >= 0) return jsonResponse(200, { data: { status: "active" } });
    if ((options.method || "GET") === "POST" && url.indexOf("/revoke") >= 0) return jsonResponse(200, { data: { status: "revoked" } });
    if ((options.method || "GET") === "POST") return jsonResponse(201, { data: { id: AUTH, status: "pending" } });
    if (url.indexOf("/native/students?") >= 0) return jsonResponse(200, { data: { rows: [{ id: STUDENT, first_name: "Amina", last_name: "Kabila" }] } });
    if (url.indexOf("pickup-authorizations") >= 0) {
      return jsonResponse(200, { data: { family: [], accredited: [{ authorization_id: AUTH, name: "Oncle Kabila", status: "pending" }] } });
    }
    return jsonResponse(200, { data: [] });
  });
  pickup.context.SchoolSafeVisualOutputClosureA.open("school", "Personnes autorisées", live());
  await flush();
  check("GAP05_STUDENTS", function () {
    assert.match(pickup.host.innerHTML, /Amina/);
    assert.equal(pickup.host.innerHTML.includes("Contrôle Gardien"), false);
    assert.equal(pickup.calls.some(function (call) { return call.url.indexOf("/security/scan") >= 0; }), false);
  });
  click(pickup, "claPickupStudent0");
  await flush();
  check("GAP05_LIST", function () {
    assert.ok(pickup.calls.some(function (call) { return call.url.indexOf("/native/family/students/" + STUDENT + "/pickup-authorizations") >= 0; }));
    assert.match(pickup.host.innerHTML, /Oncle Kabila/);
  });
  set(pickup, "claPickupGuardian", PROFILE);
  click(pickup, "claPickupCreate");
  click(pickup, "claPickupYes");
  await flush();
  check("GAP05_CREATE", function () {
    const post = pickup.calls.find(function (call) { return call.options.method === "POST" && call.url.indexOf("/pickup-authorizations") >= 0 && call.url.indexOf("validate") < 0 && call.url.indexOf("revoke") < 0; });
    const body = JSON.parse(post.options.body);
    assert.equal(body.student_id, STUDENT);
    assert.equal(body.guardian_id, PROFILE);
  });
  click(pickup, "claPickupValidate0");
  click(pickup, "claPickupValidateYes");
  await flush();
  check("GAP05_VALIDATE", function () {
    assert.ok(pickup.calls.some(function (call) { return call.options.method === "POST" && call.url.indexOf("/" + AUTH + "/validate") >= 0; }));
  });

  const pickupDeny = boot([closure], deny("security.pickup.read"), function (url) {
    if (url.indexOf("/native/students?") >= 0) return jsonResponse(200, { data: { rows: [{ id: STUDENT, first_name: "Amina", last_name: "Kabila" }] } });
    return jsonResponse(200, { data: [] });
  });
  pickupDeny.context.SchoolSafeVisualOutputClosureA.open("school", "Personnes autorisées", live({ user: { role: "admissions" } }));
  await flush();
  click(pickupDeny, "claPickupStudent0");
  await flush();
  check("GAP05_READ_DENY", function () {
    assert.equal(pickupDeny.calls.some(function (call) { return call.url.indexOf("pickup-authorizations") >= 0; }), false);
  });

  const publish = boot([p2c], allowAll(), function (url, options) {
    if (options.method === "PATCH") return jsonResponse(200, { data: { updated: true } });
    if (options.method === "DELETE") return jsonResponse(200, { data: { deleted: true } });
    if ((options.method || "GET") === "POST") return jsonResponse(200, { data: { published: true, id: "star-1" } });
    if (url.indexOf("/stars") >= 0) return jsonResponse(200, { data: [{ student_id: STUDENT }] });
    if (url.indexOf("/grades") >= 0) return jsonResponse(200, { data: [{ student_name: "Amina Kabila", score: 14, matricule: "MAT-1" }] });
    if (url.indexOf("/lesson-plans") >= 0) return jsonResponse(200, { data: [{ id: PLAN, title: "Semaine 12", content: "Texte" }] });
    if (url.indexOf("/rankings/") >= 0) return jsonResponse(200, { data: { id: RANKING, month: "2026-03", status: "draft", class_name: "6e A", data: { students: [{ student_id: STUDENT, weighted_average: 15 }] } } });
    if (url.indexOf("/rankings") >= 0) return jsonResponse(200, { data: [{ id: RANKING, month: "2026-03", status: "draft", class_name: "6e A" }] });
    if (url.indexOf("/assignments") >= 0) return jsonResponse(200, { data: [{ id: ASSIGNMENT, title: "Devoir 1", type: "homework" }] });
    return jsonResponse(200, { data: [] });
  });
  publish.context.SchoolSafeVisualOutputP2C.open("pedagogy", "Devoirs et corrections", live());
  await flush();
  click(publish, "p2cAssignPublish0");
  click(publish, "p2cAssignPublishYes");
  await flush();
  check("GAP06_ASSIGNMENT_PUBLISH", function () {
    const post = publish.calls.find(function (call) { return call.options.method === "POST" && call.url.indexOf("/assignments/" + ASSIGNMENT + "/publish") >= 0; });
    assert.ok(post);
    assert.match(publish.host.innerHTML, /ss-state--success/);
  });
  publish.context.SchoolSafeVisualOutputP2C.open("pedagogy", "Devoirs et corrections", live());
  await flush();
  click(publish, "p2cAssignEdit0");
  set(publish, "p2cEditTitle", "Devoir relu");
  set(publish, "p2cEditMax", "20");
  set(publish, "p2cEditCoef", "2");
  click(publish, "p2cAssignEditYes");
  await flush();
  check("GAP06_ASSIGNMENT_PATCH", function () {
    const patch = publish.calls.find(function (call) { return call.options.method === "PATCH"; });
    const body = JSON.parse(patch.options.body);
    assert.equal(body.title, "Devoir relu");
    assert.equal(body.max_score, 20);
    assert.equal(body.coefficient, 2);
  });
  publish.context.SchoolSafeVisualOutputP2C.open("pedagogy", "Évaluations et notes", live());
  await flush();
  click(publish, "p2cGrade0");
  await flush();
  click(publish, "p2cGradePublish");
  click(publish, "p2cGradePublishYes");
  await flush();
  check("GAP06_GRADES_PUBLISH", function () {
    assert.ok(publish.calls.some(function (call) { return call.options.method === "POST" && call.url.indexOf("/grades/publish") >= 0; }));
    assert.equal(publish.host.innerHTML.includes("moyenne"), false);
  });
  publish.context.SchoolSafeVisualOutputP2C.open("pedagogy", "Palmarès", live());
  await flush();
  click(publish, "p2cRanking0");
  await flush();
  click(publish, "p2cRankPublish");
  click(publish, "p2cRankPublishYes");
  await flush();
  check("GAP06_RANK_PUBLISH", function () {
    assert.ok(publish.calls.some(function (call) { return call.options.method === "POST" && call.url.indexOf("/rankings/" + RANKING + "/publish") >= 0; }));
  });
  publish.context.SchoolSafeVisualOutputP2C.open("pedagogy", "Palmarès", live());
  await flush();
  click(publish, "p2cRanking0");
  await flush();
  click(publish, "p2cStarLoad");
  await flush();
  check("GAP06_STARS", function () {
    assert.ok(publish.calls.some(function (call) { return call.url.indexOf("/rankings/" + RANKING + "/stars") >= 0 && (call.options.method || "GET") === "GET"; }));
    assert.match(publish.host.innerHTML, /aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa|Amina|étoile|Etoile/i);
  });
  publish.context.SchoolSafeVisualOutputP2C.open("pedagogy", "Cahiers de préparation", live());
  await flush();
  click(publish, "p2cPlanDelete0");
  click(publish, "p2cPlanDeleteYes");
  await flush();
  check("GAP06_PLAN_DELETE", function () {
    assert.ok(publish.calls.some(function (call) { return call.options.method === "DELETE" && call.url.indexOf("/lesson-plans/" + PLAN) >= 0; }));
  });

  const assignFee = boot([p2c], allowAll(), function (url, options) {
    if ((options.method || "GET") === "POST" && url.indexOf("/students/") >= 0) return jsonResponse(201, { data: { id: "sf-1" } });
    if ((options.method || "GET") === "POST") return jsonResponse(201, { data: { id: FEE } });
    return jsonResponse(200, { data: [{ id: FEE, label: "Minerval", amount: 50, currency: "USD" }] });
  });
  assignFee.context.SchoolSafeVisualOutputP2C.open("finance", "Types de frais", live());
  await flush();
  set(assignFee, "p2cFeeStudent", STUDENT);
  set(assignFee, "p2cFeeStructure", FEE);
  set(assignFee, "p2cFeeAssignYear", YEAR);
  click(assignFee, "p2cFeeAssign");
  click(assignFee, "p2cFeeAssignYes");
  await flush();
  check("GAP07_ASSIGN", function () {
    const post = assignFee.calls.find(function (call) { return call.options.method === "POST" && call.url.indexOf("/students/" + STUDENT + "/fees") >= 0; });
    const body = JSON.parse(post.options.body);
    assert.equal(body.fee_structure_id, FEE);
    assert.equal(body.academic_year_id, YEAR);
    assert.match(assignFee.host.innerHTML, /ss-state--success/);
  });

  const staff = boot([p2a], allowAll(), function (url, options) {
    if (url.indexOf("/invite") >= 0) return jsonResponse(201, { id: PROFILE });
    if (url.indexOf("/resend-invite") >= 0) return jsonResponse(200, { status: "ok" });
    if (url.indexOf("/roles") >= 0 && options.method === "PUT") return jsonResponse(200, { status: "ok" });
    if (url.indexOf("/school/staff/" + PROFILE) >= 0) {
      return jsonResponse(200, { id: PROFILE, display_name: "Jeanne Mbayo", email: "jeanne@example.test", is_active: true, roles: [{ code: "teacher", label: "Enseignant" }] });
    }
    return jsonResponse(200, [{ id: PROFILE, display_name: "Jeanne Mbayo", roles: [{ code: "teacher" }] }]);
  });
  staff.context.SchoolSafeVisualOutputP2A.open("people", "Personnel", live());
  await flush();
  const invite = staff.host.querySelector("#p2aInviteForm");
  invite.elements = {
    email: { value: "nouveau@example.test" },
    first_name: { value: "Awa" },
    last_name: { value: "Kabila" },
    role_id: { value: ROLE }
  };
  invite.listeners.submit({ preventDefault: function () {}, target: invite });
  click(staff, "p2aInviteYes");
  await flush();
  check("GAP08_INVITE", function () {
    const post = staff.calls.find(function (call) { return call.options.method === "POST" && call.url.indexOf("/invite") >= 0; });
    const body = JSON.parse(post.options.body);
    assert.equal(body.email, "nouveau@example.test");
    assert.deepEqual(body.role_ids, [ROLE]);
    assert.match(staff.host.innerHTML, /ss-state--success/);
  });
  staff.context.SchoolSafeVisualOutputP2A.open("people", "Personnel", live());
  await flush();
  click(staff, "p2aStaff0");
  await flush();
  click(staff, "p2aResend");
  click(staff, "p2aResendYes");
  await flush();
  check("GAP08_RESEND", function () {
    assert.ok(staff.calls.some(function (call) { return call.options.method === "POST" && call.url.indexOf("/resend-invite") >= 0; }));
  });
  staff.context.SchoolSafeVisualOutputP2A.open("people", "Personnel", live());
  await flush();
  click(staff, "p2aStaff0");
  await flush();
  const roles = staff.host.querySelector("#p2aRolesForm");
  roles.elements = { role_id: { value: ROLE } };
  roles.listeners.submit({ preventDefault: function () {}, target: roles });
  click(staff, "p2aRolesYes");
  await flush();
  check("GAP08_ROLES", function () {
    const put = staff.calls.find(function (call) { return call.options.method === "PUT" && call.url.indexOf("/roles") >= 0; });
    assert.deepEqual(JSON.parse(put.options.body), { role_ids: [ROLE] });
  });

  const devices = boot([closure], allowAll(), function (url, options) {
    if (url.indexOf("/devices") >= 0) return jsonResponse(201, { data: { device_id: "dev-1", status: "registered" } });
    if (url.indexOf("/mappings") >= 0) return jsonResponse(200, { data: { mapping_id: "map-1" } });
    if (url.indexOf("/events") >= 0) return jsonResponse(200, { data: { event_id: "evt-1" } });
    if (url.indexOf("/attendance/apply") >= 0) return jsonResponse(200, { data: { applied: 1 } });
    return jsonResponse(200, { data: [] });
  });
  devices.context.SchoolSafeVisualOutputClosureA.open("people", "Biométrie", live());
  check("GAP09_SURFACE", function () {
    assert.equal(devices.calls.length, 0);
    assert.match(devices.host.innerHTML, /claDeviceCode|appareil/i);
    assert.equal(/personnel du module|fiche personnel/i.test(devices.host.innerHTML), false);
  });
  set(devices, "claDeviceCode", "PORTE-1");
  set(devices, "claDeviceVendor", "ZK");
  set(devices, "claDeviceModel", "K40");
  set(devices, "claDeviceSerial", "SN-100");
  click(devices, "claDeviceSave");
  click(devices, "claDeviceYes");
  await flush();
  check("GAP09_DEVICE", function () {
    const post = devices.calls.find(function (call) { return call.options.method === "POST" && call.url.indexOf("/native/devicehub/devices") >= 0; });
    const body = JSON.parse(post.options.body);
    assert.equal(body.code, "PORTE-1");
    assert.equal(body.serial_number, "SN-100");
    assert.match(devices.host.innerHTML, /dev-1/);
  });
  devices.context.SchoolSafeVisualOutputClosureA.open("people", "Présence du personnel", live());
  set(devices, "claApplyDay", "2026-10-08");
  click(devices, "claApplySave");
  click(devices, "claApplyYes");
  await flush();
  check("GAP09_APPLY", function () {
    const post = devices.calls.find(function (call) { return call.url.indexOf("/attendance/apply") >= 0; });
    assert.equal(post.options.method, "POST");
    assert.equal(JSON.parse(post.options.body).day, "2026-10-08");
  });

  const events = boot([p2b], allowAll(), function () {
    return jsonResponse(200, { data: [{ id: "ev1", event_type: "entry", decision: "allowed", occurred_at: "2026-10-08T08:00:00Z" }], count: 1 });
  });
  events.context.SchoolSafeVisualOutputP2B.open("security", "Historique des passages", live());
  await flush();
  check("GAP10_EVENTS", function () {
    assert.match(events.calls[0].url, /\/security\/events/);
    assert.match(events.host.innerHTML, /entry/);
  });

  const alertsAlias = boot([p2c], allowAll(), function () {
    return jsonResponse(200, { data: [{ id: ALERT, status: "open", severity: "critical", source_module: "security" }], count: 1 });
  });
  alertsAlias.context.SchoolSafeVisualOutputP2C.open("security", "Alertes et anomalies", live());
  await flush();
  check("GAP10_ALERTS", function () {
    assert.match(alertsAlias.calls[0].url, /\/pilotage\/alerts\?/);
    assert.equal(alertsAlias.calls[0].url.includes("domain=finance"), false);
    assert.match(alertsAlias.host.innerHTML, /critical/);
  });

  const daily = boot([p1, closure], allowAll(), function (url) {
    if (url.indexOf("/reports/daily") >= 0) return jsonResponse(200, { date: "2026-10-08", total_paid: 10, payment_count: 1 });
    return jsonResponse(200, {});
  });
  assert.equal(daily.context.SchoolSafeVisualOutputP1.open("finance", "Historique du jour", live()), false);
  daily.context.SchoolSafeVisualOutputClosureA.open("finance", "Historique du jour", live());
  set(daily, "p1ReportDate", "2026-10-08");
  const reportForm = daily.host.querySelector("#p1ReportForm");
  reportForm.elements = { date: { value: "2026-10-08" } };
  reportForm.listeners.submit({ preventDefault: function () {}, target: { elements: { date: { value: "2026-10-08" } } } });
  await flush();
  check("GAP10_DAILY", function () {
    assert.ok(daily.calls.some(function (call) { return call.url.indexOf("/native/finance/reports/daily?date=2026-10-08") >= 0; }));
    assert.match(daily.host.innerHTML, /2026-10-08/);
  });

  const parent = boot([closure], allowAll(), function (url) {
    if (url.indexOf("/parent/children") >= 0) {
      return jsonResponse(200, { data: [
        { id: STUDENT, first_name: "Amina", last_name: "Kabila" },
        { id: OTHER, first_name: "Autre", last_name: "Enfant" }
      ] });
    }
    if (url.indexOf("/parent/grades/" + STUDENT) >= 0) {
      return jsonResponse(200, { data: [{ title: "Interrogation", subject_name: "Mathématiques", score: 14, max_score: 20, coefficient: 2, published: true }] });
    }
    if (url.indexOf("/averages") >= 0) return jsonResponse(500, { message: "moyenne interdite" });
    return jsonResponse(500, { message: url });
  });
  parent.context.SchoolSafeVisualOutputClosureA.open("school", "Résultats", live({ user: { role: "parent", childIds: [STUDENT] } }));
  await flush();
  check("GAP11_OWN_CHILD", function () {
    assert.match(parent.host.innerHTML, /Amina/);
    assert.equal(parent.host.innerHTML.includes("Autre"), false);
  });
  click(parent, "claChild0");
  await flush();
  check("GAP11_GRADES", function () {
    assert.ok(parent.calls.some(function (call) { return call.url.indexOf("/native/pedagogy/parent/grades/" + STUDENT) >= 0; }));
    assert.equal(parent.calls.some(function (call) { return call.url.indexOf(OTHER) >= 0 || call.url.indexOf("/averages") >= 0; }), false);
    assert.match(parent.host.innerHTML, /14/);
    assert.match(parent.host.innerHTML, /Mathématiques/);
  });
  const parentDeny = boot([closure], deny("pedagogy.grade.read"), function () { throw new Error("fetch"); });
  parentDeny.context.SchoolSafeVisualOutputClosureA.open("school", "Résultats", live({ user: { role: "parent", childIds: [STUDENT] } }));
  check("GAP11_DENY", function () {
    assert.match(parentDeny.host.innerHTML, /pedagogy\.grade\.read/);
    assert.equal(parentDeny.calls.length, 0);
  });

  const resolve = boot([p2c], allowAll(), function (url, options) {
    if ((options.method || "GET") === "POST") return jsonResponse(200, { data: { status: "resolved" } });
    return jsonResponse(200, { data: [{ id: ALERT, status: "open", severity: "important", source_module: "security" }], count: 1 });
  });
  resolve.context.SchoolSafeVisualOutputP2C.open("pilotage", "Alertes", live());
  await flush();
  click(resolve, "p2cAlertResolve0");
  set(resolve, "p2cAlertResolveCode", "done");
  set(resolve, "p2cAlertResolveNote", "Traité");
  click(resolve, "p2cAlertResolveYes");
  await flush();
  check("GAP12_RESOLVE", function () {
    const post = resolve.calls.find(function (call) { return call.options.method === "POST" && call.url.indexOf("/resolve") >= 0; });
    const body = JSON.parse(post.options.body);
    assert.equal(body.resolution_code, "done");
    assert.equal(body.note, "Traité");
    assert.ok(resolve.calls.filter(function (call) { return call.url.indexOf("/pilotage/alerts?") >= 0; }).length >= 2);
  });
  const resolveDeny = boot([p2c], deny("pilotage.alerts.manage"), function () {
    return jsonResponse(200, { data: [{ id: ALERT, status: "open", severity: "important", source_module: "security" }], count: 1 });
  });
  resolveDeny.context.SchoolSafeVisualOutputP2C.open("pilotage", "Alertes", live());
  await flush();
  check("GAP12_DENY", function () {
    assert.equal(resolveDeny.host.querySelector("#p2cAlertResolve0"), null);
  });

  check("BACK", function () {
    click(fee, "wiringBack");
    assert.equal(fee.host.innerHTML, "");
  });

  if (failures.length) {
    console.error(failures.join("\n"));
    process.exit(1);
  }
  console.log("qa-rosario-final-ui-closure-a: 12 écarts, session réelle, 3 viewports");
}

main().catch(function (error) {
  console.error(error);
  process.exit(1);
});
