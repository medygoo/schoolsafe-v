(function (root) {
  "use strict";

  var PAGE = 20;
  var SCAN_TYPES = {
    "security|Scanner un QR": "entry",
    "security|Entrées": "entry",
    "security|Enregistrer une entrée": "entry",
    "security|Sorties": "exit",
    "security|Enregistrer une sortie": "exit"
  };
  var PICKUP_ACTIONS = {
    "security|Personnes autorisées": true,
    "security|Vérifier l’identité": true,
    "security|Autoriser une sortie": true,
    "security|Refuser une sortie": true,
    "security|Confirmer ou refuser une sortie": true,
    "security|Préparer une sortie": true,
    "security|Sorties en attente": true
  };
  var EVENT_ACTIONS = {
    "security|Entrées et sorties": true,
    "school|Présence et passages": true,
    "security|Rechercher": true,
    "security|Recherche de passage": true,
    "security|Historique des passages": true
  };
  var MODULE_IDS = [
    "pedagogyModule", "palmaresModule", "financeModule", "accountingModule", "hrModule",
    "inventoryModule", "communicationModule", "documentCenterModule", "administrationModule",
    "securityModule", "feeControlModule", "reportsModule", "pilotageModule", "accessConsole",
    "schoolModule", "cardsProtected", "cardsStudio"
  ];
  var model = {};

  function escapeHtml(value) {
    return String(value == null ? "" : value).replace(/[&<>"']/g, function (character) {
      return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[character];
    });
  }

  function state(type, title, message) {
    if (typeof root.ssState === "function") return root.ssState({ type: type, title: title, message: message });
    return '<p class="ss-state ss-state--' + type + '"><b>' + escapeHtml(title) + "</b> " + escapeHtml(message || "") + "</p>";
  }

  function button(label, id) {
    if (typeof root.ssButton === "function") {
      return root.ssButton({ label: label, type: "button", attrs: { id: id } });
    }
    return '<button type="button" id="' + id + '">' + escapeHtml(label) + "</button>";
  }

  function blocked(context, code) {
    var access = root.SchoolSafeAccess;
    var user = (context && context.user) || {};
    if (access && typeof access.explicitDeny === "function" && access.explicitDeny(user, code)) return true;
    if (context && context.live) {
      if (!access || typeof access.canAccess !== "function" || !access.canAccess(user, code)) return true;
    }
    return false;
  }

  function ownChildrenOnly(context, permission) {
    var access = root.SchoolSafeAccess;
    var user = (context && context.user) || {};
    if (!context || !context.live || !access || typeof access.allowsScope !== "function") return false;
    return access.allowsScope(user, permission, "own_children") && !access.allowsScope(user, permission, "school");
  }

  function hideModules() {
    MODULE_IDS.forEach(function (id) {
      var element = document.getElementById(id);
      if (element) element.hidden = true;
    });
  }

  function show(context, html) {
    var surface = context && context.host ? context.host : document.getElementById("wiringSurface");
    if (!surface) return null;
    if (!context.host) {
      hideModules();
      if (typeof context.hideDashboard === "function") context.hideDashboard();
      surface.hidden = false;
    }
    surface.innerHTML = '<button type="button" class="ss-button ss-button--secondary" id="wiringBack">Retour</button>' + html;
    var back = surface.querySelector("#wiringBack");
    if (back) back.addEventListener("click", function () {
      surface.innerHTML = "";
      if (!context.host) {
        surface.hidden = true;
        if (typeof context.restoreDashboard === "function") context.restoreDashboard();
      }
    });
    return surface;
  }

  function on(surface, id, type, handler) {
    var node = surface && surface.querySelector("#" + id);
    if (node) node.addEventListener(type, handler);
  }

  function shell(screen, title, body) {
    return '<section class="p2b-screen" data-p2b-screen="' + screen + '"><h2>' + escapeHtml(title) + "</h2>" + body + "</section>";
  }

  function facts(pairs) {
    return '<dl class="p2b-facts">' + pairs.filter(function (pair) {
      return pair[1] != null && pair[1] !== "";
    }).map(function (pair) {
      return "<div><dt>" + escapeHtml(pair[0]) + "</dt><dd>" + escapeHtml(pair[1]) + "</dd></div>";
    }).join("") + "</dl>";
  }

  function deny(context, code) {
    show(context, state("error", "Accès refusé", "Cette action est explicitement refusée ou hors permission (" + code + ")."));
  }

  function liveGate(context, title) {
    if (context && context.live) return true;
    show(context, state("unavailable", title, "Session réelle requise. Aucune donnée fictive n’est affichée."));
    return false;
  }

  function apiBase() {
    return root.schoolSafeApiBase || "http://127.0.0.1:8787";
  }

  function token() {
    try {
      var raw = root.sessionStorage && root.sessionStorage.getItem("schoolsafe-v2-session");
      var session = JSON.parse(raw || "{}");
      return session && session.token ? session.token : "";
    } catch (error) { return ""; }
  }

  function request(path, options) {
    var headers = { Accept: "application/json" };
    var bearer = token();
    if (bearer) headers.Authorization = "Bearer " + bearer;
    var init = { method: (options && options.method) || "GET", headers: headers, credentials: "include" };
    if (options && options.body) {
      headers["Content-Type"] = "application/json";
      init.body = JSON.stringify(options.body);
    }
    return root.fetch(apiBase() + path, init).then(function (response) {
      return response.json().catch(function () { return {}; }).then(function (body) {
        if (!response.ok) throw new Error((body && body.message) || ("Erreur " + response.status));
        return body || {};
      });
    });
  }

  function isUuid(value) {
    return /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(String(value || ""));
  }

  function text(id) {
    var node = document.getElementById(id);
    return node ? String(node.value || "").trim() : "";
  }

  function reset(extra) {
    model = Object.assign({
      offset: 0,
      rows: [],
      count: 0,
      eventType: "",
      search: "",
      scanType: "entry",
      lockActive: null,
      studentId: "",
      guardianId: "",
      scan: null
    }, extra || {});
  }

  function eventQuery() {
    var params = new URLSearchParams();
    params.set("limit", String(PAGE));
    params.set("offset", String(model.offset || 0));
    if (model.eventType) params.set("event_type", model.eventType);
    return "/security/events?" + params.toString();
  }

  function paintEvents(context) {
    var needle = model.search;
    var rows = model.rows.filter(function (row) {
      if (ownChildrenOnly(context, "security.events.read")) {
        var ids = (context.user && context.user.childIds) || [];
        var studentId = row.student_id || row.studentId;
        if (!studentId || ids.indexOf(studentId) < 0) return false;
      }
      if (!needle) return true;
      return JSON.stringify(row).toLowerCase().indexOf(needle) >= 0;
    });
    var items = rows.map(function (row, index) {
      return "<li><b>" + escapeHtml(row.event_type || "") + "</b> <small>" + escapeHtml(row.occurred_at || row.created_at || "") + "</small> " + button("Détail", "p2bEvent" + index) + "</li>";
    }).join("");
    var next = model.count > model.offset + model.rows.length ? button("Suite", "p2bEventNext") : "";
    var body = '<form id="p2bEventForm">' +
      '<label>Recherche<input id="p2bEventSearch" type="search" value="' + escapeHtml(model.search) + '"></label>' +
      '<label>Type<input id="p2bEventType" type="text" value="' + escapeHtml(model.eventType) + '"></label>' +
      button("Rechercher", "p2bEventApply") +
      "</form>" +
      (items ? "<ul>" + items + "</ul>" : state("empty", "Aucun passage", "Aucun événement ne correspond à cette lecture.")) +
      next;
    var surface = show(context, shell("events", "Registre des passages", body));
    on(surface, "p2bEventApply", "click", function (event) {
      event.preventDefault();
      model.search = text("p2bEventSearch").toLowerCase();
      model.eventType = text("p2bEventType");
      model.offset = 0;
      loadEvents(context);
    });
    on(surface, "p2bEventNext", "click", function () {
      model.offset += PAGE;
      loadEvents(context);
    });
    rows.forEach(function (row, index) {
      on(surface, "p2bEvent" + index, "click", function () { paintEventDetail(context, row); });
    });
  }

  function paintEventDetail(context, row) {
    var body = facts([
      ["id", row.id],
      ["event_type", row.event_type],
      ["occurred_at", row.occurred_at],
      ["created_at", row.created_at],
      ["decision", row.decision],
      ["denial_reason", row.denial_reason],
      ["student_id", row.student_id],
      ["card_id", row.card_id],
      ["location_id", row.location_id],
      ["portal_id", row.portal_id],
      ["scanned_by", row.scanned_by],
      ["authorized_person_id", row.authorized_person_id]
    ]) + button("Retour à la liste", "p2bEventListBack");
    var surface = show(context, shell("event-detail", "Détail du passage", body));
    on(surface, "p2bEventListBack", "click", function () { paintEvents(context); });
  }

  function loadEvents(context) {
    show(context, shell("events", "Registre des passages", state("loading", "Registre des passages", "Lecture du registre des passages.")));
    request(eventQuery()).then(function (body) {
      model.rows = body && Array.isArray(body.data) ? body.data : [];
      model.count = body && typeof body.count === "number" ? body.count : model.rows.length;
      paintEvents(context);
    }).catch(function (error) {
      show(context, shell("events", "Registre des passages", state("error", "Registre des passages", error.message)));
    });
  }

  function openEvents(context) {
    if (blocked(context, "security.events.read")) { deny(context, "security.events.read"); return; }
    if (!liveGate(context, "Registre des passages")) return;
    reset();
    loadEvents(context);
  }

  function paintLockdown(context, notice) {
    var pending = model.lockActive;
    var controls = pending == null
      ? button("Activer le lockdown", "p2bLockdownOn") + button("Lever le lockdown", "p2bLockdownOff")
      : "<p>" + (pending ? "Activation en attente de confirmation." : "Levée en attente de confirmation.") + "</p>" + button("Confirmer", "p2bLockdownConfirm");
    var body = "<p>Le moteur ne publie aucune lecture de l’état courant. Le résultat apparaît seulement après confirmation.</p>" + controls + (notice || "");
    var surface = show(context, shell("lockdown", "Lockdown", body));
    on(surface, "p2bLockdownOn", "click", function () { model.lockActive = true; paintLockdown(context); });
    on(surface, "p2bLockdownOff", "click", function () { model.lockActive = false; paintLockdown(context); });
    on(surface, "p2bLockdownConfirm", "click", function () { commitLockdown(context); });
  }

  function commitLockdown(context) {
    if (!context || !context.live) {
      show(context, shell("lockdown", "Lockdown", state("unavailable", "Lockdown", "Session réelle requise. Aucune donnée fictive n’est affichée.")));
      return;
    }
    var active = model.lockActive === true;
    show(context, shell("lockdown", "Lockdown", state("loading", "Lockdown", "Envoi de la demande confirmée.")));
    request("/security/lockdown", { method: "POST", body: { active: active } }).then(function (body) {
      var data = body && body.data ? body.data : {};
      show(context, shell("lockdown", "Lockdown", state("success", "Lockdown mis à jour", "active=" + String(data.active) + " activated_at=" + (data.activated_at || "") + " activated_by=" + (data.activated_by || ""))));
    }).catch(function (error) {
      show(context, shell("lockdown", "Lockdown", state("error", "Lockdown", error.message)));
    });
  }

  function openLockdown(context) {
    if (blocked(context, "security.lockdown.manage")) { deny(context, "security.lockdown.manage"); return; }
    reset();
    paintLockdown(context);
  }

  function childName(child) {
    return child.name || [child.first_name, child.last_name].filter(Boolean).join(" ") || child.id || child.student_id || "";
  }

  function personStatus(person) {
    if (person.status) return String(person.status);
    if (person.authorized === true) return "true";
    if (person.authorized === false) return "false";
    return "";
  }

  function paintConsultation(context, groups) {
    var html = groups.map(function (group) {
      var people = [].concat(group.data.family || [], group.data.accredited || []);
      var items = people.map(function (person) {
        return "<li><b>" + escapeHtml(person.name || person.full_name || "") + "</b> " + facts([
          ["relation", person.type || person.guardian_type || ""],
          ["statut", personStatus(person)],
          ["principal", person.is_primary ? "true" : ""]
        ]) + "</li>";
      }).join("");
      return "<article><h3>" + escapeHtml(childName(group.child)) + "</h3>" + (items ? "<ul>" + items + "</ul>" : "<p>Aucune personne autorisée.</p>") + "</article>";
    }).join("");
    show(context, shell("authorized-persons", "Consultation", html || state("empty", "Aucune personne autorisée", "Aucune personne n’est renvoyée pour ces enfants.")));
  }

  function openConsultation(context) {
    if (blocked(context, "security.pickup.read")) { deny(context, "security.pickup.read"); return; }
    if (!liveGate(context, "Consultation")) return;
    reset();
    show(context, shell("authorized-persons", "Consultation", state("loading", "Consultation", "Lecture des enfants rattachés.")));
    request("/pedagogy/parent/children").then(function (body) {
      var data = body && body.data !== undefined ? body.data : body;
      var children = Array.isArray(data) ? data : ((data && (data.children || data.students)) || []);
      if (ownChildrenOnly(context, "security.pickup.read")) {
        var ids = (context.user && context.user.childIds) || [];
        children = children.filter(function (child) {
          var id = child.id || child.student_id;
          return id && ids.indexOf(id) >= 0;
        });
      }
      if (!children.length) {
        show(context, shell("authorized-persons", "Consultation", state("empty", "Aucun enfant", "Aucun enfant rattaché n’est visible dans votre portée.")));
        return;
      }
      return Promise.all(children.map(function (child) {
        var id = child.id || child.student_id;
        return request("/native/family/students/" + encodeURIComponent(id) + "/pickup-authorizations").then(function (payload) {
          return { child: child, data: payload && payload.data ? payload.data : {} };
        });
      })).then(function (groups) { paintConsultation(context, groups); });
    }).catch(function (error) {
      show(context, shell("authorized-persons", "Consultation", state("error", "Consultation", error.message)));
    });
  }

  function confirmable(person) {
    if (!person || !isUuid(person.guardian_id)) return false;
    if (person.status) return person.status === "active";
    return person.authorized === true;
  }

  function paintPeople(context, studentId, data, notice) {
    var people = [].concat((data && data.family) || [], (data && data.accredited) || []);
    var confirmablePeople = [];
    var items = people.map(function (person) {
      var action = "";
      if (confirmable(person) && !blocked(context, "security.scan")) {
        var index = confirmablePeople.push(person) - 1;
        action = " " + button("Confirmer la remise", "p2bPickupConfirm" + index);
      }
      return "<li><b>" + escapeHtml(person.name || person.full_name || "") + "</b> <small>" + escapeHtml(person.type || person.guardian_type || "") + " " + escapeHtml(personStatus(person)) + "</small>" + action + "</li>";
    }).join("");
    var commit = model.guardianId ? button("Confirmer la remise maintenant", "p2bPickupCommit") : "";
    var body = (notice || "") + (items ? "<ul>" + items + "</ul>" : "<p>Aucune personne autorisée.</p>") + commit;
    var surface = show(context, shell("pickup", "Mode retrait", pickupForm(studentId) + body));
    bindPickup(surface, context, studentId, confirmablePeople);
  }

  function pickupForm(studentId) {
    return '<form><label>Élève<input id="p2bStudentId" type="text" value="' + escapeHtml(studentId || "") + '"></label>' + button("Lire les personnes autorisées", "p2bPickupLoad") + "</form>";
  }

  function bindPickup(surface, context, studentId, people) {
    on(surface, "p2bPickupLoad", "click", function () { loadPickup(context, text("p2bStudentId")); });
    people.forEach(function (person, index) {
      on(surface, "p2bPickupConfirm" + index, "click", function () {
        model.studentId = studentId;
        model.guardianId = person.guardian_id;
        paintPeople(context, studentId, model.pickupData, "");
      });
    });
    on(surface, "p2bPickupCommit", "click", function () { commitPickup(context); });
  }

  function loadPickup(context, studentId, prefix) {
    if (!isUuid(studentId)) {
      var invalid = show(context, shell("pickup", "Mode retrait", pickupForm(studentId) + state("error", "Mode retrait", "student_id requis par le moteur de remise.")));
      on(invalid, "p2bPickupLoad", "click", function () { loadPickup(context, text("p2bStudentId")); });
      return;
    }
    show(context, shell("pickup", "Mode retrait", pickupForm(studentId) + state("loading", "Mode retrait", "Lecture des personnes autorisées.")));
    request("/native/family/students/" + encodeURIComponent(studentId) + "/pickup-authorizations").then(function (body) {
      model.pickupData = body && body.data ? body.data : {};
      model.studentId = studentId;
      paintPeople(context, studentId, model.pickupData, prefix || "");
    }).catch(function (error) {
      show(context, shell("pickup", "Mode retrait", pickupForm(studentId) + state("error", "Mode retrait", error.message)));
      var surface = document.getElementById("wiringSurface");
      on(surface, "p2bPickupLoad", "click", function () { loadPickup(context, text("p2bStudentId")); });
    });
  }

  function commitPickup(context) {
    if (!context || !context.live) {
      show(context, shell("pickup", "Mode retrait", state("unavailable", "Mode retrait", "Session réelle requise. Aucune donnée fictive n’est affichée.")));
      return;
    }
    var body = {
      student_id: model.studentId,
      guardian_id: model.guardianId,
      request_key: "pickup-" + model.studentId + "-" + model.guardianId + "-" + Date.now() + "-" + Math.random().toString(36).slice(2, 10)
    };
    show(context, shell("pickup", "Mode retrait", state("loading", "Remise", "Confirmation de la remise.")));
    request("/native/family/pickup-confirm", { method: "POST", body: body }).then(function (payload) {
      var data = payload && payload.data ? payload.data : {};
      show(context, shell("pickup", "Mode retrait", state("success", "Remise confirmée", data.event_type || data.status || "")));
    }).catch(function (error) {
      show(context, shell("pickup", "Mode retrait", state("error", "Remise", error.message) + button("Retour", "p2bPickupReload")));
      on(document.getElementById("wiringSurface"), "p2bPickupReload", "click", function () { loadPickup(context, model.studentId); });
    });
  }

  function openPickup(context) {
    if (blocked(context, "security.scan")) { deny(context, "security.scan"); return; }
    if (!liveGate(context, "Mode retrait")) return;
    reset();
    var surface = show(context, shell("pickup", "Mode retrait", pickupForm("") + "<p>La consultation des enfants rattachés est une autre action. Ici, la remise suit le moteur existant.</p>"));
    on(surface, "p2bPickupLoad", "click", function () { loadPickup(context, text("p2bStudentId")); });
  }

  function scanForm() {
    return '<form>' +
      '<label>QR<input id="p2bScanPayload" type="text" autocomplete="off"></label>' +
      '<label>Lieu<input id="p2bLocation" type="text"></label>' +
      '<label>Personne autorisée, si le moteur l’exige<input id="p2bAuthorizedPerson" type="text"></label>' +
      "<p>Type : " + escapeHtml(model.scanType) + "</p>" +
      button("Entrée", "p2bTypeEntry") +
      button("Sortie", "p2bTypeExit") +
      button("Sortie préparée", "p2bTypePrepared") +
      button("Incident", "p2bTypeIncident") +
      button("Envoyer au moteur", "p2bScanSubmit") +
      "</form>";
  }

  function bindScan(surface, context) {
    on(surface, "p2bTypeEntry", "click", function () { model.scanType = "entry"; });
    on(surface, "p2bTypeExit", "click", function () { model.scanType = "exit"; });
    on(surface, "p2bTypePrepared", "click", function () { model.scanType = "exit_prepared"; });
    on(surface, "p2bTypeIncident", "click", function () { model.scanType = "incident"; });
    on(surface, "p2bScanSubmit", "click", function () { submitScan(context); });
  }

  function paintScan(context, extra) {
    var surface = show(context, shell("scan", "Scanner", scanForm() + (extra || "")));
    bindScan(surface, context);
    return surface;
  }

  function submitScan(context) {
    if (!context || !context.live) {
      paintScan(context, state("unavailable", "Scanner", "Session réelle requise. Aucune donnée fictive n’est affichée."));
      return;
    }
    var location = text("p2bLocation");
    var payload = text("p2bScanPayload");
    if (!isUuid(location)) {
      paintScan(context, state("error", "Scanner", "location_id requis par le moteur de scan."));
      return;
    }
    if (!payload) {
      paintScan(context, state("error", "Scanner", "Le contenu du QR est vide."));
      return;
    }
    var body = { qr_payload: payload, event_type: model.scanType || "entry", location_id: location };
    var person = text("p2bAuthorizedPerson");
    if (isUuid(person)) body.authorized_person_id = person;
    paintScan(context, state("loading", "Scanner", "Lecture de la réponse du moteur."));
    request("/security/scan", { method: "POST", body: body }).then(function (response) {
      var data = response && response.data ? response.data : {};
      model.scan = data;
      var detail = facts([
        ["decision", data.decision],
        ["reason", data.reason],
        ["event.id", data.event && data.event.id],
        ["event.event_type", data.event && data.event.event_type],
        ["event.decision", data.event && data.event.decision],
        ["event.occurred_at", data.event && data.event.occurred_at]
      ]);
      if (data.student) {
        detail += facts([
          ["élève", [data.student.first_name, data.student.last_name].filter(Boolean).join(" ")],
          ["matricule", data.student.matricule],
          ["classe", data.student.class_name]
        ]);
      }
      (data.authorized_persons || []).forEach(function (personRow) {
        detail += "<p><b>" + escapeHtml(personRow.full_name || "") + "</b> <small>" + escapeHtml(personRow.guardian_type || "") + "</small></p>";
      });
      if (data.alert) detail += "<p>" + escapeHtml(data.alert.title || "") + "</p>";
      paintScan(context, detail);
      if (data.student && data.student.id && (data.decision === "allowed" || data.decision === "manual_override")) {
        loadPickup(context, data.student.id, detail);
      }
    }).catch(function (error) {
      paintScan(context, state("error", "Scanner", error.message));
    });
  }

  function openScan(context, eventType) {
    if (blocked(context, "security.scan")) { deny(context, "security.scan"); return; }
    if (!liveGate(context, "Scanner")) return;
    reset({ scanType: eventType || "entry" });
    paintScan(context, "");
  }

  function syncMarker(syncState) {
    if (syncState.syncing || syncState.pending) return "busy";
    if (syncState.conflicts) return "error";
    if (!syncState.lastSyncAt) return "empty";
    return "success";
  }

  function paintSync(context, marker, body) {
    var actions = button("Synchroniser maintenant", "p2bSyncNow");
    var surface = show(context, '<section class="p2b-screen" data-p2b-screen="sync" data-p2b-sync="' + marker + '"><h2>Synchronisation</h2>' + body + actions + "</section>");
    on(surface, "p2bSyncNow", "click", function () { runSync(context); });
  }

  function renderSync(context, syncState) {
    var marker = syncMarker(syncState || {});
    var copy = marker === "busy"
      ? state("loading", "En cours", syncState.syncing ? "La file est en cours de traitement." : "Des opérations restent en attente.")
      : marker === "error"
        ? state("error", "Synchronisation", "La file signale " + (syncState.conflicts || 0) + " élément(s) à vérifier.")
        : marker === "success"
          ? state("success", "Synchronisation", syncState.lastSyncAt || "")
          : state("empty", "Aucune opération en attente", "La file de cet appareil est vide.");
    paintSync(context, marker, copy);
  }

  function readSync(context) {
    var api = root.SchoolSafeSync;
    if (!api || typeof api.state !== "function") {
      paintSync(context, "error", state("error", "Synchronisation", "Le panneau de synchronisation est indisponible."));
      return;
    }
    show(context, shell("sync", "Synchronisation", state("loading", "Synchronisation", "Lecture de la file de cet appareil.")));
    Promise.resolve().then(function () { return api.state(); }).then(function (syncState) {
      renderSync(context, syncState || {});
    }).catch(function (error) {
      paintSync(context, "error", state("error", "Synchronisation", error.message));
    });
  }

  function runSync(context) {
    var api = root.SchoolSafeSync;
    show(context, shell("sync", "Synchronisation", state("loading", "En cours", "Traitement de la file.")));
    var pending = api && typeof api.syncNow === "function" ? api.syncNow() : null;
    Promise.resolve(pending).then(function () { return api.state(); }).then(function (syncState) {
      renderSync(context, syncState || {});
    }).catch(function (error) {
      paintSync(context, "error", state("error", "Synchronisation", error.message));
    });
  }

  function openSync(context) {
    if (blocked(context, "sync.submit")) { deny(context, "sync.submit"); return; }
    if (context && typeof context.openSync === "function") context.openSync();
    readSync(context);
  }

  function open(branchKey, actionName, context) {
    var key = branchKey + "|" + actionName;
    context = context || {};
    if (EVENT_ACTIONS[key]) { openEvents(context); return true; }
    if (key === "security|Lockdown") { openLockdown(context); return true; }
    if (key === "school|Personnes autorisées de mes enfants") { openConsultation(context); return true; }
    if (PICKUP_ACTIONS[key]) { openPickup(context); return true; }
    if (SCAN_TYPES[key]) {
      if (root.SchoolSafeScanControl && typeof root.SchoolSafeScanControl.render === "function") return false;
      openScan(context, SCAN_TYPES[key]);
      return true;
    }
    if (key === "administration|Mode hors ligne") { openSync(context); return true; }
    return false;
  }

  root.SchoolSafeVisualOutputP2B = { open: open };
})(window);
