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

const p1 = read("app/modules/core/visual-output-p1.js");
const css = read("app/styles/modules/visual-output-p1.css");
const html = read("app/index.html");
const wiring = read("app/modules/core/visual-wiring.js");
const dossier = read("app/modules/school/student-dossier-demo.js");
const app = read("app/app.js");

check("MODULE_MISSING", function () {
  assert.ok(p1.length > 0, "visual-output-p1.js absent");
  assert.ok(css.length > 0, "visual-output-p1.css absent");
});

check("EXACT_APIS", function () {
  assert.match(p1, /\/native\/finance\/receipts\//);
  assert.match(p1, /\/native\/finance\/cash-register\/open/);
  assert.match(p1, /\/native\/finance\/cash-register\/close/);
  assert.match(p1, /\/native\/finance\/reports\/daily\?date=/);
  assert.match(p1, /\/native\/finance\/students\//);
  assert.match(p1, /\/fees/);
  assert.match(p1, /\/native\/students\//);
  assert.match(p1, /\/health/);
  assert.match(p1, /\/dietary/);
  assert.equal(p1.includes("/native/canteen"), false);
  assert.equal(p1.includes("jsPDF"), false);
  assert.equal(p1.includes("total_paid +"), false);
  assert.equal(p1.includes(".reduce("), false);
});

check("NO_DEMO_DATA", function () {
  for (const token of ["Ethan", "Lucas Martin", "Chloé", "REC-2026", "150000", "demo-s1", "DÉMO"]) {
    assert.equal(p1.includes(token), false, token);
  }
});

check("NO_GAP_OR_FUTURE", function () {
  for (const token of ["Historique du jour", "Soumettre la journée", "Rattrapage pédagogique", "Versement de rattrapage", "Présences repas", "Dossiers santé", "Enregistrer un passage", "CREATE TABLE"]) {
    assert.equal(p1.includes(token), false, token);
  }
});

check("PERMISSIONS", function () {
  for (const code of [
    "finance.receipt.read",
    "finance.cash_register.open",
    "finance.cash_register.close",
    "finance.report.read",
    "finance.fee.read",
    "school.student.health.read",
    "school.student.health.manage",
    "school.student.dietary.read",
    "school.student.dietary.manage"
  ]) {
    assert.match(p1, new RegExp(code.replace(/\./g, "\\.")));
  }
  assert.match(p1, /explicitDeny/);
  assert.match(p1, /canAccess/);
  assert.match(p1, /own_children/);
});

check("NAVIGATION", function () {
  assert.match(wiring, /SchoolSafeVisualOutputP1\.open\(branchKey, actionName/);
  assert.match(app, /SchoolSafeVisualWiring\.open\(branchKey, actionName/);
  assert.match(html, /visual-output-p1\.js/);
  assert.match(html, /visual-output-p1\.css/);
  assert.match(dossier, /data-p1-dossier="health"/);
  assert.match(dossier, /data-p1-dossier="dietary"/);
});

check("VIEWPORTS", function () {
  assert.match(css, /@media \(min-width: 1440px\)/);
  assert.match(css, /@media \(min-width: 769px\) and \(max-width: 1024px\)/);
  assert.match(css, /@media \(max-width: 390px\)/);
  assert.match(css, /\.p1-screen/);
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
  const restored = { count: 0 };
  const context = {
    console: console,
    setTimeout: setTimeout,
    clearTimeout: clearTimeout,
    URLSearchParams: URLSearchParams,
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
      const type = button.type || "button";
      return '<button type="' + type + '" id="' + id + '">' + button.label + "</button>";
    },
    print: function () { context.printed = (context.printed || 0) + 1; }
  };
  context.window = context;
  context.document = {
    getElementById: function (id) {
      if (id === "wiringSurface") return host;
      return host.querySelector("#" + id);
    }
  };
  vm.runInNewContext(p1, context, { filename: "visual-output-p1.js" });
  return {
    host: host,
    calls: calls,
    restored: restored,
    open: function (branch, action, extra) {
      return context.SchoolSafeVisualOutputP1.open(branch, action, Object.assign({
        user: extra && extra.user ? extra.user : { role: "cashier" },
        live: !extra || extra.live !== false,
        hideDashboard: function () {},
        restoreDashboard: function () { restored.count += 1; }
      }, extra || {}));
    },
    mount: function (element, screen, extra) {
      context.SchoolSafeVisualOutputP1.mount(element, screen, extra);
    },
    printed: function () { return context.printed || 0; }
  };
}

function allowAll() {
  return {
    explicitDeny: function () { return false; },
    canAccess: function () { return true; },
    scopeFor: function () { return { type: "school" }; }
  };
}

function deny(code) {
  return {
    explicitDeny: function (_user, permission) { return permission === code; },
    canAccess: function () { return true; },
    scopeFor: function () { return { type: "school" }; }
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

const paymentId = "11111111-1111-4111-8111-111111111111";
const studentId = "22222222-2222-4222-8222-222222222222";
const otherId = "33333333-3333-4333-8333-333333333333";
const registerId = "44444444-4444-4444-8444-444444444444";

async function runBehaviors() {
  await checkAsync("DEMO_HAS_NO_DATA", async function () {
    const session = boot(allowAll(), function () { throw new Error("fetch interdit"); });
    assert.equal(session.open("finance", "Reçus PDF", { live: false }), true);
    assert.match(session.host.innerHTML, /Session réelle requise/);
    assert.equal(session.calls.length, 0);
    assert.equal(session.host.innerHTML.includes("Lucas"), false);
  });

  await checkAsync("EXPLICIT_DENY", async function () {
    const session = boot(deny("finance.receipt.read"), function () { throw new Error("fetch interdit"); });
    session.open("finance", "Reçus", { live: true });
    assert.match(session.host.innerHTML, /Accès refusé/);
    assert.match(session.host.innerHTML, /finance\.receipt\.read/);
    assert.equal(session.calls.length, 0);
  });

  await checkAsync("RECEIPT_FLOW", async function () {
    const session = boot(allowAll(), function (url) {
      if (url.indexOf("/native/finance/receipts/" + paymentId) >= 0) {
        return jsonResponse(200, { data: {
          payment_id: paymentId, receipt_no: "R-1", student_name: "Élève réel", student_matricule: "M-1",
          fee_label: "Frais", amount: 12, currency: "CDF", received_at: "2026-10-01", mode: "cash", reference: "REF"
        } });
      }
      return jsonResponse(404, { message: "Reçu introuvable" });
    });
    session.open("finance", "Produire un reçu PDF", { live: true });
    assert.match(session.host.history[0], /Chargement|paiement connu|Reçu/);
    const form = session.host.querySelector("#p1ReceiptForm");
    assert.ok(form, "formulaire reçu absent");
    form.listeners.submit({ preventDefault: function () {}, target: { elements: { payment_id: { value: paymentId } } } });
    await flush();
    assert.equal(session.calls[0].url, "/native/finance/receipts/" + paymentId);
    assert.equal(session.calls[0].options.method, "GET");
    assert.match(session.host.innerHTML, /Élève réel/);
    assert.match(session.host.innerHTML, /CDF/);
    assert.match(session.host.innerHTML, /id="p1Print"/);
    assert.equal(session.host.innerHTML.includes("application/pdf"), false);
    session.host.querySelector("#p1Print").listeners.click();
    assert.equal(session.printed(), 1);
    session.host.querySelector("#wiringBack").listeners.click();
    assert.equal(session.host.hidden, true);
    assert.equal(session.restored.count, 1);
  });

  await checkAsync("RECEIPT_EMPTY_AND_ERROR", async function () {
    const empty = boot(allowAll(), function () { return jsonResponse(404, { message: "Reçu introuvable" }); });
    empty.open("finance", "Reçus PDF", { live: true });
    empty.host.querySelector("#p1ReceiptForm").listeners.submit({ preventDefault: function () {}, target: { elements: { payment_id: { value: paymentId } } } });
    await flush();
    assert.match(empty.host.innerHTML, /ss-state--empty|Reçu introuvable/);
    const broken = boot(allowAll(), function () { return jsonResponse(500, { message: "Lecture impossible" }); });
    broken.open("finance", "Reçus PDF", { live: true });
    broken.host.querySelector("#p1ReceiptForm").listeners.submit({ preventDefault: function () {}, target: { elements: { payment_id: { value: paymentId } } } });
    await flush();
    assert.match(broken.host.innerHTML, /Lecture impossible/);
  });

  await checkAsync("CASH_OPEN_CLOSE", async function () {
    const session = boot(allowAll(), function (url, options) {
      if (url.endsWith("/open")) return jsonResponse(200, { data: { id: registerId } });
      const body = JSON.parse(options.body);
      assert.deepEqual(Object.keys(body), ["register_id"]);
      return jsonResponse(200, { data: { closed: true } });
    });
    session.open("finance", "Clôtures", { live: true });
    assert.match(session.host.innerHTML, /id="p1CashOpen"/);
    assert.match(session.host.innerHTML, /id="p1CashClose"/);
    assert.equal(session.host.innerHTML.includes("journal"), false);
    session.host.querySelector("#p1CashOpen").listeners.click();
    await flush();
    assert.equal(session.calls[0].options.method, "POST");
    assert.match(session.calls[0].url, /\/native\/finance\/cash-register\/open$/);
    assert.equal(session.calls[0].options.body, undefined);
    assert.match(session.host.innerHTML, /succès|Caisse ouverte|register/i);
    assert.match(session.host.innerHTML, new RegExp(registerId));
    session.host.querySelector("#p1RegisterId").value = registerId;
    session.host.querySelector("#p1CashClose").listeners.click();
    await flush();
    assert.match(session.calls[1].url, /\/native\/finance\/cash-register\/close$/);
    assert.match(session.host.innerHTML, /Clôture enregistrée|closed|succès/i);
  });

  await checkAsync("CASH_PARTIAL_PERMISSION", async function () {
    const access = {
      explicitDeny: function (_user, code) { return code === "finance.cash_register.close"; },
      canAccess: function (_user, code) { return code === "finance.cash_register.open"; },
      scopeFor: function () { return { type: "school" }; }
    };
    const session = boot(access, function () { return jsonResponse(200, { data: { id: registerId } }); });
    session.open("finance", "Caisses", { live: true });
    assert.match(session.host.innerHTML, /id="p1CashOpen"/);
    assert.equal(session.host.querySelector("#p1CashClose"), null);
  });

  await checkAsync("DAILY_REPORT", async function () {
    const session = boot(allowAll(), function (url) {
      assert.match(url, /\/native\/finance\/reports\/daily\?date=2026-10-08$/);
      return jsonResponse(200, { data: { date: "2026-10-08", total_paid: 40, payment_count: 2 } });
    });
    session.open("finance", "Exports financiers", { live: true });
    session.host.querySelector("#p1ReportForm").listeners.submit({
      preventDefault: function () {},
      target: { elements: { date: { value: "2026-10-08" } } }
    });
    await flush();
    assert.match(session.host.innerHTML, /2026-10-08/);
    assert.match(session.host.innerHTML, /40/);
    assert.match(session.host.innerHTML, /Aucune somme de devises/);
    assert.equal(session.host.innerHTML.includes("USD"), false);
    session.open("finance", "Rapports financiers", { live: true });
    session.open("finance", "Imprimer les rapports", { live: true });
    session.open("finance", "Exports", { live: true });
  });

  await checkAsync("PARENT_FEES_SCOPE", async function () {
    const access = {
      explicitDeny: function () { return false; },
      canAccess: function () { return true; },
      scopeFor: function () { return { type: "own_children" }; }
    };
    const session = boot(access, function (url) {
      if (url === "/native/parent/children") {
        return jsonResponse(200, { data: [{ student_id: studentId, first_name: "Awa", last_name: "Kabila" }] });
      }
      if (url === "/native/finance/students/" + studentId + "/fees") {
        return jsonResponse(200, { data: [{ id: "fee-1", label: "Scolarité", amount_expected: 10, amount_paid: 4, amount_remaining: 6, status: "partial", currency: "CDF", due_date: "2026-10-30" }] });
      }
      if (url.indexOf(otherId) >= 0) return jsonResponse(200, { data: [{ label: "Secret", amount_expected: 999, currency: "USD" }] });
      return jsonResponse(404, { message: "introuvable" });
    });
    session.open("finance", "Frais scolaires", { live: true, user: { role: "parent" } });
    await flush();
    assert.match(session.host.innerHTML, /Awa/);
    assert.equal(session.host.innerHTML.includes(otherId), false);
    assert.equal(session.host.querySelector("#p1StudentId"), null);
    session.host.querySelector("#p1ChildList").listeners.click({ target: { getAttribute: function (name) { return name === "data-p1-child" ? otherId : null; } } });
    await flush();
    assert.equal(session.calls.some(function (call) { return call.url.indexOf(otherId) >= 0; }), false);
    assert.match(session.host.innerHTML, /périmètre/);
    session.host.querySelector("#p1ChildList").listeners.click({ target: { getAttribute: function (name) { return name === "data-p1-child" ? studentId : null; } } });
    await flush();
    assert.match(session.host.innerHTML, /Scolarité/);
    assert.match(session.host.innerHTML, /CDF/);
    assert.equal(session.host.innerHTML.includes("999"), false);
  });

  await checkAsync("STAFF_FEES_EMPTY", async function () {
    const session = boot(allowAll(), function () { return jsonResponse(200, { data: [] }); });
    session.open("finance", "Frais scolaires", { live: true, user: { role: "finance" } });
    session.host.querySelector("#p1FeesForm").listeners.submit({
      preventDefault: function () {},
      target: { elements: { student_id: { value: studentId } } }
    });
    await flush();
    assert.equal(session.calls[0].url, "/native/finance/students/" + studentId + "/fees");
    assert.match(session.host.innerHTML, /Aucun frais|ss-state--empty/);
  });

  await checkAsync("HEALTH_READ_WRITE", async function () {
    let puts = 0;
    const session = boot(allowAll(), function (url, options) {
      if (options.method === "PUT") {
        puts += 1;
        const body = JSON.parse(options.body);
        assert.equal(Object.prototype.hasOwnProperty.call(body, "blood_type"), true);
        assert.equal(Object.prototype.hasOwnProperty.call(body, "infirmary"), false);
        return jsonResponse(200, { data: { blood_type: body.blood_type } });
      }
      return jsonResponse(200, { data: { profile: {}, conditions: [], allergies: [], medications: [] } });
    });
    const host = createHost();
    session.mount(host, "health", { user: { role: "admin" }, live: true, studentId: studentId });
    await flush();
    assert.equal(session.calls[0].url, "/native/students/" + studentId + "/health");
    assert.match(host.innerHTML, /Aucune fiche santé|ss-state--empty/);
    assert.match(host.innerHTML, /id="p1HealthForm"/);
    host.querySelector("#p1HealthForm").listeners.submit({
      preventDefault: function () {},
      target: { elements: {
        blood_type: { value: "O+" },
        primary_doctor_name: { value: "Dr Réel" },
        primary_doctor_phone: { value: "" },
        medical_notes: { value: "" },
        emergency_instructions: { value: "" },
        medical_declaration_completed: { checked: true },
        medical_consent_status: { checked: false }
      } }
    });
    await flush();
    assert.equal(puts, 1);
    assert.match(session.calls[1].url, /\/native\/students\/.+\/health$/);
    assert.equal(session.calls[1].options.method, "PUT");
    assert.match(host.innerHTML, /Enregistré|succès|ss-state--success/);
  });

  await checkAsync("HEALTH_WRITE_HIDDEN_WITHOUT_MANAGE", async function () {
    const access = {
      explicitDeny: function (_user, code) { return code === "school.student.health.manage"; },
      canAccess: function (_user, code) { return code === "school.student.health.read"; },
      scopeFor: function () { return { type: "school" }; }
    };
    const session = boot(access, function () {
      return jsonResponse(200, { data: { profile: { blood_type: "A+" }, conditions: [{ name: "Asthme" }], allergies: [], medications: [] } });
    });
    const host = createHost();
    session.mount(host, "health", { user: { role: "nurse" }, live: true, studentId: studentId });
    await flush();
    assert.match(host.innerHTML, /A\+/);
    assert.match(host.innerHTML, /Asthme/);
    assert.equal(host.querySelector("#p1HealthForm"), null);
  });

  await checkAsync("DIETARY_READ_WRITE", async function () {
    const session = boot(allowAll(), function (url, options) {
      if (options.method === "PUT") {
        const body = JSON.parse(options.body);
        assert.deepEqual(Object.keys(body).sort(), ["dietary_declaration_completed", "parent_food_note"]);
        return jsonResponse(200, { data: body });
      }
      if (options.method === "POST") {
        assert.match(url, /\/dietary\/restrictions$/);
        return jsonResponse(200, { data: { id: "r1" } });
      }
      return jsonResponse(200, { data: { profile: { dietary_declaration_completed: false, parent_food_note: null }, restrictions: [], preferences: [] } });
    });
    const host = createHost();
    session.mount(host, "dietary", { user: { role: "admin" }, live: true, studentId: studentId });
    await flush();
    assert.equal(session.calls[0].url, "/native/students/" + studentId + "/dietary");
    assert.match(host.innerHTML, /Aucune restriction|ss-state--empty|Profil alimentaire/);
    host.querySelector("#p1DietaryForm").listeners.submit({
      preventDefault: function () {},
      target: { elements: { dietary_declaration_completed: { checked: true }, parent_food_note: { value: "Sans arachide" } } }
    });
    await flush();
    assert.equal(session.calls[1].options.method, "PUT");
    host.querySelector("#p1RestrictionForm").listeners.submit({
      preventDefault: function () {},
      target: { elements: { kind: { value: "restriction" }, label: { value: "Arachide" }, notes: { value: "" } } }
    });
    await flush();
    assert.match(session.calls[2].url, /\/native\/students\/.+\/dietary\/restrictions$/);
  });

  await checkAsync("GAPS_STAY_CLOSED", async function () {
    const session = boot(allowAll(), function () { throw new Error("fetch interdit"); });
    assert.equal(session.open("finance", "Historique du jour", { live: true }), false);
    assert.equal(session.open("finance", "Soumettre la journée", { live: true }), false);
    assert.equal(session.open("care", "Dossiers santé", { live: true }), false);
    assert.equal(session.open("pedagogy", "Rattrapage pédagogique", { live: true }), false);
    assert.equal(session.calls.length, 0);
  });

  await checkAsync("LOADING_VISIBLE", async function () {
    const session = boot(allowAll(), function () { return jsonResponse(200, { data: [] }); });
    session.open("finance", "Frais scolaires", { live: true, user: { role: "finance" } });
    session.host.querySelector("#p1FeesForm").listeners.submit({
      preventDefault: function () {},
      target: { elements: { student_id: { value: studentId } } }
    });
    assert.ok(session.host.history.some(function (html) { return /Chargement/.test(html); }));
    await flush();
  });
}

function checkAsync(name, fn) {
  return fn().catch(function (error) { failures.push(name + ": " + error.message); });
}

runBehaviors().then(function () {
  if (failures.length) {
    console.error(failures.join("\n"));
    process.exit(1);
  }
  console.log("qa-rosario-visual-output-p1: 6 surfaces, session réelle, 3 viewports");
}).catch(function (error) {
  console.error(error);
  process.exit(1);
});
