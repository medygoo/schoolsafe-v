(function (root) {
  "use strict";

  var ACTIONS = {
    "pedagogy|Matières": "subjects",
    "pedagogy|Devoirs et corrections": "assignments",
    "pedagogy|Cahiers de préparation": "notebooks",
    "pedagogy|Cahiers de préparation des enseignants": "notebooks",
    "pedagogy|Cahier de préparation de l\u2019enseignant": "notebooks",
    "pedagogy|Évaluations et notes": "grades",
    "pedagogy|Moyennes et coefficients": "averages",
    "pedagogy|Résultats et moyennes": "averages",
    "pedagogy|Bulletins": "report",
    "pedagogy|Bulletins à consulter": "report",
    "pedagogy|Palmarès": "rankings",
    "finance|Structure des frais": "fees",
    "finance|Types de frais": "fees",
    "finance|Encaissements": "collections",
    "finance|Enregistrer un paiement": "collections",
    "finance|Impayés et soldes": "unpaid",
    "finance|Consulter le solde et les impayés": "unpaid",
    "pilotage|Alertes importantes": "alerts",
    "pilotage|Alertes": "alerts",
    "security|Alertes et anomalies": "alerts"
  };
  var READ = {
    cards: "school.student.read",
    subjects: "pedagogy.subject.read",
    assignments: "pedagogy.assignment.read",
    notebooks: "pedagogy.lesson-plan.read",
    grades: "pedagogy.grade.read",
    averages: "pedagogy.grade.read",
    rankings: "palmarques.read",
    fees: "finance.fee.read",
    collections: "finance.payment.record",
    unpaid: "finance.fee.read",
    alerts: "pilotage.alerts.read"
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
    return '<section class="p2c-screen" data-p2c-screen="' + screen + '"><h2>' + escapeHtml(title) + "</h2>" + body + "</section>";
  }

  function facts(pairs) {
    return '<dl class="p2c-facts">' + pairs.filter(function (pair) {
      return pair[1] != null && pair[1] !== "";
    }).map(function (pair) {
      return "<div><dt>" + escapeHtml(pair[0]) + "</dt><dd>" + escapeHtml(pair[1]) + "</dd></div>";
    }).join("") + "</dl>";
  }

  function deny(context, code) {
    show(context, shell("denied", "Accès refusé", state("error", "Accès refusé", "Cette action est explicitement refusée ou hors permission (" + code + ").")));
  }

  function liveGate(context, title) {
    if (context && context.live) return true;
    show(context, shell("gate", title, state("unavailable", title, "Session réelle requise. Aucune donnée fictive n’est affichée.")));
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

  function fail(context, screen, title, error) {
    show(context, shell(screen, title, state("error", "Erreur", error && error.message ? error.message : "Lecture impossible")));
  }

  function scopedPath(context, permission, path) {
    var access = root.SchoolSafeAccess;
    var scope = access && typeof access.scopeFor === "function"
      ? access.scopeFor((context && context.user) || {}, permission)
      : null;
    var params = [];
    if (scope && scope.target && scope.type === "assigned_classes") params.push("class_id=" + encodeURIComponent(scope.target));
    if (scope && scope.target && scope.type === "assigned_subjects") params.push("subject_id=" + encodeURIComponent(scope.target));
    if (!params.length) return path;
    return path + (path.indexOf("?") >= 0 ? "&" : "?") + params.join("&");
  }

  function field(label, id) {
    return "<label>" + escapeHtml(label) + ' <input id="' + id + '"></label>';
  }

  function authorizedPeople(payload) {
    var seen = {};
    var people = [];
    function add(id, name) {
      var key = String(id || name || "");
      if (!key || seen[key]) return;
      seen[key] = true;
      if (name) people.push(name);
    }
    var data = payload || {};
    (data.family || []).forEach(function (person) {
      if (person && person.authorized) add(person.guardian_id, person.name);
    });
    (data.accredited || []).forEach(function (person) {
      if (person && person.name) add(person.authorization_id || person.guardian_id, person.name);
    });
    return people;
  }

  function paintCards(context, school, years, students) {
    var identity = (school && school.identity) || {};
    var brand = (school && school.brand) || {};
    var contact = (school && school.contact) || {};
    var active = years.filter(function (year) { return year && year.is_active; })[0] || {};
    var items = students.map(function (student, index) {
      var name = [student.first_name, student.last_name].filter(Boolean).join(" ");
      return "<li>" + escapeHtml(name) + " " + escapeHtml(student.matricule || "") + " " + button("Ouvrir", "p2cStudent" + index) + "</li>";
    }).join("");
    var body = facts([
      ["École", identity.name],
      ["Nom officiel", identity.legal_name],
      ["Nom anglais", identity.name_en],
      ["Adresse", contact.address],
      ["Ville", contact.city],
      ["Ligne école", contact.phone],
      ["Logo", brand.logo_path],
      ["Année scolaire", active.label]
    ]);
    body += students.length ? "<ul>" + items + "</ul>" : state("empty", "Aucun élève", "La liste serveur est vide.");
    var surface = show(context, shell("cards", "Studio des cartes", body));
    students.forEach(function (student, index) {
      on(surface, "p2cStudent" + index, "click", function () { openDossier(context, student.id, school, years); });
    });
  }

  function openCards(context) {
    show(context, shell("cards", "Studio des cartes", state("loading", "Chargement", "Lecture des données déjà connues.")));
    var school = null;
    var years = [];
    var students = [];
    var jobs = [];
    if (!blocked(context, "school.manage")) {
      jobs.push(request("/school/settings").then(function (body) { school = body; }));
      jobs.push(request("/school/academic-years").then(function (body) { years = listFrom(body); }));
    }
    jobs.push(request("/native/students?status=active&limit=50&offset=0").then(function (body) { students = listFrom(body); }));
    Promise.all(jobs).then(function () {
      model.students = students;
      paintCards(context, school, years, students);
    }).catch(function (error) { fail(context, "cards", "Studio des cartes", error); });
  }

  function openDossier(context, studentId, school, years) {
    show(context, shell("cards", "Studio des cartes", state("loading", "Chargement", "Lecture du dossier connu.")));
    var record = null;
    var family = [];
    var classes = [];
    var pickup = null;
    var jobs = [
      request("/native/students/" + studentId + "/record").then(function (body) { record = body.data || body; })
    ];
    if (!blocked(context, "school.guardian.read")) {
      jobs.push(request("/native/students/" + studentId + "/family").then(function (body) { family = listFrom(body); }));
    }
    if (!blocked(context, "school.class.read")) {
      jobs.push(request("/native/pedagogy/classes").then(function (body) { classes = listFrom(body); }));
    }
    if (!blocked(context, "security.scan")) {
      jobs.push(request("/native/family/students/" + studentId + "/pickup-authorizations").then(function (body) {
        pickup = body.data || body;
      }));
    }
    Promise.all(jobs).then(function () {
      var identity = (school && school.identity) || {};
      var brand = (school && school.brand) || {};
      var contact = (school && school.contact) || {};
      var active = (years || []).filter(function (year) { return year && year.is_active; })[0] || {};
      var schooling = (record && record.schooling) || {};
      var course = classes.filter(function (item) { return item && item.id === schooling.class_id; })[0] || {};
      var primary = family.filter(function (item) { return item && item.is_primary; });
      var people = authorizedPeople(pickup);
      var body = facts([
        ["École", identity.name],
        ["Nom officiel", identity.legal_name],
        ["Nom anglais", identity.name_en],
        ["Adresse", contact.address],
        ["Ville", contact.city],
        ["Ligne école", contact.phone],
        ["Logo", brand.logo_path],
        ["Année scolaire", active.label],
        ["Élève", record ? [record.first_name, record.last_name].filter(Boolean).join(" ") : ""],
        ["Matricule", record && record.matricule],
        ["Naissance", record && record.date_of_birth],
        ["Photo", record && record.photo_path],
        ["Classe", schooling.class_name || course.name],
        ["Cycle", course.cycle_key],
        ["Option", course.option],
        ["Tuteur principal", primary.map(function (item) { return item.full_name; }).join(", ")]
      ]);
      body += "<p>CARD_SIGNED_QR=BACKEND_GAP</p>";
      body += "<p>CARD_TEACHER_DATA=BACKEND_GAP</p>";
      if (people.length > 1) body += "<p>CARD_AUTHORIZED_PERSON_SELECTION=UNRESOLVED</p>";
      body += people.length
        ? "<ul>" + people.map(function (name) { return "<li>" + escapeHtml(name) + "</li>"; }).join("") + "</ul>"
        : state("empty", "Aucune personne autorisée", "La liste serveur est vide.");
      show(context, shell("cards", "Studio des cartes", body));
    }).catch(function (error) { fail(context, "cards", "Studio des cartes", error); });
  }

  function openSubjects(context) {
    var title = "Matières";
    show(context, shell("subjects", title, state("loading", "Chargement", "Lecture des matières.")));
    request("/native/pedagogy/subjects").then(function (body) {
      var rows = listFrom(body);
      var list = rows.map(function (row) {
        return "<li><b>" + escapeHtml(row.name) + "</b> " + escapeHtml(row.code || "") + " " + escapeHtml(row.cycle_key || "") + "</li>";
      }).join("");
      var form = "";
      if (!blocked(context, "pedagogy.subject.manage")) {
        form = "<form>" + field("Code", "p2cSubjectCode") + field("Nom", "p2cSubjectName") + field("Cycle", "p2cSubjectCycle") + button("Créer la matière", "p2cSubjectCreate") + "</form>";
      }
      var content = (rows.length ? "<ul>" + list + "</ul>" : state("empty", "Aucune matière", "Le serveur n'a renvoyé aucune matière.")) + form;
      var surface = show(context, shell("subjects", title, content));
      on(surface, "p2cSubjectCreate", "click", function () {
        request("/native/pedagogy/subjects", {
          method: "POST",
          body: {
            code: text("p2cSubjectCode"),
            name: text("p2cSubjectName"),
            cycle_key: text("p2cSubjectCycle"),
            coefficient: 1
          }
        }).then(function (created) {
          var id = created && created.data ? created.data.id : "";
          show(context, shell("subjects", title, state("success", "Matière créée", id)));
        }).catch(function (error) { fail(context, "subjects", title, error); });
      });
    }).catch(function (error) { fail(context, "subjects", title, error); });
  }

  function openAssignments(context) {
    var title = "Devoirs et corrections";
    show(context, shell("assignments", title, state("loading", "Chargement", "Lecture des devoirs.")));
    request(scopedPath(context, "pedagogy.assignment.read", "/native/pedagogy/assignments")).then(function (body) {
      var rows = listFrom(body);
      var list = rows.map(function (row, index) {
        var actions = "";
        if (!blocked(context, "pedagogy.assignment.manage")) {
          actions = button("Publier le devoir", "p2cAssignPublish" + index) + button("Modifier le devoir", "p2cAssignEdit" + index);
        }
        return "<li><b>" + escapeHtml(row.title) + "</b> " + escapeHtml(row.type || "") + " " + actions + "</li>";
      }).join("");
      var content = "<p>Pièces jointes : BACKEND_GAP</p>" + (rows.length ? "<ul>" + list + "</ul>" : state("empty", "Aucun devoir", "Le serveur n'a renvoyé aucun devoir."));
      if (!blocked(context, "pedagogy.assignment.manage")) {
        content += "<form>" + field("Classe", "p2cAssignmentClass") + field("Matière", "p2cAssignmentSubject") + field("Titre", "p2cAssignmentTitle") + field("Type", "p2cAssignmentType") + field("Barème", "p2cAssignmentMax") + button("Créer le devoir", "p2cAssignmentCreate") + "</form>";
      }
      var surface = show(context, shell("assignments", title, content));
      rows.forEach(function (row, index) {
        on(surface, "p2cAssignPublish" + index, "click", function () {
          var ready = show(context, shell("assignments", title, state("unavailable", "Confirmation", "Publier ce devoir ?") + button("Confirmer", "p2cAssignPublishYes")));
          on(ready, "p2cAssignPublishYes", "click", function () {
            request("/native/pedagogy/assignments/" + row.id + "/publish", { method: "POST", body: {} }).then(function () {
              show(context, shell("assignments", title, state("success", "Devoir publié", row.id)));
            }).catch(function (error) { fail(context, "assignments", title, error); });
          });
        });
        on(surface, "p2cAssignEdit" + index, "click", function () {
          var ready = show(context, shell("assignments", title, field("Titre", "p2cEditTitle") + field("Barème", "p2cEditMax") + field("Coefficient", "p2cEditCoef") + button("Confirmer", "p2cAssignEditYes")));
          on(ready, "p2cAssignEditYes", "click", function () {
            request("/native/pedagogy/assignments/" + row.id, {
              method: "PATCH",
              body: { title: text("p2cEditTitle"), max_score: Number(text("p2cEditMax")), coefficient: Number(text("p2cEditCoef")) }
            }).then(function () {
              show(context, shell("assignments", title, state("success", "Devoir modifié", row.id)));
            }).catch(function (error) { fail(context, "assignments", title, error); });
          });
        });
      });
      on(surface, "p2cAssignmentCreate", "click", function () {
        request("/native/pedagogy/assignments", {
          method: "POST",
          body: {
            class_id: text("p2cAssignmentClass"),
            subject_id: text("p2cAssignmentSubject"),
            title: text("p2cAssignmentTitle"),
            type: text("p2cAssignmentType"),
            max_score: Number(text("p2cAssignmentMax")),
            coefficient: 1
          }
        }).then(function (created) {
          var id = created && created.data ? created.data.id : "";
          show(context, shell("assignments", title, state("success", "Devoir créé", id)));
        }).catch(function (error) { fail(context, "assignments", title, error); });
      });
    }).catch(function (error) { fail(context, "assignments", title, error); });
  }

  function openNotebooks(context) {
    var title = "Cahiers de préparation";
    show(context, shell("notebooks", title, state("loading", "Chargement", "Lecture des cahiers.")));
    request(scopedPath(context, "pedagogy.lesson-plan.read", "/native/pedagogy/lesson-plans")).then(function (body) {
      var rows = listFrom(body);
      var list = rows.map(function (row, index) {
        var actions = "";
        if (!blocked(context, "pedagogy.lesson-plan.manage")) {
          actions = button("Modifier", "p2cPlanPatch" + index) + button("Supprimer", "p2cPlanDelete" + index);
        }
        return "<article><b>" + escapeHtml(row.title) + "</b><p>" + escapeHtml(row.content || "") + "</p><small>" + escapeHtml(row.class_name || "") + " " + escapeHtml(row.subject_name || "") + "</small>" + actions + "</article>";
      }).join("");
      var content = rows.length ? list : state("empty", "Aucun cahier", "Le serveur n'a renvoyé aucun contenu.");
      if (!blocked(context, "pedagogy.lesson-plan.manage")) {
        content += "<form>" + field("Classe", "p2cPlanClass") + field("Matière", "p2cPlanSubject") + field("Titre", "p2cPlanTitle") + field("Semaine", "p2cPlanWeek") + field("Contenu", "p2cPlanContent") + button("Créer le cahier", "p2cPlanCreate") + "</form>";
      }
      var surface = show(context, shell("notebooks", title, content));
      rows.forEach(function (row, index) {
        on(surface, "p2cPlanPatch" + index, "click", function () {
          var ready = show(context, shell("notebooks", title, field("Titre", "p2cPlanPatchTitle") + field("Contenu", "p2cPlanPatchContent") + button("Confirmer", "p2cPlanPatchYes")));
          on(ready, "p2cPlanPatchYes", "click", function () {
            request("/native/pedagogy/lesson-plans/" + row.id, {
              method: "PATCH",
              body: { title: text("p2cPlanPatchTitle"), content: text("p2cPlanPatchContent") }
            }).then(function () {
              show(context, shell("notebooks", title, state("success", "Cahier modifié", row.id)));
            }).catch(function (error) { fail(context, "notebooks", title, error); });
          });
        });
        on(surface, "p2cPlanDelete" + index, "click", function () {
          var ready = show(context, shell("notebooks", title, state("unavailable", "Confirmation", "Supprimer ce cahier ?") + button("Confirmer", "p2cPlanDeleteYes")));
          on(ready, "p2cPlanDeleteYes", "click", function () {
            request("/native/pedagogy/lesson-plans/" + row.id, { method: "DELETE" }).then(function () {
              show(context, shell("notebooks", title, state("success", "Cahier supprimé", row.id)));
            }).catch(function (error) { fail(context, "notebooks", title, error); });
          });
        });
      });
      on(surface, "p2cPlanCreate", "click", function () {
        request("/native/pedagogy/lesson-plans", {
          method: "POST",
          body: {
            class_id: text("p2cPlanClass"),
            subject_id: text("p2cPlanSubject"),
            title: text("p2cPlanTitle"),
            week_start: text("p2cPlanWeek"),
            content: text("p2cPlanContent")
          }
        }).then(function (created) {
          var id = created && created.data ? created.data.id : "";
          show(context, shell("notebooks", title, state("success", "Cahier créé", id)));
        }).catch(function (error) { fail(context, "notebooks", title, error); });
      });
    }).catch(function (error) { fail(context, "notebooks", title, error); });
  }

  function openGrades(context) {
    var title = "Évaluations et notes";
    show(context, shell("grades", title, state("loading", "Chargement", "Lecture des évaluations.")));
    request(scopedPath(context, "pedagogy.grade.read", "/native/pedagogy/assignments")).then(function (body) {
      var rows = listFrom(body);
      model.assignments = rows;
      var list = rows.map(function (row, index) {
        return "<li>" + escapeHtml(row.title || "") + " " + button("Notes", "p2cGrade" + index) + "</li>";
      }).join("");
      var surface = show(context, shell("grades", title, rows.length ? "<ul>" + list + "</ul>" : state("empty", "Aucune évaluation", "Le serveur n'a renvoyé aucune évaluation.")));
      rows.forEach(function (row, index) {
        on(surface, "p2cGrade" + index, "click", function () {
          show(context, shell("grades", title, state("loading", "Chargement", "Lecture des notes.")));
          request("/native/pedagogy/assignments/" + row.id + "/grades").then(function (payload) {
            var grades = listFrom(payload);
            var items = grades.map(function (grade) {
              return "<li>" + escapeHtml(grade.student_name || "") + " " + escapeHtml(grade.matricule || "") + " <b>" + escapeHtml(grade.score) + "</b></li>";
            }).join("");
            var extra = "";
            if (!blocked(context, "pedagogy.grade.manage")) {
              extra = "<form>" + field("Élève", "p2cGradeStudent") + field("Note", "p2cGradeScore") + button("Enregistrer la note", "p2cGradeSave") + "</form>" + button("Publier les notes", "p2cGradePublish");
            }
            var gradeSurface = show(context, shell("grades", title, (grades.length ? "<ul>" + items + "</ul>" : state("empty", "Aucune note", "Le serveur n'a renvoyé aucune note.")) + extra));
            on(gradeSurface, "p2cGradePublish", "click", function () {
              var ready = show(context, shell("grades", title, state("unavailable", "Confirmation", "Publier ces notes ?") + button("Confirmer", "p2cGradePublishYes")));
              on(ready, "p2cGradePublishYes", "click", function () {
                request("/native/pedagogy/assignments/" + row.id + "/grades/publish", { method: "POST", body: {} }).then(function () {
                  show(context, shell("grades", title, state("success", "Notes publiées", row.id)));
                }).catch(function (error) { fail(context, "grades", title, error); });
              });
            });
            on(gradeSurface, "p2cGradeSave", "click", function () {
              request("/native/pedagogy/assignments/" + row.id + "/grades", {
                method: "POST",
                body: { grades: [{ student_id: text("p2cGradeStudent"), score: Number(text("p2cGradeScore")) }] }
              }).then(function () {
                show(context, shell("grades", title, state("success", "Note enregistrée", row.id)));
              }).catch(function (error) { fail(context, "grades", title, error); });
            });
          }).catch(function (error) { fail(context, "grades", title, error); });
        });
      });
    }).catch(function (error) { fail(context, "grades", title, error); });
  }

  function openAverages(context) {
    var title = "Moyennes et coefficients";
    show(context, shell("averages", title, state("loading", "Chargement", "Lecture des élèves.")));
    request("/native/students?status=active&limit=50&offset=0").then(function (body) {
      var students = listFrom(body);
      var list = students.map(function (student, index) {
        return "<li>" + escapeHtml([student.first_name, student.last_name].filter(Boolean).join(" ")) + " " + button("Moyennes", "p2cAverage" + index) + "</li>";
      }).join("");
      var surface = show(context, shell("averages", title, students.length ? "<ul>" + list + "</ul>" : state("empty", "Aucun élève", "La liste serveur est vide.")));
      students.forEach(function (student, index) {
        on(surface, "p2cAverage" + index, "click", function () {
          show(context, shell("averages", title, state("loading", "Chargement", "Lecture des moyennes serveur.")));
          request("/native/pedagogy/students/" + student.id + "/averages").then(function (payload) {
            var rows = listFrom(payload);
            var items = rows.map(function (row) {
              return "<li>" + escapeHtml(row.subject_name) + " <b>" + escapeHtml(row.average) + "</b> " + escapeHtml(row.coefficient) + "</li>";
            }).join("");
            show(context, shell("averages", title, rows.length ? "<ul>" + items + "</ul>" : state("empty", "Aucune moyenne", "Le serveur n'a renvoyé aucune moyenne.")));
          }).catch(function (error) { fail(context, "averages", title, error); });
        });
      });
    }).catch(function (error) { fail(context, "averages", title, error); });
  }

  function openRankings(context) {
    var title = "Palmarès";
    show(context, shell("rankings", title, state("loading", "Chargement", "Lecture des palmarès serveur.")));
    request(scopedPath(context, "palmarques.read", "/native/pedagogy/rankings?")).then(function (body) {
      var rows = listFrom(body);
      var list = rows.map(function (row, index) {
        return "<li>" + escapeHtml(row.class_name || "") + " " + escapeHtml(row.month || "") + " " + escapeHtml(row.status || "") + " " + button("Détail", "p2cRanking" + index) + "</li>";
      }).join("");
      var surface = show(context, shell("rankings", title, rows.length ? "<ul>" + list + "</ul>" : state("empty", "Aucun palmarès", "Le serveur n'a renvoyé aucun palmarès.")));
      rows.forEach(function (row, index) {
        on(surface, "p2cRanking" + index, "click", function () {
          request("/native/pedagogy/rankings/" + row.id).then(function (payload) {
            var detail = payload.data || payload;
            var students = detail.data && detail.data.students ? detail.data.students : [];
            var items = students.map(function (student) {
              return "<li>" + escapeHtml(student.student_id || "") + " <b>" + escapeHtml(student.weighted_average) + "</b></li>";
            }).join("");
            var manage = "";
            if (!blocked(context, "palmarques.manage")) {
              manage = "<form>" + field("Classe", "p2cRankClass") + field("Mois", "p2cRankMonth") + button("Calculer", "p2cRankCompute") + "</form>" + button("Publier le palmarès", "p2cRankPublish") + button("Lire les étoiles", "p2cStarLoad");
            }
            var rankSurface = show(context, shell("rankings", title, manage + facts([
              ["Mois", detail.month],
              ["Statut", detail.status],
              ["Classe", detail.class_name]
            ]) + (items ? "<ul>" + items + "</ul>" : "")));
            on(rankSurface, "p2cRankPublish", "click", function () {
              var ready = show(context, shell("rankings", title, state("unavailable", "Confirmation", "Publier ce palmarès ?") + button("Confirmer", "p2cRankPublishYes")));
              on(ready, "p2cRankPublishYes", "click", function () {
                request("/native/pedagogy/rankings/" + row.id + "/publish", { method: "POST", body: {} }).then(function () {
                  show(context, shell("rankings", title, state("success", "Palmarès publié", row.id)));
                }).catch(function (error) { fail(context, "rankings", title, error); });
              });
            });
            on(rankSurface, "p2cStarLoad", "click", function () {
              request("/native/pedagogy/rankings/" + row.id + "/stars").then(function (stars) {
                var starRows = listFrom(stars);
                var items = starRows.map(function (star, index) {
                  var studentId = star.student_id || "";
                  var remove = studentId ? " " + button("Retirer l'étoile", "p2cStarDelete" + index) : "";
                  return "<li>" + escapeHtml(studentId || star.id || "") + remove + "</li>";
                }).join("");
                var starBody = (items ? "<ul>" + items + "</ul>" : state("empty", "Aucune étoile", "Le serveur n'a renvoyé aucune étoile.")) + field("Élève", "p2cStarStudent") + button("Ajouter", "p2cStarAdd");
                var starSurface = show(context, shell("rankings", title, starBody));
                starRows.forEach(function (star, index) {
                  if (!star.student_id) return;
                  on(starSurface, "p2cStarDelete" + index, "click", function () {
                    var ready = show(context, shell("rankings", title, state("unavailable", "Confirmation", "Retirer cette étoile ?") + button("Confirmer", "p2cStarDeleteYes")));
                    on(ready, "p2cStarDeleteYes", "click", function () {
                      request("/native/pedagogy/rankings/" + row.id + "/stars/" + encodeURIComponent(star.student_id), { method: "DELETE" }).then(function () {
                        show(context, shell("rankings", title, state("success", "Étoile retirée", star.student_id)));
                      }).catch(function (error) { fail(context, "rankings", title, error); });
                    });
                  });
                });
                on(starSurface, "p2cStarAdd", "click", function () {
                  request("/native/pedagogy/rankings/" + row.id + "/stars", { method: "POST", body: { student_id: text("p2cStarStudent") } }).then(function (created) {
                    var id = created && created.data ? created.data.id : "";
                    show(context, shell("rankings", title, state("success", "Étoile enregistrée", id)));
                  }).catch(function (error) { fail(context, "rankings", title, error); });
                });
              }).catch(function (error) { fail(context, "rankings", title, error); });
            });
            on(rankSurface, "p2cRankCompute", "click", function () {
              request("/native/pedagogy/rankings/compute", {
                method: "POST",
                body: { class_id: text("p2cRankClass"), month: text("p2cRankMonth") }
              }).then(function (created) {
                var id = created && created.data ? created.data.id : "";
                show(context, shell("rankings", title, state("success", "Palmarès calculé", id)));
              }).catch(function (error) { fail(context, "rankings", title, error); });
            });
          }).catch(function (error) { fail(context, "rankings", title, error); });
        });
      });
    }).catch(function (error) { fail(context, "rankings", title, error); });
  }

  function openFees(context) {
    var title = "Structure des frais";
    show(context, shell("fees", title, state("loading", "Chargement", "Lecture des frais.")));
    request("/native/finance/fee-structures").then(function (body) {
      var rows = listFrom(body);
      var list = rows.map(function (row) {
        return "<li><b>" + escapeHtml(row.label) + "</b> " + escapeHtml(row.amount) + " " + escapeHtml(row.currency || "") + "</li>";
      }).join("");
      var form = "";
      if (!blocked(context, "finance.fee.manage")) {
        form = "<form>" + field("Libellé", "p2cFeeLabel") + field("Montant", "p2cFeeAmount") + field("Année", "p2cFeeYear") + button("Créer le frais", "p2cFeeCreate") + "</form>";
        form += "<form>" + field("Élève", "p2cFeeStudent") + field("Structure", "p2cFeeStructure") + field("Année", "p2cFeeAssignYear") + button("Affecter le frais", "p2cFeeAssign") + "</form>";
      }
      var content = (rows.length ? "<ul>" + list + "</ul>" : state("empty", "Aucun frais", "Le serveur n'a renvoyé aucune structure.")) + form;
      var surface = show(context, shell("fees", title, content));
      on(surface, "p2cFeeAssign", "click", function () {
        var studentId = text("p2cFeeStudent");
        var payload = { fee_structure_id: text("p2cFeeStructure"), academic_year_id: text("p2cFeeAssignYear") };
        var ready = show(context, shell("fees", title, state("unavailable", "Confirmation", "Affecter ce frais à l'élève ?") + button("Confirmer", "p2cFeeAssignYes")));
        on(ready, "p2cFeeAssignYes", "click", function () {
          request("/native/finance/students/" + studentId + "/fees", { method: "POST", body: payload }).then(function (created) {
            var id = created && created.data ? created.data.id : "";
            show(context, shell("fees", title, state("success", "Frais affecté", id)));
          }).catch(function (error) { fail(context, "fees", title, error); });
        });
      });
      on(surface, "p2cFeeCreate", "click", function () {
        request("/native/finance/fee-structures", {
          method: "POST",
          body: {
            academic_year_id: text("p2cFeeYear"),
            label: text("p2cFeeLabel"),
            amount: Number(text("p2cFeeAmount")),
            currency: "USD"
          }
        }).then(function (created) {
          var id = created && created.data ? created.data.id : "";
          show(context, shell("fees", title, state("success", "Frais créé", id)));
        }).catch(function (error) { fail(context, "fees", title, error); });
      });
    }).catch(function (error) { fail(context, "fees", title, error); });
  }

  function studentButtons(students, prefix, label) {
    return students.map(function (student, index) {
      return "<li>" + escapeHtml([student.first_name, student.last_name].filter(Boolean).join(" ")) + " " + escapeHtml(student.matricule || "") + " " + button(label, prefix + index) + "</li>";
    }).join("");
  }

  function openCollections(context) {
    var title = "Encaissements";
    show(context, shell("collections", title, state("loading", "Chargement", "Lecture des élèves.")));
    request("/native/students?status=active&limit=50&offset=0").then(function (body) {
      var students = listFrom(body);
      var surface = show(context, shell("collections", title, students.length ? "<ul>" + studentButtons(students, "p2cPayStudent", "Encaisser") + "</ul>" : state("empty", "Aucun élève", "La liste serveur est vide.")));
      students.forEach(function (student, index) {
        on(surface, "p2cPayStudent" + index, "click", function () { openStudentPayments(context, student); });
      });
    }).catch(function (error) { fail(context, "collections", title, error); });
  }

  function openStudentPayments(context, student) {
    var title = "Encaissements";
    show(context, shell("collections", title, state("loading", "Chargement", "Lecture des paiements.")));
    Promise.all([
      request("/native/finance/students/" + student.id + "/fees"),
      blocked(context, "finance.receipt.read") ? Promise.resolve({ data: [] }) : request("/native/finance/students/" + student.id + "/payments")
    ]).then(function (results) {
      var fees = listFrom(results[0]);
      var payments = listFrom(results[1]);
      model.fees = fees;
      var paid = payments.map(function (row) {
        return "<li>" + escapeHtml(row.amount) + " " + escapeHtml(row.currency || "") + " " + escapeHtml(row.status || "") + " " + escapeHtml(row.mode || "") + "</li>";
      }).join("");
      var form = "";
      if (!blocked(context, "finance.payment.record") && fees.length) {
        form = "<form>" + field("Montant", "p2cPayAmount") + button("Enregistrer", "p2cPayCreate") + "</form>";
      }
      if (!blocked(context, "finance.payment.cancel") && payments.length) {
        form += "<form>" + field("Motif", "p2cPayReason") + button("Annuler", "p2cPayCancel") + "</form>";
      }
      var content = (payments.length ? "<ul>" + paid + "</ul>" : state("empty", "Aucun paiement", "Le serveur n'a renvoyé aucun paiement.")) + form;
      var surface = show(context, shell("collections", title, content));
      on(surface, "p2cPayCreate", "click", function () {
        var fee = model.fees && model.fees[0];
        request("/native/finance/payments", {
          method: "POST",
          body: {
            student_fee_id: fee && fee.id,
            amount: Number(text("p2cPayAmount")),
            currency: "USD",
            mode: "cash"
          }
        }).then(function (created) {
          var id = created && created.data ? created.data.id : "";
          show(context, shell("collections", title, state("success", "Paiement enregistré", id)));
        }).catch(function (error) { fail(context, "collections", title, error); });
      });
      on(surface, "p2cPayCancel", "click", function () {
        var payment = payments[0];
        request("/native/finance/payments/" + (payment && payment.id) + "/cancel", {
          method: "POST",
          body: { reason: text("p2cPayReason") }
        }).then(function () {
          show(context, shell("collections", title, state("success", "Paiement annulé", payment && payment.id)));
        }).catch(function (error) { fail(context, "collections", title, error); });
      });
    }).catch(function (error) { fail(context, "collections", title, error); });
  }

  function openUnpaid(context) {
    var title = "Impayés et soldes";
    show(context, shell("unpaid", title, state("loading", "Chargement", "Lecture des élèves.")));
    request("/native/students?status=active&limit=50&offset=0").then(function (body) {
      var students = listFrom(body);
      var surface = show(context, shell("unpaid", title, students.length ? "<ul>" + studentButtons(students, "p2cUnpaid", "Solde") + "</ul>" : state("empty", "Aucun élève", "La liste serveur est vide.")));
      students.forEach(function (student, index) {
        on(surface, "p2cUnpaid" + index, "click", function () {
          request("/native/finance/students/" + student.id + "/fees").then(function (payload) {
            var fees = listFrom(payload).filter(function (fee) { return fee && fee.status !== "paid"; });
            var items = fees.map(function (fee) {
              return "<li><b>" + escapeHtml(fee.label) + "</b> " + escapeHtml(fee.status) + " " + escapeHtml(fee.amount_remaining) + " " + escapeHtml(fee.currency || "") + "</li>";
            }).join("");
            show(context, shell("unpaid", title, fees.length ? "<ul>" + items + "</ul>" : state("empty", "Aucun impayé", "Aucun frais n'a un statut différent de paid.")));
          }).catch(function (error) { fail(context, "unpaid", title, error); });
        });
      });
    }).catch(function (error) { fail(context, "unpaid", title, error); });
  }

  function openAlerts(context, action) {
    var title = action;
    var domain = action === "Alertes importantes" ? "domain=finance&" : "";
    show(context, shell("alerts", title, state("loading", "Chargement", "Lecture des alertes.")));
    request("/pilotage/alerts?" + domain + "limit=20&offset=0").then(function (body) {
      var rows = listFrom(body);
      var list = rows.map(function (row, index) {
        var ack = blocked(context, "pilotage.alerts.manage") ? "" : button("Acquitter", "p2cAlertAck" + index) + button("Résoudre", "p2cAlertResolve" + index);
        return "<li>" + escapeHtml(row.severity || "") + " " + escapeHtml(row.status || "") + " " + escapeHtml(row.source_module || "") + " " + ack + "</li>";
      }).join("");
      var surface = show(context, shell("alerts", title, rows.length ? "<ul>" + list + "</ul>" : state("empty", "Aucune alerte", "Le serveur n'a renvoyé aucune alerte.")));
      rows.forEach(function (row, index) {
        on(surface, "p2cAlertResolve" + index, "click", function () {
          var ready = show(context, shell("alerts", title, field("Code", "p2cAlertResolveCode") + field("Note", "p2cAlertResolveNote") + button("Confirmer", "p2cAlertResolveYes")));
          on(ready, "p2cAlertResolveYes", "click", function () {
            var payload = {};
            if (text("p2cAlertResolveCode")) payload.resolution_code = text("p2cAlertResolveCode");
            if (text("p2cAlertResolveNote")) payload.note = text("p2cAlertResolveNote");
            request("/pilotage/alerts/" + row.id + "/resolve", { method: "POST", body: payload }).then(function () {
              openAlerts(context, action);
            }).catch(function (error) { fail(context, "alerts", title, error); });
          });
        });
        on(surface, "p2cAlertAck" + index, "click", function () {
          request("/pilotage/alerts/" + row.id + "/acknowledge", { method: "POST", body: {} }).then(function () {
            show(context, shell("alerts", title, state("success", "Alerte acquittée", row.id)));
          }).catch(function (error) { fail(context, "alerts", title, error); });
        });
      });
    }).catch(function (error) { fail(context, "alerts", title, error); });
  }

  function open(branchKey, actionName, context) {
    var screen = ACTIONS[branchKey + "|" + actionName];
    if (!screen) return false;
    context = context || {};
    if (screen === "report") {
      show(context, shell("report", actionName, state("unavailable", "Bulletin", "REPORT_CARDS=BACKEND_GAP")));
      return true;
    }
    var code = READ[screen];
    if (blocked(context, code)) {
      deny(context, code);
      return true;
    }
    if (!liveGate(context, actionName)) return true;
    if (screen === "cards") openCards(context);
    else if (screen === "subjects") openSubjects(context);
    else if (screen === "assignments") openAssignments(context);
    else if (screen === "notebooks") openNotebooks(context);
    else if (screen === "grades") openGrades(context);
    else if (screen === "averages") openAverages(context);
    else if (screen === "rankings") openRankings(context);
    else if (screen === "fees") openFees(context);
    else if (screen === "collections") openCollections(context);
    else if (screen === "unpaid") openUnpaid(context);
    else if (screen === "alerts") openAlerts(context, actionName);
    return true;
  }

  root.SchoolSafeVisualOutputP2C = { open: open };
})(typeof window !== "undefined" ? window : globalThis);
