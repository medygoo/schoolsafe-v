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

const p2b = read("app/modules/core/visual-output-p2b.js");
const css = read("app/styles/modules/visual-output-p2b.css");
const html = read("app/index.html");
const wiring = read("app/modules/core/visual-wiring.js");
const app = read("app/app.js");

check("MODULE_MISSING", function () {
  assert.ok(p2b.length > 0, "visual-output-p2b.js absent");
  assert.ok(css.length > 0, "visual-output-p2b.css absent");
});

check("EXACT_APIS", function () {
  assert.match(p2b, /\/security\/events/);
  assert.match(p2b, /\/security\/lockdown/);
  assert.match(p2b, /\/security\/scan/);
  assert.match(p2b, /\/native\/family\/students\//);
  assert.match(p2b, /pickup-authorizations/);
  assert.match(p2b, /\/native\/family\/pickup-confirm/);
  assert.match(p2b, /\/pedagogy\/parent\/children/);
  assert.equal(p2b.includes("/security/lockdown/status"), false);
  assert.equal(p2b.includes("GET /security/lockdown"), false);
  assert.equal(p2b.includes("schoolsafe://card/"), false);
  assert.equal(p2b.includes("CREATE TABLE"), false);
});

check("NO_INVENTED_DOMAINS", function () {
  for (const token of ["paie", "salaire", "biom", "contrat", "cantine", "infirmerie", "rattrapage", "CREATE TABLE", "Lucas Martin", "Ethan", "DÉMO"]) {
    assert.equal(p2b.toLowerCase().includes(token.toLowerCase()), false, token);
  }
});

check("PERMISSIONS", function () {
  for (const code of [
    "security.events.read",
    "security.lockdown.manage",
    "security.pickup.read",
    "security.scan",
    "sync.submit"
  ]) {
    assert.match(p2b, new RegExp(code.replace(/\./g, "\\.")));
  }
  assert.match(p2b, /explicitDeny/);
  assert.match(p2b, /canAccess/);
  assert.match(p2b, /own_children/);
});

check("NAVIGATION", function () {
  assert.match(wiring, /SchoolSafeVisualOutputP2A\.open\(branchKey, actionName/);
  assert.match(wiring, /SchoolSafeVisualOutputP2B\.open\(branchKey, actionName/);
  assert.ok(
    wiring.indexOf("SchoolSafeVisualOutputP2B.open") > wiring.indexOf("SchoolSafeVisualOutputP2A.open"),
    "P2B doit suivre P2A"
  );
  assert.match(html, /visual-output-p2b\.js/);
  assert.match(html, /visual-output-p2b\.css/);
  const start = app.indexOf("function securityTabForAction");
  assert.ok(start >= 0, "securityTabForAction absent");
  assert.match(app.slice(start, start + 420), /actionName === "Personnes autorisées"/);
});

check("VIEWPORTS", function () {
  assert.match(css, /@media \(min-width: 1440px\)/);
  assert.match(css, /@media \(min-width: 769px\) and \(max-width: 1024px\)/);
  assert.match(css, /@media \(max-width: 390px\)/);
  assert.match(css, /\.p2b-screen/);
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

function boot(access, fetchImpl, extra) {
  const host = createHost();
  const calls = [];
  const context = Object.assign({
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
  }, extra || {});
  context.window = context;
  context.document = {
    getElementById: function (id) {
      if (id === "wiringSurface") return host;
      return host.querySelector("#" + id);
    }
  };
  vm.runInNewContext(p2b, context, { filename: "visual-output-p2b.js" });
  return {
    host: host,
    calls: calls,
    open: function (branch, action, more) {
      return context.SchoolSafeVisualOutputP2B.open(branch, action, Object.assign({
        user: { role: "school_head" },
        live: true,
        hideDashboard: function () {},
        restoreDashboard: function () {}
      }, more || {}));
    }
  };
}

function allowAll() {
  return {
    explicitDeny: function () { return false; },
    canAccess: function () { return true; },
    allowsScope: function () { return false; }
  };
}

function deny(code) {
  return {
    explicitDeny: function (_user, permission) { return permission === code; },
    canAccess: function () { return true; },
    allowsScope: function () { return false; }
  };
}

function parentScope(childIds) {
  return {
    explicitDeny: function () { return false; },
    canAccess: function () { return true; },
    allowsScope: function (_user, permission, scope) {
      return permission === "security.pickup.read" && scope === "own_children";
    },
    childIds: childIds
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
  assert.ok(node, id);
  node.listeners.click({ preventDefault: function () {} });
}

const STUDENT = "aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa";
const OTHER = "bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb";
const LOCATION = "cccccccc-cccc-4ccc-8ccc-cccccccccccc";
const GUARDIAN = "dddddddd-dddd-4ddd-8ddd-dddddddddddd";

function eventRow(extra) {
  return Object.assign({
    id: "eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee",
    student_id: STUDENT,
    event_type: "entry",
    occurred_at: "2026-10-08T08:00:00.000Z",
    decision: "allowed",
    denial_reason: "",
    location_id: LOCATION
  }, extra || {});
}

async function main() {
  check("FOREIGN_ACTIONS", function () {
    const session = boot(allowAll(), function () { return jsonResponse(200, { data: [] }); });
    assert.equal(session.open("school", "Cr\u00e9er l\u2019\u00e9l\u00e8ve"), false);
    assert.equal(session.open("school", "Personnes autoris\u00e9es"), false);
    assert.equal(session.calls.length, 0);
    assert.equal(session.host.innerHTML, "");
  });

  check("EVENTS_DENY_AND_DEMO", function () {
    const denied = boot(deny("security.events.read"), function () { throw new Error("fetch"); });
    assert.equal(denied.open("security", "Entr\u00e9es et sorties"), true);
    assert.match(denied.host.innerHTML, /security\.events\.read/);
    assert.equal(denied.calls.length, 0);
    const demo = boot(allowAll(), function () { throw new Error("fetch"); });
    assert.equal(demo.open("security", "Entr\u00e9es et sorties", { live: false }), true);
    assert.match(demo.host.innerHTML, /Session r\u00e9elle requise/);
    assert.match(demo.host.innerHTML, /Aucune donn\u00e9e fictive/);
    assert.equal(demo.calls.length, 0);
    assert.match(demo.host.innerHTML, /id="wiringBack"/);
  });

  const listed = eventRow();
  const events = boot(allowAll(), function (url) {
    if (url.indexOf("/security/events") >= 0) {
      return jsonResponse(200, { data: [listed], count: 21 });
    }
    return jsonResponse(500, { message: "hors registre" });
  });
  assert.equal(events.open("security", "Entr\u00e9es et sorties"), true);
  assert.match(events.host.innerHTML, /ss-state--loading/);
  await flush();
  check("EVENTS_LIST", function () {
    assert.equal(events.calls.length, 1);
    assert.match(events.calls[0].url, /\/security\/events\?/);
    assert.match(events.calls[0].url, /limit=20/);
    assert.match(events.calls[0].url, /offset=0/);
    assert.equal(events.calls[0].options.method || "GET", "GET");
    assert.match(events.host.innerHTML, /entry/);
    assert.match(events.host.innerHTML, /2026-10-08T08:00:00.000Z/);
    assert.match(events.host.innerHTML, /id="p2bEventNext"/);
    assert.match(events.host.innerHTML, /data-p2b-screen="events"/);
  });
  click(events, "p2bEvent0");
  check("EVENTS_DETAIL", function () {
    assert.equal(events.calls.length, 1);
    assert.match(events.host.innerHTML, /allowed/);
    assert.match(events.host.innerHTML, /eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee/);
    assert.match(events.host.innerHTML, /data-p2b-screen="event-detail"/);
  });

  const empty = boot(allowAll(), function () { return jsonResponse(200, { data: [], count: 0 }); });
  empty.open("school", "Pr\u00e9sence et passages");
  await flush();
  check("EVENTS_EMPTY", function () {
    assert.match(empty.host.innerHTML, /ss-state--empty/);
  });

  const broken = boot(allowAll(), function () { return jsonResponse(503, { message: "registre indisponible" }); });
  broken.open("security", "Recherche de passage");
  await flush();
  check("EVENTS_ERROR", function () {
    assert.match(broken.host.innerHTML, /ss-state--error/);
    assert.match(broken.host.innerHTML, /registre indisponible/);
  });

  const filtered = boot(allowAll(), function (url, _options, index) {
    if (index > 0) assert.match(url, /event_type=exit/);
    return jsonResponse(200, { data: [eventRow({ event_type: index > 0 ? "exit" : "entry" })], count: 1 });
  });
  filtered.open("security", "Rechercher");
  await flush();
  filtered.host.querySelector("#p2bEventType").value = "exit";
  click(filtered, "p2bEventApply");
  await flush();
  check("EVENTS_FILTER", function () {
    assert.equal(filtered.calls.length, 2);
    assert.match(filtered.host.innerHTML, /exit/);
  });

  const lockdownDenied = boot(deny("security.lockdown.manage"), function () { throw new Error("fetch"); });
  lockdownDenied.open("security", "Lockdown");
  check("LOCKDOWN_DENY", function () {
    assert.match(lockdownDenied.host.innerHTML, /security\.lockdown\.manage/);
    assert.equal(lockdownDenied.calls.length, 0);
    assert.equal(lockdownDenied.host.innerHTML.includes("activated_at"), false);
  });

  const lockdown = boot(allowAll(), function (_url, options) {
    const body = JSON.parse(options.body);
    assert.equal(body.active, true);
    assert.equal(options.method, "POST");
    return jsonResponse(200, { data: { active: true, activated_at: "2026-10-08T09:00:00.000Z", activated_by: "profile-1" } });
  });
  lockdown.open("security", "Lockdown");
  check("LOCKDOWN_NO_STATUS", function () {
    assert.equal(lockdown.host.innerHTML.includes("activated_at"), false);
    assert.match(lockdown.host.innerHTML, /data-p2b-screen="lockdown"/);
    assert.equal(lockdown.calls.length, 0);
  });
  click(lockdown, "p2bLockdownOn");
  check("LOCKDOWN_CONFIRM_FIRST", function () {
    assert.equal(lockdown.calls.length, 0);
    assert.match(lockdown.host.innerHTML, /id="p2bLockdownConfirm"/);
  });
  click(lockdown, "p2bLockdownConfirm");
  await flush();
  check("LOCKDOWN_SUCCESS", function () {
    assert.equal(lockdown.calls.length, 1);
    assert.match(lockdown.calls[0].url, /\/security\/lockdown$/);
    assert.match(lockdown.host.innerHTML, /ss-state--success/);
    assert.match(lockdown.host.innerHTML, /2026-10-08T09:00:00.000Z/);
    assert.match(lockdown.host.innerHTML, /profile-1/);
  });

  const lockdownDemo = boot(allowAll(), function () { throw new Error("fetch"); });
  lockdownDemo.open("security", "Lockdown", { live: false });
  click(lockdownDemo, "p2bLockdownOff");
  click(lockdownDemo, "p2bLockdownConfirm");
  check("LOCKDOWN_DEMO", function () {
    assert.equal(lockdownDemo.calls.length, 0);
    assert.match(lockdownDemo.host.innerHTML, /Session r\u00e9elle requise/);
  });

  const lockdownError = boot(allowAll(), function () { return jsonResponse(403, { message: "confinement refus\u00e9" }); });
  lockdownError.open("security", "Lockdown");
  click(lockdownError, "p2bLockdownOn");
  click(lockdownError, "p2bLockdownConfirm");
  await flush();
  check("LOCKDOWN_ERROR", function () {
    assert.match(lockdownError.host.innerHTML, /ss-state--error/);
    assert.match(lockdownError.host.innerHTML, /confinement refus/);
  });

  const childId = STUDENT;
  const persons = boot(parentScope([childId]), function (url) {
    if (url.indexOf("/pedagogy/parent/children") >= 0) {
      return jsonResponse(200, { data: { children: [
        { id: childId, first_name: "Awa", last_name: "Kabila" },
        { id: OTHER, first_name: "Ines", last_name: "Kabila" }
      ] } });
    }
    assert.match(url, new RegExp("/native/family/students/" + childId + "/pickup-authorizations"));
    assert.equal(url.indexOf(OTHER), -1);
    return jsonResponse(200, { data: {
      student_id: childId,
      family: [{ guardian_id: GUARDIAN, name: "Marie Kabila", type: "mere", is_primary: true, authorized: true }],
      accredited: [{ guardian_id: "ffffffff-ffff-4fff-8fff-ffffffffffff", name: "Paul Mbayo", status: "suspended" }]
    } });
  }, {
    user: { role: "parent", childIds: [childId] }
  });
  persons.open("school", "Personnes autoris\u00e9es de mes enfants", { user: { role: "parent", childIds: [childId] } });
  assert.match(persons.host.innerHTML, /ss-state--loading/);
  await flush();
  await flush();
  check("AUTHORIZED_CONSULTATION", function () {
    assert.match(persons.host.innerHTML, /data-p2b-screen="authorized-persons"/);
    assert.match(persons.host.innerHTML, /Consultation/);
    assert.match(persons.host.innerHTML, /Marie Kabila/);
    assert.match(persons.host.innerHTML, /mere/);
    assert.match(persons.host.innerHTML, /Paul Mbayo/);
    assert.match(persons.host.innerHTML, /suspended/);
    assert.equal(persons.host.innerHTML.includes("Confirmer la remise"), false);
    assert.equal(persons.host.innerHTML.includes("Ines"), false);
    assert.equal(persons.calls.some(function (call) { return call.url.indexOf(OTHER) >= 0; }), false);
  });

  const personsEmpty = boot(allowAll(), function () { return jsonResponse(200, { data: { children: [] } }); });
  personsEmpty.open("school", "Personnes autoris\u00e9es de mes enfants");
  await flush();
  check("AUTHORIZED_EMPTY", function () {
    assert.match(personsEmpty.host.innerHTML, /ss-state--empty/);
  });

  const personsDenied = boot(deny("security.pickup.read"), function () { throw new Error("fetch"); });
  personsDenied.open("school", "Personnes autoris\u00e9es de mes enfants");
  check("AUTHORIZED_DENY", function () {
    assert.match(personsDenied.host.innerHTML, /security\.pickup\.read/);
    assert.equal(personsDenied.calls.length, 0);
  });

  const scanDenied = boot(allowAll(), function () {
    return jsonResponse(200, { data: {
      decision: "denied",
      reason: "lockdown",
      student: null,
      authorized_persons: [],
      event: { id: "99999999-9999-4999-8999-999999999999", event_type: "entry", decision: "denied", occurred_at: "2026-10-08T10:00:00.000Z" }
    } });
  });
  scanDenied.open("security", "Scanner un QR");
  check("SCAN_FORM", function () {
    assert.match(scanDenied.host.innerHTML, /data-p2b-screen="scan"/);
    assert.equal(scanDenied.host.innerHTML.includes("schoolsafe://"), false);
  });
  click(scanDenied, "p2bScanSubmit");
  check("SCAN_LOCATION_REQUIRED", function () {
    assert.equal(scanDenied.calls.length, 0);
    assert.match(scanDenied.host.innerHTML, /location_id/);
  });
  scanDenied.host.querySelector("#p2bScanPayload").value = "schoolsafe://card/matricule/signature-existante";
  scanDenied.host.querySelector("#p2bLocation").value = LOCATION;
  click(scanDenied, "p2bScanSubmit");
  await flush();
  check("SCAN_DENIED", function () {
    assert.equal(scanDenied.calls.length, 1);
    assert.match(scanDenied.calls[0].url, /\/security\/scan$/);
    assert.equal(scanDenied.calls[0].options.method, "POST");
    const body = JSON.parse(scanDenied.calls[0].options.body);
    assert.equal(body.qr_payload, "schoolsafe://card/matricule/signature-existante");
    assert.equal(body.event_type, "entry");
    assert.equal(body.location_id, LOCATION);
    assert.equal(Object.prototype.hasOwnProperty.call(body, "signature"), false);
    assert.match(scanDenied.host.innerHTML, /lockdown/);
    assert.match(scanDenied.host.innerHTML, /99999999-9999-4999-8999-999999999999/);
    assert.equal(scanDenied.host.innerHTML.includes("Confirmer la remise"), false);
  });

  const scanAllowed = boot(allowAll(), function (url, options) {
    if (url.indexOf("/security/scan") >= 0) {
      return jsonResponse(200, { data: {
        decision: "allowed",
        student: { id: STUDENT, matricule: "MAT-1", first_name: "Awa", last_name: "Kabila", class_name: "6e A", photo_path: null },
        authorized_persons: [{ id: GUARDIAN, full_name: "Marie Kabila", guardian_type: "mere", is_primary: true, is_authorized_pickup: true, phone: null }],
        event: { id: "abababab-abab-4aba-8aba-abababababab", event_type: "exit", decision: "allowed", occurred_at: "2026-10-08T16:00:00.000Z" }
      } });
    }
    if (url.indexOf("pickup-authorizations") >= 0) {
      return jsonResponse(200, { data: {
        family: [{ guardian_id: GUARDIAN, name: "Marie Kabila", type: "mere", is_primary: true, authorized: true }],
        accredited: []
      } });
    }
    if (url.indexOf("pickup-confirm") >= 0) {
      const body = JSON.parse(options.body);
      assert.equal(body.student_id, STUDENT);
      assert.equal(body.guardian_id, GUARDIAN);
      assert.ok(String(body.request_key).length >= 8);
      assert.equal(Object.prototype.hasOwnProperty.call(body, "signature"), false);
      return jsonResponse(200, { data: { status: "confirmed", idempotent: false, event_type: "pickup_confirmed" } });
    }
    return jsonResponse(404, { message: "route inattendue" });
  });
  scanAllowed.open("security", "Sorties");
  scanAllowed.host.querySelector("#p2bScanPayload").value = "deja-signe";
  scanAllowed.host.querySelector("#p2bLocation").value = LOCATION;
  click(scanAllowed, "p2bScanSubmit");
  await flush();
  await flush();
  check("SCAN_ALLOWED_PICKUP", function () {
    assert.match(scanAllowed.host.innerHTML, /Awa/);
    assert.match(scanAllowed.host.innerHTML, /abababab-abab-4aba-8aba-abababababab/);
    assert.match(scanAllowed.host.innerHTML, /Marie Kabila/);
    assert.match(scanAllowed.host.innerHTML, /id="p2bPickupConfirm0"/);
    const scanCall = scanAllowed.calls[0];
    assert.equal(JSON.parse(scanCall.options.body).event_type, "exit");
  });
  click(scanAllowed, "p2bPickupConfirm0");
  check("PICKUP_CONFIRM_STEP", function () {
    assert.equal(scanAllowed.calls.some(function (call) { return call.url.indexOf("pickup-confirm") >= 0; }), false);
    assert.match(scanAllowed.host.innerHTML, /id="p2bPickupCommit"/);
  });
  click(scanAllowed, "p2bPickupCommit");
  await flush();
  check("PICKUP_SUCCESS", function () {
    assert.match(scanAllowed.host.innerHTML, /ss-state--success/);
    assert.match(scanAllowed.host.innerHTML, /pickup_confirmed/);
  });

  const pickupError = boot(allowAll(), function (url) {
    if (url.indexOf("pickup-authorizations") >= 0) return jsonResponse(403, { message: "liste de retrait refus\u00e9e" });
    return jsonResponse(500, { message: "autre" });
  });
  pickupError.open("security", "Personnes autoris\u00e9es");
  pickupError.host.querySelector("#p2bStudentId").value = STUDENT;
  click(pickupError, "p2bPickupLoad");
  await flush();
  check("PICKUP_ERROR", function () {
    assert.match(pickupError.host.innerHTML, /Mode retrait/);
    assert.match(pickupError.host.innerHTML, /liste de retrait refus/);
    assert.equal(pickupError.host.innerHTML.includes("Consultation"), false);
  });

  const syncDenied = boot(deny("sync.submit"), function () { throw new Error("fetch"); }, {
    SchoolSafeSync: { state: function () { throw new Error("queue"); }, syncNow: function () { throw new Error("queue"); } }
  });
  syncDenied.open("administration", "Mode hors ligne");
  check("SYNC_DENY", function () {
    assert.match(syncDenied.host.innerHTML, /sync\.submit/);
    assert.equal(syncDenied.host.innerHTML.includes("data-p2b-sync"), false);
  });

  let syncCalls = 0;
  const sync = boot(allowAll(), function () { throw new Error("fetch"); }, {
    SchoolSafeSync: {
      state: function () {
        syncCalls += 1;
        if (syncCalls === 1) return Promise.resolve({ pending: 0, conflicts: 0, syncing: false, operations: [], lastSyncAt: "" });
        return Promise.resolve({ pending: 0, conflicts: 0, syncing: false, operations: [], lastSyncAt: "2026-10-08T11:00:00.000Z" });
      },
      syncNow: function () { return Promise.resolve(); }
    }
  });
  sync.open("administration", "Mode hors ligne");
  assert.match(sync.host.innerHTML, /ss-state--loading/);
  await flush();
  check("SYNC_EMPTY", function () {
    assert.match(sync.host.innerHTML, /data-p2b-screen="sync"/);
    assert.match(sync.host.innerHTML, /data-p2b-sync="empty"/);
    assert.match(sync.host.innerHTML, /id="wiringBack"/);
  });
  click(sync, "p2bSyncNow");
  await flush();
  await flush();
  check("SYNC_SUCCESS", function () {
    assert.match(sync.host.innerHTML, /data-p2b-sync="success"/);
    assert.match(sync.host.innerHTML, /2026-10-08T11:00:00.000Z/);
  });

  const syncBusy = boot(allowAll(), function () { throw new Error("fetch"); }, {
    SchoolSafeSync: {
      state: function () { return { pending: 2, conflicts: 0, syncing: true, operations: [], lastSyncAt: "" }; },
      syncNow: function () { return Promise.resolve(); }
    }
  });
  syncBusy.open("administration", "Mode hors ligne");
  await flush();
  check("SYNC_BUSY", function () {
    assert.match(syncBusy.host.innerHTML, /data-p2b-sync="busy"/);
  });

  const syncError = boot(allowAll(), function () { throw new Error("fetch"); }, {
    SchoolSafeSync: {
      state: function () { return Promise.reject(new Error("file illisible")); },
      syncNow: function () { return Promise.resolve(); }
    }
  });
  syncError.open("administration", "Mode hors ligne");
  await flush();
  check("SYNC_ERROR", function () {
    assert.match(syncError.host.innerHTML, /data-p2b-sync="error"/);
    assert.match(syncError.host.innerHTML, /file illisible/);
  });

  if (failures.length) {
    console.error(failures.join("\n"));
    process.exit(1);
  }
  console.log("qa-rosario-visual-output-p2b: 5 surfaces, session r\u00e9elle, 3 viewports");
}

main().catch(function (error) {
  console.error(error);
  process.exit(1);
});
