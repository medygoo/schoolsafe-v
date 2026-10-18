(function (root) {
  "use strict";

  var SCREENS = {
    "finance|Reçus PDF": "receipt",
    "finance|Produire un reçu PDF": "receipt",
    "finance|Reçus": "receipt",
    "finance|Rapports de caisse": "cash",
    "finance|Clôtures": "cash",
    "finance|Rapport de caisse": "cash",
    "finance|Caisses": "cash",
    "finance|Exports financiers": "daily",
    "finance|Imprimer les rapports": "daily",
    "finance|Rapports financiers": "daily",
    "finance|Exports": "daily",
    "finance|Frais scolaires": "fees",
    "élèves|Dossier santé": "health",
    "élèves|Profil alimentaire": "dietary"
  };

  var MODULE_IDS = [
    "pedagogyModule", "palmaresModule", "financeModule", "accountingModule", "hrModule",
    "inventoryModule", "communicationModule", "documentCenterModule", "administrationModule",
    "securityModule", "feeControlModule", "reportsModule", "pilotageModule", "accessConsole",
    "schoolModule", "cardsProtected", "cardsStudio"
  ];

  function escapeHtml(value) {
    return String(value == null ? "" : value).replace(/[&<>"']/g, function (character) {
      return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[character];
    });
  }

  function state(type, title, message) {
    if (typeof root.ssState === "function") return root.ssState({ type: type, title: title, message: message });
    return '<p class="ss-state ss-state--' + type + '"><b>' + escapeHtml(title) + "</b> " + escapeHtml(message || "") + "</p>";
  }

  function button(label, id, type) {
    if (typeof root.ssButton === "function") {
      return root.ssButton({ label: label, type: type || "button", attrs: { id: id } });
    }
    return '<button type="' + (type || "button") + '" id="' + id + '">' + escapeHtml(label) + "</button>";
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

  function parentLimited(context, permission) {
    var user = (context && context.user) || {};
    if (user.role === "parent" || user.roleKey === "parent") return true;
    var access = root.SchoolSafeAccess;
    if (access && typeof access.scopeFor === "function") {
      var scope = access.scopeFor(user, permission);
      if (scope && scope.type === "own_children") return true;
    }
    return false;
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
    surface.innerHTML = '<button type="button" class="ss-button ss-button--secondary" id="wiringBack">' + (context.host ? "Fermer" : "Retour") + "</button>" + html;
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

  function shell(screen, title, contextLine, body) {
    return '<section class="p1-screen" data-p1-screen="' + screen + '"><header><h2>' + escapeHtml(title) + "</h2><p>" + escapeHtml(contextLine) + "</p></header>" + body + "</section>";
  }

  function facts(pairs) {
    return '<dl class="p1-facts">' + pairs.map(function (pair) {
      return "<div><dt>" + escapeHtml(pair[0]) + "</dt><dd>" + escapeHtml(pair[1] == null || pair[1] === "" ? "—" : pair[1]) + "</dd></div>";
    }).join("") + "</dl>";
  }

  function liveGate(context, codes, screen, title) {
    var list = [].concat(codes);
    var denied = list.filter(function (code) { return blocked(context, code); });
    if (denied.length === list.length) {
      show(context, shell(screen, title, "", state("error", "Accès refusé", "Cette action est explicitement refusée ou hors permission (" + denied.join(", ") + ").")));
      return false;
    }
    if (!context || !context.live) {
      show(context, shell(screen, title, "", state("unavailable", title, "Session réelle requise. Aucune donnée de démonstration n’est affichée.")));
      return false;
    }
    return true;
  }

  function native(method, path, body) {
    var options = { method: method, credentials: "include", headers: { Accept: "application/json" } };
    if (body !== undefined) {
      options.headers["Content-Type"] = "application/json";
      options.body = JSON.stringify(body);
    }
    return fetch(path, options).then(function (response) {
      return response.json().catch(function () { return {}; }).then(function (payload) {
        if (!response.ok) {
          var error = new Error(payload && payload.message ? payload.message : "Erreur " + response.status);
          error.status = response.status;
          throw error;
        }
        return payload && Object.prototype.hasOwnProperty.call(payload, "data") ? payload.data : payload;
      });
    });
  }

  function fail(context, screen, title, error) {
    show(context, shell(screen, title, "", state("error", "Erreur", error && error.message ? error.message : "Lecture impossible")));
  }

  function isUuid(value) {
    return /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(String(value || ""));
  }

  function field(event, name) {
    var elements = event && event.target && event.target.elements;
    return elements && elements[name] ? elements[name] : {};
  }

  function renderReceipt(context) {
    if (!liveGate(context, "finance.receipt.read", "receipt", "Reçu d’un paiement")) return;
    var surface = show(context, shell("receipt", "Reçu d’un paiement", "Chargement d’un paiement connu. Aucune liste n’est proposée.",
      '<form id="p1ReceiptForm"><label>Identifiant du paiement<input id="p1PaymentId" name="payment_id" required></label>' + button("Charger le reçu", "p1ReceiptLoad", "submit") + "</form>"));
    on(surface, "p1ReceiptForm", "submit", function (event) {
      if (event && event.preventDefault) event.preventDefault();
      var paymentId = field(event, "payment_id").value;
      if (!isUuid(paymentId)) {
        show(context, shell("receipt", "Reçu d’un paiement", "", state("error", "Identifiant invalide", "Indiquez l’identifiant du paiement connu.")));
        return;
      }
      show(context, shell("receipt", "Reçu d’un paiement", "", state("loading", "Chargement", "Lecture du reçu.")));
      native("GET", "/native/finance/receipts/" + encodeURIComponent(paymentId)).then(function (data) {
        if (!data) {
          show(context, shell("receipt", "Reçu d’un paiement", "", state("empty", "Reçu introuvable", "Le moteur n’a renvoyé aucun reçu pour ce paiement.")));
          return;
        }
        var pdf = data.pdf_url || data.pdf || "";
        var body = facts([
          ["Paiement", data.payment_id],
          ["Numéro", data.receipt_no],
          ["Élève", data.student_name],
          ["Matricule", data.student_matricule],
          ["Frais", data.fee_label],
          ["Montant", data.amount],
          ["Devise", data.currency],
          ["Reçu le", data.received_at],
          ["Mode", data.mode],
          ["Référence", data.reference]
        ]);
        var print = button("Imprimer", "p1Print");
        var link = pdf ? '<a href="' + escapeHtml(pdf) + '">Document fourni par le moteur</a>' : "";
        var next = show(context, shell("receipt", "Reçu d’un paiement", data.student_name || "", body + link + print));
        on(next, "p1Print", "click", function () { if (typeof root.print === "function") root.print(); });
      }).catch(function (error) {
        if (error && error.status === 404) {
          show(context, shell("receipt", "Reçu d’un paiement", "", state("empty", "Reçu introuvable", error.message)));
          return;
        }
        fail(context, "receipt", "Reçu d’un paiement", error);
      });
    });
  }

  function cashControls(context, notice) {
    var canOpen = !blocked(context, "finance.cash_register.open");
    var canClose = !blocked(context, "finance.cash_register.close");
    var controls = "";
    if (canOpen) controls += button("Ouvrir la caisse", "p1CashOpen");
    if (canClose) controls += '<label>Identifiant de caisse<input id="p1RegisterId" name="register_id"></label>' + button("Clôturer la caisse", "p1CashClose");
    var surface = show(context, shell("cash", "Caisse", "Ouverture et clôture. Aucune lecture d’historique n’est disponible.", (notice || "") + controls));
    on(surface, "p1CashOpen", "click", function () {
      show(context, shell("cash", "Caisse", "", state("loading", "Chargement", "Ouverture de la caisse.")));
      native("POST", "/native/finance/cash-register/open").then(function (data) {
        cashControls(context, state("success", "Caisse ouverte", "Identifiant renvoyé : " + ((data && data.id) || "")));
      }).catch(function (error) { fail(context, "cash", "Caisse", error); });
    });
    on(surface, "p1CashClose", "click", function () {
      var input = surface.querySelector("#p1RegisterId");
      var registerId = input ? input.value : "";
      if (!isUuid(registerId)) {
        cashControls(context, state("error", "Identifiant invalide", "La clôture attend uniquement register_id."));
        return;
      }
      show(context, shell("cash", "Caisse", "", state("loading", "Chargement", "Clôture de la caisse.")));
      native("POST", "/native/finance/cash-register/close", { register_id: registerId }).then(function (data) {
        if (data && data.closed) cashControls(context, state("success", "Clôture enregistrée", "La caisse a été clôturée."));
        else cashControls(context, state("error", "Clôture refusée", "Le moteur n’a pas confirmé la clôture."));
      }).catch(function (error) { fail(context, "cash", "Caisse", error); });
    });
  }

  function renderCash(context) {
    if (!liveGate(context, ["finance.cash_register.open", "finance.cash_register.close"], "cash", "Caisse")) return;
    cashControls(context, "");
  }

  function renderDaily(context) {
    if (!liveGate(context, "finance.report.read", "daily", "Rapport financier journalier")) return;
    var surface = show(context, shell("daily", "Rapport financier journalier", "Une date, une lecture. Aucune somme de devises n’est calculée dans l’interface.",
      '<form id="p1ReportForm"><label>Date<input id="p1ReportDate" name="date" type="date" required></label>' + button("Lire le rapport", "p1ReportLoad", "submit") + "</form>"));
    on(surface, "p1ReportForm", "submit", function (event) {
      if (event && event.preventDefault) event.preventDefault();
      var date = field(event, "date").value;
      if (!/^\d{4}-\d{2}-\d{2}$/.test(String(date || ""))) {
        show(context, shell("daily", "Rapport financier journalier", "", state("error", "Date invalide", "Choisissez une date.")));
        return;
      }
      show(context, shell("daily", "Rapport financier journalier", "", state("loading", "Chargement", "Lecture du rapport du jour.")));
      native("GET", "/native/finance/reports/daily?date=" + encodeURIComponent(date)).then(function (report) {
        if (!report) {
          show(context, shell("daily", "Rapport financier journalier", "", state("empty", "Aucun rapport", "Le moteur n’a rien renvoyé pour cette date.")));
          return;
        }
        var body = "";
        if (Array.isArray(report.by_currency)) {
          var lines = "";
          for (var index = 0; index < report.by_currency.length; index += 1) {
            var row = report.by_currency[index] || {};
            lines += "<li>" + escapeHtml(row.currency) + " " + escapeHtml(row.total_paid) + "</li>";
          }
          body = "<ul>" + lines + "</ul>";
        } else {
          body = facts([["Date", report.date], ["Total renvoyé", report.total_paid], ["Paiements", report.payment_count]]);
        }
        body += "<p>Aucune somme de devises n’est calculée dans l’interface.</p>" + button("Imprimer", "p1Print");
        var next = show(context, shell("daily", "Rapport financier journalier", String(report.date || date), body));
        on(next, "p1Print", "click", function () { if (typeof root.print === "function") root.print(); });
      }).catch(function (error) { fail(context, "daily", "Rapport financier journalier", error); });
    });
  }

  function renderFeeRows(rows) {
    if (!rows.length) return state("empty", "Aucun frais", "Le moteur n’a renvoyé aucun frais pour cet élève.");
    var items = "";
    for (var index = 0; index < rows.length; index += 1) {
      var row = rows[index] || {};
      items += "<li><b>" + escapeHtml(row.label || row.id) + "</b> " + escapeHtml(row.status) + " " + escapeHtml(row.amount_expected) + " / " + escapeHtml(row.amount_paid) + " / " + escapeHtml(row.amount_remaining) + " " + escapeHtml(row.currency) + "</li>";
    }
    return "<ul>" + items + "</ul>";
  }

  function showChildFees(context, children, notice) {
    var allowed = {};
    if (!children.length) {
      show(context, shell("fees", "Frais de l’élève", "", state("empty", "Aucun enfant rattaché", "Le moteur n’a renvoyé aucun enfant pour cette session.")));
      return;
    }
    var buttons = children.map(function (child) {
      var id = child.student_id || child.id;
      allowed[id] = true;
      var name = [child.first_name, child.last_name].filter(Boolean).join(" ") || id;
      return '<button type="button" data-p1-child="' + escapeHtml(id) + '">' + escapeHtml(name) + "</button>";
    }).join("");
    var surface = show(context, shell("fees", "Frais de l’élève", "Sélection limitée à vos enfants.", (notice || "") + '<div id="p1ChildList">' + buttons + "</div>"));
    on(surface, "p1ChildList", "click", function (event) {
      var target = event && event.target;
      var id = target && target.getAttribute ? target.getAttribute("data-p1-child") : "";
      if (!id) return;
      if (!allowed[id]) {
        showChildFees(context, children, state("error", "Hors périmètre", "Cet enfant n’est pas dans votre périmètre."));
        return;
      }
      loadFees(context, id, allowed);
    });
  }

  function loadFees(context, studentId, allowed) {
    if (allowed && !allowed[studentId]) {
      show(context, shell("fees", "Frais de l’élève", "", state("error", "Hors périmètre", "Cet enfant n’est pas dans votre périmètre.")));
      return;
    }
    if (!isUuid(studentId)) {
      show(context, shell("fees", "Frais de l’élève", "", state("error", "Identifiant invalide", "Indiquez l’identifiant de l’élève.")));
      return;
    }
    show(context, shell("fees", "Frais de l’élève", studentId, state("loading", "Chargement", "Lecture des frais de l’élève.")));
    native("GET", "/native/finance/students/" + encodeURIComponent(studentId) + "/fees").then(function (data) {
      var rows = Array.isArray(data) ? data : [];
      show(context, shell("fees", "Frais de l’élève", studentId, renderFeeRows(rows)));
    }).catch(function (error) { fail(context, "fees", "Frais de l’élève", error); });
  }

  function renderFees(context) {
    if (!liveGate(context, "finance.fee.read", "fees", "Frais de l’élève")) return;
    if (parentLimited(context, "finance.fee.read")) {
      show(context, shell("fees", "Frais de l’élève", "Enfants rattachés uniquement.", state("loading", "Chargement", "Lecture des enfants rattachés.")));
      native("GET", "/native/parent/children").then(function (data) {
        showChildFees(context, Array.isArray(data) ? data : [], "");
      }).catch(function (error) { fail(context, "fees", "Frais de l’élève", error); });
      return;
    }
    var surface = show(context, shell("fees", "Frais de l’élève", "Un élève connu.",
      '<form id="p1FeesForm"><label>Identifiant de l’élève<input id="p1StudentId" name="student_id" required></label>' + button("Lire les frais", "p1FeesLoad", "submit") + "</form>"));
    on(surface, "p1FeesForm", "submit", function (event) {
      if (event && event.preventDefault) event.preventDefault();
      loadFees(context, field(event, "student_id").value, null);
    });
  }

  function listItems(rows, labelOf, emptyTitle) {
    if (!rows || !rows.length) return state("empty", emptyTitle, "Le moteur n’a renvoyé aucune ligne.");
    return "<ul>" + rows.map(function (row) { return "<li>" + escapeHtml(labelOf(row || {})) + "</li>"; }).join("") + "</ul>";
  }

  function renderHealth(context, studentId, data, notice) {
    var profile = data && data.profile || {};
    var empty = !profile.blood_type && !profile.primary_doctor_name && !profile.medical_notes && !(data.conditions || []).length && !(data.allergies || []).length && !(data.medications || []).length;
    var body = (notice || "") + (empty ? state("empty", "Aucune fiche santé", "Le moteur n’a pas encore de fiche pour cet élève.") : facts([
      ["Groupe sanguin", profile.blood_type],
      ["Médecin", profile.primary_doctor_name],
      ["Téléphone", profile.primary_doctor_phone],
      ["Notes", profile.medical_notes],
      ["Consignes", profile.emergency_instructions],
      ["Déclaration", profile.medical_declaration_completed ? "Oui" : "Non"]
    ]));
    body += "<h3>Conditions</h3>" + listItems(data.conditions, function (row) { return row.name || row.id; }, "Aucune condition");
    body += "<h3>Allergies</h3>" + listItems(data.allergies, function (row) { return [row.allergen || row.name, row.severity].filter(Boolean).join(" · "); }, "Aucune allergie");
    body += "<h3>Médicaments</h3>" + listItems(data.medications, function (row) { return row.name || row.id; }, "Aucun médicament");
    if (!blocked(context, "school.student.health.manage")) {
      body += '<form id="p1HealthForm"><label>Groupe sanguin<select name="blood_type"><option value="">—</option><option>A+</option><option>A-</option><option>B+</option><option>B-</option><option>AB+</option><option>AB-</option><option>O+</option><option>O-</option><option>UNKNOWN</option></select></label>' +
        '<label>Médecin<input name="primary_doctor_name" value="' + escapeHtml(profile.primary_doctor_name || "") + '"></label>' +
        '<label>Téléphone<input name="primary_doctor_phone" value="' + escapeHtml(profile.primary_doctor_phone || "") + '"></label>' +
        '<label>Notes<textarea name="medical_notes">' + escapeHtml(profile.medical_notes || "") + "</textarea></label>" +
        '<label>Consignes d’urgence<textarea name="emergency_instructions">' + escapeHtml(profile.emergency_instructions || "") + "</textarea></label>" +
        '<label><input name="medical_declaration_completed" type="checkbox"' + (profile.medical_declaration_completed ? " checked" : "") + "> Déclaration complétée</label>" +
        '<label><input name="medical_consent_status" type="checkbox"' + (profile.medical_consent_status ? " checked" : "") + "> Consentement</label>" +
        button("Enregistrer la fiche", "p1HealthSave", "submit") + "</form>";
    }
    var surface = show(context, shell("health", "Dossier santé", studentId, body));
    on(surface, "p1HealthForm", "submit", function (event) {
      if (event && event.preventDefault) event.preventDefault();
      var bodyPayload = {
        blood_type: field(event, "blood_type").value || undefined,
        primary_doctor_name: field(event, "primary_doctor_name").value,
        primary_doctor_phone: field(event, "primary_doctor_phone").value,
        medical_notes: field(event, "medical_notes").value,
        emergency_instructions: field(event, "emergency_instructions").value,
        medical_declaration_completed: !!field(event, "medical_declaration_completed").checked,
        medical_consent_status: !!field(event, "medical_consent_status").checked
      };
      if (!bodyPayload.blood_type) delete bodyPayload.blood_type;
      show(context, shell("health", "Dossier santé", studentId, state("loading", "Chargement", "Enregistrement de la fiche.")));
      native("PUT", "/native/students/" + encodeURIComponent(studentId) + "/health", bodyPayload).then(function () {
        var next = Object.assign({}, data, { profile: Object.assign({}, profile, bodyPayload) });
        renderHealth(context, studentId, next, state("success", "Enregistré", "La fiche renvoyée par le moteur a été relue localement."));
      }).catch(function (error) { fail(context, "health", "Dossier santé", error); });
    });
  }

  function loadHealth(context, studentId) {
    if (!isUuid(studentId)) {
      show(context, shell("health", "Dossier santé", "", state("error", "Identifiant invalide", "La fiche exige un élève connu.")));
      return;
    }
    if (parentLimited(context, "school.student.health.read")) {
      native("GET", "/native/parent/children").then(function (children) {
        var allowed = {};
        (Array.isArray(children) ? children : []).forEach(function (child) { allowed[child.student_id || child.id] = true; });
        if (!allowed[studentId]) {
          show(context, shell("health", "Dossier santé", "", state("error", "Hors périmètre", "Cet enfant n’est pas dans votre périmètre.")));
          return;
        }
        readHealth(context, studentId);
      }).catch(function (error) { fail(context, "health", "Dossier santé", error); });
      return;
    }
    readHealth(context, studentId);
  }

  function readHealth(context, studentId) {
    show(context, shell("health", "Dossier santé", studentId, state("loading", "Chargement", "Lecture de la fiche santé.")));
    native("GET", "/native/students/" + encodeURIComponent(studentId) + "/health").then(function (data) {
      renderHealth(context, studentId, data || { profile: {}, conditions: [], allergies: [], medications: [] }, "");
    }).catch(function (error) { fail(context, "health", "Dossier santé", error); });
  }

  function renderDietary(context, studentId, data, notice) {
    var profile = data && data.profile || {};
    var body = (notice || "") + facts([
      ["Déclaration", profile.dietary_declaration_completed ? "Oui" : "Non"],
      ["Note", profile.parent_food_note]
    ]);
    body += "<h3>Restrictions</h3>" + listItems(data.restrictions, function (row) { return [row.kind, row.label, row.notes].filter(Boolean).join(" · "); }, "Aucune restriction");
    body += "<h3>Préférences</h3>" + listItems(data.preferences, function (row) { return [row.preference_type, row.item].filter(Boolean).join(" · "); }, "Aucune préférence");
    if (!blocked(context, "school.student.dietary.manage")) {
      body += '<form id="p1DietaryForm"><label><input name="dietary_declaration_completed" type="checkbox"' + (profile.dietary_declaration_completed ? " checked" : "") + "> Déclaration complétée</label>" +
        '<label>Note<textarea name="parent_food_note">' + escapeHtml(profile.parent_food_note || "") + "</textarea></label>" +
        button("Enregistrer le profil", "p1DietarySave", "submit") + "</form>";
      body += '<form id="p1RestrictionForm"><label>Type<select name="kind"><option>intolerance</option><option>prohibited_food</option><option>restriction</option><option>diet</option></select></label>' +
        '<label>Libellé<input name="label" required></label><label>Notes<input name="notes"></label>' +
        button("Ajouter la restriction", "p1RestrictionSave", "submit") + "</form>";
    }
    var surface = show(context, shell("dietary", "Profil alimentaire", studentId, body));
    on(surface, "p1DietaryForm", "submit", function (event) {
      if (event && event.preventDefault) event.preventDefault();
      var bodyPayload = {
        dietary_declaration_completed: !!field(event, "dietary_declaration_completed").checked,
        parent_food_note: field(event, "parent_food_note").value
      };
      show(context, shell("dietary", "Profil alimentaire", studentId, state("loading", "Chargement", "Enregistrement du profil.")));
      native("PUT", "/native/students/" + encodeURIComponent(studentId) + "/dietary", bodyPayload).then(function () {
        var next = Object.assign({}, data, { profile: Object.assign({}, profile, bodyPayload) });
        renderDietary(context, studentId, next, state("success", "Enregistré", "Le profil a été enregistré."));
      }).catch(function (error) { fail(context, "dietary", "Profil alimentaire", error); });
    });
    on(surface, "p1RestrictionForm", "submit", function (event) {
      if (event && event.preventDefault) event.preventDefault();
      var restriction = { kind: field(event, "kind").value, label: field(event, "label").value };
      var notes = field(event, "notes").value;
      if (notes) restriction.notes = notes;
      native("POST", "/native/students/" + encodeURIComponent(studentId) + "/dietary/restrictions", restriction).then(function () {
        renderDietary(context, studentId, data, state("success", "Enregistré", "La restriction a été enregistrée."));
      }).catch(function (error) { fail(context, "dietary", "Profil alimentaire", error); });
    });
  }

  function readDietary(context, studentId) {
    show(context, shell("dietary", "Profil alimentaire", studentId, state("loading", "Chargement", "Lecture du profil alimentaire.")));
    native("GET", "/native/students/" + encodeURIComponent(studentId) + "/dietary").then(function (data) {
      renderDietary(context, studentId, data || { profile: {}, restrictions: [], preferences: [] }, "");
    }).catch(function (error) { fail(context, "dietary", "Profil alimentaire", error); });
  }

  function loadDietary(context, studentId) {
    if (!isUuid(studentId)) {
      show(context, shell("dietary", "Profil alimentaire", "", state("error", "Identifiant invalide", "Le profil exige un élève connu.")));
      return;
    }
    if (parentLimited(context, "school.student.dietary.read")) {
      native("GET", "/native/parent/children").then(function (children) {
        var allowed = {};
        (Array.isArray(children) ? children : []).forEach(function (child) { allowed[child.student_id || child.id] = true; });
        if (!allowed[studentId]) {
          show(context, shell("dietary", "Profil alimentaire", "", state("error", "Hors périmètre", "Cet enfant n’est pas dans votre périmètre.")));
          return;
        }
        readDietary(context, studentId);
      }).catch(function (error) { fail(context, "dietary", "Profil alimentaire", error); });
      return;
    }
    readDietary(context, studentId);
  }

  function askStudent(context, screen, title, permission, loader) {
    if (!liveGate(context, permission, screen, title)) return;
    if (context.studentId) {
      loader(context, context.studentId);
      return;
    }
    if (parentLimited(context, permission)) {
      show(context, shell(screen, title, "", state("loading", "Chargement", "Lecture des enfants rattachés.")));
      native("GET", "/native/parent/children").then(function (data) {
        var children = Array.isArray(data) ? data : [];
        var allowed = {};
        var buttons = children.map(function (child) {
          var id = child.student_id || child.id;
          allowed[id] = true;
          return '<button type="button" data-p1-child="' + escapeHtml(id) + '">' + escapeHtml([child.first_name, child.last_name].filter(Boolean).join(" ") || id) + "</button>";
        }).join("");
        var surface = show(context, shell(screen, title, "Enfants rattachés uniquement.", buttons ? '<div id="p1ChildList">' + buttons + "</div>" : state("empty", "Aucun enfant rattaché", "")));
        on(surface, "p1ChildList", "click", function (event) {
          var id = event && event.target && event.target.getAttribute ? event.target.getAttribute("data-p1-child") : "";
          if (!id || !allowed[id]) {
            show(context, shell(screen, title, "", state("error", "Hors périmètre", "Cet enfant n’est pas dans votre périmètre.")));
            return;
          }
          loader(context, id);
        });
      }).catch(function (error) { fail(context, screen, title, error); });
      return;
    }
    var surface = show(context, shell(screen, title, "Un élève connu.",
      '<form id="p1StudentPick"><label>Identifiant de l’élève<input name="student_id" required></label>' + button("Ouvrir", "p1StudentOpen", "submit") + "</form>"));
    on(surface, "p1StudentPick", "submit", function (event) {
      if (event && event.preventDefault) event.preventDefault();
      loader(context, field(event, "student_id").value);
    });
  }

  function start(screen, context) {
    if (screen === "receipt") return renderReceipt(context);
    if (screen === "cash") return renderCash(context);
    if (screen === "daily") return renderDaily(context);
    if (screen === "fees") return renderFees(context);
    if (screen === "health") return askStudent(context, "health", "Dossier santé", "school.student.health.read", loadHealth);
    if (screen === "dietary") return askStudent(context, "dietary", "Profil alimentaire", "school.student.dietary.read", loadDietary);
  }

  function open(branchKey, actionName, context) {
    var screen = SCREENS[branchKey + "|" + actionName];
    if (!screen) return false;
    start(screen, context || {});
    return true;
  }

  function mount(element, screen, context) {
    var next = Object.assign({}, context || {});
    next.host = element;
    start(screen, next);
  }

  root.SchoolSafeVisualOutputP1 = { open: open, mount: mount };
})(typeof window !== "undefined" ? window : globalThis);
