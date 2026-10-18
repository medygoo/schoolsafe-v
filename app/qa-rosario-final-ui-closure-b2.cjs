"use strict";

const assert = require("node:assert/strict");
const fs = require("node:fs");
const path = require("node:path");
const vm = require("node:vm");

const root = path.resolve(__dirname, "..");
const failures = [];
const read = (relativePath) => fs.readFileSync(path.join(root, relativePath), "utf8");
const historical = read("database/pedagogy/v1/01_pedagogy_native.sql");
const additive = read("database/pedagogy/v2/01_teacher_assignment_list.sql");
const p2a = read("app/modules/core/visual-output-p2a.js");

const STUDENT_PROFILE = "aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa";
const OTHER_PROFILE = "bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb";
const SUBJECT = "cccccccc-cccc-4ccc-8ccc-cccccccccccc";
const CLASS_ID = "dddddddd-dddd-4ddd-8ddd-dddddddddddd";
const YEAR = "eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee";

function check(name, fn) {
  try { fn(); } catch (error) { failures.push(name + ": " + error.message); }
}

function checkAsync(name, fn) {
  return fn().catch(function (error) { failures.push(name + ": " + (error && error.stack ? error.stack : error)); });
}

function historicalList() {
  const start = historical.indexOf("function api.teacher_assignment_list()");
  const end = historical.indexOf("function api.teacher_assignment_create");
  assert.ok(start >= 0 && end > start, "teacher_assignment_list absent");
  return historical.slice(start, end);
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
        addEventListener: function (type, fn) { this.listeners[type] = fn; }
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
      if (id === "wiringSurface") return host;
      return host.querySelector("#" + id);
    }
  };
  vm.runInNewContext(p2a, context, { filename: "p2a.js" });
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
    canAccess: function () { return true; }
  };
}

function jsonResponse(status, body) {
  return { ok: status >= 200 && status < 300, status: status, json: function () { return Promise.resolve(body); } };
}

function flush() {
  return new Promise(function (resolve) { setTimeout(resolve, 0); });
}

function submit(session, id, fields) {
  const form = session.host.querySelector("#" + id);
  assert.ok(form && form.listeners.submit, id);
  const elements = {};
  Object.keys(fields).forEach(function (name) { elements[name] = { value: fields[name] }; });
  form.listeners.submit({ preventDefault: function () {}, target: { elements: elements } });
}

function live() {
  return { user: { role: "admin" }, live: true, hideDashboard: function () {}, restoreDashboard: function () {} };
}

function rows() {
  return [
    { id: "11111111-1111-4111-8111-111111111111", profile_id: STUDENT_PROFILE, teacher_name: "Jeanne Mbayo", class_name: "6e A", subject_name: "Math", class_id: CLASS_ID, is_tutor: true },
    { id: "22222222-2222-4222-8222-222222222222", profile_id: OTHER_PROFILE, teacher_name: "Paul Kabila", class_name: "6e A", subject_name: "Francais", class_id: CLASS_ID, is_tutor: false }
  ];
}

check("SERVER_EXPOSES_CANONICAL_TUTOR", function () {
  const list = historicalList();
  assert.equal(list.includes("'is_tutor'"), false);
  assert.match(additive, /'is_tutor', ta\.is_tutor/);
  assert.match(additive, /ta\.teacher_profile_id/);
  assert.match(additive, /school\.structure\.manage/);
  assert.equal(/create table/i.test(additive), false);
  assert.equal(/is_tutor\s*=\s*true/.test(additive), false);
});

check("CLIENT_DOES_NOT_INVENT_TUTOR", function () {
  assert.match(p2a, /row\.is_tutor === true/);
  assert.equal(/is_tutor\s*[:=]\s*true/.test(p2a), false);
  assert.match(p2a, /Titulaire de la classe/);
  assert.match(p2a, /Enseignant affecté/);
});

async function main() {
  await checkAsync("UI_SHOWS_SERVER_TUTOR", async function () {
    const session = boot(allowAll(), function () {
      return jsonResponse(200, { data: rows() });
    });
    session.context.SchoolSafeVisualOutputP2A.open("pedagogy", "Affectations", live());
    await flush();
    const html = session.host.innerHTML;
    const tutor = html.indexOf("Titulaire de la classe");
    const teacher = html.indexOf("Enseignant affecté");
    assert.ok(tutor >= 0 && teacher > tutor);
    assert.ok(html.indexOf("Jeanne Mbayo") < tutor);
    assert.ok(html.indexOf("Paul Kabila") < teacher);
    assert.equal(html.indexOf("Paul Kabila") < tutor || html.indexOf("Titulaire de la classe", tutor + 1) < 0, true);
  });

  await checkAsync("HOMEROOM_REREADS", async function () {
    let reads = 0;
    const session = boot(allowAll(), function (url, options) {
      if (url.indexOf("/homeroom") >= 0) {
        assert.equal(options.method, "POST");
        return jsonResponse(200, { data: { id: "home-1" } });
      }
      reads += 1;
      const listed = rows().map(function (row) { return Object.assign({}, row, { is_tutor: false }); });
      if (reads > 1) listed[1].is_tutor = true;
      return jsonResponse(200, { data: listed });
    });
    session.context.SchoolSafeVisualOutputP2A.open("pedagogy", "Affectations", live());
    await flush();
    assert.equal(session.host.innerHTML.includes("Titulaire de la classe"), false);
    submit(session, "p2aHomeroomForm", {
      profile_id: OTHER_PROFILE,
      subject_id: SUBJECT,
      class_id: CLASS_ID,
      academic_year_id: YEAR
    });
    await flush();
    await flush();
    const methods = session.calls.map(function (call) { return call.options.method || "GET"; });
    const postAt = methods.indexOf("POST");
    assert.ok(postAt >= 0);
    assert.equal(methods[postAt + 1], "GET");
    assert.match(session.host.innerHTML, /Paul Kabila/);
    const after = session.host.innerHTML;
    assert.ok(after.indexOf("Paul Kabila") < after.indexOf("Titulaire de la classe"));
    assert.ok(after.indexOf("Jeanne Mbayo") < after.indexOf("Enseignant affecté"));
  });

  await checkAsync("EXPLICIT_DENY", async function () {
    const session = boot(deny("school.structure.manage"), function () { throw new Error("fetch interdit"); });
    session.context.SchoolSafeVisualOutputP2A.open("pedagogy", "Affectations", live());
    await flush();
    assert.match(session.host.innerHTML, /Accès refusé/);
    assert.equal(session.calls.length, 0);
    assert.equal(session.host.innerHTML.includes("Titulaire de la classe"), false);
  });

  if (failures.length) {
    console.error(failures.join("\n"));
    process.exit(1);
  }
  console.log("qa-rosario-final-ui-closure-b2: titulaire relu, session réelle");
}

main();
