(function (root) {
  "use strict";

  var MODES = {
    entry: { capability: "SCAN_ENTRY", label: "Entrée / Présence", banner: "MODE ENTRÉE" },
    pre_exit: { capability: "SCAN_PRE_EXIT", label: "Pré-sortie", banner: "MODE PRÉ-SORTIE" },
    gate_exit: { capability: "SCAN_GATE_EXIT", label: "Retrait / Sortie", banner: "MODE SORTIE" },
    pickup: { capability: "SCAN_PICKUP", label: "Retrait / Sortie", banner: "MODE SORTIE" },
    fees: { capability: "SCAN_FEES", label: "Contrôle des frais", banner: "MODE FRAIS" },
    verify: { capability: "SCAN_VERIFY", label: "Vérifier la carte", banner: "MODE VÉRIFICATION" }
  };

  var MANAGE = [
    ["CARD_CREATE", "Émettre une carte"],
    ["CARD_PRINT", "Impression"],
    ["CARD_LOST", "Perte"],
    ["CARD_REPLACE", "Remplacement"],
    ["CARD_REPRINT", "Réimpression"],
    ["CARD_REVOKE", "Révocation"]
  ];

  var CARD_ACTIONS = {
    CARD_CREATE: { title: "Émettre une carte", submit: "Émettre la carte", reason: false, card: false },
    CARD_LOST: { title: "Déclarer la perte", submit: "Déclarer la perte", reason: true, card: false },
    CARD_REPLACE: { title: "Remplacer la carte", submit: "Remplacer la carte", reason: false, card: true },
    CARD_REPRINT: { title: "Autoriser la réimpression", submit: "Autoriser la réimpression", reason: true, card: true },
    CARD_REVOKE: { title: "Révoquer la carte", submit: "Révoquer la carte", reason: true, card: true }
  };

  function apiBase() {
    return root.schoolSafeApiBase || "";
  }

  function offlinePanel() {
    return '<p class="scan-control-offline" role="status">Réseau absent. Aucune action de sécurité n’est validée sur cet appareil.</p>';
  }

  function isUuid(value) {
    return /^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i.test(String(value || ""));
  }

  async function request(path, options) {
    if (root.navigator && root.navigator.onLine === false) {
      var offline = new Error("offline");
      offline.offline = true;
      throw offline;
    }
    var response;
    try {
      response = await root.fetch(apiBase() + path, Object.assign({ credentials: "include", headers: { "Content-Type": "application/json", Accept: "application/json" } }, options || {}));
    } catch (error) {
      var network = new Error("offline");
      network.offline = true;
      throw network;
    }
    var body = null;
    try { body = await response.json(); } catch (ignore) { body = null; }
    if (!response.ok) {
      var failed = new Error(body && body.message ? body.message : "Erreur " + response.status);
      failed.status = response.status;
      throw failed;
    }
    return body && body.data !== undefined ? body.data : body;
  }

  function renderPeople(people, host) {
    var list = Array.isArray(people) ? people : [];
    host.innerHTML = list.map(function (person) {
      var photo = person.photo_path
        ? '<img src="' + escapeHtml(person.photo_path) + '" alt="Photographie de ' + escapeHtml(person.name) + '">'
        : '<span>Aucune photo</span>';
      var ready = person.pickup_ready === true ? "Contrôle possible" : "Non autorisée pour la remise";
      return '<article class="scan-control-person"><div>' + photo + '</div><div><strong>' + escapeHtml(person.name) + '</strong><p>' + escapeHtml(person.relation || "") + '</p><p>' + escapeHtml(ready) + '</p><button type="button" data-person="' + escapeHtml(person.id) + '">Choisir cette personne</button></div></article>';
    }).join("") || "<p>Aucune personne autorisée.</p>";
  }

  var OFFICIAL_MODES = ["entry", "pickup", "fees", "verify"];
  var scanStream = null;
  var scanTimer = null;

  function needsLocation(mode) {
    return mode === "entry" || mode === "pickup";
  }

  function stopCamera(scope) {
    if (scanTimer) {
      clearTimeout(scanTimer);
      scanTimer = null;
    }
    if (scanStream) {
      scanStream.getTracks().forEach(function (track) { track.stop(); });
      scanStream = null;
    }
    if (!scope) return;
    var video = scope.querySelector("[data-scan-video]");
    var box = scope.querySelector("[data-scan-camera]");
    if (video) video.srcObject = null;
    if (box) box.hidden = true;
  }

  function resultMessage(data) {
    var code = data && data.result ? String(data.result) : "";
    var reason = data && data.reason ? String(data.reason) : "";
    var labels = {
      VALID: "Carte valide.",
      INVALID: "Carte invalide.",
      LOST: "Carte déclarée perdue.",
      REPLACED: "Carte remplacée.",
      REVOKED: "Carte révoquée.",
      UNAVAILABLE: "Dossier élève indisponible.",
      SCOPE_DENIED: "Cette carte est hors de votre périmètre.",
      PRESENT: "Entrée enregistrée.",
      ALREADY_PRESENT: "Présence déjà enregistrée aujourd’hui. Aucune nouvelle présence n’a été créée.",
      STUDENT_NOT_OPERATIONAL: "Élève non opérationnel.",
      LOCATION_DENIED: "Lieu non autorisé.",
      PORTAL_DENIED: "Portail non attribué ou non autorisé.",
      FEES_DISABLED: "Le contrôle des frais est désactivé pour cet établissement.",
      NOT_DELEGATED: "Vous n’êtes pas autorisé à contrôler les frais.",
      DELEGATION_EXPIRED: "La délégation a expiré.",
      DELEGATION_REVOKED: "La délégation a été retirée.",
      OUT_OF_SCOPE: "Classe ou campagne hors périmètre.",
      FEE_STATUS: "Lecture financière.",
      PICKUP_CHOICE: "Choisissez la personne présente. Le QR seul n’autorise pas la sortie.",
      NEED_HUMAN_CONFIRMATION: "La confirmation humaine est obligatoire.",
      CONFIRMED: "Remise enregistrée.",
      DENIED: "Remise refusée.",
      PHOTO_NOT_VALIDATED: "Photographie non validée.",
      PICKUP_ALREADY_DONE: "Une remise est déjà enregistrée aujourd’hui.",
      PICKUP_RESTRICTED: "Cette personne n’est pas autorisée à récupérer l’élève.",
      PICKUP_PROOF_REQUIRED: "La remise exige la preuve du scan préparatoire.",
      PICKUP_PROOF_INVALID: "La preuve de scan n’est pas valable.",
      PICKUP_PROOF_EXPIRED: "La preuve de scan a expiré. Recommencez le contrôle.",
      PICKUP_PROOF_REUSED: "Cette preuve de scan a déjà été utilisée.",
      CARD_NOT_ACTIVE: "La carte n’est plus active."
    };
    var text = labels[reason] || labels[code] || "Décision indisponible.";
    if (code === "FEE_STATUS") {
      var balance = {
        paid: "Réglé",
        partial: "Paiement partiel",
        due: "Frais exigibles",
        exempt: "Exemption",
        no_data: "Aucune donnée financière"
      }[data.balance_status] || "Aucune donnée financière";
      text = balance + ". Cette lecture ne modifie aucun paiement et ne bloque ni l’entrée ni la sortie.";
    }
    var who = data && data.student_name ? " " + data.student_name : "";
    var klass = data && data.class_name ? " · " + data.class_name : "";
    var when = data && data.occurred_at ? " · " + data.occurred_at : "";
    return text + who + klass + when;
  }

  function escapeHtml(value) {
    return String(value == null ? "" : value).replace(/[&<>"']/g, function (ch) {
      return { "&": "&amp;", "<": "&lt;", ">": "&gt;", "\"": "&quot;", "'": "&#39;" }[ch];
    });
  }

  function readSchool() {
    try {
      var raw = root.sessionStorage && root.sessionStorage.getItem("schoolsafe-v2-session");
      var session = raw ? JSON.parse(raw) : null;
      return session && session.schoolId ? String(session.schoolId) : "";
    } catch (ignore) {
      return "";
    }
  }

  function startCamera(scope, status, onCode) {
    var box = scope.querySelector("[data-scan-camera]");
    var video = scope.querySelector("[data-scan-video]");
    if (!root.navigator || !root.navigator.mediaDevices || !root.navigator.mediaDevices.getUserMedia) {
      if (status) status.textContent = "Caméra indisponible. Utilisez la saisie manuelle ou un lecteur externe.";
      return;
    }
    if (!root.BarcodeDetector) {
      if (status) status.textContent = "La détection de QR par caméra n’est pas supportée par ce navigateur. Utilisez la saisie manuelle.";
      return;
    }
    root.navigator.mediaDevices.getUserMedia({ video: { facingMode: "environment" } }).then(function (stream) {
      scanStream = stream;
      if (video) video.srcObject = stream;
      if (box) box.hidden = false;
      var detector = new root.BarcodeDetector({ formats: ["qr_code"] });
      var loop = function () {
        if (!scanStream || !video) return;
        detector.detect(video).then(function (codes) {
          if (codes.length > 0 && codes[0].rawValue) {
            stopCamera(scope);
            onCode(String(codes[0].rawValue));
            return;
          }
          scanTimer = root.setTimeout(loop, 300);
        }).catch(function () {
          scanTimer = root.setTimeout(loop, 300);
        });
      };
      loop();
    }).catch(function () {
      if (status) status.textContent = "Permission caméra refusée. Utilisez la saisie manuelle.";
    });
  }

  function render(hostId, options) {
    options = options || {};
    stopCamera(null);
    var host = root.document.getElementById(hostId);
    if (!host) return;
    host.innerHTML = '<section class="scan-control-shell"><header><h2>Contrôle par carte</h2><p>Un seul scanner. Quatre modes. Une donnée de démonstration ne produit aucun résultat officiel.</p></header><div id="scanControlModes" class="scan-control-modes"></div><div id="scanControlDelegations"></div><p id="scanControlBanner" class="scan-control-banner" hidden></p><form id="scanControlForm" hidden><label class="scan-control-location" id="scanControlLocationField">Lieu du scan<select id="scanControlLocation" name="location"></select></label><p id="scanControlLocationStatus"></p><label>QR canonique ou lecteur externe<input name="qr" autocomplete="off" required></label><div class="scan-control-actions"><button type="submit" id="scanControlSubmit">Scanner</button><button type="button" id="scanControlCamera">Caméra</button></div><div data-scan-camera hidden><video data-scan-video autoplay playsinline muted></video><p>Placez le QR devant la caméra.</p></div><p id="scanControlCameraStatus"></p></form><div id="scanControlPeople" class="scan-control-people"></div><form id="scanControlHuman" hidden><label><input id="scanControlHumanCheck" type="checkbox"> Je confirme avoir contrôlé la personne présente et sa photographie.</label><button type="submit">Confirmer la remise</button></form><div id="scanControlResult" class="scan-control-result" role="status"></div><div id="scanControlManage" class="scan-control-manage"></div><form id="scanControlCard" class="scan-control-card" hidden><h3 id="scanControlCardTitle"></h3><p id="scanControlCardWho"></p><label id="scanControlCardReasonField">Motif<textarea id="scanControlCardReason" maxlength="500"></textarea></label><label id="scanControlCardConfirmField"><input id="scanControlCardConfirm" type="checkbox"> Je confirme cette opération.</label><button type="submit" id="scanControlCardSubmit">Confirmer</button></form><button type="button" id="scanControlBack" hidden>Retour au menu</button></section>';
    var state = { mode: "", personId: "", humanConfirmed: false, proofToken: "", schoolId: readSchool(), locations: [], locationError: "", subject: null, cardAction: "", cardBusy: false };
    var fetchGeneration = 0;
    var cardGeneration = 0;
    var cardOp = 0;
    var modes = host.querySelector("#scanControlModes");
    var delegations = host.querySelector("#scanControlDelegations");
    var humanForm = host.querySelector("#scanControlHuman");
    var humanCheck = host.querySelector("#scanControlHumanCheck");
    var cameraButton = host.querySelector("#scanControlCamera");
    var cameraStatus = host.querySelector("#scanControlCameraStatus");
    var banner = host.querySelector("#scanControlBanner");
    var form = host.querySelector("#scanControlForm");
    var result = host.querySelector("#scanControlResult");
    var people = host.querySelector("#scanControlPeople");
    var manage = host.querySelector("#scanControlManage");
    var back = host.querySelector("#scanControlBack");
    var locationField = host.querySelector("#scanControlLocationField");
    var locationSelect = host.querySelector("#scanControlLocation");
    var locationStatus = host.querySelector("#scanControlLocationStatus");
    var submit = host.querySelector("#scanControlSubmit");
    var cardForm = host.querySelector("#scanControlCard");
    var cardTitle = host.querySelector("#scanControlCardTitle");
    var cardWho = host.querySelector("#scanControlCardWho");
    var reasonField = host.querySelector("#scanControlCardReasonField");
    var reasonInput = host.querySelector("#scanControlCardReason");
    var confirmInput = host.querySelector("#scanControlCardConfirm");
    var cardSubmit = host.querySelector("#scanControlCardSubmit");
    cardForm.hidden = true;

    function paintLocation() {
      var rows = state.locations;
      locationSelect.innerHTML = '<option value="">Choisir un lieu</option>' + rows.map(function (row) {
        return '<option value="' + escapeHtml(row.id) + '">' + escapeHtml(row.code) + " — " + escapeHtml(row.label) + "</option>";
      }).join("");
      locationSelect.value = "";
      var blocked = needsLocation(state.mode) && rows.length === 0;
      locationSelect.disabled = blocked;
      submit.disabled = blocked;
      locationStatus.textContent = state.locationError
        ? "Les lieux de scan n'ont pas pu être chargés."
        : (blocked ? "Aucun lieu de scan n'est disponible." : "");
    }

    function paintSubject() {
      if (!state.subject || !state.subject.studentId) {
        cardWho.textContent = "Aucun élève n'est sélectionné. Vérifiez d'abord sa carte.";
        return;
      }
      var status = state.subject.status || "inconnue";
      var active = status === "active" ? "active" : "non active (" + status + ")";
      cardWho.textContent = (state.subject.name || "Élève") + " — " + (state.subject.number || "sans numéro") + " — " + active;
    }

    function rememberSubject(data) {
      if (!data || !data.student_id) return;
      var schoolNow = readSchool();
      if (!schoolNow || schoolNow !== state.schoolId) return;
      var next = {
        studentId: String(data.student_id),
        cardId: data.card_id ? String(data.card_id) : "",
        name: data.student_name ? String(data.student_name) : "",
        number: data.card_number ? String(data.card_number) : "",
        status: data.status ? String(data.status) : "",
        schoolId: schoolNow
      };
      var changed = !state.subject || state.subject.studentId !== next.studentId || state.subject.schoolId !== next.schoolId;
      state.subject = next;
      if (changed) cardGeneration += 1;
      paintSubject();
    }

    function applyCardResult(data) {
      if (!state.subject || !data) return;
      if (data.student_id && String(data.student_id) !== state.subject.studentId) return;
      if (data.card_id) state.subject.cardId = String(data.card_id);
      if (data.card_number) state.subject.number = String(data.card_number);
      if (data.status) state.subject.status = String(data.status);
    }

    function successText(action, data) {
      if (action === "CARD_CREATE") return "Carte émise. " + (data.card_number || "") + ". État : " + (data.status || "active") + ".";
      if (action === "CARD_LOST") return "Perte enregistrée.";
      if (action === "CARD_REPLACE") return "Carte remplacée. L'ancienne carte n'est plus valide.";
      if (action === "CARD_REPRINT") return "Réimpression autorisée.";
      if (action === "CARD_REVOKE") return "Carte révoquée. Elle n'est plus active.";
      return "";
    }

    function setCardBusy(busy) {
      state.cardBusy = busy;
      cardSubmit.disabled = busy;
      manage.querySelectorAll("[data-manage]").forEach(function (item) { item.disabled = busy; });
    }

    function showMenu() {
      stopCamera(host);
      state.mode = "";
      state.personId = "";
      state.humanConfirmed = false;
      state.cardAction = "";
      banner.hidden = true;
      form.hidden = true;
      if (humanForm) humanForm.hidden = true;
      cardForm.hidden = true;
      back.hidden = true;
      people.innerHTML = "";
      result.textContent = "";
      modes.hidden = false;
      manage.hidden = false;
      submit.disabled = false;
    }

    function choose(mode) {
      if (readSchool() !== state.schoolId) loadContext();
      stopCamera(host);
      state.mode = mode;
      state.personId = "";
      state.humanConfirmed = false;
      state.cardAction = "";
      if (humanForm) humanForm.hidden = true;
      modes.hidden = true;
      manage.hidden = true;
      cardForm.hidden = true;
      banner.hidden = false;
      banner.textContent = MODES[mode].banner;
      form.hidden = false;
      back.hidden = false;
      people.innerHTML = "";
      result.textContent = "";
      locationField.hidden = !needsLocation(mode);
      paintLocation();
    }

    function openCard(action) {
      var spec = CARD_ACTIONS[action];
      if (!spec || state.cardBusy) return;
      state.cardAction = action;
      modes.hidden = true;
      manage.hidden = true;
      form.hidden = true;
      banner.hidden = true;
      cardForm.hidden = false;
      back.hidden = false;
      cardTitle.textContent = spec.title;
      reasonField.hidden = !spec.reason;
      reasonInput.value = "";
      confirmInput.checked = false;
      cardSubmit.textContent = spec.submit;
      result.textContent = "";
      paintSubject();
    }

    function cardPayload(action, reason) {
      var subject = state.subject;
      if (action === "CARD_CREATE") {
        return { path: "/native/scan-control/cards/issue", body: { student_id: subject.studentId } };
      }
      if (action === "CARD_LOST") {
        var loss = { student_id: subject.studentId, reason: reason };
        if (subject.cardId) loss = { student_id: subject.studentId, card_id: subject.cardId, reason: reason };
        return { path: "/native/scan-control/cards/loss", body: loss };
      }
      if (action === "CARD_REPLACE") {
        return { path: "/native/scan-control/cards/" + subject.cardId + "/canonical-replace", body: {} };
      }
      if (action === "CARD_REPRINT") {
        return { path: "/native/scan-control/cards/" + subject.cardId + "/reprint", body: { reason: reason } };
      }
      return { path: "/native/scan-control/cards/" + subject.cardId + "/revoke", body: { reason: reason } };
    }

    function paintModes(data) {
      var owned = new Set(data && data.capabilities ? data.capabilities : []);
      modes.innerHTML = OFFICIAL_MODES.map(function (mode) {
        return '<button type="button" data-mode="' + mode + '">' + MODES[mode].label + "</button>";
      }).join("");
      modes.querySelectorAll("[data-mode]").forEach(function (button) {
        button.addEventListener("click", function () { choose(button.getAttribute("data-mode")); });
      });
      manage.innerHTML = MANAGE.filter(function (item) { return owned.has(item[0]); }).map(function (item) {
        return '<button type="button" data-manage="' + item[0] + '">' + item[1] + "</button>";
      }).join("");
      manage.querySelectorAll("[data-manage]").forEach(function (button) {
        button.addEventListener("click", function () {
          var action = button.getAttribute("data-manage");
          if (action === "CARD_PRINT") {
            result.textContent = "L'impression physique n'est pas faite depuis cet écran.";
            return;
          }
          openCard(action);
        });
      });
      if (state.cardBusy) setCardBusy(true);
    }

    function loadContext() {
      var generation = ++fetchGeneration;
      var schoolAtRequest = readSchool();
      state.schoolId = schoolAtRequest;
      state.subject = null;
      cardGeneration += 1;
      state.locations = [];
      state.locationError = "";
      locationSelect.value = "";
      Promise.all([
        request("/native/scan-control/capabilities"),
        request("/native/scan-control/locations")
      ]).then(function (pair) {
        if (generation !== fetchGeneration) return;
        if (readSchool() !== schoolAtRequest) {
          loadContext();
          return;
        }
        state.locations = pair[1] && Array.isArray(pair[1].locations) ? pair[1].locations : [];
        state.locationError = "";
        paintModes(pair[0]);
        paintLocation();
      }).catch(function (error) {
        if (generation !== fetchGeneration) return;
        if (error.offline) {
          result.innerHTML = offlinePanel();
          return;
        }
        state.locations = [];
        state.locationError = "failed";
        paintModes({ capabilities: [] });
        result.textContent = error && error.message ? error.message : "Les lieux de scan n'ont pas pu être chargés.";
        paintLocation();
      });
    }

    back.addEventListener("click", showMenu);

    form.addEventListener("submit", function (event) {
      event.preventDefault();
      if (root.navigator && root.navigator.onLine === false) {
        result.innerHTML = offlinePanel();
        return;
      }
      if (needsLocation(state.mode) && readSchool() !== state.schoolId) {
        result.textContent = "Le contexte a changé. Choisissez à nouveau le lieu.";
        loadContext();
        return;
      }
      var qr = new root.FormData(form).get("qr");
      var body = { mode: state.mode === "pickup" ? "pickup" : state.mode, qr_payload: String(qr || "") };
      if (needsLocation(state.mode)) {
        var locationId = locationSelect.value;
        if (!locationId) {
          result.textContent = "Choisissez le lieu du scan.";
          return;
        }
        body.location_id = locationId;
      }
      if (state.personId) body.authorized_person_id = state.personId;
      if (state.humanConfirmed) {
        body.human_confirmed = true;
        if (!state.proofToken) {
          result.textContent = "La remise exige la preuve du scan préparatoire.";
          state.humanConfirmed = false;
          return;
        }
        body.proof_token = state.proofToken;
      }
      var schoolAtScan = state.schoolId;
      result.textContent = "Contrôle en cours";
      request("/native/scan-control/scan", { method: "POST", body: JSON.stringify(body) }).then(function (data) {
        if (readSchool() !== schoolAtScan || state.schoolId !== schoolAtScan) return;
        rememberSubject(data);
        if (data && data.result === "PICKUP_CHOICE") {
          renderPeople(data.people, people);
          people.querySelectorAll("[data-person]").forEach(function (button) {
            button.addEventListener("click", function () {
              state.personId = button.getAttribute("data-person");
              state.humanConfirmed = false;
              state.proofToken = "";
              if (humanCheck) humanCheck.checked = false;
              if (humanForm) humanForm.hidden = false;
              result.textContent = "Préparation du contrôle.";
              var prepare = { mode: "pickup", qr_payload: String(qr || ""), location_id: body.location_id, authorized_person_id: state.personId };
              request("/native/scan-control/scan", { method: "POST", body: JSON.stringify(prepare) }).then(function (prepared) {
                if (readSchool() !== schoolAtScan) return;
                if (!prepared || prepared.result !== "NEED_HUMAN_CONFIRMATION" || !prepared.proof_token) {
                  state.proofToken = "";
                  result.textContent = resultMessage(prepared);
                  return;
                }
                state.proofToken = String(prepared.proof_token);
                result.textContent = "Personne choisie. Confirmez le contrôle humain. Le QR seul n’autorise pas la sortie.";
              }).catch(function (error) {
                state.proofToken = "";
                result.innerHTML = error.offline ? offlinePanel() : "<p>" + error.message + "</p>";
              });
            });
          });
          return;
        }
        state.humanConfirmed = false;
        result.textContent = resultMessage(data);
      }).catch(function (error) {
        if (readSchool() !== schoolAtScan) return;
        result.innerHTML = error.offline ? offlinePanel() : "<p>" + error.message + "</p>";
      });
    });

    cardForm.addEventListener("submit", function (event) {
      event.preventDefault();
      if (state.cardBusy) return;
      var action = state.cardAction;
      var spec = CARD_ACTIONS[action];
      if (!spec) return;
      if (root.navigator && root.navigator.onLine === false) {
        result.innerHTML = offlinePanel();
        return;
      }
      if (readSchool() !== state.schoolId) {
        state.subject = null;
        cardGeneration += 1;
        result.textContent = "Le contexte a changé. Vérifiez à nouveau la carte.";
        paintSubject();
        return;
      }
      if (!state.subject || !state.subject.studentId) {
        result.textContent = "Aucun élève n'est sélectionné. Vérifiez d'abord sa carte.";
        return;
      }
      if (state.subject.schoolId !== state.schoolId || state.subject.schoolId !== readSchool()) {
        state.subject = null;
        cardGeneration += 1;
        result.textContent = "Le contexte a changé. Vérifiez à nouveau la carte.";
        paintSubject();
        return;
      }
      if (!isUuid(state.subject.studentId)) {
        result.textContent = "Identifiant d'élève invalide.";
        return;
      }
      if (spec.card && !state.subject.cardId) {
        result.textContent = "Aucune carte n'est sélectionnée. Vérifiez d'abord la carte.";
        return;
      }
      if (spec.card && !isUuid(state.subject.cardId)) {
        result.textContent = "Identifiant de carte invalide.";
        return;
      }
      var reason = reasonInput.value.trim();
      if (spec.reason && !reason) {
        result.textContent = "Le motif est obligatoire.";
        return;
      }
      if (spec.reason && reason.length > 500) {
        result.textContent = "Le motif est trop long.";
        return;
      }
      if (!confirmInput.checked) {
        result.textContent = "Confirmez l'opération.";
        return;
      }
      var payload = cardPayload(action, reason);
      var generation = cardGeneration;
      var schoolAt = state.schoolId;
      var studentAt = state.subject.studentId;
      var op = ++cardOp;
      setCardBusy(true);
      request(payload.path, { method: "POST", body: JSON.stringify(payload.body) }).then(function (data) {
        if (generation !== cardGeneration) return;
        if (readSchool() !== schoolAt || state.schoolId !== schoolAt) return;
        if (!state.subject || state.subject.studentId !== studentAt || state.subject.schoolId !== schoolAt) return;
        if (data && (data.status === "failed" || data.error)) {
          result.textContent = data.error || data.message || "Opération refusée.";
          return;
        }
        applyCardResult(data);
        result.textContent = successText(action, data || {});
        paintSubject();
      }).catch(function (error) {
        if (generation !== cardGeneration) return;
        if (readSchool() !== schoolAt) return;
        if (error.offline) {
          result.innerHTML = offlinePanel();
          return;
        }
        result.textContent = error.message || "Opération refusée.";
      }).finally(function () {
        if (op === cardOp) setCardBusy(false);
      });
    });

    if (cameraButton) cameraButton.addEventListener("click", function () {
      var box = host.querySelector("[data-scan-camera]");
      if (box && !box.hidden) {
        stopCamera(host);
        return;
      }
      startCamera(host, cameraStatus, function (payload) {
        var input = form.querySelector("[name=qr]");
        if (input) input.value = payload;
        if (typeof form.requestSubmit === "function") form.requestSubmit();
        else form.dispatchEvent(new Event("submit", { cancelable: true, bubbles: true }));
      });
    });

    if (humanForm) humanForm.addEventListener("submit", function (event) {
      event.preventDefault();
      if (!humanCheck || !humanCheck.checked) {
        result.textContent = "La confirmation humaine est obligatoire.";
        return;
      }
      state.humanConfirmed = true;
      if (typeof form.requestSubmit === "function") form.requestSubmit();
      else form.dispatchEvent(new Event("submit", { cancelable: true, bubbles: true }));
    });

    function paintDelegations() {
      delegations.innerHTML = '<section class="scan-control-delegations"><h3>Gestion des autorisations</h3><p>L’activation du module, l’utilisation du scanner et la gestion des délégations restent séparées.</p><form id="scanControlFeeModule"><label><input type="checkbox" name="enabled"> Activer le contrôle des frais pour l’établissement</label><button type="submit">Enregistrer l’activation</button></form><form id="scanControlFeeGrant"><label>Profil de l’agent<input name="profile_id" required autocomplete="off"></label><label>Autorisation<select name="capability"><option value="SCAN_FEES">Utiliser le scanner</option><option value="SCAN_FEES_DELEGATE">Gérer les autorisations</option></select></label><label>Action<select name="action"><option value="assign">Accorder</option><option value="revoke">Retirer immédiatement</option></select></label><label>Fin éventuelle<input name="expires_at" type="datetime-local"></label><label>Classes<input name="class_ids" placeholder="Identifiants séparés par une virgule"></label><label>Campagne<input name="campaign_id"></label><label>Motif<input name="reason" required maxlength="500"></label><button type="submit">Enregistrer la délégation</button></form><div id="scanControlFeeList" role="status"></div></section>';
      var moduleForm = delegations.querySelector("#scanControlFeeModule");
      var grantForm = delegations.querySelector("#scanControlFeeGrant");
      var list = delegations.querySelector("#scanControlFeeList");
      request("/native/scan-control/fees/module").then(function (data) {
        var box = moduleForm.querySelector("[name=enabled]");
        if (box) box.checked = !!(data && data.enabled);
      }).catch(function () {
        list.textContent = "L’état du module n’a pas pu être lu.";
      });
      request("/native/scan-control/fees/delegations").then(function (rows) {
        var items = Array.isArray(rows) ? rows : [];
        list.innerHTML = items.map(function (row) {
          var end = row.revoked_at ? "retirée" : (row.expires_at ? "jusqu’au " + row.expires_at : "sans échéance");
          return "<p>" + escapeHtml(row.operator_name || row.profile_id) + " · " + escapeHtml(row.capability) + " · " + escapeHtml(end) + "</p>";
        }).join("") || "<p>Aucune délégation.</p>";
      }).catch(function (error) {
        list.textContent = error.message || "Historique inaccessible.";
      });
      moduleForm.addEventListener("submit", function (event) {
        event.preventDefault();
        var enabled = moduleForm.querySelector("[name=enabled]").checked;
        request("/native/scan-control/fees/module", { method: "POST", body: JSON.stringify({ enabled: enabled }) }).then(function () {
          result.textContent = enabled ? "Contrôle des frais activé." : "Contrôle des frais désactivé.";
        }).catch(function (error) {
          result.textContent = error.message || "Activation refusée.";
        });
      });
      grantForm.addEventListener("submit", function (event) {
        event.preventDefault();
        var data = new root.FormData(grantForm);
        var classes = String(data.get("class_ids") || "").split(",").map(function (item) { return item.trim(); }).filter(Boolean);
        var body = {
          profile_id: String(data.get("profile_id") || ""),
          capability: String(data.get("capability") || "SCAN_FEES"),
          action: String(data.get("action") || "assign"),
          reason: String(data.get("reason") || "")
        };
        var expires = String(data.get("expires_at") || "");
        var campaign = String(data.get("campaign_id") || "");
        if (expires) body.expires_at = expires;
        if (classes.length) body.class_ids = classes;
        if (campaign) body.campaign_id = campaign;
        request("/native/scan-control/fees/delegations", { method: "POST", body: JSON.stringify(body) }).then(function () {
          result.textContent = "Délégation enregistrée.";
          paintDelegations();
        }).catch(function (error) {
          result.textContent = error.message || "Délégation refusée.";
        });
      });
    }

    if (options.panel === "delegations") paintDelegations();
    if (options.mode && MODES[options.mode]) choose(options.mode);

    paintModes({ capabilities: [] });
    if (root.navigator && root.navigator.onLine === false) {
      result.innerHTML = offlinePanel();
      return;
    }

    loadContext();
  }

  function renderDelegations(hostId) {
    render(hostId, { panel: "delegations" });
  }

  if (root.addEventListener && !root.__schoolsafeScanCameraBound) {
    root.__schoolsafeScanCameraBound = true;
    root.addEventListener("pagehide", function () { stopCamera(null); });
  }

  root.SchoolSafeScanControl = { render: render, renderDelegations: renderDelegations };
})(window);
