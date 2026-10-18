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

const p2c = read("app/modules/core/visual-output-p2c.js");
const css = read("app/styles/modules/visual-output-p2c.css");
const html = read("app/index.html");
const wiring = read("app/modules/core/visual-wiring.js");

check("MODULE_MISSING", function () {
  assert.ok(p2c.length > 0, "visual-output-p2c.js absent");
  assert.ok(css.length > 0, "visual-output-p2c.css absent");
});

check("EXACT_APIS", function () {
  assert.match(p2c, /\/school\/settings/);
  assert.match(p2c, /\/school\/academic-years/);
  assert.match(p2c, /\/native\/students\//);
  assert.match(p2c, /\/family/);
  assert.match(p2c, /\/native\/pedagogy\/classes/);
  assert.match(p2c, /\/native\/pedagogy\/subjects/);
  assert.match(p2c, /\/native\/pedagogy\/assignments/);
  assert.match(p2c, /\/native\/pedagogy\/lesson-plans/);
  assert.match(p2c, /\/grades/);
  assert.match(p2c, /\/averages/);
  assert.match(p2c, /\/native\/pedagogy\/rankings/);
  assert.match(p2c, /\/native\/finance\/fee-structures/);
  assert.match(p2c, /\/native\/finance\/payments/);
  assert.match(p2c, /\/native\/finance\/students\//);
  assert.match(p2c, /\/pilotage\/alerts/);
  assert.match(p2c, /pickup-authorizations/);
  assert.equal(p2c.includes("schoolsafe://card/"), false);
  assert.equal(p2c.includes("CARD_HMAC"), false);
  assert.equal(p2c.includes("createHmac"), false);
  assert.equal(p2c.includes(".sort("), false);
  assert.equal(p2c.includes("reduce("), false);
  assert.equal(/score\s*\/\s*/.test(p2c), false);
  assert.equal(p2c.includes("CREATE TABLE"), false);
  assert.equal(p2c.includes("/native/cards/replace"), false);
});

check("GAPS", function () {
  assert.match(p2c, /CARD_SIGNED_QR=BACKEND_GAP/);
  assert.match(p2c, /CARD_TEACHER_DATA=BACKEND_GAP/);
  assert.match(p2c, /CARD_AUTHORIZED_PERSON_SELECTION=UNRESOLVED/);
  assert.match(p2c, /REPORT_CARDS=BACKEND_GAP/);
  assert.match(p2c, /Pièces jointes : BACKEND_GAP/);
});

check("NO_INVENTED_DOMAINS", function () {
  for (const token of ["salaire", "biom", "cantine", "infirmerie", "rattrapage", "syscohada", "grand livre", "CREATE TABLE", "démo"]) {
    assert.equal(p2c.toLowerCase().includes(token), false, token);
  }
  assert.equal(/\bpaie\b/.test(p2c.toLowerCase()), false);
});

check("PERMISSIONS", function () {
  for (const code of [
    "school.student.read",
    "school.manage",
    "school.guardian.read",
    "school.class.read",
    "security.scan",
    "pedagogy.subject.read",
    "pedagogy.subject.manage",
    "pedagogy.assignment.read",
    "pedagogy.assignment.manage",
    "pedagogy.lesson-plan.read",
    "pedagogy.lesson-plan.manage",
    "pedagogy.grade.read",
    "pedagogy.grade.manage",
    "palmarques.read",
    "palmarques.manage",
    "finance.fee.read",
    "finance.fee.manage",
    "finance.payment.record",
    "finance.receipt.read",
    "finance.payment.cancel",
    "pilotage.alerts.read",
    "pilotage.alerts.manage"
  ]) {
    assert.match(p2c, new RegExp(code.replace(/\./g, "\\.")));
  }
  assert.match(p2c, /explicitDeny/);
  assert.match(p2c, /canAccess/);
  assert.match(p2c, /assigned_classes/);
  assert.match(p2c, /assigned_subjects/);
});

check("NAVIGATION", function () {
  assert.match(wiring, /SchoolSafeVisualOutputP2B\.open\(branchKey, actionName/);
  assert.match(wiring, /SchoolSafeVisualOutputP2C\.open\(branchKey, actionName/);
  assert.ok(
    wiring.indexOf("SchoolSafeVisualOutputP2C.open") > wiring.indexOf("SchoolSafeVisualOutputP2B.open"),
    "P2C doit suivre P2B"
  );
  assert.ok(
    wiring.indexOf("VISUAL_WIRING[") > wiring.indexOf("SchoolSafeVisualOutputP2C.open"),
    "P2C reste avant la carte des 37 cibles"
  );
  assert.match(html, /visual-output-p2c\.js/);
  assert.match(html, /visual-output-p2c\.css/);
  assert.ok(html.indexOf("visual-output-p2c.js") < html.indexOf("visual-wiring.js"));
});

check("VIEWPORTS", function () {
  assert.match(css, /@media \(min-width: 1440px\)/);
  assert.match(css, /@media \(min-width: 769px\) and \(max-width: 1024px\)/);
  assert.match(css, /@media \(max-width: 390px\)/);
  assert.match(css, /\.p2c-screen/);
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
  vm.runInNewContext(p2c, context, { filename: "visual-output-p2c.js" });
  return {
    host: host,
    calls: calls,
    open: function (branch, action, more) {
      return context.SchoolSafeVisualOutputP2C.open(branch, action, Object.assign({
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
const CLASS_ID = "bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb";
const SUBJECT = "cccccccc-cccc-4ccc-8ccc-cccccccccccc";
const ASSIGNMENT = "dddddddd-dddd-4ddd-8ddd-dddddddddddd";
const FEE = "eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee";
const PAYMENT = "ffffffff-ffff-4fff-8fff-ffffffffffff";
const ALERT = "11111111-1111-4111-8111-111111111111";
const RANKING = "22222222-2222-4222-8222-222222222222";

async function main() {
  check("FOREIGN_ACTIONS", function () {
    const session = boot(allowAll(), function () { return jsonResponse(200, { data: [] }); });
    assert.equal(session.open("school", "Cr\u00e9er l\u2019\u00e9l\u00e8ve"), false);
    assert.equal(session.open("school", "Personnes autoris\u00e9es"), false);
    assert.equal(session.open("security", "Entr\u00e9es et sorties"), false);
    assert.equal(session.open("finance", "Re\u00e7us PDF"), false);
    assert.equal(session.open("pedagogy", "Rattrapage p\u00e9dagogique"), false);
    assert.equal(session.open("administration", "Centre documentaire"), false);
    assert.equal(session.open("accounting", "Journal comptable"), false);
    assert.equal(session.calls.length, 0);
    assert.equal(session.host.innerHTML, "");
  });

  check("CARDS_MENU_NOT_INTERCEPTED", function () {
    const denied = boot(deny("school.student.read"), function () { throw new Error("fetch"); });
    assert.equal(denied.open("school", "Studio des cartes"), false);
    assert.equal(denied.host.innerHTML, "");
    assert.equal(denied.calls.length, 0);
    const demo = boot(allowAll(), function () { throw new Error("fetch"); });
    assert.equal(demo.open("school", "Studio des cartes", { live: false }), false);
    assert.equal(demo.calls.length, 0);
    const cards = boot(allowAll(), function () { throw new Error("fetch"); });
    assert.equal(cards.open("school", "Studio des cartes"), false);
    assert.equal(cards.calls.length, 0);
    assert.equal(cards.host.innerHTML.includes("CARD_SIGNED_QR"), false);
  });

  const subjects = boot(allowAll(), function (url, options) {
    if ((options.method || "GET") === "POST") return jsonResponse(201, { data: { id: SUBJECT } });
    if (url.indexOf("/native/pedagogy/subjects") >= 0) {
      return jsonResponse(200, { data: [{ id: SUBJECT, code: "MATH", name: "Mathematiques", cycle_key: "secondary", coefficient: 4 }] });
    }
    return jsonResponse(200, { data: [] });
  });
  subjects.open("pedagogy", "Mati\u00e8res");
  assert.match(subjects.host.innerHTML, /ss-state--loading/);
  await flush();
  check("PEDAGOGY_SUBJECTS", function () {
    assert.match(subjects.calls[0].url, /\/native\/pedagogy\/subjects/);
    assert.match(subjects.host.innerHTML, /Mathematiques/);
    assert.match(subjects.host.innerHTML, /id="p2cSubjectCreate"/);
  });
  subjects.host.querySelector("#p2cSubjectCode").value = "PHYS";
  subjects.host.querySelector("#p2cSubjectName").value = "Physique";
  subjects.host.querySelector("#p2cSubjectCycle").value = "secondary";
  click(subjects, "p2cSubjectCreate");
  await flush();
  check("SUBJECTS_SUCCESS", function () {
    const post = subjects.calls.find(function (call) { return (call.options.method || "") === "POST"; });
    assert.ok(post);
    assert.match(post.url, /\/native\/pedagogy\/subjects$/);
    const body = JSON.parse(post.options.body);
    assert.equal(body.code, "PHYS");
    assert.equal(body.name, "Physique");
    assert.equal(body.cycle_key, "secondary");
    assert.match(subjects.host.innerHTML, /ss-state--success/);
    assert.match(subjects.host.innerHTML, new RegExp(SUBJECT));
  });

  const deniedSubjects = boot(deny("pedagogy.subject.read"), function () { throw new Error("fetch"); });
  deniedSubjects.open("pedagogy", "Mati\u00e8res");
  check("SUBJECTS_EXPLICIT_DENY", function () {
    assert.match(deniedSubjects.host.innerHTML, /pedagogy\.subject\.read/);
    assert.equal(deniedSubjects.calls.length, 0);
  });

  const emptySubjects = boot(allowAll(), function () { return jsonResponse(200, { data: [] }); });
  emptySubjects.open("pedagogy", "Mati\u00e8res");
  await flush();
  check("SUBJECTS_EMPTY", function () {
    assert.match(emptySubjects.host.innerHTML, /ss-state--empty/);
  });

  const errorSubjects = boot(allowAll(), function () { return jsonResponse(500, { message: "echec matiere" }); });
  errorSubjects.open("pedagogy", "Mati\u00e8res");
  await flush();
  check("SUBJECTS_ERROR", function () {
    assert.match(errorSubjects.host.innerHTML, /ss-state--error/);
    assert.match(errorSubjects.host.innerHTML, /echec matiere/);
  });

  const assignments = boot({
    explicitDeny: function () { return false; },
    canAccess: function () { return true; },
    allowsScope: function (_user, _permission, scope) { return scope === "assigned_classes"; },
    scopeFor: function () { return { type: "assigned_classes", target: CLASS_ID }; }
  }, function (url) {
    if (url.indexOf("/native/pedagogy/assignments") >= 0) {
      return jsonResponse(200, { data: [{ id: ASSIGNMENT, title: "Devoir 1", class_id: CLASS_ID, subject_id: SUBJECT, type: "homework" }] });
    }
    return jsonResponse(200, { data: [] });
  });
  assignments.open("pedagogy", "Devoirs et corrections");
  await flush();
  check("PEDAGOGY_ASSIGNMENTS", function () {
    assert.match(assignments.calls[0].url, /\/native\/pedagogy\/assignments\?/);
    assert.match(assignments.calls[0].url, new RegExp("class_id=" + CLASS_ID));
    assert.match(assignments.host.innerHTML, /Devoir 1/);
    assert.match(assignments.host.innerHTML, /Pièces jointes : BACKEND_GAP/);
    assert.equal(/type="file"/.test(assignments.host.innerHTML), false);
  });

  const notebooks = boot(allowAll(), function (url) {
    if (url.indexOf("/lesson-plans") >= 0) {
      return jsonResponse(200, { data: [{ id: "lp", title: "Semaine 12", content: "Objectifs de la semaine", class_name: "6e A", subject_name: "Mathematiques" }] });
    }
    return jsonResponse(200, { data: [] });
  });
  notebooks.open("pedagogy", "Cahier de pr\u00e9paration de l\u2019enseignant");
  await flush();
  check("PEDAGOGY_NOTEBOOKS", function () {
    assert.match(notebooks.calls[0].url, /\/native\/pedagogy\/lesson-plans/);
    assert.match(notebooks.host.innerHTML, /Objectifs de la semaine/);
    assert.match(notebooks.host.innerHTML, /Semaine 12/);
  });

  const grades = boot(allowAll(), function (url, options) {
    if ((options.method || "GET") === "POST") return jsonResponse(200, { data: { saved: true } });
    if (url.indexOf("/grades") >= 0) {
      return jsonResponse(200, { data: [{ id: "g", student_id: STUDENT, student_name: "Amina Kabila", score: 14, matricule: "MAT-1" }] });
    }
    if (url.indexOf("/assignments") >= 0) {
      return jsonResponse(200, { data: [{ id: ASSIGNMENT, title: "Interrogation", class_name: "6e A" }] });
    }
    return jsonResponse(200, { data: [] });
  });
  grades.open("pedagogy", "\u00c9valuations et notes");
  await flush();
  click(grades, "p2cGrade0");
  await flush();
  check("GRADES_REAL_DATA", function () {
    assert.ok(grades.calls.some(function (call) { return call.url.indexOf("/native/pedagogy/assignments/" + ASSIGNMENT + "/grades") >= 0; }));
    assert.match(grades.host.innerHTML, /14/);
    assert.match(grades.host.innerHTML, /Amina Kabila/);
    assert.equal(grades.host.innerHTML.includes("moyenne"), false);
  });

  const averages = boot(allowAll(), function (url) {
    if (url.indexOf("/averages") >= 0) {
      return jsonResponse(200, { data: [{ subject_name: "Mathematiques", average: 12.5, coefficient: 4, max: 20 }] });
    }
    if (url.indexOf("/native/students?") >= 0) {
      return jsonResponse(200, { data: { rows: [{ id: STUDENT, first_name: "Amina", last_name: "Kabila", matricule: "MAT-1" }] } });
    }
    return jsonResponse(200, { data: [] });
  });
  averages.open("pedagogy", "Moyennes et coefficients");
  await flush();
  click(averages, "p2cAverage0");
  await flush();
  check("AVERAGES_REAL_DATA", function () {
    const call = averages.calls.find(function (item) { return item.url.indexOf("/averages") >= 0; });
    assert.ok(call);
    assert.match(call.url, new RegExp("/native/pedagogy/students/" + STUDENT + "/averages"));
    assert.equal(call.options.method || "GET", "GET");
    assert.match(averages.host.innerHTML, /12\.5/);
    assert.match(averages.host.innerHTML, /Mathematiques/);
  });

  const bulletin = boot(allowAll(), function () { throw new Error("fetch"); });
  bulletin.open("pedagogy", "Bulletins");
  check("REPORT_CARD_REAL_DATA", function () {
    assert.match(bulletin.host.innerHTML, /REPORT_CARDS=BACKEND_GAP/);
    assert.equal(bulletin.calls.length, 0);
  });

  const rankings = boot(allowAll(), function (url, options) {
    if ((options.method || "GET") === "POST") return jsonResponse(200, { data: { id: RANKING } });
    if (url.indexOf("/rankings/") >= 0) {
      return jsonResponse(200, { data: { id: RANKING, month: "2026-03", status: "draft", class_name: "6e A", data: { students: [{ student_id: STUDENT, weighted_average: 15 }] } } });
    }
    return jsonResponse(200, { data: [{ id: RANKING, month: "2026-03", status: "draft", class_name: "6e A" }] });
  });
  rankings.open("pedagogy", "Palmar\u00e8s");
  await flush();
  click(rankings, "p2cRanking0");
  await flush();
  check("PALMARES_SERVER", function () {
    assert.ok(rankings.calls.some(function (call) { return call.url.indexOf("/native/pedagogy/rankings?") >= 0 || call.url.endsWith("/native/pedagogy/rankings"); }));
    assert.ok(rankings.calls.some(function (call) { return call.url.indexOf("/native/pedagogy/rankings/" + RANKING) >= 0; }));
    assert.match(rankings.host.innerHTML, /2026-03/);
    assert.match(rankings.host.innerHTML, /15/);
  });

  const fees = boot(allowAll(), function (url, options) {
    if ((options.method || "GET") === "POST") return jsonResponse(201, { data: { id: FEE } });
    return jsonResponse(200, { data: [{ id: FEE, label: "Minerval", amount: 50, currency: "USD", cycle_key: "secondary" }] });
  });
  fees.open("finance", "Structure des frais");
  await flush();
  check("FINANCE_FEES", function () {
    assert.match(fees.calls[0].url, /\/native\/finance\/fee-structures/);
    assert.match(fees.host.innerHTML, /Minerval/);
    assert.match(fees.host.innerHTML, /50/);
  });
  fees.host.querySelector("#p2cFeeLabel").value = "Inscription";
  fees.host.querySelector("#p2cFeeAmount").value = "25";
  fees.host.querySelector("#p2cFeeYear").value = "yyyyyyyy-yyyy-4yyy-8yyy-yyyyyyyyyyyy";
  click(fees, "p2cFeeCreate");
  await flush();
  check("FEES_SUCCESS", function () {
    const post = fees.calls.find(function (call) { return (call.options.method || "") === "POST"; });
    const body = JSON.parse(post.options.body);
    assert.equal(body.label, "Inscription");
    assert.equal(body.amount, 25);
    assert.match(fees.host.innerHTML, /ss-state--success/);
  });

  const money = boot(allowAll(), function (url, options) {
    if ((options.method || "GET") === "POST" && url.indexOf("/payments/") >= 0 && url.indexOf("/cancel") >= 0) {
      return jsonResponse(200, { data: { cancelled: true } });
    }
    if ((options.method || "GET") === "POST") return jsonResponse(201, { data: { id: PAYMENT } });
    if (url.indexOf("/fees") >= 0) {
      return jsonResponse(200, { data: [
        { id: FEE, label: "Minerval", status: "pending", amount_expected: 50, amount_paid: 0, amount_remaining: 50, currency: "USD" },
        { id: "paid-fee", label: "Tenue", status: "paid", amount_expected: 10, amount_paid: 10, amount_remaining: 0, currency: "USD" }
      ] });
    }
    if (url.indexOf("/payments") >= 0) {
      return jsonResponse(200, { data: [{ id: PAYMENT, amount: 20, currency: "USD", status: "valid", mode: "cash" }] });
    }
    if (url.indexOf("/native/students?") >= 0) {
      return jsonResponse(200, { data: { rows: [{ id: STUDENT, first_name: "Amina", last_name: "Kabila", matricule: "MAT-1" }] } });
    }
    return jsonResponse(200, { data: [] });
  });
  money.open("finance", "Encaissements");
  await flush();
  click(money, "p2cPayStudent0");
  await flush();
  check("FINANCE_COLLECTIONS", function () {
    assert.ok(money.calls.some(function (call) { return call.url.indexOf("/native/finance/students/" + STUDENT + "/payments") >= 0; }));
    assert.match(money.host.innerHTML, /20/);
    assert.match(money.host.innerHTML, /valid/);
  });
  money.host.querySelector("#p2cPayAmount").value = "15";
  click(money, "p2cPayCreate");
  await flush();
  check("COLLECTIONS_SUCCESS", function () {
    const post = money.calls.find(function (call) { return (call.options.method || "") === "POST" && call.url.indexOf("/native/finance/payments") >= 0 && call.url.indexOf("cancel") < 0; });
    const body = JSON.parse(post.options.body);
    assert.equal(body.student_fee_id, FEE);
    assert.equal(body.amount, 15);
    assert.match(money.host.innerHTML, /ss-state--success/);
  });

  const unpaid = boot(allowAll(), function (url) {
    if (url.indexOf("/fees") >= 0) {
      return jsonResponse(200, { data: [
        { id: FEE, label: "Minerval", status: "pending", amount_remaining: 50, currency: "USD" },
        { id: "paid-fee", label: "Tenue", status: "paid", amount_remaining: 0, currency: "USD" }
      ] });
    }
    if (url.indexOf("/native/students?") >= 0) {
      return jsonResponse(200, { data: { rows: [{ id: STUDENT, first_name: "Amina", last_name: "Kabila", matricule: "MAT-1" }] } });
    }
    return jsonResponse(200, { data: [] });
  });
  unpaid.open("finance", "Impay\u00e9s et soldes");
  await flush();
  click(unpaid, "p2cUnpaid0");
  await flush();
  check("FINANCE_UNPAID", function () {
    assert.match(unpaid.host.innerHTML, /pending/);
    assert.match(unpaid.host.innerHTML, /Minerval/);
    assert.equal(unpaid.host.innerHTML.includes("Tenue"), false);
  });

  const alerts = boot(allowAll(), function (url, options) {
    if ((options.method || "GET") === "POST") return jsonResponse(200, { data: { status: "acknowledged" } });
    return jsonResponse(200, { data: [{ id: ALERT, status: "open", severity: "important", source_module: "finance" }], count: 1 });
  });
  alerts.open("pilotage", "Alertes importantes");
  await flush();
  check("FINANCE_ALERTS", function () {
    assert.match(alerts.calls[0].url, /\/pilotage\/alerts\?/);
    assert.match(alerts.calls[0].url, /domain=finance/);
    assert.match(alerts.calls[0].options.headers.Authorization, /Bearer live-token/);
    assert.match(alerts.host.innerHTML, /important/);
    assert.match(alerts.host.innerHTML, /open/);
  });
  click(alerts, "p2cAlertAck0");
  await flush();
  check("ALERTS_SUCCESS", function () {
    const post = alerts.calls.find(function (call) { return (call.options.method || "") === "POST"; });
    assert.match(post.url, new RegExp("/pilotage/alerts/" + ALERT + "/acknowledge"));
    assert.match(alerts.host.innerHTML, /ss-state--success/);
  });

  if (failures.length) {
    console.error(failures.join("\n"));
    process.exit(1);
  }
  console.log("qa-rosario-visual-output-p2c: 4 domaines, session réelle, 3 viewports");
}

main().catch(function (error) {
  console.error(error);
  process.exit(1);
});
