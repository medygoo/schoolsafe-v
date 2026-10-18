"use strict";

const assert = require("node:assert/strict");
const fs = require("node:fs");
const path = require("node:path");
const vm = require("node:vm");

const root = path.resolve(__dirname, "..");
const failures = [];
const read = (relativePath) => fs.readFileSync(path.join(root, relativePath), "utf8");
const b1 = read("app/modules/core/visual-output-closure-b1.js");
const css = read("app/styles/modules/visual-output-closure-b1.css");
const html = read("app/index.html");
const wiring = read("app/modules/core/visual-wiring.js");
const app = read("app/app.js");
const p2a = read("app/modules/core/visual-output-p2a.js");
const p2c = read("app/modules/core/visual-output-p2c.js");
const closureA = read("app/modules/core/visual-output-closure-a.js");
const pilotage = read("app/modules/pilotage/pilotage-module.js");
const pilotageCss = read("app/styles/modules/pilotage.css");

const STUDENT = "aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa";
const GUARDIAN = "bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb";
const RANK = "cccccccc-cccc-4ccc-8ccc-cccccccccccc";

function check(name, fn) {
  try { fn(); } catch (error) { failures.push(name + ": " + error.message); }
}

function checkAsync(name, fn) {
  return fn().catch(function (error) { failures.push(name + ": " + (error && error.stack ? error.stack : error)); });
}

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

function boot(scripts, access, fetchImpl) {
  const host = createHost();
  const calls = [];
  const context = {
    console: console,
    setTimeout: setTimeout,
    schoolSafeApiBase: "http://api.test",
    sessionStorage: { getItem: function () { return JSON.stringify({ token: "live-token" }); } },
    fetch: function (url, options) {
      calls.push({ url: String(url), options: options || {} });
      return Promise.resolve(fetchImpl(String(url), options || {}));
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
      if (id === "wiringSurface" || id === "pilotageContent") return host;
      return host.querySelector("#" + id);
    }
  };
  scripts.forEach(function (source, index) {
    vm.runInNewContext(source, context, { filename: "b1-" + index + ".js" });
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
  assert.ok(node && node.listeners.click, id);
  node.listeners.click({ preventDefault: function () {} });
}

function set(session, id, value) {
  const node = session.host.querySelector("#" + id);
  assert.ok(node, id);
  node.value = value;
}

function submit(session, id, fields) {
  const form = session.host.querySelector("#" + id);
  assert.ok(form && form.listeners.submit, id);
  const elements = {};
  Object.keys(fields).forEach(function (name) { elements[name] = { value: fields[name] }; });
  form.listeners.submit({ preventDefault: function () {}, target: { elements: elements } });
}

function live(extra) {
  return Object.assign({ user: { role: "admin" }, live: true, hideDashboard: function () {}, restoreDashboard: function () {} }, extra || {});
}

function openB1(session, branch, action, extra) {
  return session.context.SchoolSafeVisualOutputClosureB1.open(branch, action, live(extra));
}

check("MODULE_AND_NAV", function () {
  assert.match(b1, /SchoolSafeVisualOutputClosureB1/);
  assert.match(wiring, /SchoolSafeVisualOutputClosureB1\.open\(branchKey, actionName/);
  assert.ok(wiring.indexOf("SchoolSafeVisualOutputClosureB1.open") > wiring.indexOf("SchoolSafeVisualOutputClosureA.open"));
  assert.ok(wiring.indexOf("VISUAL_WIRING[") > wiring.indexOf("SchoolSafeVisualOutputClosureB1.open"));
  assert.match(html, /visual-output-closure-b1\.js/);
  assert.match(html, /visual-output-closure-b1\.css/);
  assert.ok(html.indexOf("visual-output-closure-a.js") < html.indexOf("visual-output-closure-b1.js"));
  assert.ok(html.indexOf("visual-output-closure-b1.js") < html.indexOf("visual-wiring.js"));
  assert.match(app, /\["Cycles","layers"\]/);
  assert.match(app, /\["Licence","key"\]/);
  assert.match(app, /\["Instantanés","camera"\]/);
  assert.match(app, /\["E-mail","mail"\]/);
  assert.match(app, /\["Identité de l'élève","id-card"\]/);
  assert.match(app, /\["Consentements","shield-check"\]/);
  assert.match(app, /openAccess: openAccessConsole/);
});

check("VIEWPORTS", function () {
  assert.match(css, /@media \(min-width: 1440px\)/);
  assert.match(css, /@media \(min-width: 769px\) and \(max-width: 1024px\)/);
  assert.match(css, /@media \(max-width: 390px\)/);
  assert.match(css, /\.clb-screen/);
  assert.match(css, /button \{ width: 100% \}/);
  assert.match(pilotageCss, /@media \(min-width: 1440px\)/);
  assert.match(pilotageCss, /@media \(min-width: 769px\) and \(max-width: 1024px\)/);
  assert.match(pilotageCss, /@media \(max-width: 390px\)/);
  assert.match(pilotageCss, /\.pilotage-dashboard button \{ width: 100% \}/);
});

check("CONTRACTS_AND_GAPS", function () {
  ["school.guardian.manage", "school.student.create", "school.student.health.confirm", "school.student.dietary.confirm", "school.student.health.manage", "school.manage", "email.send", "notification.subscribe", "pilotage.dashboard.read", "explicitDeny", "canAccess"].forEach(function (code) {
    assert.match(b1, new RegExp(code.replace(/\./g, "\\.")));
  });
  assert.match(p2a, /school\.student\.read/);
  ["photo_video", "emergency_care", "family", "medical", "dietary", "pickup"].forEach(function (key) {
    assert.match(b1, new RegExp(key));
  });
  assert.equal(b1.includes("is_tutor"), false);
  assert.equal(/is_tutor\s*[:=]\s*true/.test(p2a), false);
  assert.match(p2a, /row\.is_tutor === true/);
  assert.equal(/GET\s+\/documents/.test(b1), false);
  assert.match(p2a, /\/guardians\/" \+ encodeURIComponent\(person\.id\)/);
  assert.match(p2c, /\/stars\/" \+ encodeURIComponent/);
  const dashboard = pilotage.slice(pilotage.indexOf("function renderDashboard"), pilotage.indexOf("function renderAlerts"));
  assert.ok(dashboard.indexOf("explicitDeny") < dashboard.indexOf(".dashboard("));
  assert.ok(dashboard.indexOf("canAccess") < dashboard.indexOf(".dashboard("));
});

check("FOREIGN_STAYS", function () {
  const session = boot([b1], allowAll(), function () { throw new Error("fetch interdit"); });
  ["pedagogy|Rattrapage pédagogique", "school|Studio des cartes", "pedagogy|Affectations", "school|Affectations"].forEach(function (pair) {
    const parts = pair.split("|");
    assert.equal(openB1(session, parts[0], parts[1]), false);
  });
  assert.equal(session.calls.length, 0);
});

async function main() {
  await checkAsync("GUARDIAN_EDIT", async function () {
    const session = boot([p2a], allowAll(), function (url, options) {
      if (url.indexOf("/family") >= 0 && (!options.method || options.method === "GET")) {
        return jsonResponse(200, { data: [{ id: GUARDIAN, full_name: "Marie Kabila", guardian_type: "mere", phone: "+243800000001", is_primary: true, is_active: true }] });
      }
      if (url.indexOf("/emergency-contacts") >= 0 && (!options.method || options.method === "GET")) return jsonResponse(200, { data: [] });
      if (options.method === "PATCH" && url === "/native/students/" + STUDENT + "/guardians/" + GUARDIAN) {
        const body = JSON.parse(options.body);
        assert.deepEqual(Object.keys(body).sort(), ["full_name", "guardian_type", "phone"]);
        assert.equal(body.profile_id, undefined);
        assert.equal(body.is_primary, undefined);
        return jsonResponse(200, { data: { id: GUARDIAN } });
      }
      return jsonResponse(500, { message: url });
    });
    assert.equal(session.context.SchoolSafeVisualOutputP2A.open("school", "Parents et tuteurs", live()), true);
    submit(session, "p2aFamilyForm", { student_id: STUDENT });
    await flush();
    click(session, "p2aGuardianEdit0");
    submit(session, "p2aGuardianEditForm", { guardian_type: "mere", full_name: "Marie Kabila", phone: "+243800000009", email: "", address: "" });
    await flush();
    assert.match(session.host.innerHTML, /Tuteur enregistré/);
  });

  await checkAsync("GUARDIAN_DENY", async function () {
    const session = boot([p2a], deny("school.guardian.manage"), function (url) {
      if (url.indexOf("/family") >= 0) return jsonResponse(200, { data: [{ id: GUARDIAN, full_name: "Marie Kabila", guardian_type: "mere", is_primary: true, is_active: true }] });
      if (url.indexOf("/emergency-contacts") >= 0) return jsonResponse(200, { data: [{ slot_no: 1, full_name: "Claire", relation: "tante", phone: "+243" }] });
      return jsonResponse(500, { message: url });
    });
    session.context.SchoolSafeVisualOutputP2A.open("school", "Parents et tuteurs", live());
    submit(session, "p2aFamilyForm", { student_id: STUDENT });
    await flush();
    assert.equal(session.host.innerHTML.includes("p2aGuardianEdit0"), false);
    assert.equal(session.host.innerHTML.includes("p2aEmergencyEdit1"), false);
  });

  await checkAsync("EMERGENCY_EDIT", async function () {
    const session = boot([p2a], allowAll(), function (url, options) {
      if (url.indexOf("/family") >= 0 && (!options.method || options.method === "GET")) return jsonResponse(200, { data: [] });
      if (url.indexOf("/emergency-contacts") >= 0 && (!options.method || options.method === "GET")) {
        return jsonResponse(200, { data: [{ slot_no: 1, full_name: "Claire Kabila", relation: "tante", phone: "+243800000000" }] });
      }
      if (options.method === "PUT" && url === "/native/students/" + STUDENT + "/emergency-contacts/1") {
        assert.deepEqual(JSON.parse(options.body), { full_name: "Claire Kabila", relation: "tante", phone: "+243811111111" });
        return jsonResponse(200, { data: { slot_no: 1 } });
      }
      return jsonResponse(500, { message: url });
    });
    session.context.SchoolSafeVisualOutputP2A.open("school", "Parents et tuteurs", live());
    submit(session, "p2aFamilyForm", { student_id: STUDENT });
    await flush();
    click(session, "p2aEmergencyEdit1");
    submit(session, "p2aEmergencyForm", { full_name: "Claire Kabila", relation: "tante", phone: "+243811111111", alternate_phone: "", notes: "" });
    await flush();
    assert.match(session.host.innerHTML, /Contact enregistré/);
  });

  await checkAsync("IDENTITY", async function () {
    const session = boot([b1], allowAll(), function (url, options) {
      assert.equal(options.method, "PATCH");
      assert.equal(url, "http://api.test/native/students/" + STUDENT + "/identity");
      const body = JSON.parse(options.body);
      assert.deepEqual(Object.keys(body).sort(), ["first_name", "home_address", "last_name"]);
      return jsonResponse(200, { data: { id: STUDENT } });
    });
    openB1(session, "school", "Identité de l'élève");
    set(session, "clbIdentityStudent", STUDENT);
    set(session, "clbFirstName", "Awa");
    set(session, "clbLastName", "Kabila");
    set(session, "clbHomeAddress", "Gombe");
    click(session, "clbIdentitySave");
    click(session, "clbIdentityYes");
    await flush();
    assert.match(session.host.innerHTML, /Identité enregistrée/);
  });

  await checkAsync("IDENTITY_DENY", async function () {
    const session = boot([b1], deny("school.student.create"), function () { throw new Error("fetch interdit"); });
    openB1(session, "school", "Identité de l'élève");
    assert.match(session.host.innerHTML, /Accès refusé/);
    assert.equal(session.calls.length, 0);
  });

  await checkAsync("CONSENTS", async function () {
    const session = boot([b1], allowAll(), function (url, options) {
      assert.equal(options.method, "PUT");
      if (url.indexOf("/confirmations/family") >= 0) {
        assert.deepEqual(JSON.parse(options.body), { confirmed: true });
        return jsonResponse(200, { data: { confirmation_key: "family", confirmed: true } });
      }
      if (url.indexOf("/consents/photo_video") >= 0) {
        assert.deepEqual(JSON.parse(options.body), { decision: false });
        return jsonResponse(200, { data: { consent_key: "photo_video", decision: false } });
      }
      return jsonResponse(500, { message: url });
    });
    openB1(session, "school", "Consentements");
    set(session, "clbConsentStudent", STUDENT);
    click(session, "clbConfirmFamily");
    await flush();
    assert.match(session.host.innerHTML, /family/);
    openB1(session, "school", "Consentements");
    set(session, "clbConsentStudent", STUDENT);
    click(session, "clbConsentPhotoNo");
    await flush();
    assert.match(session.host.innerHTML, /photo_video/);
  });

  await checkAsync("CYCLES", async function () {
    const session = boot([b1], allowAll(), function (url, options) {
      if (url === "http://api.test/school/cycles" && (!options.method || options.method === "GET")) {
        assert.match(options.headers.Authorization, /Bearer live-token/);
        return jsonResponse(200, [{ cycle_key: "primary", cycle_name: "Primaire", is_active: true }]);
      }
      if (options.method === "PUT" && url === "http://api.test/school/cycles/primary/toggle") {
        assert.deepEqual(JSON.parse(options.body), { is_active: false });
        return jsonResponse(200, { status: "ok" });
      }
      return jsonResponse(500, { message: url });
    });
    openB1(session, "administration", "Cycles");
    await flush();
    assert.match(session.host.innerHTML, /Primaire/);
    click(session, "clbCycleprimary");
    click(session, "clbCycleYes");
    await flush();
    assert.match(session.host.innerHTML, /Cycle enregistré/);
  });

  await checkAsync("CYCLES_EMPTY_AND_DENY", async function () {
    const empty = boot([b1], allowAll(), function (url) {
      if (url.indexOf("/school/cycles") >= 0) return jsonResponse(200, []);
      return jsonResponse(500, { message: url });
    });
    openB1(empty, "administration", "Cycles");
    await flush();
    assert.match(empty.host.innerHTML, /Aucun cycle/);
    const denied = boot([b1], deny("school.manage"), function () { throw new Error("fetch interdit"); });
    openB1(denied, "administration", "Cycles");
    assert.match(denied.host.innerHTML, /Accès refusé/);
    assert.equal(denied.calls.length, 0);
  });

  await checkAsync("ACCESS_CONSOLE", async function () {
    let opened = 0;
    const session = boot([b1], allowAll(), function () { throw new Error("fetch interdit"); });
    assert.equal(openB1(session, "administration", "Comptes et droits", { openAccess: function () { opened += 1; } }), true);
    assert.equal(opened, 1);
    assert.equal(session.host.innerHTML.includes("BACKEND_LATER"), false);
    assert.equal(session.calls.length, 0);
  });

  await checkAsync("DOCUMENT_BUTTONS", async function () {
    const session = boot([closureA, b1], allowAll(), function () { throw new Error("fetch interdit"); });
    ["school|Documents", "administration|Documents R2", "administration|Archives"].forEach(function (pair) {
      const parts = pair.split("|");
      assert.equal(openB1(session, parts[0], parts[1]), true);
      assert.match(session.host.innerHTML, /Centre documentaire/);
      assert.match(session.host.innerHTML, /Aucune archive/);
    });
    assert.equal(session.calls.length, 0);
  });

  await checkAsync("STAR_DELETE", async function () {
    const session = boot([p2c], allowAll(), function (url, options) {
      if (url.indexOf("/native/pedagogy/rankings?") >= 0) return jsonResponse(200, { data: [{ id: RANK, class_name: "6e A", month: "2026-03", status: "draft" }] });
      if (url === "http://api.test/native/pedagogy/rankings/" + RANK && (!options.method || options.method === "GET")) {
        return jsonResponse(200, { data: { id: RANK, class_name: "6e A", month: "2026-03", status: "draft", data: { students: [] } } });
      }
      if (url.indexOf("/stars") >= 0 && (!options.method || options.method === "GET")) {
        return jsonResponse(200, { data: [{ student_id: STUDENT }] });
      }
      if (options.method === "DELETE" && url === "http://api.test/native/pedagogy/rankings/" + RANK + "/stars/" + STUDENT) {
        return jsonResponse(200, { data: { removed: true } });
      }
      return jsonResponse(500, { message: url + " " + (options.method || "GET") });
    });
    assert.equal(session.context.SchoolSafeVisualOutputP2C.open("pedagogy", "Palmarès", live()), true);
    await flush();
    click(session, "p2cRanking0");
    await flush();
    click(session, "p2cStarLoad");
    await flush();
    click(session, "p2cStarDelete0");
    click(session, "p2cStarDeleteYes");
    await flush();
    assert.match(session.host.innerHTML, /Étoile retirée/);
  });

  await checkAsync("DASHBOARD", async function () {
    function run(access, user, impl) {
      const calls = [];
      const session = boot([pilotage], access, function () { return jsonResponse(200, {}); });
      session.context.currentSession = user;
      session.context.SchoolSafePilotageAPI = {
        dashboard: function () {
          calls.push("dashboard");
          return impl();
        }
      };
      session.context.icons = function () {};
      session.context.SchoolSafePilotageModule.renderDashboard("pilotageContent", user);
      return { session: session, calls: calls };
    }
    const denied = run(deny("pilotage.dashboard.read"), { token: "live-token" }, function () { throw new Error("appel interdit"); });
    assert.equal(denied.calls.length, 0);
    assert.match(denied.session.host.innerHTML, /Accès refusé/);
    const missing = run({ explicitDeny: function () { return false; }, canAccess: function () { return false; } }, { token: "live-token" }, function () { throw new Error("appel interdit"); });
    assert.equal(missing.calls.length, 0);
    const loaded = run(allowAll(), { token: "live-token" }, function () {
      return Promise.resolve({ date: "2026-10-08", kpis: [{ code: "presence", value: 12, unit: "eleves" }], latest_alerts: [] });
    });
    assert.match(loaded.session.host.innerHTML, /Chargement/);
    await flush();
    assert.equal(loaded.calls.length, 1);
    assert.match(loaded.session.host.innerHTML, /presence/);
    assert.match(loaded.session.host.innerHTML, /Aucune alerte/);
    const failed = run(allowAll(), { token: "live-token" }, function () { return Promise.reject(new Error("panne tableau")); });
    await flush();
    assert.match(failed.session.host.innerHTML, /panne tableau/);
  });

  await checkAsync("SNAPSHOTS", async function () {
    const session = boot([b1], allowAll(), function (url, options) {
      if (url.indexOf("/pilotage/snapshots/trend?") >= 0) {
        assert.match(url, /indicator_code=open_alerts/);
        assert.match(url, /days=7/);
        assert.match(options.headers.Authorization, /Bearer live-token/);
        return jsonResponse(200, { data: [{ snapshot_date: "2026-10-08", value: 3 }] });
      }
      if (options.method === "POST" && url.indexOf("/pilotage/snapshots/capture") >= 0) {
        assert.equal(options.body, undefined);
        return jsonResponse(200, { data: [{ indicator_code: "open_alerts", value: 3, unit: "count" }] });
      }
      return jsonResponse(500, { message: url });
    });
    openB1(session, "pilotage", "Instantanés");
    set(session, "clbIndicator", "open_alerts");
    set(session, "clbDays", "7");
    click(session, "clbTrend");
    await flush();
    assert.match(session.host.innerHTML, /2026-10-08/);
    click(session, "clbCapture");
    click(session, "clbCaptureYes");
    await flush();
    assert.match(session.host.innerHTML, /open_alerts/);
  });

  await checkAsync("SNAPSHOTS_EMPTY_ERROR_DENY", async function () {
    const empty = boot([b1], allowAll(), function () { return jsonResponse(200, { data: [] }); });
    openB1(empty, "pilotage", "Instantanés");
    set(empty, "clbIndicator", "open_alerts");
    set(empty, "clbDays", "30");
    click(empty, "clbTrend");
    await flush();
    assert.match(empty.host.innerHTML, /Aucune mesure/);
    const failed = boot([b1], allowAll(), function () { return jsonResponse(500, { message: "tendance indisponible" }); });
    openB1(failed, "pilotage", "Instantanés");
    set(failed, "clbIndicator", "open_alerts");
    click(failed, "clbTrend");
    await flush();
    assert.match(failed.host.innerHTML, /tendance indisponible/);
    const denied = boot([b1], deny("pilotage.dashboard.read"), function () { throw new Error("fetch interdit"); });
    openB1(denied, "pilotage", "Instantanés");
    assert.match(denied.host.innerHTML, /Accès refusé/);
    assert.equal(denied.calls.length, 0);
  });

  await checkAsync("EMAIL", async function () {
    const session = boot([b1], allowAll(), function (url, options) {
      assert.equal(url, "http://api.test/email/send");
      assert.equal(options.method, "POST");
      assert.match(options.headers.Authorization, /Bearer live-token/);
      assert.deepEqual(JSON.parse(options.body), {
        to: [{ email: "parent@example.test", name: "Marie Kabila" }],
        subject: "Convocation",
        text: "Présence demandée."
      });
      return jsonResponse(200, { data: { status: "sent", provider: "brevo", messageId: "msg-1" } });
    });
    openB1(session, "communication", "E-mail");
    set(session, "clbMailTo", "parent@example.test");
    set(session, "clbMailName", "Marie Kabila");
    set(session, "clbMailSubject", "Convocation");
    set(session, "clbMailText", "Présence demandée.");
    click(session, "clbMailSend");
    click(session, "clbMailYes");
    await flush();
    assert.match(session.host.innerHTML, /sent/);
    const denied = boot([b1], deny("email.send"), function () { throw new Error("fetch interdit"); });
    openB1(denied, "communication", "E-mail");
    assert.match(denied.host.innerHTML, /Accès refusé/);
    assert.equal(denied.calls.length, 0);
  });

  await checkAsync("PUSH", async function () {
    const session = boot([b1], allowAll(), function (url, options) {
      if (url.indexOf("/push/public-key") >= 0) return jsonResponse(200, { public_key: "vapid-public" });
      if (options.method === "POST" && url.indexOf("/push/subscribe") >= 0) {
        assert.match(options.headers.Authorization, /Bearer live-token/);
        assert.deepEqual(JSON.parse(options.body), { endpoint: "https://push.example.test/1", keys: { p256dh: "p256", auth: "authkey" } });
        return jsonResponse(200, { success: true });
      }
      return jsonResponse(500, { message: url });
    });
    openB1(session, "communication", "Notifications");
    await flush();
    assert.match(session.host.innerHTML, /Non abonné/);
    assert.match(session.host.innerHTML, /vapid-public/);
    set(session, "clbPushEndpoint", "https://push.example.test/1");
    set(session, "clbPushP256", "p256");
    set(session, "clbPushAuth", "authkey");
    click(session, "clbPushSubscribe");
    await flush();
    assert.match(session.host.innerHTML, /Abonné/);
    const failed = boot([b1], allowAll(), function () { return jsonResponse(503, { message: "Web Push non configuré" }); });
    openB1(failed, "communication", "Notifications");
    await flush();
    assert.match(failed.host.innerHTML, /Web Push non configuré/);
    const denied = boot([b1], deny("notification.subscribe"), function () { throw new Error("fetch interdit"); });
    openB1(denied, "communication", "Notifications");
    assert.match(denied.host.innerHTML, /Accès refusé/);
    assert.equal(denied.calls.length, 0);
  });

  await checkAsync("LICENSE", async function () {
    const session = boot([b1], allowAll(), function (url, options) {
      if (url.indexOf("/native/license/status") >= 0 && (!options.method || options.method === "GET")) {
        return jsonResponse(200, { data: { state: "expired", license_id: null, expires_at: null, perpetual: false } });
      }
      if (options.method === "POST" && url.indexOf("/native/license/redeem") >= 0) {
        assert.deepEqual(JSON.parse(options.body), { activation_code: "CODE-1" });
        return jsonResponse(200, { data: { state: "active", license_id: "lic-1", expires_at: null, perpetual: true } });
      }
      if (options.method === "POST" && url.indexOf("/native/license/refresh") >= 0) {
        return jsonResponse(200, { data: { state: "active", license_id: "lic-1" } });
      }
      return jsonResponse(500, { message: url });
    });
    openB1(session, "administration", "Licence");
    await flush();
    assert.match(session.host.innerHTML, /expired/);
    set(session, "clbLicenseCode", "CODE-1");
    click(session, "clbLicenseRedeem");
    click(session, "clbLicenseYes");
    await flush();
    assert.match(session.host.innerHTML, /active/);
    assert.match(session.host.innerHTML, /lic-1/);
    click(session, "clbLicenseRefresh");
    await flush();
    assert.match(session.host.innerHTML, /active/);
  });

  await checkAsync("DEMO_FETCHES_NOTHING", async function () {
    const session = boot([b1], allowAll(), function () { throw new Error("fetch interdit"); });
    ["school|Identité de l'élève", "school|Consentements", "administration|Cycles", "pilotage|Instantanés", "communication|E-mail", "communication|Notifications", "administration|Licence"].forEach(function (pair) {
      const parts = pair.split("|");
      openB1(session, parts[0], parts[1], { live: false });
      assert.match(session.host.innerHTML, /Session réelle requise/);
    });
    assert.equal(session.calls.length, 0);
  });

  if (failures.length) {
    console.error(failures.join("\n"));
    process.exit(1);
  }
  console.log("qa-rosario-final-ui-closure-b1: 13 écarts, session réelle, 3 viewports");
}

main();
