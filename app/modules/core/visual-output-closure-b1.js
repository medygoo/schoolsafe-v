(function (root) {
  "use strict";

  var CONFIRMATIONS = {
    family: "school.guardian.manage",
    medical: "school.student.health.confirm",
    dietary: "school.student.dietary.confirm",
    pickup: "school.guardian.manage"
  };
  var CONSENTS = {
    photo_video: "school.guardian.manage",
    emergency_care: "school.student.health.manage"
  };

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

  function field(label, id) {
    return "<label>" + escapeHtml(label) + ' <input id="' + id + '"></label>';
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

  function show(context, html) {
    var surface = context && context.host ? context.host : document.getElementById("wiringSurface");
    if (!surface) return null;
    if (!context.host) {
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
    return '<section class="clb-screen" data-clb-screen="' + screen + '"><h2>' + escapeHtml(title) + "</h2>" + body + "</section>";
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
    if (options && options.body !== undefined) {
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

  function isUuid(value) {
    return /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(String(value || ""));
  }

  function facts(pairs) {
    return "<dl>" + pairs.filter(function (pair) {
      return pair[1] != null && pair[1] !== "";
    }).map(function (pair) {
      return "<div><dt>" + escapeHtml(pair[0]) + "</dt><dd>" + escapeHtml(pair[1]) + "</dd></div>";
    }).join("") + "</dl>";
  }

  function confirm(context, screen, title, message, yesId, done) {
    var surface = show(context, shell(screen, title, state("unavailable", "Confirmation", message) + button("Confirmer", yesId)));
    on(surface, yesId, "click", done);
  }

  function filled(values) {
    var payload = {};
    Object.keys(values).forEach(function (key) {
      if (values[key]) payload[key] = values[key];
    });
    return payload;
  }

  function openIdentity(context) {
    var title = "Identité de l'élève";
    if (blocked(context, "school.student.create")) { deny(context, "school.student.create"); return; }
    if (!liveGate(context, title)) return;
    var body = field("Élève", "clbIdentityStudent") + field("Prénom", "clbFirstName") + field("Deuxième prénom", "clbMiddleName") + field("Nom", "clbLastName") + field("Date de naissance", "clbBirthDate") + field("Sexe", "clbGender") + field("Lieu de naissance", "clbBirthPlace") + field("Nationalité", "clbNationality") + field("Adresse", "clbHomeAddress") + button("Enregistrer", "clbIdentitySave");
    var surface = show(context, shell("identity", title, body));
    on(surface, "clbIdentitySave", "click", function () {
      var studentId = text("clbIdentityStudent");
      if (!isUuid(studentId)) {
        show(context, shell("identity", title, state("error", "Saisie", "Identifiant élève invalide.")));
        return;
      }
      var payload = filled({
        first_name: text("clbFirstName"),
        middle_name: text("clbMiddleName"),
        last_name: text("clbLastName"),
        date_of_birth: text("clbBirthDate"),
        gender: text("clbGender"),
        place_of_birth: text("clbBirthPlace"),
        nationality: text("clbNationality"),
        home_address: text("clbHomeAddress")
      });
      confirm(context, "identity", title, "Enregistrer cette identité ?", "clbIdentityYes", function () {
        request("/native/students/" + studentId + "/identity", { method: "PATCH", body: payload }).then(function () {
          show(context, shell("identity", title, state("success", "Identité enregistrée", studentId)));
        }).catch(function (error) { fail(context, "identity", title, error); });
      });
    });
  }

  function openConsents(context) {
    var title = "Consentements";
    if (!liveGate(context, title)) return;
    var body = field("Élève", "clbConsentStudent");
    Object.keys(CONFIRMATIONS).forEach(function (key) {
      if (!blocked(context, CONFIRMATIONS[key])) body += button("Confirmer " + key, "clbConfirm" + key.charAt(0).toUpperCase() + key.slice(1));
    });
    if (!blocked(context, CONSENTS.photo_video)) {
      body += button("photo_video oui", "clbConsentPhotoYes") + button("photo_video non", "clbConsentPhotoNo");
    }
    if (!blocked(context, CONSENTS.emergency_care)) {
      body += button("emergency_care oui", "clbConsentCareYes") + button("emergency_care non", "clbConsentCareNo");
    }
    var surface = show(context, shell("consents", title, body));
    function send(path, payload, label) {
      var studentId = text("clbConsentStudent");
      if (!isUuid(studentId)) {
        show(context, shell("consents", title, state("error", "Saisie", "Identifiant élève invalide.")));
        return;
      }
      request("/native/students/" + studentId + path, { method: "PUT", body: payload }).then(function (result) {
        var data = result.data || result;
        show(context, shell("consents", title, state("success", "Décision enregistrée", label + " " + (data.confirmed === true || data.decision === true ? "oui" : data.confirmed === false || data.decision === false ? "non" : ""))));
      }).catch(function (error) { fail(context, "consents", title, error); });
    }
    on(surface, "clbConfirmFamily", "click", function () { send("/confirmations/family", { confirmed: true }, "family"); });
    on(surface, "clbConfirmMedical", "click", function () { send("/confirmations/medical", { confirmed: true }, "medical"); });
    on(surface, "clbConfirmDietary", "click", function () { send("/confirmations/dietary", { confirmed: true }, "dietary"); });
    on(surface, "clbConfirmPickup", "click", function () { send("/confirmations/pickup", { confirmed: true }, "pickup"); });
    on(surface, "clbConsentPhotoYes", "click", function () { send("/consents/photo_video", { decision: true }, "photo_video"); });
    on(surface, "clbConsentPhotoNo", "click", function () { send("/consents/photo_video", { decision: false }, "photo_video"); });
    on(surface, "clbConsentCareYes", "click", function () { send("/consents/emergency_care", { decision: true }, "emergency_care"); });
    on(surface, "clbConsentCareNo", "click", function () { send("/consents/emergency_care", { decision: false }, "emergency_care"); });
  }

  function cycleList(context, notice) {
    var title = "Cycles";
    show(context, shell("cycles", title, state("loading", "Chargement", "Lecture des cycles.")));
    request("/school/cycles").then(function (body) {
      var rows = listFrom(body);
      var list = rows.map(function (row) {
        var next = row.is_active ? "Désactiver" : "Activer";
        return "<li><b>" + escapeHtml(row.cycle_name || row.cycle_key) + "</b> " + escapeHtml(row.is_active ? "actif" : "inactif") + " " + button(next, "clbCycle" + row.cycle_key) + "</li>";
      }).join("");
      var surface = show(context, shell("cycles", title, (notice || "") + (rows.length ? "<ul>" + list + "</ul>" : state("empty", "Aucun cycle", "Le serveur n'a renvoyé aucun cycle."))));
      rows.forEach(function (row) {
        on(surface, "clbCycle" + row.cycle_key, "click", function () {
          var next = !row.is_active;
          confirm(context, "cycles", title, (next ? "Activer" : "Désactiver") + " " + (row.cycle_name || row.cycle_key) + " ?", "clbCycleYes", function () {
            request("/school/cycles/" + encodeURIComponent(row.cycle_key) + "/toggle", { method: "PUT", body: { is_active: next } }).then(function () {
              show(context, shell("cycles", title, state("success", "Cycle enregistré", row.cycle_key)));
            }).catch(function (error) { fail(context, "cycles", title, error); });
          });
        });
      });
    }).catch(function (error) { fail(context, "cycles", title, error); });
  }

  function openCycles(context) {
    if (blocked(context, "school.manage")) { deny(context, "school.manage"); return; }
    if (!liveGate(context, "Cycles")) return;
    cycleList(context, "");
  }

  function snapshotForm(result) {
    return (result || "") + field("Indicateur", "clbIndicator") + field("Jours", "clbDays") + button("Lire la tendance", "clbTrend") + button("Capturer", "clbCapture");
  }

  function bindSnapshots(context, surface) {
    var title = "Instantanés";
    on(surface, "clbTrend", "click", function () {
      var code = text("clbIndicator");
      var days = text("clbDays") || "30";
      if (!code || !/^\d+$/.test(days) || Number(days) < 1 || Number(days) > 365) {
        show(context, shell("snapshots", title, state("error", "Saisie", "L'indicateur et un nombre de jours entre 1 et 365 sont requis.") + snapshotForm("")));
        bindSnapshots(context, document.getElementById("wiringSurface"));
        return;
      }
      show(context, shell("snapshots", title, state("loading", "Chargement", "Lecture de la tendance.")));
      request("/pilotage/snapshots/trend?indicator_code=" + encodeURIComponent(code) + "&days=" + encodeURIComponent(days)).then(function (body) {
        var rows = listFrom(body);
        var list = rows.map(function (row) {
          return "<li>" + escapeHtml(row.snapshot_date) + " <b>" + escapeHtml(row.value) + "</b></li>";
        }).join("");
        var html = (rows.length ? "<ul>" + list + "</ul>" : state("empty", "Aucune mesure", "Le serveur n'a renvoyé aucune mesure.")) + snapshotForm("");
        var next = show(context, shell("snapshots", title, html));
        setValue("clbIndicator", code);
        setValue("clbDays", days);
        bindSnapshots(context, next);
      }).catch(function (error) { fail(context, "snapshots", title, error); });
    });
    on(surface, "clbCapture", "click", function () {
      confirm(context, "snapshots", title, "Capturer les indicateurs du jour ?", "clbCaptureYes", function () {
        show(context, shell("snapshots", title, state("loading", "Chargement", "Capture en cours.")));
        request("/pilotage/snapshots/capture", { method: "POST" }).then(function (body) {
          var rows = listFrom(body);
          var list = rows.map(function (row) {
            return "<li>" + escapeHtml(row.indicator_code) + " <b>" + escapeHtml(row.value) + "</b> " + escapeHtml(row.unit || "") + "</li>";
          }).join("");
          var html = (rows.length ? "<ul>" + list + "</ul>" : state("empty", "Aucune mesure", "La capture n'a renvoyé aucun indicateur.")) + snapshotForm("");
          bindSnapshots(context, show(context, shell("snapshots", title, html)));
        }).catch(function (error) { fail(context, "snapshots", title, error); });
      });
    });
  }

  function setValue(id, value) {
    var node = document.getElementById(id);
    if (node) node.value = value;
  }

  function openSnapshots(context) {
    var title = "Instantanés";
    if (blocked(context, "pilotage.dashboard.read")) { deny(context, "pilotage.dashboard.read"); return; }
    if (!liveGate(context, title)) return;
    bindSnapshots(context, show(context, shell("snapshots", title, snapshotForm(""))));
  }

  function openEmail(context) {
    var title = "E-mail";
    if (blocked(context, "email.send")) { deny(context, "email.send"); return; }
    if (!liveGate(context, title)) return;
    var body = field("Destinataire", "clbMailTo") + field("Nom", "clbMailName") + field("Sujet", "clbMailSubject") + field("Texte", "clbMailText") + button("Envoyer", "clbMailSend");
    var surface = show(context, shell("email", title, body));
    on(surface, "clbMailSend", "click", function () {
      var email = text("clbMailTo");
      var subject = text("clbMailSubject");
      var message = text("clbMailText");
      if (!email || !subject) {
        show(context, shell("email", title, state("error", "Saisie", "Le destinataire et le sujet sont requis.")));
        return;
      }
      var payload = { to: [{ email: email }], subject: subject };
      var name = text("clbMailName");
      if (name) payload.to[0].name = name;
      if (message) payload.text = message;
      confirm(context, "email", title, "Envoyer cet e-mail ?", "clbMailYes", function () {
        request("/email/send", { method: "POST", body: payload }).then(function (result) {
          var data = result.data || result;
          show(context, shell("email", title, state("success", "E-mail traité", [data.status, data.provider, data.messageId, data.error].filter(Boolean).join(" "))));
        }).catch(function (error) { fail(context, "email", title, error); });
      });
    });
  }

  function openPush(context) {
    var title = "Notifications";
    if (blocked(context, "notification.subscribe")) { deny(context, "notification.subscribe"); return; }
    if (!liveGate(context, title)) return;
    show(context, shell("push", title, state("loading", "Chargement", "Lecture de la clé publique.")));
    request("/push/public-key").then(function (body) {
      var html = state("empty", "Non abonné", "Aucun abonnement n'est enregistré pour cette session.") + facts([["Clé publique", body.public_key]]) + field("Endpoint", "clbPushEndpoint") + field("p256dh", "clbPushP256") + field("auth", "clbPushAuth") + button("S'abonner", "clbPushSubscribe");
      var surface = show(context, shell("push", title, html));
      on(surface, "clbPushSubscribe", "click", function () {
        var endpoint = text("clbPushEndpoint");
        var p256dh = text("clbPushP256");
        var auth = text("clbPushAuth");
        if (!endpoint || !p256dh || !auth) {
          show(context, shell("push", title, state("error", "Saisie", "L'endpoint et les deux clés du contrat sont requis.")));
          return;
        }
        request("/push/subscribe", { method: "POST", body: { endpoint: endpoint, keys: { p256dh: p256dh, auth: auth } } }).then(function (result) {
          var subscribed = result && result.success === true;
          show(context, shell("push", title, state(subscribed ? "success" : "error", subscribed ? "Abonné" : "Erreur", subscribed ? "L'abonnement est enregistré." : "L'abonnement n'a pas été confirmé.")));
        }).catch(function (error) { fail(context, "push", title, error); });
      });
    }).catch(function (error) { fail(context, "push", title, error); });
  }

  function licenseFacts(data) {
    var pairs = [["État", data.state]];
    if (Object.prototype.hasOwnProperty.call(data, "license_id")) pairs.push(["Licence", data.license_id]);
    if (Object.prototype.hasOwnProperty.call(data, "expires_at")) pairs.push(["Expiration", data.expires_at]);
    if (Object.prototype.hasOwnProperty.call(data, "perpetual")) pairs.push(["Perpétuelle", data.perpetual === true ? "oui" : "non"]);
    return facts(pairs);
  }

  function licenseScreen(context, data) {
    var title = "Licence";
    var body = licenseFacts(data || {}) + field("Code d'activation", "clbLicenseCode") + button("Activer", "clbLicenseRedeem") + button("Actualiser", "clbLicenseRefresh");
    var surface = show(context, shell("license", title, body));
    on(surface, "clbLicenseRedeem", "click", function () {
      var code = text("clbLicenseCode");
      if (!code) {
        show(context, shell("license", title, state("error", "Saisie", "Le code d'activation est requis.")));
        return;
      }
      confirm(context, "license", title, "Activer cette licence ?", "clbLicenseYes", function () {
        request("/native/license/redeem", { method: "POST", body: { activation_code: code } }).then(function (result) {
          licenseScreen(context, result.data || {});
        }).catch(function (error) { fail(context, "license", title, error); });
      });
    });
    on(surface, "clbLicenseRefresh", "click", function () {
      request("/native/license/refresh", { method: "POST" }).then(function (result) {
        licenseScreen(context, result.data || {});
      }).catch(function (error) { fail(context, "license", title, error); });
    });
  }

  function openLicense(context) {
    var title = "Licence";
    if (!liveGate(context, title)) return;
    show(context, shell("license", title, state("loading", "Chargement", "Lecture de la licence.")));
    request("/native/license/status").then(function (result) {
      licenseScreen(context, result.data || {});
    }).catch(function (error) { fail(context, "license", title, error); });
  }

  function open(branchKey, actionName, context) {
    context = context || {};
    var key = branchKey + "|" + actionName;
    if (key === "school|Documents" || key === "administration|Documents R2" || key === "administration|Archives") {
      return !!(root.SchoolSafeVisualOutputClosureA && root.SchoolSafeVisualOutputClosureA.open("administration", "Centre documentaire", context));
    }
    if (key === "administration|Comptes et droits") {
      if (typeof context.openAccess === "function") context.openAccess();
      return true;
    }
    if (key === "school|Identité de l'élève") { openIdentity(context); return true; }
    if (key === "school|Consentements") { openConsents(context); return true; }
    if (key === "administration|Cycles") { openCycles(context); return true; }
    if (key === "pilotage|Instantanés") { openSnapshots(context); return true; }
    if (key === "communication|E-mail") { openEmail(context); return true; }
    if (key === "communication|Notifications") { openPush(context); return true; }
    if (key === "administration|Licence") { openLicense(context); return true; }
    return false;
  }

  root.SchoolSafeVisualOutputClosureB1 = { open: open };
})(window);
