const assert = require("node:assert/strict");
const fs = require("node:fs");
const path = require("node:path");

const root = path.resolve(__dirname, "..");
const read = (file) => fs.readFileSync(path.join(root, file), "utf8");
const ui = read("app/modules/security/scan-control.js");
const service = read("server/src/scancontrol/service.ts");
const sql = read("database/scancontrol/v1/01_scan_control.sql");
const legacy = read("server/src/cardsnative/service.ts");
const app = read("app/app.js");
const html = read("app/index.html");

for (const label of ["MODE ENTRÉE", "MODE PRÉ-SORTIE", "MODE SORTIE", "MODE FRAIS", "MODE VÉRIFICATION"]) {
  assert.match(ui, new RegExp(label));
}
assert.match(ui, /Réseau absent/);
assert.equal(ui.includes("createHmac"), false);
assert.equal(ui.includes("schoolsafe://student/"), false);
assert.match(ui, /navigator\.onLine === false/);
assert.match(service, /scanner_entry_record/);
assert.match(service, /scanner_pickup_confirm/);
assert.match(service, /scanner_fee_consult/);
assert.match(service, /scanner_pickup_prepare/);
assert.match(service, /proof_token/);
assert.match(service, /PICKUP_PROOF_REQUIRED/);
assert.match(service, /Ce mode n'est pas un accès officiel du scanner/);
assert.equal(service.includes("pickupConfirm"), false);
assert.equal(service.includes('event_type: "exit"'), false);
assert.match(service, /signCardNumber/);
assert.equal(service.includes("api.card_replace"), false);
assert.equal(service.includes("api.attendance_apply"), false);
assert.equal(sql.includes("api.card_replace"), false);
assert.equal(sql.includes("create or replace function api.attendance_apply"), false);
assert.equal(sql.includes("create or replace function api.pickup_confirm"), false);
assert.match(legacy, /signCardNumber\(cardNumber, secret\)/);
assert.equal(legacy.includes('digest("hex")'), false);
assert.match(ui, /OFFICIAL_MODES = \["entry", "pickup", "fees", "verify"\]/);
assert.match(ui, /Je confirme avoir contrôlé la personne présente/);
assert.match(app, /openScanControlModule/);
assert.match(html, /id="openScanControl"/);
assert.match(html, /scan-control\.js/);
assert.match(ui, /id="scanControlLocation"/);
assert.match(ui, /\/native\/scan-control\/locations/);
assert.match(ui, /location_id/);
assert.match(ui, /fetchGeneration/);
assert.match(ui, /Aucun lieu de scan n'est disponible/);
assert.match(ui, /Les lieux de scan n'ont pas pu être chargés/);
assert.match(ui, /Choisissez le lieu du scan/);
assert.equal(ui.includes("localStorage"), false);
assert.match(ui, /schoolsafe-v2-session/);
assert.match(read("app/styles/modules/scan-control.css"), /scan-control-location/);

const vm = require("node:vm");

function response(data, status) {
  return { ok: status < 400, status: status, json: async () => ({ data: data }) };
}

function harness(schoolId, onLine, fetchImpl) {
  const storage = { "schoolsafe-v2-session": JSON.stringify({ schoolId: schoolId, token: "session-token" }) };
  const nodes = new Map();
  function ensure(id) {
    if (!nodes.has(id)) {
      const element = {
        id: id,
        hidden: false,
        disabled: false,
        value: "",
        checked: false,
        textContent: "",
        _html: "",
        listeners: {},
        children: [],
        options: [],
        addEventListener(type, fn) {
          this.listeners[type] = this.listeners[type] || [];
          this.listeners[type].push(fn);
        },
        querySelector(selector) {
          if (selector.charAt(0) === "#") return nodes.get(selector.slice(1)) || null;
          return null;
        },
        querySelectorAll(selector) {
          if (selector === "[data-mode]") return this.children.filter((child) => child.mode);
          if (selector === "[data-person]") return this.children.filter((child) => child.person);
          if (selector === "[data-manage]") return this.children.filter((child) => child.manage);
          return [];
        },
        getAttribute(name) {
          if (name === "data-mode") return this.mode || null;
          if (name === "data-person") return this.person || null;
          if (name === "data-manage") return this.manage || null;
          return null;
        },
      };
      Object.defineProperty(element, "innerHTML", {
        get() { return this._html; },
        set(value) {
          this._html = String(value);
          this.children = [];
          this.options = [];
          const ids = [...this._html.matchAll(/id="([^"]+)"/g)].map((match) => match[1]);
          for (const found of ids) ensure(found);
          const buttons = [...this._html.matchAll(/<button\b([^>]*)>([\s\S]*?)<\/button>/g)];
          for (const buttonMatch of buttons) {
            const button = ensure("button-" + id + "-" + this.children.length);
            const mode = /data-mode="([^"]*)"/.exec(buttonMatch[1]);
            const person = /data-person="([^"]*)"/.exec(buttonMatch[1]);
            const manage = /data-manage="([^"]*)"/.exec(buttonMatch[1]);
            button.mode = mode ? mode[1] : "";
            button.person = person ? person[1] : "";
            button.manage = manage ? manage[1] : "";
            button.textContent = buttonMatch[2];
            this.children.push(button);
          }
          const options = [...this._html.matchAll(/<option\b([^>]*)>([\s\S]*?)<\/option>/g)];
          for (const optionMatch of options) {
            const optionValue = /value="([^"]*)"/.exec(optionMatch[1]);
            this.options.push({ value: optionValue ? optionValue[1] : "", label: optionMatch[2] });
          }
        },
      });
      nodes.set(id, element);
    }
    return nodes.get(id);
  }
  const host = ensure("host");
  const calls = [];
  const traffic = [];
  const pending = [];
  const sandbox = {
    window: {
      document: { getElementById(id) { return id === "host" ? host : nodes.get(id) || null; } },
      navigator: { onLine: onLine !== false },
      schoolSafeApiBase: "",
      sessionStorage: {
        getItem(key) { return Object.prototype.hasOwnProperty.call(storage, key) ? storage[key] : null; },
        setItem(key, value) { storage[key] = String(value); },
      },
      FormData: class FormData {
        get(name) {
          if (name === "qr") return "schoolsafe://card/SS-1/abcdef";
          if (name === "location") return (nodes.get("scanControlLocation") || {}).value || "";
          return "";
        }
      },
      fetch(url, options) {
        const entry = {
          url: String(url),
          method: options && options.method ? options.method : "GET",
          body: options && options.body ? String(options.body) : "",
        };
        calls.push(entry.url);
        traffic.push(entry);
        if (fetchImpl) return fetchImpl(entry, pending);
        if (entry.url.includes("/locations")) {
          return new Promise((resolve) => pending.push(resolve));
        }
        return Promise.resolve(response({
          capabilities: ["SCAN_ENTRY", "SCAN_PRE_EXIT", "SCAN_GATE_EXIT", "SCAN_PICKUP", "SCAN_VERIFY"],
          fee_campaign_open: false,
        }, 200));
      },
    },
  };
  vm.runInNewContext(ui, sandbox, { filename: "scan-control.js" });
  sandbox.window.SchoolSafeScanControl.render("host");
  return { nodes, calls, traffic, pending, storage, sandbox };
}

assert.equal(ui.includes("localStorage"), false);

async function proveLocationUi() {
  const first = harness("school-a");
  const locationCalls = () => first.calls.filter((url) => url.includes("/locations")).length;
  assert.equal(locationCalls(), 1);
  first.storage["schoolsafe-v2-session"] = JSON.stringify({ schoolId: "school-b", token: "next-session" });
  first.pending[0](response({ locations: [{ id: "old-location", code: "OLD", label: "Ancien portail", kind: "gate" }] }, 200));
  await new Promise((resolve) => setImmediate(resolve));
  assert.equal(locationCalls(), 2);
  assert.equal((first.nodes.get("scanControlLocation").options || []).some((option) => option.value === "old-location"), false);
  first.pending[1](response({ locations: [{ id: "new-location", code: "NEW", label: "Nouveau portail", kind: "gate" }] }, 200));
  await new Promise((resolve) => setImmediate(resolve));
  const select = first.nodes.get("scanControlLocation");
  assert.equal(select.options.some((option) => option.value === "old-location"), false);
  assert.equal(select.options.some((option) => option.value === "new-location"), true);
  assert.equal(first.storage["schoolsafe-v2-session"].includes("old-location"), false);

  const offlineWindow = harness("school-a", false);
  assert.match(offlineWindow.nodes.get("scanControlResult").innerHTML, /Réseau absent/);
  assert.equal(offlineWindow.calls.length, 0);
}

const flush = () => new Promise((resolve) => setImmediate(resolve));
const studentA = "11111111-1111-4111-8111-111111111111";
const cardA = "22222222-2222-4222-8222-222222222222";
const studentB = "33333333-3333-4333-8333-333333333333";
const cardB = "44444444-4444-4444-8444-444444444444";
const issuedCard = "55555555-5555-4555-8555-555555555555";
const replacedCard = "66666666-6666-4666-8666-666666666666";
const successWords = /Carte émise|Perte enregistrée|Carte remplacée|Réimpression autorisée|Carte révoquée/;

function click(node, label) {
  const fn = node && node.listeners && node.listeners.click && node.listeners.click[0];
  assert.ok(fn, label || "click");
  fn();
}

function submit(node, label) {
  const fn = node && node.listeners && node.listeners.submit && node.listeners.submit[0];
  assert.ok(fn, label || "submit");
  fn({ preventDefault() {} });
}

function manageButton(view, action) {
  const button = view.nodes.get("scanControlManage").children.find((child) => child.manage === action);
  assert.ok(button, action);
  return button;
}

async function proveCardUi() {
  const script = {
    scan: {
      result: "VALID",
      student_id: studentA,
      card_id: cardA,
      card_number: "SS-A",
      status: "active",
      student_name: "Amina Kabila",
    },
    refuse: "",
    throwNetwork: false,
    hold: false,
    held: [],
  };
  const view = harness("school-a", true, (entry) => {
    if (entry.url.includes("/locations")) {
      return Promise.resolve(response({ locations: [{ id: "gate", code: "PORTAIL", label: "Portail", kind: "gate" }] }, 200));
    }
    if (entry.url.includes("/capabilities")) {
      return Promise.resolve(response({
        capabilities: ["SCAN_VERIFY", "SCAN_ENTRY", "CARD_CREATE", "CARD_PRINT", "CARD_LOST", "CARD_REPLACE", "CARD_REPRINT", "CARD_REVOKE"],
        fee_campaign_open: false,
      }, 200));
    }
    if (entry.url.includes("/scan-control/scan")) {
      return Promise.resolve(response(script.scan, 200));
    }
    if (entry.url.includes("/cards/")) {
      if (script.throwNetwork) return Promise.reject(new Error("network"));
      if (script.hold) return new Promise((resolve) => script.held.push(resolve));
      if (script.refuse) {
        return Promise.resolve({ ok: false, status: 403, json: async () => ({ message: script.refuse }) });
      }
      if (entry.url.includes("/cards/issue")) {
        return Promise.resolve(response({
          student_id: studentA, card_id: issuedCard, card_number: "SS-EMITTED", status: "active",
          qr_payload: "schoolsafe://card/SS-EMITTED/news",
        }, 200));
      }
      if (entry.url.includes("/cards/loss")) {
        return Promise.resolve(response({ card_id: issuedCard, card_number: "SS-EMITTED", status: "lost" }, 200));
      }
      if (entry.url.includes("/canonical-replace")) {
        return Promise.resolve(response({
          old_card_id: issuedCard, old_status: "replaced", card_id: replacedCard, card_number: "SS-REPLACED",
          status: "active", student_id: studentA, qr_payload: "schoolsafe://card/SS-REPLACED/newsig", signature: "newsig",
        }, 200));
      }
      if (entry.url.includes("/reprint")) {
        return Promise.resolve(response({ card_id: replacedCard, card_number: "SS-REPLACED", status: "active", reprint: "authorized" }, 200));
      }
      if (entry.url.includes("/revoke")) {
        return Promise.resolve(response({ card_id: replacedCard, status: "revoked", student_id: studentA }, 200));
      }
    }
    return Promise.resolve(response({}, 200));
  });
  const posts = (fragment) => view.traffic.filter((entry) => entry.url.includes(fragment) && entry.method === "POST");
  await flush();
  for (const action of ["CARD_CREATE", "CARD_PRINT", "CARD_LOST", "CARD_REPLACE", "CARD_REPRINT", "CARD_REVOKE"]) {
    assert.ok(manageButton(view, action));
  }

  click(manageButton(view, "CARD_CREATE"), "ouvrir émission");
  view.nodes.get("scanControlCardConfirm").checked = true;
  submit(view.nodes.get("scanControlCard"), "émission sans élève");
  assert.equal(posts("/cards/issue").length, 0);
  assert.match(view.nodes.get("scanControlResult").textContent, /Aucun élève/);

  click(view.nodes.get("scanControlBack"), "retour");
  click(view.nodes.get("scanControlModes").children.find((child) => child.mode === "verify"), "vérifier");
  submit(view.nodes.get("scanControlForm"), "scan élève");
  await flush();
  assert.equal(posts("/scan-control/scan").length, 1);

  click(manageButton(view, "CARD_CREATE"), "ouvrir émission");
  submit(view.nodes.get("scanControlCard"), "émission sans confirmation");
  assert.equal(posts("/cards/issue").length, 0);
  view.nodes.get("scanControlCardConfirm").checked = true;
  submit(view.nodes.get("scanControlCard"), "émission");
  await flush();
  const issue = posts("/cards/issue");
  assert.equal(issue.length, 1);
  assert.deepEqual(JSON.parse(issue[0].body), { student_id: studentA });
  assert.match(view.nodes.get("scanControlResult").textContent, /Carte émise/);
  assert.match(view.nodes.get("scanControlCardWho").textContent, /SS-EMITTED/);
  assert.doesNotMatch(view.nodes.get("scanControlResult").textContent, /schoolsafe:\/\//);

  click(view.nodes.get("scanControlBack"), "retour perte");
  click(manageButton(view, "CARD_LOST"), "ouvrir perte");
  view.nodes.get("scanControlCardConfirm").checked = true;
  submit(view.nodes.get("scanControlCard"), "perte sans motif");
  assert.equal(posts("/cards/loss").length, 0);
  assert.match(view.nodes.get("scanControlResult").textContent, /motif/i);
  view.nodes.get("scanControlCardReason").value = "Perdue à la cour";
  view.nodes.get("scanControlCardConfirm").checked = true;
  submit(view.nodes.get("scanControlCard"), "perte");
  await flush();
  const loss = posts("/cards/loss");
  assert.equal(loss.length, 1);
  assert.deepEqual(JSON.parse(loss[0].body), { student_id: studentA, card_id: issuedCard, reason: "Perdue à la cour" });
  assert.match(view.nodes.get("scanControlResult").textContent, /Perte enregistrée/);

  click(view.nodes.get("scanControlBack"), "retour remplacement");
  click(manageButton(view, "CARD_REPLACE"), "ouvrir remplacement");
  view.nodes.get("scanControlCardConfirm").checked = true;
  submit(view.nodes.get("scanControlCard"), "remplacement");
  await flush();
  const replaced = posts("/canonical-replace");
  assert.equal(replaced.length, 1);
  assert.match(replaced[0].url, new RegExp("/cards/" + issuedCard + "/canonical-replace$"));
  assert.deepEqual(JSON.parse(replaced[0].body), {});
  assert.match(view.nodes.get("scanControlResult").textContent, /Carte remplacée/);
  assert.match(view.nodes.get("scanControlCardWho").textContent, /SS-REPLACED/);
  assert.doesNotMatch(view.nodes.get("scanControlResult").textContent, /SS-EMITTED|schoolsafe:\/\//);

  click(view.nodes.get("scanControlBack"), "retour réimpression");
  click(manageButton(view, "CARD_REPRINT"), "ouvrir réimpression");
  view.nodes.get("scanControlCardConfirm").checked = true;
  submit(view.nodes.get("scanControlCard"), "réimpression sans motif");
  assert.equal(posts("/reprint").length, 0);
  view.nodes.get("scanControlCardReason").value = "Support abîmé";
  view.nodes.get("scanControlCardConfirm").checked = true;
  submit(view.nodes.get("scanControlCard"), "réimpression");
  await flush();
  const reprint = posts("/reprint");
  assert.equal(reprint.length, 1);
  assert.match(reprint[0].url, new RegExp("/cards/" + replacedCard + "/reprint$"));
  assert.deepEqual(JSON.parse(reprint[0].body), { reason: "Support abîmé" });
  assert.match(view.nodes.get("scanControlResult").textContent, /Réimpression autorisée/);
  assert.match(view.nodes.get("scanControlCardWho").textContent, /SS-REPLACED/);

  click(view.nodes.get("scanControlBack"), "retour révocation");
  click(manageButton(view, "CARD_REVOKE"), "ouvrir révocation");
  view.nodes.get("scanControlCardReason").value = "Carte retirée";
  submit(view.nodes.get("scanControlCard"), "révocation sans confirmation");
  assert.equal(posts("/revoke").length, 0);
  view.nodes.get("scanControlCardConfirm").checked = true;
  submit(view.nodes.get("scanControlCard"), "révocation");
  await flush();
  const revoked = posts("/revoke");
  assert.equal(revoked.length, 1);
  assert.match(revoked[0].url, new RegExp("/cards/" + replacedCard + "/revoke$"));
  assert.deepEqual(JSON.parse(revoked[0].body), { reason: "Carte retirée" });
  assert.match(view.nodes.get("scanControlResult").textContent, /Carte révoquée/);
  assert.match(view.nodes.get("scanControlCardWho").textContent, /non active \(revoked\)/);

  script.refuse = "Capacité non déléguée";
  click(view.nodes.get("scanControlBack"), "retour refus");
  click(manageButton(view, "CARD_CREATE"), "ouvrir refus");
  view.nodes.get("scanControlCardConfirm").checked = true;
  submit(view.nodes.get("scanControlCard"), "refus serveur");
  await flush();
  assert.match(view.nodes.get("scanControlResult").textContent, /Capacité non déléguée/);
  assert.doesNotMatch(view.nodes.get("scanControlResult").textContent, successWords);
  script.refuse = "Session requise";
  view.nodes.get("scanControlCardConfirm").checked = true;
  submit(view.nodes.get("scanControlCard"), "session absente");
  await flush();
  assert.match(view.nodes.get("scanControlResult").textContent, /Session requise/);
  assert.doesNotMatch(view.nodes.get("scanControlResult").textContent, successWords);
  script.refuse = "";
  script.throwNetwork = true;
  view.nodes.get("scanControlCardConfirm").checked = true;
  submit(view.nodes.get("scanControlCard"), "réseau");
  await flush();
  assert.match(view.nodes.get("scanControlResult").innerHTML, /Réseau absent/);
  assert.doesNotMatch(view.nodes.get("scanControlResult").innerHTML, successWords);
  script.throwNetwork = false;

  script.hold = true;
  const beforeDouble = posts("/cards/issue").length;
  view.nodes.get("scanControlCardConfirm").checked = true;
  submit(view.nodes.get("scanControlCard"), "premier clic");
  view.nodes.get("scanControlCardConfirm").checked = true;
  submit(view.nodes.get("scanControlCard"), "second clic");
  assert.equal(posts("/cards/issue").length, beforeDouble + 1);
  assert.equal(view.nodes.get("scanControlCardSubmit").disabled, true);
  script.hold = false;
  script.held[0](response({ student_id: studentA, card_id: issuedCard, card_number: "SS-EMITTED", status: "active" }, 200));
  await flush();

  script.hold = true;
  script.held.length = 0;
  click(view.nodes.get("scanControlBack"), "retour tardif");
  click(manageButton(view, "CARD_CREATE"), "émission tardive");
  view.nodes.get("scanControlCardConfirm").checked = true;
  submit(view.nodes.get("scanControlCard"), "émission en attente");
  click(view.nodes.get("scanControlBack"), "retour scan B");
  script.scan = {
    result: "VALID", student_id: studentB, card_id: cardB, card_number: "SS-B", status: "active", student_name: "Boris Ilunga",
  };
  click(view.nodes.get("scanControlModes").children.find((child) => child.mode === "verify"), "vérifier B");
  submit(view.nodes.get("scanControlForm"), "scan B");
  await flush();
  script.hold = false;
  script.held[0](response({ student_id: studentA, card_id: issuedCard, card_number: "SS-LATE", status: "active" }, 200));
  await flush();
  assert.match(view.nodes.get("scanControlResult").textContent, /Boris Ilunga/);
  assert.doesNotMatch(view.nodes.get("scanControlResult").textContent, /Carte émise|SS-LATE/);
  assert.match(view.nodes.get("scanControlCardWho").textContent, /Boris Ilunga/);
  assert.doesNotMatch(view.nodes.get("scanControlCardWho").textContent, /SS-LATE/);

  const beforeSchool = view.traffic.length;
  view.storage["schoolsafe-v2-session"] = JSON.stringify({ schoolId: "school-b", token: "next-session" });
  click(manageButton(view, "CARD_REPLACE"), "remplacement autre école");
  view.nodes.get("scanControlCardConfirm").checked = true;
  submit(view.nodes.get("scanControlCard"), "autre école");
  assert.equal(view.traffic.length, beforeSchool);
  assert.match(view.nodes.get("scanControlResult").textContent, /contexte/i);
  view.storage["schoolsafe-v2-session"] = JSON.stringify({ schoolId: "school-a", token: "session-token" });

  script.scan = {
    result: "VALID", student_id: studentA, card_id: "pas-un-uuid", card_number: "SS-A", status: "active", student_name: "Amina Kabila",
  };
  click(view.nodes.get("scanControlBack"), "retour identifiant");
  click(view.nodes.get("scanControlModes").children.find((child) => child.mode === "verify"), "scan identifiant");
  submit(view.nodes.get("scanControlForm"), "scan identifiant");
  await flush();
  const beforeInvalid = posts("/canonical-replace").length;
  click(manageButton(view, "CARD_REPLACE"), "identifiant invalide");
  view.nodes.get("scanControlCardConfirm").checked = true;
  submit(view.nodes.get("scanControlCard"), "identifiant invalide");
  assert.equal(posts("/canonical-replace").length, beforeInvalid);
  assert.match(view.nodes.get("scanControlResult").textContent, /Identifiant de carte invalide/);

  const printBefore = view.traffic.filter((entry) => entry.url.includes("print")).length;
  click(manageButton(view, "CARD_PRINT"), "impression");
  assert.equal(view.traffic.filter((entry) => entry.url.includes("print")).length, printBefore);
  assert.match(view.nodes.get("scanControlResult").textContent, /impression physique/i);

  click(view.nodes.get("scanControlBack"), "retour scanner");
  click(view.nodes.get("scanControlModes").children.find((child) => child.mode === "entry"), "entrée");
  submit(view.nodes.get("scanControlForm"), "entrée sans lieu");
  assert.match(view.nodes.get("scanControlResult").textContent, /Choisissez le lieu du scan/);
  view.nodes.get("scanControlLocation").value = "gate";
  const scansBefore = posts("/scan-control/scan").length;
  submit(view.nodes.get("scanControlForm"), "entrée avec lieu");
  await flush();
  const entry = posts("/scan-control/scan");
  assert.equal(entry.length, scansBefore + 1);
  const entryBody = JSON.parse(entry[entry.length - 1].body);
  assert.equal(entryBody.mode, "entry");
  assert.equal(entryBody.location_id, "gate");
  assert.match(entryBody.qr_payload, /^schoolsafe:\/\/card\//);

  const limited = harness("school-a", true, (entry) => {
    if (entry.url.includes("/locations")) return Promise.resolve(response({ locations: [] }, 200));
    return Promise.resolve(response({ capabilities: ["SCAN_VERIFY", "CARD_CREATE"], fee_campaign_open: false }, 200));
  });
  await flush();
  assert.equal(limited.nodes.get("scanControlManage").children.some((child) => child.manage === "CARD_REVOKE"), false);
  assert.equal(limited.nodes.get("scanControlManage").children.some((child) => child.manage === "CARD_CREATE"), true);
}

proveLocationUi().then(() => proveCardUi()).then(() => {
  console.log("qa-scan-control: quatre modes, remise humaine, signature canonique");
}).catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
