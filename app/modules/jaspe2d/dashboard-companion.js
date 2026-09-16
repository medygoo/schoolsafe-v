/* One existing JASPE renderer, moved between its bust and full-body hosts.
 * Conversation and authorization remain owned by SafeAssistant / Access Law. */
(function (global) {
  "use strict";
  var initialized = false;
  var showcase = null;
  var dialog, character, launcher, fullStage, input, log, mic, status;
  var audioMode = false;
  var recognition = null;
  var returnFocus = null;
  var renderedResponse = "";
  var lastAnimation = "";
  var lastSpoken = "";

  function allowed() {
    return document.body.classList.contains("screen-workspace") &&
      !!global.SafeAssistant && global.SafeAssistant.isAllowed();
  }

  function intent(kind) {
    if (showcase && allowed()) showcase.dispatch({ kind: kind, source: "dashboard-ui" });
  }

  function setStatus(message) {
    status.textContent = message || "";
    status.hidden = !message;
    var heroStatus = document.getElementById("jaspeHeroAudioStatus");
    heroStatus.textContent = message || "";
    heroStatus.hidden = !message || dialog.open;
  }

  function stopListening() {
    var previous = recognition;
    recognition = null;
    if (previous) {
      previous.onresult = previous.onerror = previous.onend = null;
      try { previous.abort(); } catch (e) { /* already stopped */ }
    }
    mic.setAttribute("aria-pressed", "false");
    mic.setAttribute("aria-label", "Démarrer l’écoute");
    document.getElementById("jaspeHeroMic").setAttribute("aria-pressed", "false");
  }

  function setMode(audio) {
    audioMode = audio;
    stopListening();
    if (global.speechSynthesis) global.speechSynthesis.cancel();
    document.querySelectorAll("[data-jaspe-mode]").forEach(function (button) {
      button.setAttribute("aria-pressed", String((button.dataset.jaspeMode === "audio") === audio));
    });
    var supported = !!(global.SpeechRecognition || global.webkitSpeechRecognition);
    mic.hidden = !audio;
    mic.disabled = !supported;
    setStatus(audio ? (supported ? "Appuyez sur le micro pour parler." : "L’écoute n’est pas disponible dans ce navigateur. Vous pouvez écrire à Jaspe.") : "");
    // Do not speak older or another account's messages when entering audio mode.
    lastSpoken = global.SafeAssistant.getCurrentMessage();
  }

  function submit(text) {
    text = String(text || "").trim();
    if (!text || !allowed()) return;
    input.value = "";
    stopListening();
    renderedResponse = null;
    lastSpoken = "";
    global.SafeAssistant.openWithQuery(text);
  }

  function startListening() {
    if (recognition) { stopListening(); setStatus("Écoute arrêtée."); return; }
    var Recognition = global.SpeechRecognition || global.webkitSpeechRecognition;
    if (!Recognition || !allowed()) return;
    if (global.speechSynthesis) global.speechSynthesis.cancel();
    recognition = new Recognition();
    recognition.lang = "fr-FR";
    recognition.interimResults = false;
    recognition.onresult = function (event) {
      if (!allowed()) return;
      submit(event.results[0][0].transcript);
      setStatus("Message envoyé.");
    };
    recognition.onerror = function () {
      stopListening();
      intent("error");
      setStatus("Micro indisponible ou accès refusé. Vous pouvez continuer par écrit.");
    };
    recognition.onend = function () { stopListening(); };
    mic.setAttribute("aria-pressed", "true");
    mic.setAttribute("aria-label", "Arrêter l’écoute");
    document.getElementById("jaspeHeroMic").setAttribute("aria-pressed", "true");
    setStatus("Je vous écoute…");
    intent("listen");
    try { recognition.start(); } catch (e) {
      stopListening();
      setStatus("Impossible de démarrer le micro. Vous pouvez continuer par écrit.");
    }
  }

  function syncAccess() {
    if (!initialized) return;
    var canUse = allowed();
    document.querySelectorAll("[data-jaspe-open], [data-jaspe-audio], [data-jaspe-chat-input], [data-jaspe-chat-send], [data-bottom-nav='jaspe']").forEach(function (element) {
      element.disabled = !canUse;
    });
    document.getElementById("jaspeAccessMessage").hidden = canUse;
    if (!canUse) {
      close();
      stopListening();
      if (global.speechSynthesis) global.speechSynthesis.cancel();
      renderedResponse = "";
      lastSpoken = "";
      log.replaceChildren();
      document.querySelectorAll("[data-jaspe-chat-log]").forEach(function (element) { element.replaceChildren(); });
      document.querySelectorAll("[data-jaspe-chat-input]").forEach(function (element) { element.value = ""; });
      input.value = "";
      if (showcase) showcase.stop();
    }
  }

  function syncConversation() {
    syncAccess();
    if (!allowed()) return;
    var response = global.SafeAssistant.getCurrentMessage();
    var signature = response;
    if (signature !== renderedResponse) {
      renderedResponse = signature;
      log.replaceChildren();
      if (!response) {
        var empty = document.createElement("p");
        empty.className = "jaspe-panel__empty";
        empty.textContent = "Bonjour ! Que souhaitez-vous faire aujourd’hui ?";
        log.appendChild(empty);
      }
      if (response) {
        var line = document.createElement("p");
        line.className = "jaspe-message jaspe-message--assistant";
        var label = document.createElement("b");
        label.textContent = "Jaspe";
        var text = document.createElement("span");
        text.textContent = response;
        line.append(label, text);
        log.appendChild(line);
      }
      log.scrollTop = log.scrollHeight;
    }
    var animation = global.SafeAssistant.getAnimation();
    if (audioMode && response && response !== lastSpoken && animation !== "Listening" && animation !== "Thinking" && global.speechSynthesis) {
      lastSpoken = response;
      global.speechSynthesis.cancel();
      var utterance = new SpeechSynthesisUtterance(response);
      utterance.lang = "fr-FR";
      global.speechSynthesis.speak(utterance);
    }
    if (animation !== lastAnimation) {
      lastAnimation = animation;
      var mapping = { Idle: "idle", Listening: "listen", Thinking: "think", Wave: "explain", Agree: "success", Shrug: "refuse", TalkHandsOpen: "speak", TalkPassionately: "speak" };
      intent(mapping[animation] || "speak");
    }
  }

  function mount() {
    if (showcase || !global.SchoolSafeJaspe2d || !allowed()) return;
    showcase = global.SchoolSafeJaspe2d.mountShowcase(character, {
      transparent: true,
      surface: "workspace-bust",
      label: "Jaspe, votre assistante SchoolSafe",
      isBust: function () { return !dialog.open; },
      isVisible: function () { return allowed() && character.getClientRects().length > 0; },
      variants: [
        { pack: "pack1", key: "idle" }, { pack: "pack1", key: "wave" },
        { pack: "pack2", key: "listening", rotate: false },
        { pack: "pack2", key: "thinking", rotate: false },
        { pack: "pack2", key: "speaking", rotate: false },
        { pack: "pack3", key: "congratulate", rotate: false },
        { pack: "pack4", key: "worried", rotate: false }
      ]
    });
    if (showcase) showcase.presentationReady.then(function () { intent("explain"); });
  }

  function open(trigger, audio) {
    init();
    if (!allowed() || dialog.open) return;
    mount();
    returnFocus = trigger || document.activeElement;
    fullStage.prepend(character);
    document.body.classList.add("jaspe-is-out");
    dialog.show();
    setMode(audio === true);
    global.SafeAssistant.openWithQuery("");
    syncConversation();
    input.focus({ preventScroll: true });
    intent("explain");
  }

  function returned() {
    stopListening();
    if (global.speechSynthesis) global.speechSynthesis.cancel();
    launcher.prepend(character);
    document.body.classList.remove("jaspe-is-out");
    input.value = "";
    intent("idle");
    if (returnFocus && returnFocus.isConnected && !returnFocus.disabled && returnFocus.getClientRects().length) returnFocus.focus({ preventScroll: true });
    returnFocus = null;
  }

  function close() {
    if (dialog && dialog.open) dialog.close();
  }

  function init() {
    if (initialized) return;
    dialog = document.getElementById("jaspePanelOverlay");
    if (!dialog) return;
    initialized = true;
    character = document.getElementById("jaspeDashboardCharacter");
    launcher = document.getElementById("jaspeHeroLauncher");
    fullStage = document.getElementById("jaspeFullStage");
    input = document.getElementById("jaspePanelInput");
    log = document.getElementById("jaspePanelBody");
    mic = document.getElementById("jaspePanelMic");
    status = document.getElementById("jaspePanelStatus");
    global.SafeAssistant.setEmbeddedPresentation(true);
    global.SafeAssistant.onHistoryChange(syncConversation);
    document.getElementById("jaspeHeroMic").addEventListener("click", function () {
      if (!allowed()) return;
      if (recognition) { stopListening(); setStatus("Écoute arrêtée."); return; }
      setMode(true);
      startListening();
    });
    document.querySelectorAll("[data-jaspe-open]").forEach(function (button) {
      button.addEventListener("click", function () { open(button, button.hasAttribute("data-jaspe-audio")); });
    });
    document.querySelectorAll("[data-jaspe-open], [data-bottom-nav='jaspe']").forEach(function (button) {
      var holdTimer = 0;
      function cancelHold() { global.clearTimeout(holdTimer); }
      button.addEventListener("pointerdown", function (event) {
        if (event.button !== 0 || button.disabled) return;
        cancelHold();
        holdTimer = global.setTimeout(function () { open(button, button.hasAttribute("data-jaspe-audio")); }, 450);
      });
      ["pointerup", "pointercancel", "pointerleave", "blur"].forEach(function (name) { button.addEventListener(name, cancelHold); });
      button.addEventListener("contextmenu", function (event) { event.preventDefault(); });
    });
    document.querySelectorAll("[data-jaspe-mode]").forEach(function (button) {
      button.addEventListener("click", function () { setMode(button.dataset.jaspeMode === "audio"); });
    });
    document.getElementById("jaspePanelClose").addEventListener("click", close);
    dialog.addEventListener("close", returned);
    document.addEventListener("keydown", function (event) { if (event.key === "Escape" && dialog.open) close(); });
    document.getElementById("jaspePanelForm").addEventListener("submit", function (event) { event.preventDefault(); submit(input.value); });
    mic.addEventListener("click", startListening);
    document.querySelectorAll("[data-jaspe-chat-input], #jaspePanelInput").forEach(function (field) {
      field.addEventListener("focus", function () { intent("listen"); });
    });
    document.addEventListener("visibilitychange", function () { if (document.hidden) stopListening(); });
  }

  global.SchoolSafeJaspeDashboard = {
    open: open,
    syncAccess: syncAccess,
    refresh: function (firstName) {
      init();
      document.getElementById("jaspeHeroName").textContent = firstName || "";
      syncAccess();
      mount();
    }
  };
})(window);
