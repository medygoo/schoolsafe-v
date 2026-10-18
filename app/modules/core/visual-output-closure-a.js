(function (root) {
  "use strict";

  var DOC_TYPES = ["DEV", "PREP", "CDT", "EVAL", "BUL", "PAL", "REC", "FAC", "LET", "CONV", "ATT", "RAP"];
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

  function button(label, id) {
    if (typeof root.ssButton === "function") return root.ssButton({ label: label, type: "button", attrs: { id: id } });
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
    return '<section class="cla-screen" data-cla-screen="' + screen + '"><h2>' + escapeHtml(title) + "</h2>" + body + "</section>";
  }

  function deny(context, code) {
    show(context, shell("denied", "Accès refusé", state("error", "Accès refusé", "Cette action est explicitement refusée ou hors permission (" + code + ").")));
  }

  function liveGate(context, title) {
    if (context && context.live) return true;
    show(context, shell("gate", title, state("unavailable", title, "Session réelle requise. Aucune donnée fictive n’est affichée.")));
    return false;
  }

  function fail(context, screen, title, error) {
    show(context, shell(screen, title, state("error", "Erreur", error && error.message ? error.message : "Lecture impossible")));
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
    if (options && options.body && !(root.FormData && options.body instanceof root.FormData)) {
      headers["Content-Type"] = "application/json";
      init.body = JSON.stringify(options.body);
    } else if (options && options.body) {
      init.body = options.body;
    }
    return root.fetch(apiBase() + path, init).then(function (response) {
      return response.json().catch(function () { return {}; }).then(function (body) {
        if (!response.ok) throw new Error((body && body.message) || ("Erreur " + response.status));
        return body || {};
      });
    });
  }

  function text(id) {
    var node = document.getElementById(id);
    return node ? String(node.value || "").trim() : "";
  }

  function listFrom(body) {
    if (Array.isArray(body)) return body;
    if (body && Array.isArray(body.data)) return body.data;
    if (body && body.data && Array.isArray(body.data.rows)) return body.data.rows;
    return [];
  }

  function isUuid(value) {
    return /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(String(value || ""));
  }

  function field(label, id) {
    return "<label>" + escapeHtml(label) + ' <input id="' + id + '"></label>';
  }

  function confirm(context, screen, title, message, yesId, extra, done) {
    var surface = show(context, shell(screen, title, state("unavailable", "Confirmation", message) + (extra || "") + button("Confirmer", yesId)));
    on(surface, yesId, "click", done);
  }

  function openFeeControl(context) {
    var title = "Contrôle des frais";
    if (blocked(context, "finance.control.read")) { deny(context, "finance.control.read"); return; }
    if (!liveGate(context, title)) return;
    show(context, shell("fees", title, state("loading", "Chargement", "Lecture des campagnes.")));
    request("/native/finance/fee-control/campaigns").then(function (body) {
      var rows = listFrom(body);
      var list = rows.map(function (row) {
        return "<li><b>" + escapeHtml(row.label || row.id) + "</b> " + escapeHtml(row.status || "") + "</li>";
      }).join("");
      var content = rows.length ? "<ul>" + list + "</ul>" : state("empty", "Aucune campagne", "Le serveur n'a renvoyé aucune campagne.");
      content += "<p>Le moteur ne renvoie pas de liste de scans. Seul le résultat d'écriture est affiché.</p>";
      if (!blocked(context, "finance.control.manage")) {
        content += "<form>" + field("Structure", "claCampaignStructure") + field("Libellé", "claCampaignLabel") + button("Créer la campagne", "claCampaignCreate") + "</form>";
      }
      if (!blocked(context, "finance.control.scan")) {
        content += "<form>" + field("Campagne", "claScanCampaign") + field("Élève", "claScanStudent") + field("Résultat", "claScanResult") + button("Enregistrer le scan", "claScanCreate") + "</form>";
      }
      var surface = show(context, shell("fees", title, content));
      on(surface, "claCampaignCreate", "click", function () {
        var payload = { fee_structure_id: text("claCampaignStructure"), label: text("claCampaignLabel") };
        if (!isUuid(payload.fee_structure_id) || !payload.label) {
          show(context, shell("fees", title, state("error", "Saisie", "La structure et le libellé sont requis.")));
          return;
        }
        confirm(context, "fees", title, "Créer cette campagne ?", "claCampaignYes", "", function () {
          request("/native/finance/fee-control/campaigns", { method: "POST", body: payload }).then(function (created) {
            var id = created && created.data ? created.data.id : "";
            show(context, shell("fees", title, state("success", "Campagne créée", id)));
          }).catch(function (error) { fail(context, "fees", title, error); });
        });
      });
      on(surface, "claScanCreate", "click", function () {
        var payload = { campaign_id: text("claScanCampaign"), student_id: text("claScanStudent"), result: text("claScanResult") };
        if (!isUuid(payload.campaign_id) || !isUuid(payload.student_id) || !payload.result) {
          show(context, shell("fees", title, state("error", "Saisie", "Campagne, élève et résultat sont requis.")));
          return;
        }
        confirm(context, "fees", title, "Enregistrer ce scan ?", "claScanYes", "", function () {
          request("/native/finance/fee-control/scans", { method: "POST", body: payload }).then(function (created) {
            var id = created && created.data ? created.data.id : "";
            show(context, shell("fees", title, state("success", "Scan enregistré", id)));
          }).catch(function (error) { fail(context, "fees", title, error); });
        });
      });
    }).catch(function (error) { fail(context, "fees", title, error); });
  }

  function documentForms(context, notice) {
    var title = "Centre documentaire";
    var body = (notice || "") + "<p>Le moteur expose la création et l'émission. Aucune archive n'est lue.</p>";
    body += "<form>" + field("Année", "claDocYear") + field("Type", "claDocType") + "<small>" + escapeHtml(DOC_TYPES.join(" ")) + "</small>" + button("Créer le document", "claDocCreate") + "</form>";
    body += "<form>" + field("Document", "claDocId") + button("Émettre", "claDocIssue") + "</form>";
    var surface = show(context, shell("documents", title, body));
    on(surface, "claDocCreate", "click", function () {
      var year = text("claDocYear");
      var typeCode = text("claDocType");
      if (!isUuid(year) || DOC_TYPES.indexOf(typeCode) < 0) {
        show(context, shell("documents", title, state("error", "Saisie", "Année et type doivent suivre le contrat du moteur.")));
        return;
      }
      confirm(context, "documents", title, "Créer ce document ?", "claDocYes", "", function () {
        request("/documents/create", { method: "POST", body: { academicYearId: year, typeCode: typeCode, metadata: {} } }).then(function (created) {
          documentForms(context, state("success", "Document créé", [created.document_id, created.code, created.status].filter(Boolean).join(" ")));
        }).catch(function (error) { fail(context, "documents", title, error); });
      });
    });
    on(surface, "claDocIssue", "click", function () {
      var id = text("claDocId");
      if (!isUuid(id)) {
        show(context, shell("documents", title, state("error", "Saisie", "L'identifiant du document est requis.")));
        return;
      }
      confirm(context, "documents", title, "Émettre ce document ?", "claDocIssueYes", "", function () {
        request("/documents/" + id + "/issue", { method: "POST", body: {} }).then(function (issued) {
          show(context, shell("documents", title, state("success", "Document émis", issued.status || "")));
        }).catch(function (error) { fail(context, "documents", title, error); });
      });
    });
  }

  function openDocuments(context) {
    if (!liveGate(context, "Centre documentaire")) return;
    documentForms(context, "");
  }

  function paintYears(context, rows, notice) {
    var title = "Année scolaire";
    var list = rows.map(function (row, index) {
      var active = row.is_active === true ? "active" : "";
      var activate = row.is_active === true ? "" : button("Activer", "claYearActivate" + index);
      return "<li><b>" + escapeHtml(row.label || row.id) + "</b> " + escapeHtml(row.starts_on || "") + " " + escapeHtml(row.ends_on || "") + " " + escapeHtml(row.periods || "") + " " + escapeHtml(active) + " " + activate + "</li>";
    }).join("");
    var body = (notice || "") + (rows.length ? "<ul>" + list + "</ul>" : state("empty", "Aucune année", "Le serveur n'a renvoyé aucune année scolaire."));
    if (!blocked(context, "school.manage")) {
      body += "<form>" + field("Libellé", "claYearLabel") + field("Début", "claYearStart") + field("Fin", "claYearEnd") + field("Périodes", "claYearPeriods") + button("Créer l'année", "claYearCreate") + "</form>";
      body += "<form>" + field("Année", "claYearId") + field("Libellé", "claYearEditLabel") + field("Début", "claYearEditStart") + field("Fin", "claYearEditEnd") + field("Périodes", "claYearEditPeriods") + button("Modifier l'année", "claYearSave") + "</form>";
    }
    var surface = show(context, shell("years", title, body));
    rows.forEach(function (row, index) {
      on(surface, "claYearActivate" + index, "click", function () {
        confirm(context, "years", title, "Activer cette année ?", "claYearActivateYes", "", function () {
          request("/school/academic-years/" + row.id + "/activate", { method: "POST", body: {} }).then(function () {
            loadYears(context, state("success", "Année activée", row.label || row.id));
          }).catch(function (error) { fail(context, "years", title, error); });
        });
      });
    });
    on(surface, "claYearCreate", "click", function () {
      var payload = { label: text("claYearLabel"), starts_on: text("claYearStart"), ends_on: text("claYearEnd"), periods: text("claYearPeriods") };
      if (!payload.label || !/^\d{4}-\d{2}-\d{2}$/.test(payload.starts_on) || !/^\d{4}-\d{2}-\d{2}$/.test(payload.ends_on) || (payload.periods !== "Trimestres" && payload.periods !== "Semestres")) {
        show(context, shell("years", title, state("error", "Saisie", "Libellé, dates et périodes doivent suivre le contrat.")));
        return;
      }
      confirm(context, "years", title, "Créer cette année ?", "claYearYes", "", function () {
        request("/school/academic-years", { method: "POST", body: payload }).then(function (created) {
          loadYears(context, state("success", "Année créée", created.id || ""));
        }).catch(function (error) { fail(context, "years", title, error); });
      });
    });
    on(surface, "claYearSave", "click", function () {
      var id = text("claYearId");
      var payload = {};
      if (text("claYearEditLabel")) payload.label = text("claYearEditLabel");
      if (text("claYearEditStart")) payload.starts_on = text("claYearEditStart");
      if (text("claYearEditEnd")) payload.ends_on = text("claYearEditEnd");
      if (text("claYearEditPeriods")) payload.periods = text("claYearEditPeriods");
      if (!isUuid(id)) {
        show(context, shell("years", title, state("error", "Saisie", "L'identifiant de l'année est requis.")));
        return;
      }
      confirm(context, "years", title, "Modifier cette année ?", "claYearSaveYes", "", function () {
        request("/school/academic-years/" + id, { method: "PUT", body: payload }).then(function () {
          loadYears(context, state("success", "Année modifiée", id));
        }).catch(function (error) { fail(context, "years", title, error); });
      });
    });
  }

  function loadYears(context, notice) {
    var title = "Année scolaire";
    if (blocked(context, "school.manage")) { deny(context, "school.manage"); return; }
    if (!liveGate(context, title)) return;
    show(context, shell("years", title, state("loading", "Chargement", "Lecture des années scolaires.")));
    request("/school/academic-years").then(function (body) {
      paintYears(context, listFrom(body), notice || "");
    }).catch(function (error) { fail(context, "years", title, error); });
  }

  function openIdentity(context) {
    var title = "Identité de l'école";
    if (blocked(context, "school.manage")) { deny(context, "school.manage"); return; }
    if (!liveGate(context, title)) return;
    show(context, shell("identity", title, state("loading", "Chargement", "Lecture de l'identité.")));
    request("/school/settings").then(function (body) {
      var identity = body.identity || {};
      var contact = body.contact || {};
      var facts = "<dl><div><dt>Nom</dt><dd>" + escapeHtml(identity.name || "") + "</dd></div><div><dt>Nom légal</dt><dd>" + escapeHtml(identity.legal_name || "") + "</dd></div><div><dt>Adresse</dt><dd>" + escapeHtml(contact.address || "") + "</dd></div><div><dt>Ville</dt><dd>" + escapeHtml(contact.city || "") + "</dd></div></dl>";
      var form = "<form>" + field("Nom", "claSchoolName") + field("Nom légal", "claLegalName") + button("Enregistrer l'identité", "claIdentitySave") + "</form>";
      form += "<form>" + field("Logo", "claLogo") + button("Enregistrer le logo", "claLogoSave") + "</form>";
      var surface = show(context, shell("identity", title, facts + form));
      var name = document.getElementById("claSchoolName");
      var legal = document.getElementById("claLegalName");
      var logo = document.getElementById("claLogo");
      if (name) name.value = identity.name || "";
      if (legal) legal.value = identity.legal_name || "";
      if (logo) logo.type = "file";
      on(surface, "claIdentitySave", "click", function () {
        var payload = { identity: { name: text("claSchoolName") } };
        if (text("claLegalName")) payload.identity.legal_name = text("claLegalName");
        if (!payload.identity.name) {
          show(context, shell("identity", title, state("error", "Saisie", "Le nom de l'école est requis.")));
          return;
        }
        confirm(context, "identity", title, "Enregistrer l'identité ?", "claIdentityYes", "", function () {
          request("/school/settings", { method: "PUT", body: payload }).then(function () {
            show(context, shell("identity", title, state("success", "Identité enregistrée", payload.identity.name)));
          }).catch(function (error) { fail(context, "identity", title, error); });
        });
      });
      on(surface, "claLogoSave", "click", function () {
        var input = document.getElementById("claLogo");
        var file = input && input.files && input.files[0];
        if (!file) {
          show(context, shell("identity", title, state("error", "Fichier", "Aucun fichier reçu.")));
          return;
        }
        confirm(context, "identity", title, "Envoyer ce logo ?", "claLogoYes", "", function () {
          var formData = new root.FormData();
          formData.append("file", file);
          request("/school/logo", { method: "POST", body: formData }).then(function (saved) {
            show(context, shell("identity", title, state("success", "Logo enregistré", saved.logo_path || "")));
          }).catch(function (error) { fail(context, "identity", title, error); });
        });
      });
    }).catch(function (error) { fail(context, "identity", title, error); });
  }

  function paintPickup(context, studentId, body) {
    var title = "Personnes autorisées";
    var data = body && body.data ? body.data : {};
    var family = Array.isArray(data.family) ? data.family : [];
    var accredited = Array.isArray(data.accredited) ? data.accredited : [];
    var people = family.concat(accredited);
    var list = people.map(function (person, index) {
      var id = person.authorization_id || "";
      var actions = "";
      if (id && !blocked(context, "school.guardian.manage")) {
        actions = button("Valider", "claPickupValidate" + index) + button("Révoquer", "claPickupRevoke" + index);
      }
      return "<li><b>" + escapeHtml(person.name || "") + "</b> " + escapeHtml(person.status || "") + " " + actions + "</li>";
    }).join("");
    var content = people.length ? "<ul>" + list + "</ul>" : state("empty", "Aucune autorisation", "Le serveur n'a renvoyé aucune personne.");
    if (!blocked(context, "school.guardian.manage")) {
      content += "<form>" + field("Personne", "claPickupGuardian") + button("Créer l'autorisation", "claPickupCreate") + "</form>";
    }
    var surface = show(context, shell("pickup", title, content));
    people.forEach(function (person, index) {
      var id = person.authorization_id;
      if (!id) return;
      on(surface, "claPickupValidate" + index, "click", function () {
        confirm(context, "pickup", title, "Valider cette autorisation ?", "claPickupValidateYes", "", function () {
          request("/native/family/pickup-authorizations/" + id + "/validate", { method: "POST", body: {} }).then(function () {
            request("/native/family/students/" + studentId + "/pickup-authorizations").then(function (next) {
              paintPickup(context, studentId, next);
            }).catch(function (error) { fail(context, "pickup", title, error); });
          }).catch(function (error) { fail(context, "pickup", title, error); });
        });
      });
      on(surface, "claPickupRevoke" + index, "click", function () {
        confirm(context, "pickup", title, "Révoquer cette autorisation ?", "claPickupRevokeYes", "", function () {
          request("/native/family/pickup-authorizations/" + id + "/revoke", { method: "POST", body: { mode: "revoked" } }).then(function () {
            request("/native/family/students/" + studentId + "/pickup-authorizations").then(function (next) {
              paintPickup(context, studentId, next);
            }).catch(function (error) { fail(context, "pickup", title, error); });
          }).catch(function (error) { fail(context, "pickup", title, error); });
        });
      });
    });
    on(surface, "claPickupCreate", "click", function () {
      var guardian = text("claPickupGuardian");
      if (!isUuid(guardian)) {
        show(context, shell("pickup", title, state("error", "Saisie", "L'identifiant de la personne est requis.")));
        return;
      }
      confirm(context, "pickup", title, "Créer cette autorisation ?", "claPickupYes", "", function () {
        request("/native/family/pickup-authorizations", { method: "POST", body: { student_id: studentId, guardian_id: guardian } }).then(function () {
          if (blocked(context, "security.pickup.read")) {
            show(context, shell("pickup", title, state("success", "Autorisation créée", "")));
            return;
          }
          request("/native/family/students/" + studentId + "/pickup-authorizations").then(function (next) {
            paintPickup(context, studentId, next);
          }).catch(function (error) { fail(context, "pickup", title, error); });
        }).catch(function (error) { fail(context, "pickup", title, error); });
      });
    });
  }

  function selectPickupStudent(context, student) {
    var title = "Personnes autorisées";
    if (blocked(context, "security.pickup.read")) {
      if (blocked(context, "school.guardian.manage")) { deny(context, "security.pickup.read"); return; }
      var form = "<form>" + field("Personne", "claPickupGuardian") + button("Créer l'autorisation", "claPickupCreate") + "</form>";
      var surface = show(context, shell("pickup", title, form));
      on(surface, "claPickupCreate", "click", function () {
        var guardian = text("claPickupGuardian");
        confirm(context, "pickup", title, "Créer cette autorisation ?", "claPickupYes", "", function () {
          request("/native/family/pickup-authorizations", { method: "POST", body: { student_id: student.id, guardian_id: guardian } }).then(function () {
            show(context, shell("pickup", title, state("success", "Autorisation créée", "")));
          }).catch(function (error) { fail(context, "pickup", title, error); });
        });
      });
      return;
    }
    show(context, shell("pickup", title, state("loading", "Chargement", "Lecture des autorisations.")));
    request("/native/family/students/" + student.id + "/pickup-authorizations").then(function (body) {
      paintPickup(context, student.id, body);
    }).catch(function (error) { fail(context, "pickup", title, error); });
  }

  function openPickup(context) {
    var title = "Personnes autorisées";
    var user = (context && context.user) || {};
    var parent = user.role === "parent";
    if (blocked(context, "security.pickup.read") && blocked(context, "school.guardian.manage") && blocked(context, "school.student.read") && blocked(context, "pedagogy.grade.read")) {
      deny(context, "security.pickup.read");
      return;
    }
    if (!liveGate(context, title)) return;
    show(context, shell("pickup", title, state("loading", "Chargement", "Lecture des élèves.")));
    var path = parent ? "/native/pedagogy/parent/children" : "/native/students?status=active&limit=50&offset=0";
    if (!parent && blocked(context, "school.student.read")) {
      show(context, shell("pickup", title, state("empty", "Aucun élève", "La lecture des élèves n'est pas autorisée.")));
      return;
    }
    request(path).then(function (body) {
      var rows = listFrom(body).filter(function (row) {
        if (parent && Array.isArray(user.childIds) && user.childIds.indexOf(row.id) < 0) return false;
        var access = root.SchoolSafeAccess;
        var scope = access && access.scopeFor ? access.scopeFor(user, "security.pickup.read") : null;
        if (scope && scope.type === "own_children" && scope.target && row.id !== scope.target) return false;
        return true;
      });
      var list = rows.map(function (row, index) {
        var name = [row.first_name, row.last_name].filter(Boolean).join(" ") || row.id;
        return "<li>" + escapeHtml(name) + " " + button("Autorisations", "claPickupStudent" + index) + "</li>";
      }).join("");
      var surface = show(context, shell("pickup", title, rows.length ? "<ul>" + list + "</ul>" : state("empty", "Aucun élève", "Le serveur n'a renvoyé aucun élève.")));
      rows.forEach(function (row, index) {
        on(surface, "claPickupStudent" + index, "click", function () { selectPickupStudent(context, row); });
      });
    }).catch(function (error) { fail(context, "pickup", title, error); });
  }

  function deviceSection(context) {
    var title = "Appareils";
    var parts = ["school.manage", "school.student.create", "security.scan"].filter(function (code) { return blocked(context, code); });
    if (parts.length === 3) { deny(context, parts.join(", ")); return; }
    if (!liveGate(context, title)) return;
    var body = "<p>Le moteur n'expose pas de lecture des appareils, événements ou mappings.</p>";
    if (!blocked(context, "school.manage")) {
      body += "<form>" + field("Code", "claDeviceCode") + field("Fournisseur", "claDeviceVendor") + field("Modèle", "claDeviceModel") + field("Série", "claDeviceSerial") + button("Enregistrer l'appareil", "claDeviceSave") + "</form>";
    }
    if (!blocked(context, "school.student.create")) {
      body += "<form>" + field("Appareil", "claMapDevice") + field("Élève", "claMapStudent") + button("Enregistrer le mapping", "claMapSave") + "</form>";
    }
    if (!blocked(context, "security.scan")) {
      body += "<form>" + field("Appareil", "claEventDevice") + field("Identifiant", "claEventCredential") + field("Type", "claEventType") + field("Instant", "claEventAt") + button("Enregistrer l'événement", "claEventSave") + "</form>";
      body += "<form>" + field("Jour", "claApplyDay") + button("Appliquer les présences", "claApplySave") + "</form>";
    }
    var surface = show(context, shell("devices", title, body));
    on(surface, "claDeviceSave", "click", function () {
      var payload = { code: text("claDeviceCode"), vendor: text("claDeviceVendor"), model: text("claDeviceModel"), serial_number: text("claDeviceSerial") };
      if (!payload.code || !payload.vendor || !payload.model || payload.serial_number.length < 3) {
        show(context, shell("devices", title, state("error", "Saisie", "Code, fournisseur, modèle et série sont requis.")));
        return;
      }
      confirm(context, "devices", title, "Enregistrer cet appareil ?", "claDeviceYes", "", function () {
        request("/native/devicehub/devices", { method: "POST", body: payload }).then(function (created) {
          var id = created && created.data ? created.data.device_id : "";
          show(context, shell("devices", title, state("success", "Appareil enregistré", id)));
        }).catch(function (error) { fail(context, "devices", title, error); });
      });
    });
    on(surface, "claMapSave", "click", function () {
      var payload = { device_id: text("claMapDevice"), student_id: text("claMapStudent"), subject_type: "student" };
      if (!isUuid(payload.device_id) || !isUuid(payload.student_id)) {
        show(context, shell("devices", title, state("error", "Saisie", "Appareil et élève sont requis.")));
        return;
      }
      confirm(context, "devices", title, "Enregistrer ce mapping ?", "claMapYes", "", function () {
        request("/native/devicehub/mappings", { method: "POST", body: payload }).then(function (created) {
          var id = created && created.data ? (created.data.mapping_id || created.data.id || "") : "";
          show(context, shell("devices", title, state("success", "Mapping enregistré", id)));
        }).catch(function (error) { fail(context, "devices", title, error); });
      });
    });
    on(surface, "claEventSave", "click", function () {
      var payload = {
        device_id: text("claEventDevice"),
        credential_type: text("claEventCredential"),
        event_type: text("claEventType"),
        occurred_at: text("claEventAt"),
        source: "terminal"
      };
      confirm(context, "devices", title, "Enregistrer cet événement ?", "claEventYes", "", function () {
        request("/native/devicehub/events", { method: "POST", body: payload }).then(function (created) {
          var id = created && created.data ? created.data.event_id : "";
          show(context, shell("devices", title, state("success", "Événement enregistré", id)));
        }).catch(function (error) { fail(context, "devices", title, error); });
      });
    });
    on(surface, "claApplySave", "click", function () {
      var day = text("claApplyDay");
      if (!/^\d{4}-\d{2}-\d{2}$/.test(day)) {
        show(context, shell("devices", title, state("error", "Saisie", "Le jour doit être une date.")));
        return;
      }
      confirm(context, "devices", title, "Appliquer les présences de ce jour ?", "claApplyYes", "", function () {
        request("/native/devicehub/attendance/apply", { method: "POST", body: { day: day } }).then(function (created) {
          var applied = created && created.data ? created.data.applied : "";
          show(context, shell("devices", title, state("success", "Présences appliquées", applied)));
        }).catch(function (error) { fail(context, "devices", title, error); });
      });
    });
  }

  function visibleChildren(context, rows) {
    var user = (context && context.user) || {};
    var ids = Array.isArray(user.childIds) ? user.childIds : null;
    var access = root.SchoolSafeAccess;
    var scope = access && typeof access.scopeFor === "function" ? access.scopeFor(user, "pedagogy.grade.read") : null;
    return rows.filter(function (row) {
      if (!row || !row.id) return false;
      if (scope && scope.type === "own_children" && scope.target && row.id !== scope.target) return false;
      if (ids && ids.indexOf(row.id) < 0) return false;
      return true;
    });
  }

  function openParentGrades(context) {
    var title = "Résultats";
    if (blocked(context, "pedagogy.grade.read")) { deny(context, "pedagogy.grade.read"); return; }
    if (!liveGate(context, title)) return;
    show(context, shell("grades", title, state("loading", "Chargement", "Lecture des enfants rattachés.")));
    request("/native/pedagogy/parent/children").then(function (body) {
      var rows = visibleChildren(context, listFrom(body));
      var list = rows.map(function (row, index) {
        var name = [row.first_name, row.last_name].filter(Boolean).join(" ") || row.id;
        return "<li>" + escapeHtml(name) + " " + button("Notes", "claChild" + index) + "</li>";
      }).join("");
      var surface = show(context, shell("grades", title, rows.length ? "<ul>" + list + "</ul>" : state("empty", "Aucun enfant", "Aucun enfant rattaché n'est renvoyé.")));
      rows.forEach(function (row, index) {
        on(surface, "claChild" + index, "click", function () {
          show(context, shell("grades", title, state("loading", "Chargement", "Lecture des notes.")));
          request("/native/pedagogy/parent/grades/" + row.id).then(function (payload) {
            var grades = listFrom(payload);
            var items = grades.map(function (grade) {
              return "<li>" + escapeHtml(grade.subject_name || "") + " " + escapeHtml(grade.title || "") + " <b>" + escapeHtml(grade.score) + "</b> / " + escapeHtml(grade.max_score) + "</li>";
            }).join("");
            show(context, shell("grades", title, grades.length ? "<ul>" + items + "</ul>" : state("empty", "Aucune note", "Le serveur n'a renvoyé aucune note publiée.")));
          }).catch(function (error) { fail(context, "grades", title, error); });
        });
      });
    }).catch(function (error) { fail(context, "grades", title, error); });
  }

  function open(branchKey, actionName, context) {
    context = context || {};
    var key = branchKey + "|" + actionName;
    if (key === "finance|Historique du jour") {
      return !!(root.SchoolSafeVisualOutputP1 && root.SchoolSafeVisualOutputP1.open("finance", "Rapports financiers", context));
    }
    if (key === "finance|Contrôle des frais") {
      if (root.SchoolSafeFeeControlModule && typeof root.SchoolSafeFeeControlModule.render === "function") return false;
      openFeeControl(context);
      return true;
    }
    if (key === "administration|Centre documentaire") { openDocuments(context); return true; }
    if (key === "school|Année scolaire") { loadYears(context, ""); return true; }
    if (key === "administration|Paramètres") { openIdentity(context); return true; }
    if (key === "school|Personnes autorisées") { openPickup(context); return true; }
    if (key === "people|Biométrie" || key === "people|Présence du personnel" || key === "people|Présence personnel") {
      deviceSection(context);
      return true;
    }
    if (key === "school|Résultats" && context.user && context.user.role === "parent") {
      openParentGrades(context);
      return true;
    }
    return false;
  }

  root.SchoolSafeVisualOutputClosureA = { open: open };
})(typeof window !== "undefined" ? window : globalThis);
