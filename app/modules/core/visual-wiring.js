(function (root) {
  "use strict";

  var VISUAL_WIRING = {
    "school|Import massif": "students-import",
    "school|Créer l’élève": "students-create",
    "school|Vérifier les dossiers": "students-drafts",
    "school|Accepter ou refuser": "students-drafts",
    "school|Préinscriptions": "students-drafts",
    "school|Inscriptions": "students-active",
    "school|Inscriptions et réinscriptions": "students-active",
    "school|Parent principal et tuteurs": "guardians",
    "school|Parents et tuteurs": "guardians",
    "school|Affectations": "classes",
    "school|Importer des dossiers": "students-import",
    "school|Présence et passages": "events",
    "school|Emploi du temps": "gap",
    "school|Justifier une absence": "gap",
    "school|Documents": "documents",
    "people|Enseignants": "staff",
    "people|Personnel": "staff",
    "pedagogy|6e A": "classes",
    "pedagogy|5e A": "classes",
    "pedagogy|Classes": "classes",
    "pedagogy|Affectations": "teacher-assignments",
    "pilotage|Approbations": "approvals",
    "security|Rechercher": "events",
    "administration|Documents R2": "documents",
    "administration|Archives": "documents",
    "administration|Mode hors ligne": "sync",
    "administration|Attestations": "gap",
    "administration|Certificats": "gap",
    "administration|Convocations": "gap",
    "administration|Dossiers ENAFEP, TENASOSP et EXETAT": "gap",
    "administration|Contrôle des identités candidats": "gap",
    "administration|Rechercher": "students-search",
    "administration|Français et anglais": "gap",
    "administration|Séparation public et privé": "gap",
    "security|Lockdown": "lockdown",
    "security|Recherche de passage": "events",
    "school|Personnes autorisées de mes enfants": "authorized-persons"
  };

  var MODULE_IDS = [
    "pedagogyModule", "palmaresModule", "financeModule", "accountingModule", "hrModule",
    "inventoryModule", "communicationModule", "documentCenterModule", "administrationModule",
    "securityModule", "feeControlModule", "reportsModule", "pilotageModule", "accessConsole",
    "schoolModule", "cardsProtected", "cardsStudio"
  ];

  function allowed(context, codes) {
    var list = Array.isArray(codes) ? codes : [codes];
    var access = root.SchoolSafeAccess;
    var user = (context && context.user) || {};
    if (access && typeof access.explicitDeny === "function" && list.every(function (code) { return access.explicitDeny(user, code); })) return false;
    if (!context || !context.live) return true;
    if (!access || typeof access.canAccess !== "function") return false;
    return list.some(function (code) { return access.canAccess(user, code) && !access.explicitDeny(user, code); });
  }

  function escapeHtml(value) {
    return String(value == null ? "" : value).replace(/[&<>"']/g, function (character) {
      return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[character];
    });
  }

  function stateHtml(type, title, message) {
    if (typeof root.ssState === "function") return root.ssState({ type: type, title: title, message: message });
    return "<p><b>" + escapeHtml(title) + "</b></p><p>" + escapeHtml(message) + "</p>";
  }

  function hideModules() {
    MODULE_IDS.forEach(function (id) {
      var element = document.getElementById(id);
      if (element) element.hidden = true;
    });
  }

  function showSurface(context, html) {
    var host = document.getElementById("wiringSurface");
    if (!host) return null;
    hideModules();
    if (context && typeof context.hideDashboard === "function") context.hideDashboard();
    host.hidden = false;
    host.innerHTML = '<button type="button" class="ss-button ss-button--secondary" id="wiringBack">Retour</button>' + html;
    var back = document.getElementById("wiringBack");
    if (back) back.addEventListener("click", function () {
      host.hidden = true;
      host.innerHTML = "";
      if (context && typeof context.restoreDashboard === "function") context.restoreDashboard();
    });
    return host;
  }

  function showDenied(context, permission) {
    showSurface(context, stateHtml("error", "Accès refusé", "Cette action est hors permission ou explicitement refusée (" + permission + ")."));
  }

  function showGap(context, actionName) {
    showSurface(context, stateHtml("unavailable", "BACKEND_GAP", actionName + " n’a pas de moteur SchoolSafe. Aucun écran de substitution n’est ouvert."));
  }

  function requireLive(context, title) {
    if (context && context.live) return true;
    showSurface(context, stateHtml("unavailable", title, "Session réelle requise. Aucune donnée de démonstration n’est affichée."));
    return false;
  }

  function openStudents(context, options) {
    if (!allowed(context, options.permission)) {
      showDenied(context, [].concat(options.permission).join(", "));
      return true;
    }
    if (typeof context.openSchool === "function") {
      context.openSchool("students", {
        exact: true,
        status: options.status || "",
        intent: options.intent || "",
        family: !!options.family
      });
    }
    return true;
  }

  function openClasses(context) {
    var codes = ["school.class.read", "school.structure.manage", "pedagogy.assignment.read"];
    if (!allowed(context, codes)) {
      showDenied(context, codes.join(", "));
      return true;
    }
    var structure = root.SchoolSafeAcademicStructure;
    if (structure && typeof structure.canRead === "function" && structure.canRead(context.user || {}) && typeof context.openSchool === "function") {
      context.openSchool("structure", { exact: true });
      return true;
    }
    renderClassList(context);
    return true;
  }

  function renderClassList(context) {
    if (!requireLive(context, "Classes")) return;
    showSurface(context, stateHtml("loading", "Classes", "Lecture des classes réelles."));
    var api = root.SchoolSafePedagogyAPI;
    if (!api || typeof api.listClasses !== "function") {
      showSurface(context, stateHtml("error", "Classes", "Le client des classes est indisponible."));
      return;
    }
    api.listClasses().then(function (rows) {
      var list = Array.isArray(rows) ? rows : [];
      var body = list.map(function (row) {
        return "<li><b>" + escapeHtml(row.name || row.label || row.id) + "</b> <small>" + escapeHtml(row.cycle_key || row.cycle || "") + "</small></li>";
      }).join("");
      showSurface(context, '<section data-wiring-target="classes"><h2>Classes</h2>' + (body ? "<ul>" + body + "</ul>" : stateHtml("empty", "Aucune classe", "La structure ne contient encore aucune classe.")) + "</section>");
    }).catch(function (error) {
      showSurface(context, stateHtml("error", "Classes", error.message));
    });
  }

  function renderTeacherAssignments(context) {
    if (!allowed(context, "pedagogy.assignment.read")) {
      showDenied(context, "pedagogy.assignment.read");
      return;
    }
    if (!requireLive(context, "Affectations enseignants")) return;
    showSurface(context, stateHtml("loading", "Affectations", "Lecture des affectations enseignants."));
    var api = root.SchoolSafePedagogyAPI;
    if (!api || typeof api.listTeacherAssignments !== "function") {
      showSurface(context, stateHtml("error", "Affectations", "Le client des affectations est indisponible."));
      return;
    }
    api.listTeacherAssignments().then(function (rows) {
      var list = Array.isArray(rows) ? rows : [];
      var body = list.map(function (row) {
        var label = row.teacher_name || row.teacher || row.profile_name || row.id;
        var detail = [row.class_name || row.class, row.subject_name || row.subject].filter(Boolean).join(" · ");
        return "<li><b>" + escapeHtml(label) + "</b> <small>" + escapeHtml(detail) + "</small></li>";
      }).join("");
      showSurface(context, '<section data-wiring-target="teacher-assignments"><h2>Affectations enseignants</h2>' + (body ? "<ul>" + body + "</ul>" : stateHtml("empty", "Aucune affectation", "Aucune affectation enseignant n’est enregistrée.")) + "</section>");
    }).catch(function (error) {
      showSurface(context, stateHtml("error", "Affectations", error.message));
    });
  }

  function renderApprovals(context) {
    if (!allowed(context, "pilotage.approvals.read")) {
      showDenied(context, "pilotage.approvals.read");
      return;
    }
    if (!requireLive(context, "Approbations")) return;
    showSurface(context, stateHtml("loading", "Approbations", "Lecture des demandes d’approbation."));
    var api = root.SchoolSafePilotageAPI;
    if (!api || typeof api.listApprovals !== "function") {
      showSurface(context, stateHtml("error", "Approbations", "Le client des approbations est indisponible."));
      return;
    }
    var canDecide = allowed(context, "pilotage.approvals.manage");
    api.listApprovals({ limit: 20, offset: 0 }).then(function (result) {
      var list = result && Array.isArray(result.data) ? result.data : (Array.isArray(result) ? result : []);
      var body = list.map(function (row, index) {
        var label = row.title || row.subject || row.type || row.kind || row.id;
        var actions = canDecide
          ? '<input id="wiringApprovalReason' + index + '" type="text" maxlength="1000" placeholder="Motif de décision">' +
            '<button type="button" data-approval-index="' + index + '" data-approval-id="' + escapeHtml(row.id) + '" data-approval-decision="approved">Approuver</button>' +
            '<button type="button" data-approval-index="' + index + '" data-approval-id="' + escapeHtml(row.id) + '" data-approval-decision="rejected">Refuser</button>'
          : "";
        return "<li><b>" + escapeHtml(label) + "</b> <small>" + escapeHtml(row.status || "") + "</small>" + actions + "</li>";
      }).join("");
      showSurface(context, '<section data-wiring-target="approvals"><h2>Approbations</h2><div id="wiringApprovalStatus"></div>' + (body ? "<ul>" + body + "</ul>" : stateHtml("empty", "Aucune approbation", "Aucune demande en attente.")) + "</section>");
      if (!canDecide) return;
      document.querySelectorAll("[data-approval-id]").forEach(function (button) {
        button.addEventListener("click", function () {
          var status = document.getElementById("wiringApprovalStatus");
          var reasonInput = document.getElementById("wiringApprovalReason" + button.getAttribute("data-approval-index"));
          if (status) status.innerHTML = stateHtml("loading", "Décision", "Envoi de la décision.");
          api.decideApproval(button.getAttribute("data-approval-id"), button.getAttribute("data-approval-decision"), reasonInput ? reasonInput.value : "").then(function () {
            if (status) status.innerHTML = stateHtml("success", "Décision enregistrée", "L’approbation a été mise à jour.");
          }).catch(function (error) {
            if (status) status.innerHTML = stateHtml("error", "Décision refusée", error.message);
          });
        });
      });
    }).catch(function (error) {
      showSurface(context, stateHtml("error", "Approbations", error.message));
    });
  }

  function limitedToOwnChildren(context, permission) {
    var access = root.SchoolSafeAccess;
    var user = (context && context.user) || {};
    if (!context || !context.live || !access || typeof access.allowsScope !== "function") return false;
    return access.allowsScope(user, permission, "own_children") && !access.allowsScope(user, permission, "school");
  }

  function renderEvents(context) {
    if (!allowed(context, "security.events.read")) {
      showDenied(context, "security.events.read");
      return;
    }
    showSurface(context, '<section data-wiring-target="events"><h2>Recherche de passage</h2><form id="wiringEventForm"><label>Recherche<input id="wiringEventSearch" type="search" placeholder="Nom, type ou référence"></label><button type="submit">Rechercher</button></form><div id="wiringEventResults"></div></section>');
    var form = document.getElementById("wiringEventForm");
    if (!form) return;
    form.addEventListener("submit", function (event) {
      event.preventDefault();
      loadEvents(context);
    });
    if (context && context.live) loadEvents(context);
    else {
      var results = document.getElementById("wiringEventResults");
      if (results) results.innerHTML = stateHtml("unavailable", "Recherche de passage", "Session réelle requise. Le scanner n’est pas ouvert.");
    }
  }

  function loadEvents(context) {
    var results = document.getElementById("wiringEventResults");
    var query = (document.getElementById("wiringEventSearch") || {}).value || "";
    if (!context || !context.live) {
      if (results) results.innerHTML = stateHtml("unavailable", "Recherche de passage", "Session réelle requise.");
      return;
    }
    var api = root.SchoolSafeSecurityAPI;
    if (!api || typeof api.listEvents !== "function") {
      if (results) results.innerHTML = stateHtml("error", "Recherche de passage", "Le registre des passages est indisponible.");
      return;
    }
    if (results) results.innerHTML = stateHtml("loading", "Recherche", "Lecture de l’historique des passages.");
    api.listEvents({ limit: 50, offset: 0 }).then(function (result) {
      var rows = result && Array.isArray(result.data) ? result.data : [];
      if (limitedToOwnChildren(context, "security.events.read")) {
        var childIds = (context.user && context.user.childIds) || [];
        rows = rows.filter(function (row) {
          var studentId = row.student_id || row.studentId;
          return studentId && childIds.indexOf(studentId) >= 0;
        });
      }
      var needle = String(query).trim().toLowerCase();
      if (needle) {
        rows = rows.filter(function (row) { return JSON.stringify(row).toLowerCase().indexOf(needle) >= 0; });
      }
      if (!rows.length) {
        if (results) results.innerHTML = stateHtml("empty", "Aucun passage", "Aucun événement ne correspond à cette recherche.");
        return;
      }
      if (results) {
        results.innerHTML = "<ul>" + rows.map(function (row) {
          return "<li><b>" + escapeHtml(row.event_type || row.type || "passage") + "</b> <small>" + escapeHtml(row.created_at || row.occurred_at || row.student_id || "") + "</small></li>";
        }).join("") + "</ul>";
      }
    }).catch(function (error) {
      if (results) results.innerHTML = stateHtml("error", "Recherche de passage", error.message);
    });
  }

  function renderLockdown(context) {
    if (!allowed(context, "security.lockdown.manage")) {
      showDenied(context, "security.lockdown.manage");
      return;
    }
    showSurface(context, '<section data-wiring-target="lockdown"><h2>Lockdown</h2><p>Cette surface prépare le confinement. L’ouverture ne l’active pas.</p><button type="button" data-lockdown-active="true">Activer le lockdown</button><button type="button" data-lockdown-active="false">Lever le lockdown</button><div id="wiringLockdownStatus"></div></section>');
    document.querySelectorAll("[data-lockdown-active]").forEach(function (button) {
      button.addEventListener("click", function () {
        // LOCKDOWN_ON_CONFIRM_ONLY
        var active = button.getAttribute("data-lockdown-active") === "true";
        var status = document.getElementById("wiringLockdownStatus");
        var api = root.SchoolSafeSecurityAPI;
        if (!context || !context.live || !api || typeof api.setLockdown !== "function") {
          if (status) status.innerHTML = stateHtml("unavailable", "Lockdown", "Session réelle requise. Aucun confinement n’a été envoyé.");
          return;
        }
        if (status) status.innerHTML = stateHtml("loading", "Lockdown", active ? "Activation demandée." : "Levée demandée.");
        api.setLockdown(active).then(function () {
          if (status) status.innerHTML = stateHtml("success", "Lockdown mis à jour", active ? "Le confinement est actif." : "Le confinement est levé.");
        }).catch(function (error) {
          if (status) status.innerHTML = stateHtml("error", "Lockdown", error.message);
        });
      });
    });
  }

  function bindParentPickupActions() {
    var surface = document.querySelector("[data-wiring-target='authorized-persons']");
    if (!surface) return;
    var apiBase = root.schoolSafeApiBase || "http://127.0.0.1:8787";
    surface.querySelectorAll("[data-parent-suspend], [data-parent-request]").forEach(function (button) {
      button.addEventListener("click", function () {
        var kind = button.getAttribute("data-parent-kind");
        var id = button.getAttribute("data-parent-suspend") || button.getAttribute("data-parent-request");
        var request = button.hasAttribute("data-parent-request");
        var reason = window.prompt(request ? "Motif de la demande" : "Motif de la suspension");
        if (!reason || reason.trim().length < 3) return;
        button.disabled = true;
        var path = kind === "family"
          ? "/native/family/guardians/" + encodeURIComponent(id) + (request ? "/reactivation-request" : "/parent-suspend")
          : "/native/family/pickup-authorizations/" + encodeURIComponent(id) + (request ? "/reactivation-request" : "/parent-suspend");
        fetch(apiBase + path, {
          method: "POST",
          credentials: "include",
          headers: { Accept: "application/json", "Content-Type": "application/json" },
          body: JSON.stringify({ reason: reason.trim() })
        }).then(function (response) {
          if (!response.ok) throw new Error("Le serveur a refusé l'opération.");
          return response.json();
        }).then(function () {
          button.textContent = request ? "Demande envoyée" : "Suspension enregistrée";
        }).catch(function (error) {
          button.disabled = false;
          window.alert(error.message);
        });
      });
    });
  }

  function renderAuthorizedPersons(context) {
    if (!allowed(context, "security.pickup.read")) {
      showDenied(context, "security.pickup.read");
      return;
    }
    var access = root.SchoolSafeAccess;
    var user = (context && context.user) || {};
    if (context && context.live && access && typeof access.allowsScope === "function" && !access.allowsScope(user, "security.pickup.read", "own_children") && !access.allowsScope(user, "security.pickup.read", "school")) {
      showDenied(context, "security.pickup.read");
      return;
    }
    if (!requireLive(context, "Personnes autorisées")) return;
    showSurface(context, stateHtml("loading", "Personnes autorisées", "Lecture des enfants rattachés."));
    var pedagogy = root.SchoolSafePedagogyAPI;
    if (!pedagogy || typeof pedagogy.getParentChildren !== "function") {
      showSurface(context, stateHtml("error", "Personnes autorisées", "Le dossier familial est indisponible."));
      return;
    }
    pedagogy.getParentChildren().then(function (payload) {
      var children = Array.isArray(payload) ? payload : (payload && (payload.children || payload.students)) || [];
      if (limitedToOwnChildren(context, "security.pickup.read") || (user.childIds && user.childIds.length)) {
        var ids = user.childIds || [];
        if (ids.length) {
          children = children.filter(function (child) {
            var id = child.id || child.student_id;
            return ids.indexOf(id) >= 0;
          });
        }
      }
      if (!children.length) {
        showSurface(context, stateHtml("empty", "Aucun enfant", "Aucun enfant rattaché n’est visible dans votre portée."));
        return;
      }
      return Promise.all(children.map(function (child) {
        var id = child.id || child.student_id;
        var apiBase = root.schoolSafeApiBase || "http://127.0.0.1:8787";
        return fetch(apiBase + "/native/family/students/" + encodeURIComponent(id) + "/pickup-authorizations/mine", {
          credentials: "include",
          headers: { Accept: "application/json" }
        }).then(function (response) {
          if (!response.ok) throw new Error("Lecture des personnes autorisées impossible.");
          return response.json();
        }).then(function (body) {
          return { child: child, data: body && body.data ? body.data : {} };
        });
      })).then(function (groups) {
        var html = groups.map(function (group) {
          var child = group.child;
          var name = child.name || [child.first_name, child.last_name].filter(Boolean).join(" ") || child.id;
          var people = [].concat(group.data.family || [], group.data.accredited || []);
          var items = people.map(function (person) {
            var id = person.authorization_id || person.guardian_id;
            var kind = person.authorization_id ? "authorization" : "family";
            var photo = person.photo_path && String(person.photo_path).indexOf("/uploads/authorized-persons/") === 0
              ? '<img alt="" src="' + escapeHtml((root.schoolSafeApiBase || "http://127.0.0.1:8787") + "/native/family/portrait-file/" + encodeURIComponent(String(person.photo_path).split("/").pop())) + '" style="width:72px;height:auto">'
              : "<small>Photo absente</small>";
            var actions = "";
            if (person.status === "active" || (kind === "family" && person.authorized)) {
              actions += '<button type="button" data-parent-suspend="' + escapeHtml(id) + '" data-parent-kind="' + kind + '">Suspendre</button>';
            }
            if (person.status === "suspended" || (kind === "family" && person.authorized === false && person.pickup_hold !== "reactivation_pending")) {
              actions += '<button type="button" data-parent-request="' + escapeHtml(id) + '" data-parent-kind="' + kind + '">Demander la réactivation</button>';
            }
            if (person.status === "reactivation_pending" || person.pickup_hold === "reactivation_pending") {
              actions += "<small>Demande en attente de décision scolaire. Aucun droit de récupération.</small>";
            }
            return "<li><b>" + escapeHtml(person.name || person.full_name) + "</b> <small>" + escapeHtml(person.type || person.status || "") +
              (person.pickup_ready ? " · remise possible" : " · remise refusée") + "</small><br>" + photo + actions + "</li>";
          }).join("");
          return "<section data-parent-child=\"" + escapeHtml(child.id || child.student_id) + "\"><h3>" + escapeHtml(name) + "</h3>" + (items ? "<ul>" + items + "</ul>" : "<p>Aucune personne autorisée.</p>") + "</section>";
        }).join("");
        showSurface(context, '<section data-wiring-target="authorized-persons"><h2>Personnes autorisées</h2>' + html + "</section>");
        bindParentPickupActions();
      });
    }).catch(function (error) {
      showSurface(context, stateHtml("error", "Personnes autorisées", error.message));
    });
  }

  function open(branchKey, actionName, context) {
    if (root.SchoolSafeVisualOutputP1 && root.SchoolSafeVisualOutputP1.open(branchKey, actionName, context)) return true;
    if (root.SchoolSafeVisualOutputP2A && root.SchoolSafeVisualOutputP2A.open(branchKey, actionName, context)) return true;
    if (root.SchoolSafeVisualOutputP2B && root.SchoolSafeVisualOutputP2B.open(branchKey, actionName, context)) return true;
    if (root.SchoolSafeVisualOutputP2C && root.SchoolSafeVisualOutputP2C.open(branchKey, actionName, context)) return true;
    if (root.SchoolSafeVisualOutputClosureA && root.SchoolSafeVisualOutputClosureA.open(branchKey, actionName, context)) return true;
    if (root.SchoolSafeVisualOutputClosureB1 && root.SchoolSafeVisualOutputClosureB1.open(branchKey, actionName, context)) return true;
    var target = VISUAL_WIRING[branchKey + "|" + actionName];
    if (!target) return false;
    context = context || {};
    if (target === "gap") {
      showGap(context, actionName);
      return true;
    }
    if (target === "students-import") return openStudents(context, { permission: "school.student.create", intent: "import" });
    if (target === "students-create") return openStudents(context, { permission: "school.student.create", intent: "create" });
    if (target === "students-drafts") return openStudents(context, { permission: "school.student.read", status: "draft" });
    if (target === "students-active") return openStudents(context, { permission: "school.student.read", status: "active" });
    if (target === "students-search") return openStudents(context, { permission: "school.student.read", intent: "search" });
    if (target === "guardians") {
      if (!allowed(context, "school.guardian.read") || !allowed(context, "school.student.read")) {
        showDenied(context, "school.guardian.read");
        return true;
      }
      return openStudents(context, { permission: ["school.guardian.read", "school.student.read"], family: true });
    }
    if (target === "staff") {
      if (!allowed(context, ["staff.read", "staff.manage", "school.guardian.manage"])) {
        showDenied(context, "staff.read");
        return true;
      }
      if (typeof context.openSchool === "function") context.openSchool("staff", { exact: true });
      return true;
    }
    if (target === "classes") return openClasses(context);
    if (target === "teacher-assignments") {
      renderTeacherAssignments(context);
      return true;
    }
    if (target === "approvals") {
      renderApprovals(context);
      return true;
    }
    if (target === "events") {
      renderEvents(context);
      return true;
    }
    if (target === "documents") {
      if (!allowed(context, ["file.download", "file.upload"])) {
        showDenied(context, "file.download");
        return true;
      }
      if (typeof context.openDocuments === "function") context.openDocuments();
      return true;
    }
    if (target === "sync") {
      if (!allowed(context, "sync.submit")) {
        showDenied(context, "sync.submit");
        return true;
      }
      if (typeof context.openSync === "function") context.openSync();
      return true;
    }
    if (target === "lockdown") {
      renderLockdown(context);
      return true;
    }
    if (target === "authorized-persons") {
      renderAuthorizedPersons(context);
      return true;
    }
    showGap(context, actionName);
    return true;
  }

  root.SchoolSafeVisualWiring = { MAP: VISUAL_WIRING, open: open };
})(window);
