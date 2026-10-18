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

const p2 = read("app/modules/core/visual-output-p2a.js");
const css = read("app/styles/modules/visual-output-p2a.css");
const html = read("app/index.html");
const wiring = read("app/modules/core/visual-wiring.js");
const p1 = read("app/modules/core/visual-output-p1.js");

check("MODULE_MISSING", function () {
  assert.ok(p2.length > 0, "visual-output-p2a.js absent");
  assert.ok(css.length > 0, "visual-output-p2a.css absent");
});

check("EXACT_APIS", function () {
  assert.match(p2, /\/native\/students\?status=/);
  assert.match(p2, /\/native\/students\/drafts/);
  assert.match(p2, /\/native\/students\//);
  assert.match(p2, /\/record/);
  assert.match(p2, /\/completeness/);
  assert.match(p2, /\/native\/family\/student-imports/);
  assert.match(p2, /\/family/);
  assert.match(p2, /\/guardians/);
  assert.match(p2, /\/set-primary/);
  assert.match(p2, /\/emergency-contacts/);
  assert.match(p2, /\/school\/staff/);
  assert.match(p2, /\/native\/pedagogy\/classes/);
  assert.match(p2, /\/native\/pedagogy\/teacher-assignments/);
  assert.match(p2, /\/pilotage\/approvals/);
  assert.match(p2, /\/decide/);
  assert.equal(p2.includes("/native/canteen"), false);
  assert.equal(p2.includes("pickup-authorizations"), false);
  assert.equal(p2.includes("/accept"), false);
  assert.equal(p2.includes("/refuse"), false);
});

check("NO_INVENTED_DOMAINS", function () {
  for (const token of ["paie", "salaire", "biom", "contrat", "cantine", "infirmerie", "rattrapage", "scanner", "CREATE TABLE", "Lucas Martin", "Ethan", "DÉMO"]) {
    assert.equal(p2.toLowerCase().includes(token.toLowerCase()), false, token);
  }
});

check("PERMISSIONS", function () {
  for (const code of [
    "school.student.read",
    "school.student.create",
    "school.guardian.read",
    "school.guardian.manage",
    "staff.manage",
    "school.class.read",
    "school.structure.manage",
    "pilotage.approvals.read",
    "pilotage.approvals.manage"
  ]) {
    assert.match(p2, new RegExp(code.replace(/\./g, "\\.")));
  }
  assert.match(p2, /explicitDeny/);
  assert.match(p2, /canAccess/);
});

check("NAVIGATION", function () {
  assert.match(wiring, /SchoolSafeVisualOutputP1\.open\(branchKey, actionName/);
  assert.match(wiring, /SchoolSafeVisualOutputP2A\.open\(branchKey, actionName/);
  assert.match(html, /visual-output-p2a\.js/);
  assert.match(html, /visual-output-p2a\.css/);
  assert.match(p1, /SchoolSafeVisualOutputP1/);
});

check("VIEWPORTS", function () {
  assert.match(css, /@media \(min-width: 1440px\)/);
  assert.match(css, /@media \(min-width: 769px\) and \(max-width: 1024px\)/);
  assert.match(css, /@media \(max-width: 390px\)/);
  assert.match(css, /\.p2a-screen/);
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
        listeners: {},
        addEventListener: function (type, fn) { this.listeners[type] = fn; },
        getAttribute: function () { return null; }
      };
    }
    return host._nodes[match[1]];
  };
  return host;
}

function boot(access, fetchImpl) {
  const host = createHost();
  const calls = [];
  const context = {
    console: console,
    setTimeout: setTimeout,
    clearTimeout: clearTimeout,
    URLSearchParams: URLSearchParams,
    schoolSafeApiBase: "http://api.test",
    sessionStorage: {
      getItem: function () { return JSON.stringify({ token: "live-token" }); }
    },
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
  vm.runInNewContext(p2, context, { filename: "visual-output-p2a.js" });
  return {
    host: host,
    calls: calls,
    open: function (branch, action, extra) {
      return context.SchoolSafeVisualOutputP2A.open(branch, action, Object.assign({
        user: { role: "school_head" },
        live: true,
        hideDashboard: function () {},
        restoreDashboard: function () {}
      }, extra || {}));
    }
  };
}

function allowAll() {
  return {
    explicitDeny: function () { return false; },
    canAccess: function () { return true; }
  };
}

function deny(code) {
  return {
    explicitDeny: function (_user, permission) { return permission === code; },
    canAccess: function () { return true; }
  };
}

function allowExcept(codes) {
  const blocked = Array.isArray(codes) ? codes : [codes];
  return {
    explicitDeny: function () { return false; },
    canAccess: function (_user, permission) { return blocked.indexOf(permission) < 0; }
  };
}

function jsonResponse(status, body) {
  return {
    ok: status >= 200 && status < 300,
    status: status,
    json: function () { return Promise.resolve(body); }
  };
}

function flush() {
  return new Promise(function (resolve) { setTimeout(resolve, 0); });
}

function click(session, id) {
  const node = session.host.querySelector("#" + id);
  assert.ok(node && node.listeners.click, id);
  node.listeners.click({ preventDefault: function () {}, target: node });
}

function submit(session, id, fields) {
  const form = session.host.querySelector("#" + id);
  assert.ok(form && form.listeners.submit, id);
  const elements = {};
  Object.keys(fields).forEach(function (name) { elements[name] = { value: fields[name] }; });
  form.listeners.submit({ preventDefault: function () {}, target: { elements: elements } });
}

const studentId = "22222222-2222-4222-8222-222222222222";
const classId = "55555555-5555-4555-8555-555555555555";
const yearId = "66666666-6666-4666-8666-666666666666";
const profileId = "77777777-7777-4777-8777-777777777777";
const subjectId = "88888888-8888-4888-8888-888888888888";
const assignmentId = "99999999-9999-4999-8999-999999999999";
const approvalId = "aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa";
const guardianId = "bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb";

function checkAsync(name, fn) {
  return fn().catch(function (error) { failures.push(name + ": " + (error && error.stack ? error.stack : error)); });
}

async function runBehaviors() {
  await checkAsync("DEMO_HAS_NO_DATA", async function () {
    const session = boot(allowAll(), function () { throw new Error("fetch interdit"); });
    assert.equal(session.open("school", "Préinscriptions", { live: false }), true);
    assert.match(session.host.innerHTML, /Session réelle requise/);
    assert.equal(session.calls.length, 0);
    assert.equal(session.host.innerHTML.includes("Lucas"), false);
  });

  await checkAsync("EXPLICIT_DENY", async function () {
    const session = boot(deny("school.student.read"), function () { throw new Error("fetch interdit"); });
    session.open("school", "Préinscriptions", { live: true });
    assert.match(session.host.innerHTML, /Accès refusé/);
    assert.match(session.host.innerHTML, /school\.student\.read/);
    assert.equal(session.calls.length, 0);
  });

  await checkAsync("ADMISSIONS_LIST_DOSSIER", async function () {
    const session = boot(allowAll(), function (url) {
      if (url.indexOf("/native/students?status=draft") === 0) {
        return jsonResponse(200, { data: { total: 1, rows: [{ id: studentId, matricule: "MAT-1", first_name: "Awa", last_name: "Kabila", lifecycle_status: "draft" }], limit: 50, offset: 0 } });
      }
      if (url === "/native/students/" + studentId + "/record") {
        return jsonResponse(200, { data: { id: studentId, matricule: "MAT-1", first_name: "Awa", last_name: "Kabila", lifecycle_status: "draft", schooling: { class_name: "6e A", academic_year_id: yearId } } });
      }
      if (url === "/native/students/" + studentId + "/completeness") {
        return jsonResponse(200, { data: { status: "INCOMPLETE", completed_checkpoints: 4, total_checkpoints: 12, missing: ["class_missing"] } });
      }
      return jsonResponse(500, { message: "inattendu " + url });
    });
    session.open("school", "Vérifier les dossiers", { live: true });
    assert.ok(session.host.history.some(function (item) { return /Chargement/.test(item); }));
    await flush();
    assert.equal(session.calls[0].url, "/native/students?status=draft&limit=50&offset=0");
    assert.match(session.host.innerHTML, /MAT-1/);
    click(session, "p2aOpen0");
    await flush();
    assert.equal(session.calls[1].url, "/native/students/" + studentId + "/record");
    assert.equal(session.calls[2].url, "/native/students/" + studentId + "/completeness");
    assert.match(session.host.innerHTML, /INCOMPLETE/);
    assert.match(session.host.innerHTML, /6e A/);
    assert.equal(session.host.innerHTML.includes("Lucas"), false);
  });

  await checkAsync("ADMISSIONS_EMPTY_AND_ERROR", async function () {
    const empty = boot(allowAll(), function () { return jsonResponse(200, { data: { total: 0, rows: [], limit: 50, offset: 0 } }); });
    empty.open("school", "Inscriptions", { live: true });
    await flush();
    assert.match(empty.host.innerHTML, /Aucun élève/);
    const failed = boot(allowAll(), function () { return jsonResponse(500, { message: "Lecture impossible" }); });
    failed.open("school", "Inscriptions", { live: true });
    await flush();
    assert.match(failed.host.innerHTML, /Lecture impossible/);
  });

  await checkAsync("ADMISSIONS_NO_DECISION_ROUTE", async function () {
    const session = boot(allowAll(), function (url) {
      if (url.indexOf("status=draft") >= 0) return jsonResponse(200, { data: { total: 1, rows: [{ id: studentId, matricule: "MAT-1", first_name: "Awa", last_name: "Kabila", lifecycle_status: "draft" }] } });
      if (url.indexOf("/record") >= 0) return jsonResponse(200, { data: { matricule: "MAT-1", lifecycle_status: "draft" } });
      if (url.indexOf("/completeness") >= 0) return jsonResponse(200, { data: { status: "INCOMPLETE", completed_checkpoints: 1, total_checkpoints: 12, missing: [] } });
      return jsonResponse(500, { message: url });
    });
    session.open("school", "Accepter ou refuser", { live: true });
    await flush();
    click(session, "p2aOpen0");
    await flush();
    assert.match(session.host.innerHTML, /Aucune route de décision/);
    assert.equal(session.host.innerHTML.includes("Approuver"), false);
    assert.equal(session.calls.some(function (call) { return call.options.method && call.options.method !== "GET"; }), false);
  });

  await checkAsync("ADMISSIONS_CREATE", async function () {
    const session = boot(allowAll(), function (url, options) {
      assert.equal(url, "/native/students/drafts");
      assert.equal(options.method, "POST");
      return jsonResponse(201, { data: { id: studentId } });
    });
    session.open("school", "Créer l’élève", { live: true });
    submit(session, "p2aCreateForm", {
      matricule: "MAT-9",
      first_name: "Awa",
      middle_name: "",
      last_name: "Kabila",
      date_of_birth: "",
      gender: "",
      academic_year_id: yearId,
      planned_class_id: classId,
      enrollment_starts_on: "2026-09-01"
    });
    await flush();
    const body = JSON.parse(session.calls[0].options.body);
    assert.deepEqual(Object.keys(body).sort(), ["academic_year_id", "enrollment_starts_on", "first_name", "last_name", "matricule", "planned_class_id"]);
    assert.match(session.host.innerHTML, /Brouillon créé/);
  });

  await checkAsync("FAMILY_READ_AND_PRIMARY", async function () {
    const session = boot(allowAll(), function (url, options) {
      if (url === "/native/students/" + studentId + "/family" && (!options.method || options.method === "GET")) {
        return jsonResponse(200, { data: [
          { id: guardianId, full_name: "Marie Kabila", guardian_type: "mere", is_primary: true, is_active: true },
          { id: "cccccccc-cccc-4ccc-8ccc-cccccccccccc", full_name: "Paul Kabila", guardian_type: "pere", is_primary: false, is_active: true }
        ] });
      }
      if (url === "/native/students/" + studentId + "/emergency-contacts") {
        return jsonResponse(200, { data: [{ id: "dddddddd-dddd-4ddd-8ddd-dddddddddddd", full_name: "Claire Kabila", relation: "tante", phone: "+243800000000", slot_no: 1 }] });
      }
      if (url === "/native/students/" + studentId + "/guardians/cccccccc-cccc-4ccc-8ccc-cccccccccccc/set-primary") {
        assert.equal(options.method, "POST");
        return jsonResponse(200, { data: { is_primary: true } });
      }
      return jsonResponse(500, { message: url });
    });
    session.open("school", "Parents et tuteurs", { live: true });
    submit(session, "p2aFamilyForm", { student_id: studentId });
    await flush();
    assert.match(session.host.innerHTML, /Marie Kabila/);
    assert.match(session.host.innerHTML, /Parent principal/);
    assert.match(session.host.innerHTML, /Paul Kabila/);
    assert.match(session.host.innerHTML, /Claire Kabila/);
    click(session, "p2aPrimary1");
    await flush();
    assert.match(session.host.innerHTML, /Parent principal enregistré/);
  });

  await checkAsync("FAMILY_DENY_MANAGE", async function () {
    const session = boot(deny("school.guardian.manage"), function (url) {
      if (url.indexOf("/family") >= 0) return jsonResponse(200, { data: [] });
      if (url.indexOf("/emergency-contacts") >= 0) return jsonResponse(200, { data: [] });
      return jsonResponse(500, { message: url });
    });
    session.open("school", "Parent principal et tuteurs", { live: true });
    submit(session, "p2aFamilyForm", { student_id: studentId });
    await flush();
    assert.equal(session.host.innerHTML.includes("p2aGuardianForm"), false);
    assert.match(session.host.innerHTML, /Aucun tuteur/);
  });

  await checkAsync("STAFF_LIST_AND_FICHE", async function () {
    const session = boot(allowAll(), function (url, options) {
      if (url === "http://api.test/school/staff" && (!options.method || options.method === "GET")) {
        assert.equal(options.headers.Authorization, "Bearer live-token");
        return jsonResponse(200, [{ id: profileId, display_name: "Jeanne Mbayo", first_name: "Jeanne", last_name: "Mbayo", is_active: true, roles: [{ code: "teacher", label: "Enseignant" }] }]);
      }
      if (url === "http://api.test/school/staff/" + profileId && (!options.method || options.method === "GET")) {
        return jsonResponse(200, { id: profileId, display_name: "Jeanne Mbayo", email: "jeanne@example.test", phone: "+243811111111", is_active: true, roles: [{ code: "teacher", label: "Enseignant" }], scopes: [{ scope_type: "school", label: "École" }] });
      }
      if (url === "http://api.test/school/staff/" + profileId + "/toggle") {
        assert.equal(options.method, "POST");
        assert.deepEqual(JSON.parse(options.body), { is_active: false });
        return jsonResponse(200, { status: "ok" });
      }
      return jsonResponse(500, { message: url });
    });
    session.open("people", "Personnel", { live: true });
    await flush();
    assert.match(session.host.innerHTML, /Jeanne Mbayo/);
    click(session, "p2aStaff0");
    await flush();
    assert.match(session.host.innerHTML, /jeanne@example.test/);
    assert.equal(/paie|salaire|biom|contrat/i.test(session.host.innerHTML), false);
    click(session, "p2aToggle");
    click(session, "p2aToggleYes");
    await flush();
    assert.match(session.host.innerHTML, /Statut enregistré/);
  });

  await checkAsync("STAFF_DENY", async function () {
    const session = boot(deny("staff.manage"), function () { throw new Error("fetch interdit"); });
    session.open("people", "Enseignants", { live: true });
    assert.match(session.host.innerHTML, /staff\.manage/);
    assert.equal(session.calls.length, 0);
  });

  await checkAsync("CLASS_NAMED_PAGE", async function () {
    const session = boot(allowAll(), function (url) {
      if (url === "/native/pedagogy/classes") {
        return jsonResponse(200, { data: [
          { id: classId, name: "6e A", cycle_key: "secondaire", option: null, academic_year_id: yearId, is_active: true },
          { id: "eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee", name: "5e A", cycle_key: "secondaire", option: null, academic_year_id: yearId, is_active: true }
        ] });
      }
      if (url === "/native/students?status=active&class_id=" + classId + "&limit=50&offset=0") {
        return jsonResponse(200, { data: { rows: [{ id: studentId, matricule: "MAT-1", first_name: "Awa", last_name: "Kabila" }] } });
      }
      if (url === "/native/pedagogy/teacher-assignments") {
        return jsonResponse(200, { data: [
          { id: assignmentId, class_id: classId, teacher_name: "Jeanne Mbayo", subject_name: "Mathématiques", class_name: "6e A" },
          { id: "ffffffff-ffff-4fff-8fff-ffffffffffff", class_id: "eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee", teacher_name: "Autre", subject_name: "Français", class_name: "5e A" }
        ] });
      }
      return jsonResponse(500, { message: url });
    });
    session.open("pedagogy", "6e A", { live: true });
    await flush();
    await flush();
    assert.equal(session.host.querySelector("#p2aClassTitle") !== null || /6e A/.test(session.host.innerHTML), true);
    assert.match(session.host.innerHTML, /id="p2aClassTitle"/);
    assert.match(session.host.innerHTML, />6e A</);
    assert.equal(session.host.innerHTML.includes(">5e A<"), false);
    assert.match(session.host.innerHTML, new RegExp(yearId));
    assert.match(session.host.innerHTML, /Awa Kabila/);
    assert.match(session.host.innerHTML, /Jeanne Mbayo/);
    assert.equal(session.host.innerHTML.includes("Autre"), false);
  });

  await checkAsync("CLASS_LIST", async function () {
    const session = boot(allowExcept("school.structure.manage"), function (url) {
      if (url === "/native/pedagogy/classes") return jsonResponse(200, { data: [] });
      return jsonResponse(500, { message: url });
    });
    session.open("pedagogy", "Classes", { live: true });
    await flush();
    assert.match(session.host.innerHTML, /Aucune classe/);
    assert.equal(session.calls.some(function (call) { return call.url.indexOf("teacher-assignments") >= 0; }), false);
  });

  await checkAsync("ASSIGNMENTS_CREATE_DELETE", async function () {
    const session = boot(allowAll(), function (url, options) {
      if (url === "/native/pedagogy/teacher-assignments" && (!options.method || options.method === "GET")) {
        return jsonResponse(200, { data: [{ id: assignmentId, teacher_name: "Jeanne Mbayo", class_name: "6e A", subject_name: "Mathématiques", class_id: classId }] });
      }
      if (url === "/native/pedagogy/teacher-assignments" && options.method === "POST") {
        assert.deepEqual(Object.keys(JSON.parse(options.body)).sort(), ["academic_year_id", "class_id", "profile_id", "subject_id"]);
        return jsonResponse(200, { data: { id: assignmentId } });
      }
      if (url === "/native/pedagogy/teacher-assignments/" + assignmentId && options.method === "DELETE") {
        return jsonResponse(200, { data: { deleted: true } });
      }
      return jsonResponse(500, { message: url });
    });
    session.open("pedagogy", "Affectations", { live: true });
    await flush();
    assert.match(session.host.innerHTML, /Jeanne Mbayo/);
    submit(session, "p2aAssignmentForm", {
      profile_id: profileId,
      subject_id: subjectId,
      class_id: classId,
      academic_year_id: yearId
    });
    await flush();
    assert.match(session.host.innerHTML, /Affectation créée/);
    session.open("pedagogy", "Affectations", { live: true });
    await flush();
    click(session, "p2aDelete0");
    assert.equal(session.calls.some(function (call) { return call.options.method === "DELETE"; }), false);
    click(session, "p2aDeleteYes");
    await flush();
    assert.equal(session.calls[session.calls.length - 1].options.method, "DELETE");
    assert.match(session.host.innerHTML, /Affectation supprimée/);
  });

  await checkAsync("ASSIGNMENTS_DENY", async function () {
    const session = boot(deny("school.structure.manage"), function () { throw new Error("fetch interdit"); });
    assert.equal(session.open("pedagogy", "Affectations", { live: true }), true);
    assert.match(session.host.innerHTML, /school\.structure\.manage/);
    assert.equal(session.calls.length, 0);
  });

  await checkAsync("APPROVALS_DECIDE", async function () {
    const session = boot(allowAll(), function (url, options) {
      if (url.indexOf("http://api.test/pilotage/approvals?") === 0) {
        assert.equal(options.headers.Authorization, "Bearer live-token");
        return jsonResponse(200, { data: [{ id: approvalId, request_type: "grade_change", entity_type: "grade", entity_id: studentId, status: "pending", reason: "Erreur de saisie", payload: { score: 12 } }], count: 1 });
      }
      if (url === "http://api.test/pilotage/approvals/" + approvalId + "/decide") {
        assert.equal(options.method, "POST");
        assert.deepEqual(JSON.parse(options.body), { decision: "approved", reason: "Pièce vérifiée" });
        return jsonResponse(200, { data: { id: approvalId, status: "approved" } });
      }
      return jsonResponse(500, { message: url });
    });
    session.open("pilotage", "Approbations", { live: true });
    await flush();
    assert.match(session.host.innerHTML, /grade_change/);
    click(session, "p2aApproval0");
    assert.match(session.host.innerHTML, /Erreur de saisie/);
    assert.match(session.host.innerHTML, /12/);
    const reason = session.host.querySelector("#p2aReason");
    reason.value = "Pièce vérifiée";
    click(session, "p2aApprove");
    assert.equal(session.calls.some(function (call) { return call.options.method === "POST"; }), false);
    click(session, "p2aDecideYes");
    await flush();
    assert.match(session.host.innerHTML, /Décision enregistrée/);
  });

  await checkAsync("APPROVALS_EMPTY_DENY", async function () {
    const empty = boot(allowExcept("pilotage.approvals.manage"), function () {
      return jsonResponse(200, { data: [], count: 0 });
    });
    empty.open("pilotage", "Approbations", { live: true });
    await flush();
    assert.match(empty.host.innerHTML, /Aucune approbation/);
    assert.equal(empty.host.innerHTML.includes("p2aApprove"), false);
    const denied = boot(deny("pilotage.approvals.read"), function () { throw new Error("fetch interdit"); });
    denied.open("pilotage", "Approbations", { live: true });
    assert.equal(denied.calls.length, 0);
  });

  await checkAsync("GAPS_STAY_CLOSED", async function () {
    const session = boot(allowAll(), function () { throw new Error("fetch interdit"); });
    assert.equal(session.open("pedagogy", "Rattrapage pédagogique", { live: true }), false);
    assert.equal(session.open("finance", "Reçus PDF", { live: true }), false);
    assert.equal(session.open("security", "Recherche de passage", { live: true }), false);
    assert.equal(session.open("care", "Dossiers santé", { live: true }), false);
    assert.equal(session.calls.length, 0);
  });
}

runBehaviors().then(function () {
  if (failures.length) {
    console.error(failures.join("\n"));
    process.exit(1);
  }
  console.log("qa-rosario-visual-output-p2a: 6 surfaces, session réelle, 3 viewports");
});
