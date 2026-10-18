(function (root) {
  "use strict";

  var SCREENS = {
    "school|Import massif": "import",
    "school|Importer des dossiers": "import",
    "school|Créer l’élève": "create",
    "school|Vérifier les dossiers": "drafts",
    "school|Accepter ou refuser": "decision",
    "school|Préinscriptions": "drafts",
    "school|Inscriptions": "active",
    "school|Inscriptions et réinscriptions": "active",
    "school|Parent principal et tuteurs": "family",
    "school|Parents et tuteurs": "family",
    "people|Enseignants": "staff",
    "people|Personnel": "staff",
    "school|Affectations": "classes",
    "pedagogy|6e A": "class",
    "pedagogy|5e A": "class",
    "pedagogy|Classes": "classes",
    "pedagogy|Affectations": "assignments",
    "pilotage|Approbations": "approvals"
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

  function shell(screen, title, body) {
    return '<section class="p2a-screen" data-p2a-screen="' + screen + '"><h2>' + escapeHtml(title) + "</h2>" + body + "</section>";
  }

  function facts(pairs) {
    return '<dl class="p2a-facts">' + pairs.filter(function (pair) {
      return pair[1] != null && pair[1] !== "";
    }).map(function (pair) {
      return "<div><dt>" + escapeHtml(pair[0]) + "</dt><dd>" + escapeHtml(pair[1]) + "</dd></div>";
    }).join("") + "</dl>";
  }

  function field(form, name) {
    var element = form && form.elements ? form.elements[name] : null;
    return element ? String(element.value || "").trim() : "";
  }

  function isUuid(value) {
    return /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(String(value || ""));
  }

  function rowsOf(payload) {
    if (Array.isArray(payload)) return payload;
    if (payload && Array.isArray(payload.data)) return payload.data;
    if (payload && payload.data && Array.isArray(payload.data.rows)) return payload.data.rows;
    return [];
  }

  function objectOf(payload) {
    if (payload && payload.data && !Array.isArray(payload.data)) return payload.data;
    return payload || {};
  }

  function apiBase() {
    var base = root.schoolSafeApiBase;
    if (base === "") return "";
    if (!base) base = "http://127.0.0.1:8787";
    return String(base).replace(/\/$/, "");
  }

  function token() {
    try {
      var raw = root.sessionStorage && root.sessionStorage.getItem("schoolsafe-v2-session");
      if (!raw) return "";
      var session = JSON.parse(raw);
      return session && session.token ? session.token : "";
    } catch (error) {
      return "";
    }
  }

  function request(method, path, body, auth) {
    var headers = { Accept: "application/json" };
    if (body !== undefined) headers["Content-Type"] = "application/json";
    if (auth) {
      var value = token();
      if (value) headers.Authorization = "Bearer " + value;
    }
    return fetch((auth ? apiBase() : "") + path, {
      method: method,
      credentials: "include",
      headers: headers,
      body: body === undefined ? undefined : JSON.stringify(body)
    }).then(function (response) {
      return response.json().catch(function () { return {}; }).then(function (payload) {
        if (!response.ok) throw new Error(payload && payload.message ? payload.message : "Erreur " + response.status);
        return payload;
      });
    });
  }

  function gate(context, codes, title) {
    var denied = [].concat(codes).filter(function (code) { return blocked(context, code); });
    if (denied.length) {
      show(context, shell("denied", title, state("error", "Accès refusé", "Cette action est explicitement refusée ou hors permission (" + denied.join(", ") + ").")));
      return false;
    }
    if (!(context && context.live)) {
      show(context, shell("live", title, state("unavailable", title, "Session réelle requise. Aucune donnée fictive n’est affichée.")));
      return false;
    }
    return true;
  }

  function fail(context, screen, title, error) {
    show(context, shell(screen, title, state("error", title, error && error.message ? error.message : "Erreur")));
  }

  function renderStudents(context, title, status, decision) {
    if (!gate(context, "school.student.read", title)) return;
    var surface = show(context, shell("students", title, state("loading", "Chargement", "Lecture des élèves.")));
    request("GET", "/native/students?status=" + encodeURIComponent(status) + "&limit=50&offset=0").then(function (payload) {
      var rows = rowsOf(payload);
      var body = rows.length ? "<ul>" + rows.map(function (row, index) {
        var name = [row.first_name, row.last_name].filter(Boolean).join(" ");
        return "<li><b>" + escapeHtml(name || row.id) + "</b> <small>" + escapeHtml(row.matricule || "") + " " + escapeHtml(row.lifecycle_status || "") + "</small> " + button("Ouvrir le dossier", "p2aOpen" + index) + "</li>";
      }).join("") + "</ul>" : state("empty", "Aucun élève", "La liste réelle est vide.");
      var next = show(context, shell("students", title, body));
      rows.forEach(function (row, index) {
        on(next, "p2aOpen" + index, "click", function () { openDossier(context, row.id, decision); });
      });
    }).catch(function (error) { fail(context, "students", title, error); });
    return surface;
  }

  function openDossier(context, studentId, decision) {
    show(context, shell("dossier", "Dossier", state("loading", "Chargement", "Lecture du dossier.")));
    Promise.all([
      request("GET", "/native/students/" + encodeURIComponent(studentId) + "/record"),
      request("GET", "/native/students/" + encodeURIComponent(studentId) + "/completeness")
    ]).then(function (results) {
      var record = objectOf(results[0]);
      var completeness = objectOf(results[1]);
      var schooling = record.schooling || {};
      var missing = Array.isArray(completeness.missing) ? completeness.missing.join(", ") : "";
      var body = facts([
        ["Matricule", record.matricule],
        ["Prénom", record.first_name],
        ["Nom", record.last_name],
        ["Statut", record.lifecycle_status],
        ["Classe", schooling.class_name],
        ["Année", schooling.academic_year_id],
        ["État du dossier", completeness.status],
        ["Points complets", completeness.completed_checkpoints],
        ["Points attendus", completeness.total_checkpoints],
        ["Manques", missing]
      ]);
      if (decision) body += state("unavailable", "Décision", "Aucune route de décision accepter ou refuser n’existe pour ce dossier.");
      show(context, shell("dossier", "Dossier", body));
    }).catch(function (error) { fail(context, "dossier", "Dossier", error); });
  }

  function renderCreate(context) {
    if (!gate(context, "school.student.create", "Créer l’élève")) return;
    var form = '<form id="p2aCreateForm">' +
      '<label>Matricule<input name="matricule" required></label>' +
      '<label>Prénom<input name="first_name" required></label>' +
      '<label>Deuxième prénom<input name="middle_name"></label>' +
      '<label>Nom<input name="last_name" required></label>' +
      '<label>Date de naissance<input name="date_of_birth" type="date"></label>' +
      '<label>Genre<input name="gender" maxlength="1"></label>' +
      '<label>Année<input name="academic_year_id" required></label>' +
      '<label>Classe prévue<input name="planned_class_id" required></label>' +
      '<label>Début<input name="enrollment_starts_on" type="date" required></label>' +
      button("Créer le brouillon", "p2aCreate", "submit") +
      "</form>";
    var surface = show(context, shell("create", "Créer l’élève", form));
    on(surface, "p2aCreateForm", "submit", function (event) {
      event.preventDefault();
      var gender = field(event.target, "gender");
      if (gender && gender !== "M" && gender !== "F") {
        show(context, shell("create", "Créer l’élève", state("error", "Genre", "Le genre accepté par le moteur est M ou F.")));
        return;
      }
      var payload = {
        matricule: field(event.target, "matricule"),
        first_name: field(event.target, "first_name"),
        last_name: field(event.target, "last_name"),
        academic_year_id: field(event.target, "academic_year_id"),
        planned_class_id: field(event.target, "planned_class_id"),
        enrollment_starts_on: field(event.target, "enrollment_starts_on")
      };
      ["middle_name", "date_of_birth"].forEach(function (name) {
        var value = field(event.target, name);
        if (value) payload[name] = value;
      });
      if (gender) payload.gender = gender;
      if (!isUuid(payload.academic_year_id) || !isUuid(payload.planned_class_id)) {
        show(context, shell("create", "Créer l’élève", state("error", "Identifiant", "L’année et la classe prévue doivent être des identifiants existants.")));
        return;
      }
      show(context, shell("create", "Créer l’élève", state("loading", "Chargement", "Création du brouillon.")));
      request("POST", "/native/students/drafts", payload).then(function (result) {
        var created = objectOf(result);
        show(context, shell("create", "Créer l’élève", state("success", "Brouillon créé", created.id || "")));
      }).catch(function (error) { fail(context, "create", "Créer l’élève", error); });
    });
  }

  function renderImport(context) {
    if (!gate(context, "school.student.create", "Import")) return;
    var form = '<form id="p2aImportForm"><label>Fichier<input name="filename" required></label><label>Contenu<textarea name="file_content" required></textarea></label>' + button("Préparer", "p2aImport", "submit") + "</form>";
    var surface = show(context, shell("import", "Import", form));
    on(surface, "p2aImportForm", "submit", function (event) {
      event.preventDefault();
      var payload = { filename: field(event.target, "filename"), file_content: field(event.target, "file_content") };
      show(context, shell("import", "Import", state("loading", "Chargement", "Préparation du lot.")));
      request("POST", "/native/family/student-imports", payload).then(function (result) {
        var job = objectOf(result);
        var body = facts([["Lot", job.job_id], ["Statut", job.status], ["Lignes", job.total_rows]]) + button("Aperçu", "p2aPreview");
        var next = show(context, shell("import", "Import", body));
        on(next, "p2aPreview", "click", function () {
          request("POST", "/native/family/student-imports/" + encodeURIComponent(job.job_id) + "/preview").then(function (preview) {
            var data = objectOf(preview);
            var confirm = facts([["Lot", data.job_id || job.job_id], ["Statut", data.status || job.status]]) + button("Confirmer l’import", "p2aCommitYes");
            var ready = show(context, shell("import", "Import", confirm));
            on(ready, "p2aCommitYes", "click", function () {
              request("POST", "/native/family/student-imports/" + encodeURIComponent(job.job_id) + "/commit").then(function (committed) {
                var done = objectOf(committed);
                show(context, shell("import", "Import", state("success", "Import enregistré", done.status || "")));
              }).catch(function (error) { fail(context, "import", "Import", error); });
            });
          }).catch(function (error) { fail(context, "import", "Import", error); });
        });
      }).catch(function (error) { fail(context, "import", "Import", error); });
    });
  }

  function renderFamily(context) {
    if (!gate(context, ["school.guardian.read", "school.student.read"], "Famille")) return;
    var form = '<form id="p2aFamilyForm"><label>Élève<input name="student_id" required></label>' + button("Lire la famille", "p2aFamily", "submit") + "</form>";
    var surface = show(context, shell("family", "Famille", form));
    on(surface, "p2aFamilyForm", "submit", function (event) {
      event.preventDefault();
      var studentId = field(event.target, "student_id");
      if (!isUuid(studentId)) {
        show(context, shell("family", "Famille", state("error", "Élève", "Identifiant élève invalide.")));
        return;
      }
      loadFamily(context, studentId);
    });
  }

  function loadFamily(context, studentId) {
    show(context, shell("family", "Famille", state("loading", "Chargement", "Lecture de la famille.")));
    var jobs = [request("GET", "/native/students/" + encodeURIComponent(studentId) + "/family")];
    if (!blocked(context, "school.student.read")) jobs.push(request("GET", "/native/students/" + encodeURIComponent(studentId) + "/emergency-contacts"));
    Promise.all(jobs).then(function (results) {
      var guardians = rowsOf(results[0]);
      var contacts = results[1] ? rowsOf(results[1]) : [];
      var canManage = !blocked(context, "school.guardian.manage");
      var guardianHtml = guardians.length ? guardians.map(function (person, index) {
        var title = person.is_primary ? "Parent principal" : "Autre tuteur";
        var actions = "";
        if (canManage) actions += button("Modifier", "p2aGuardianEdit" + index);
        if (canManage && !person.is_primary) actions += button("Définir comme parent principal", "p2aPrimary" + index);
        if (canManage) actions += button("Retirer le lien", "p2aDeactivate" + index);
        return "<article><h3>" + escapeHtml(title) + "</h3>" + facts([
          ["Nom", person.full_name],
          ["Type", person.guardian_type],
          ["Principal", person.is_primary ? "oui" : "non"],
          ["Actif", person.is_active ? "oui" : "non"]
        ]) + actions + "</article>";
      }).join("") : state("empty", "Aucun tuteur", "Aucun responsable familial actif.");
      var contactHtml = "<h3>Personnes liées</h3>" + (contacts.length ? contacts.map(function (person) {
        var slot = Number(person.slot_no);
        var edit = canManage && (slot === 1 || slot === 2) ? button("Modifier le créneau", "p2aEmergencyEdit" + slot) : "";
        return facts([["Nom", person.full_name], ["Lien", person.relation], ["Téléphone", person.phone], ["Créneau", person.slot_no]]) + edit;
      }).join("") : state("empty", "Aucune personne liée", "Aucun contact lié n’est enregistré."));
      var editor = canManage ? '<form id="p2aGuardianForm"><label>Type<select name="guardian_type"><option value="pere">pere</option><option value="mere">mere</option><option value="tuteur">tuteur</option></select></label><label>Nom<input name="full_name" required></label><label>Téléphone<input name="phone"></label><label>Email<input name="email"></label><label>Adresse<input name="address"></label>' + button("Ajouter le tuteur", "p2aGuardianAdd", "submit") + "</form>" : "";
      var next = show(context, shell("family", "Famille", guardianHtml + contactHtml + editor));
      guardians.forEach(function (person, index) {
        on(next, "p2aGuardianEdit" + index, "click", function () {
          var form = '<form id="p2aGuardianEditForm"><label>Type<select name="guardian_type"><option value="pere">pere</option><option value="mere">mere</option><option value="tuteur">tuteur</option></select></label><label>Nom<input name="full_name" required></label><label>Téléphone<input name="phone"></label><label>Email<input name="email"></label><label>Adresse<input name="address"></label>' + button("Enregistrer le tuteur", "p2aGuardianEditSave", "submit") + "</form>";
          var editor = show(context, shell("family", "Famille", form));
          on(editor, "p2aGuardianEditForm", "submit", function (event) {
            event.preventDefault();
            var payload = { guardian_type: field(event.target, "guardian_type"), full_name: field(event.target, "full_name") };
            ["phone", "email", "address"].forEach(function (name) {
              var value = field(event.target, name);
              if (value) payload[name] = value;
            });
            request("PATCH", "/native/students/" + encodeURIComponent(studentId) + "/guardians/" + encodeURIComponent(person.id), payload).then(function () {
              show(context, shell("family", "Famille", state("success", "Tuteur enregistré", person.full_name || "")));
            }).catch(function (error) { fail(context, "family", "Famille", error); });
          });
        });
        on(next, "p2aPrimary" + index, "click", function () {
          request("POST", "/native/students/" + encodeURIComponent(studentId) + "/guardians/" + encodeURIComponent(person.id) + "/set-primary").then(function () {
            show(context, shell("family", "Famille", state("success", "Parent principal enregistré", person.full_name || "")));
          }).catch(function (error) { fail(context, "family", "Famille", error); });
        });
        on(next, "p2aDeactivate" + index, "click", function () {
          var ready = show(context, shell("family", "Famille", state("unavailable", "Confirmation", "Retirer ce lien ?") + button("Confirmer le retrait", "p2aDeactivateYes")));
          on(ready, "p2aDeactivateYes", "click", function () {
            request("POST", "/native/students/" + encodeURIComponent(studentId) + "/guardians/" + encodeURIComponent(person.id) + "/deactivate").then(function () {
              show(context, shell("family", "Famille", state("success", "Lien retiré", "")));
            }).catch(function (error) { fail(context, "family", "Famille", error); });
          });
        });
      });
      contacts.forEach(function (person) {
        var slot = Number(person.slot_no);
        if (!canManage || (slot !== 1 && slot !== 2)) return;
        on(next, "p2aEmergencyEdit" + slot, "click", function () {
          var form = '<form id="p2aEmergencyForm"><label>Nom<input name="full_name" required></label><label>Lien<input name="relation" required></label><label>Téléphone<input name="phone" required></label><label>Autre téléphone<input name="alternate_phone"></label><label>Notes<input name="notes"></label>' + button("Enregistrer le contact", "p2aEmergencySave", "submit") + "</form>";
          var editor = show(context, shell("family", "Famille", form));
          on(editor, "p2aEmergencyForm", "submit", function (event) {
            event.preventDefault();
            var payload = {
              full_name: field(event.target, "full_name"),
              relation: field(event.target, "relation"),
              phone: field(event.target, "phone")
            };
            var alternate = field(event.target, "alternate_phone");
            var notes = field(event.target, "notes");
            if (alternate) payload.alternate_phone = alternate;
            if (notes) payload.notes = notes;
            request("PUT", "/native/students/" + encodeURIComponent(studentId) + "/emergency-contacts/" + slot, payload).then(function () {
              show(context, shell("family", "Famille", state("success", "Contact enregistré", "")));
            }).catch(function (error) { fail(context, "family", "Famille", error); });
          });
        });
      });
      on(next, "p2aGuardianForm", "submit", function (event) {
        event.preventDefault();
        var payload = { guardian_type: field(event.target, "guardian_type"), full_name: field(event.target, "full_name") };
        ["phone", "email", "address"].forEach(function (name) {
          var value = field(event.target, name);
          if (value) payload[name] = value;
        });
        request("POST", "/native/students/" + encodeURIComponent(studentId) + "/guardians", payload).then(function () {
          loadFamily(context, studentId);
        }).catch(function (error) { fail(context, "family", "Famille", error); });
      });
    }).catch(function (error) { fail(context, "family", "Famille", error); });
  }

  function renderStaff(context, teachersOnly) {
    if (!gate(context, "staff.manage", "Personnel")) return;
    show(context, shell("staff", "Personnel", state("loading", "Chargement", "Lecture du personnel.")));
    request("GET", "/school/staff", undefined, true).then(function (payload) {
      var people = rowsOf(payload).filter(function (person) {
        if (!teachersOnly) return true;
        return (person.roles || []).some(function (role) { return role && role.code === "teacher"; });
      });
      var body = people.length ? "<ul>" + people.map(function (person, index) {
        var label = person.display_name || [person.first_name, person.last_name].filter(Boolean).join(" ") || person.id;
        return "<li><b>" + escapeHtml(label) + "</b> " + button("Ouvrir la fiche", "p2aStaff" + index) + "</li>";
      }).join("") + "</ul>" : state("empty", "Aucun personnel", "La liste réelle est vide.");
      body += '<form id="p2aInviteForm"><label>Email<input name="email" required></label><label>Prénom<input name="first_name" required></label><label>Nom<input name="last_name" required></label><label>Rôle<input name="role_id" required></label>' + button("Inviter", "p2aInvite", "submit") + "</form>";
      var next = show(context, shell("staff", "Personnel", body));
      people.forEach(function (person, index) {
        on(next, "p2aStaff" + index, "click", function () { openStaff(context, person.id); });
      });
      on(next, "p2aInviteForm", "submit", function (event) {
        event.preventDefault();
        var roleId = field(event.target, "role_id");
        var payload = {
          email: field(event.target, "email"),
          first_name: field(event.target, "first_name"),
          last_name: field(event.target, "last_name"),
          role_ids: [roleId]
        };
        if (!payload.email || !payload.first_name || !payload.last_name || !isUuid(roleId)) {
          show(context, shell("staff", "Personnel", state("error", "Saisie", "Email, nom et rôle sont requis.")));
          return;
        }
        var ready = show(context, shell("staff", "Personnel", state("unavailable", "Confirmation", "Envoyer cette invitation ?") + button("Confirmer", "p2aInviteYes")));
        on(ready, "p2aInviteYes", "click", function () {
          request("POST", "/school/staff/invite", payload, true).then(function () {
            show(context, shell("staff", "Personnel", state("success", "Invitation envoyée", payload.email)));
          }).catch(function (error) { fail(context, "staff", "Personnel", error); });
        });
      });
    }).catch(function (error) { fail(context, "staff", "Personnel", error); });
  }

  function openStaff(context, profileId) {
    show(context, shell("staff", "Fiche personnel", state("loading", "Chargement", "Lecture de la fiche.")));
    request("GET", "/school/staff/" + encodeURIComponent(profileId), undefined, true).then(function (payload) {
      var person = objectOf(payload);
      var roles = (person.roles || []).map(function (role) { return role.label || role.code; }).filter(Boolean).join(", ");
      var scopes = (person.scopes || []).map(function (scope) { return scope.label || scope.scope_type; }).filter(Boolean).join(", ");
      var body = facts([
        ["Nom affiché", person.display_name],
        ["Prénom", person.first_name],
        ["Nom", person.last_name],
        ["Email", person.email],
        ["Téléphone", person.phone],
        ["Actif", person.is_active === true ? "oui" : person.is_active === false ? "non" : ""],
        ["Rôles", roles],
        ["Portées", scopes]
      ]) + (person.is_active === true || person.is_active === false ? button(person.is_active ? "Désactiver" : "Activer", "p2aToggle") : "");
      body += button("Renvoyer l'invitation", "p2aResend");
      body += '<form id="p2aRolesForm"><label>Rôle<input name="role_id" required></label>' + button("Enregistrer les rôles", "p2aRoles", "submit") + "</form>";
      var next = show(context, shell("staff", "Fiche personnel", body));
      on(next, "p2aResend", "click", function () {
        var ready = show(context, shell("staff", "Fiche personnel", state("unavailable", "Confirmation", "Renvoyer l'invitation ?") + button("Confirmer", "p2aResendYes")));
        on(ready, "p2aResendYes", "click", function () {
          request("POST", "/school/staff/" + encodeURIComponent(profileId) + "/resend-invite", {}, true).then(function () {
            show(context, shell("staff", "Fiche personnel", state("success", "Invitation renvoyée", "")));
          }).catch(function (error) { fail(context, "staff", "Fiche personnel", error); });
        });
      });
      on(next, "p2aRolesForm", "submit", function (event) {
        event.preventDefault();
        var roleId = field(event.target, "role_id");
        if (!isUuid(roleId)) {
          show(context, shell("staff", "Fiche personnel", state("error", "Saisie", "Le rôle doit être un identifiant du moteur.")));
          return;
        }
        var ready = show(context, shell("staff", "Fiche personnel", state("unavailable", "Confirmation", "Enregistrer ces rôles ?") + button("Confirmer", "p2aRolesYes")));
        on(ready, "p2aRolesYes", "click", function () {
          request("PUT", "/school/staff/" + encodeURIComponent(profileId) + "/roles", { role_ids: [roleId] }, true).then(function () {
            show(context, shell("staff", "Fiche personnel", state("success", "Rôles enregistrés", "")));
          }).catch(function (error) { fail(context, "staff", "Fiche personnel", error); });
        });
      });
      on(next, "p2aToggle", "click", function () {
        var ready = show(context, shell("staff", "Fiche personnel", state("unavailable", "Confirmation", "Confirmer le changement de statut.") + button("Confirmer", "p2aToggleYes")));
        on(ready, "p2aToggleYes", "click", function () {
          request("POST", "/school/staff/" + encodeURIComponent(profileId) + "/toggle", { is_active: person.is_active !== true }, true).then(function () {
            show(context, shell("staff", "Fiche personnel", state("success", "Statut enregistré", "")));
          }).catch(function (error) { fail(context, "staff", "Fiche personnel", error); });
        });
      });
    }).catch(function (error) { fail(context, "staff", "Fiche personnel", error); });
  }

  function renderClassList(context) {
    if (!gate(context, "school.class.read", "Classes")) return;
    show(context, shell("classes", "Classes", state("loading", "Chargement", "Lecture des classes.")));
    request("GET", "/native/pedagogy/classes").then(function (payload) {
      var rows = rowsOf(payload);
      var body = rows.length ? "<ul>" + rows.map(function (row, index) {
        return "<li>" + button(row.name || row.id, "p2aClass" + index) + "</li>";
      }).join("") + "</ul>" : state("empty", "Aucune classe", "La structure ne contient encore aucune classe.");
      var next = show(context, shell("classes", "Classes", body));
      rows.forEach(function (row, index) {
        on(next, "p2aClass" + index, "click", function () { loadClass(context, row); });
      });
    }).catch(function (error) { fail(context, "classes", "Classes", error); });
  }

  function renderNamedClass(context, className) {
    if (!gate(context, "school.class.read", className)) return;
    show(context, shell("class", className, state("loading", "Chargement", "Lecture de la classe.")));
    request("GET", "/native/pedagogy/classes").then(function (payload) {
      var found = rowsOf(payload).filter(function (row) { return row.name === className; })[0];
      if (!found) {
        show(context, shell("class", className, state("empty", "Aucune classe", "Aucune classe ne porte ce nom.")));
        return;
      }
      loadClass(context, found);
    }).catch(function (error) { fail(context, "class", className, error); });
  }

  function loadClass(context, classRow) {
    var jobs = [];
    if (!blocked(context, "school.student.read")) {
      jobs.push(request("GET", "/native/students?status=active&class_id=" + encodeURIComponent(classRow.id) + "&limit=50&offset=0").then(function (payload) {
        return { kind: "students", rows: rowsOf(payload) };
      }));
    }
    if (!blocked(context, "school.structure.manage")) {
      jobs.push(request("GET", "/native/pedagogy/teacher-assignments").then(function (payload) {
        return { kind: "teachers", rows: rowsOf(payload).filter(function (row) { return row.class_id === classRow.id; }) };
      }));
    }
    show(context, shell("class", classRow.name || "Classe", state("loading", "Chargement", "Lecture de la classe.")));
    Promise.all(jobs).then(function (parts) {
      var students = [];
      var teachers = [];
      parts.forEach(function (part) {
        if (part.kind === "students") students = part.rows;
        if (part.kind === "teachers") teachers = part.rows;
      });
      var body = '<h2 id="p2aClassTitle">' + escapeHtml(classRow.name || "") + "</h2>" + facts([
        ["Nom", classRow.name],
        ["Année", classRow.academic_year_id],
        ["Cycle", classRow.cycle_key],
        ["Option", classRow.option],
        ["Active", classRow.is_active === true ? "oui" : classRow.is_active === false ? "non" : ""]
      ]);
      body += "<h3>Élèves</h3>" + (students.length ? "<ul>" + students.map(function (row) {
        return "<li>" + escapeHtml([row.first_name, row.last_name].filter(Boolean).join(" ")) + " <small>" + escapeHtml(row.matricule || "") + "</small></li>";
      }).join("") + "</ul>" : state("empty", "Aucun élève", "Aucun élève actif dans cette classe."));
      if (!blocked(context, "school.structure.manage")) {
        body += "<h3>Enseignants</h3>" + (teachers.length ? "<ul>" + teachers.map(function (row) {
          return "<li>" + escapeHtml(row.teacher_name || row.profile_id || "") + " <small>" + escapeHtml(row.subject_name || "") + "</small> <span>" + escapeHtml(assignmentRole(row)) + "</span></li>";
        }).join("") + "</ul>" : state("empty", "Aucun enseignant", "Aucune affectation pour cette classe."));
      }
      show(context, shell("class", classRow.name || "Classe", body));
    }).catch(function (error) { fail(context, "class", classRow.name || "Classe", error); });
  }

  function renderAssignments(context) {
    if (!gate(context, "school.structure.manage", "Affectations enseignants")) return;
    show(context, shell("assignments", "Affectations enseignants", state("loading", "Chargement", "Lecture des affectations.")));
    request("GET", "/native/pedagogy/teacher-assignments").then(function (payload) {
      paintAssignments(context, rowsOf(payload), "");
    }).catch(function (error) { fail(context, "assignments", "Affectations enseignants", error); });
  }

  function assignmentRole(row) {
    return row.is_tutor === true ? "Titulaire de la classe" : "Enseignant affecté";
  }

  function paintAssignments(context, rows, notice) {
    var list = rows.length ? "<ul>" + rows.map(function (row, index) {
      var label = row.teacher_name || row.profile_id || row.id;
      var detail = [row.class_name, row.subject_name].filter(Boolean).join(" · ");
      return "<li><b>" + escapeHtml(label) + "</b> <small>" + escapeHtml(detail) + "</small> <span>" + escapeHtml(assignmentRole(row)) + "</span> " + button("Supprimer", "p2aDelete" + index) + "</li>";
    }).join("") + "</ul>" : state("empty", "Aucune affectation", "Aucune affectation enseignant n’est enregistrée.");
    var form = '<form id="p2aAssignmentForm"><label>Profil<input name="profile_id" required></label><label>Matière<input name="subject_id" required></label><label>Classe<input name="class_id" required></label><label>Année<input name="academic_year_id" required></label>' + button("Créer l’affectation", "p2aAssignment", "submit") + "</form>";
    form += '<form id="p2aHomeroomForm"><label>Profil<input name="profile_id" required></label><label>Matière<input name="subject_id" required></label><label>Classe<input name="class_id" required></label><label>Année<input name="academic_year_id" required></label>' + button("Définir le titulaire", "p2aHomeroom", "submit") + "</form>";
    var next = show(context, shell("assignments", "Affectations enseignants", (notice || "") + list + form));
    rows.forEach(function (row, index) {
      on(next, "p2aDelete" + index, "click", function () {
        var ready = show(context, shell("assignments", "Affectations enseignants", state("unavailable", "Confirmation", "Supprimer cette affectation ?") + button("Confirmer la suppression", "p2aDeleteYes")));
        on(ready, "p2aDeleteYes", "click", function () {
          request("DELETE", "/native/pedagogy/teacher-assignments/" + encodeURIComponent(row.id)).then(function () {
            show(context, shell("assignments", "Affectations enseignants", state("success", "Affectation supprimée", "")));
          }).catch(function (error) { fail(context, "assignments", "Affectations enseignants", error); });
        });
      });
    });
    on(next, "p2aAssignmentForm", "submit", function (event) {
      event.preventDefault();
      var payload = {
        profile_id: field(event.target, "profile_id"),
        subject_id: field(event.target, "subject_id"),
        class_id: field(event.target, "class_id"),
        academic_year_id: field(event.target, "academic_year_id")
      };
      if (!isUuid(payload.profile_id) || !isUuid(payload.subject_id) || !isUuid(payload.class_id) || !isUuid(payload.academic_year_id)) {
        paintAssignments(context, rows, state("error", "Identifiant", "Les quatre identifiants doivent être ceux du moteur."));
        return;
      }
      request("POST", "/native/pedagogy/teacher-assignments", payload).then(function (result) {
        var created = objectOf(result);
        show(context, shell("assignments", "Affectations enseignants", state("success", "Affectation créée", created.id || "")));
      }).catch(function (error) { fail(context, "assignments", "Affectations enseignants", error); });
    });
    on(next, "p2aHomeroomForm", "submit", function (event) {
      event.preventDefault();
      var payload = {
        profile_id: field(event.target, "profile_id"),
        subject_id: field(event.target, "subject_id"),
        class_id: field(event.target, "class_id"),
        academic_year_id: field(event.target, "academic_year_id")
      };
      if (!isUuid(payload.profile_id) || !isUuid(payload.subject_id) || !isUuid(payload.class_id) || !isUuid(payload.academic_year_id)) {
        paintAssignments(context, rows, state("error", "Identifiant", "Les quatre identifiants doivent être ceux du moteur."));
        return;
      }
      request("POST", "/native/pedagogy/teacher-assignments/homeroom", payload).then(function (result) {
        var created = objectOf(result);
        return request("GET", "/native/pedagogy/teacher-assignments").then(function (payload) {
          paintAssignments(context, rowsOf(payload), state("success", "Enregistrement renvoyé", created.id || ""));
        });
      }).catch(function (error) { fail(context, "assignments", "Affectations enseignants", error); });
    });
  }

  function renderApprovals(context) {
    if (!gate(context, "pilotage.approvals.read", "Approbations")) return;
    show(context, shell("approvals", "Approbations", state("loading", "Chargement", "Lecture des approbations.")));
    request("GET", "/pilotage/approvals?limit=20&offset=0", undefined, true).then(function (payload) {
      var rows = rowsOf(payload);
      var body = rows.length ? "<ul>" + rows.map(function (row, index) {
        return "<li><b>" + escapeHtml(row.request_type || row.id) + "</b> <small>" + escapeHtml(row.status || "") + "</small> " + button("Ouvrir", "p2aApproval" + index) + "</li>";
      }).join("") + "</ul>" : state("empty", "Aucune approbation", "Aucune demande n’est enregistrée.");
      var next = show(context, shell("approvals", "Approbations", body));
      rows.forEach(function (row, index) {
        on(next, "p2aApproval" + index, "click", function () { openApproval(context, row); });
      });
    }).catch(function (error) { fail(context, "approvals", "Approbations", error); });
  }

  function openApproval(context, row) {
    var canDecide = !blocked(context, "pilotage.approvals.manage") && row.status === "pending";
    var body = facts([
      ["Type", row.request_type],
      ["Entité", row.entity_type],
      ["Identifiant", row.entity_id],
      ["Statut", row.status],
      ["Motif", row.reason],
      ["Demandeur", row.requester && row.requester.display_name],
      ["Dossier", row.payload ? JSON.stringify(row.payload) : ""]
    ]);
    if (canDecide) {
      body += '<label>Motif de décision<input id="p2aReason" name="reason"></label>' + button("Approuver", "p2aApprove") + button("Refuser", "p2aReject");
    }
    var surface = show(context, shell("approvals", "Approbation", body));
    function arm(decision) {
      var reasonNode = document.getElementById("p2aReason");
      var reason = reasonNode ? String(reasonNode.value || "").trim() : "";
      var ready = show(context, shell("approvals", "Approbation", state("unavailable", "Confirmation", "Confirmer la décision " + decision + ".") + button("Confirmer", "p2aDecideYes")));
      on(ready, "p2aDecideYes", "click", function () {
        var payload = { decision: decision };
        if (reason) payload.reason = reason;
        request("POST", "/pilotage/approvals/" + encodeURIComponent(row.id) + "/decide", payload, true).then(function (result) {
          var decided = objectOf(result);
          show(context, shell("approvals", "Approbation", state("success", "Décision enregistrée", decided.status || "")));
        }).catch(function (error) { fail(context, "approvals", "Approbation", error); });
      });
    }
    on(surface, "p2aApprove", "click", function () { arm("approved"); });
    on(surface, "p2aReject", "click", function () { arm("rejected"); });
  }

  function open(branchKey, actionName, context) {
    var screen = SCREENS[branchKey + "|" + actionName];
    if (!screen) return false;
    context = context || {};
    if (screen === "drafts") renderStudents(context, "Préinscriptions", "draft", false);
    else if (screen === "active") renderStudents(context, "Élèves actifs", "active", false);
    else if (screen === "decision") renderStudents(context, "Dossiers", "draft", true);
    else if (screen === "create") renderCreate(context);
    else if (screen === "import") renderImport(context);
    else if (screen === "family") renderFamily(context);
    else if (screen === "staff") renderStaff(context, actionName === "Enseignants");
    else if (screen === "classes") renderClassList(context);
    else if (screen === "class") renderNamedClass(context, actionName);
    else if (screen === "assignments") renderAssignments(context);
    else if (screen === "approvals") renderApprovals(context);
    else return false;
    return true;
  }

  root.SchoolSafeVisualOutputP2A = { open: open };
})(window);
