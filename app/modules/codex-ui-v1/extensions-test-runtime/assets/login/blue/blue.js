/* ============================================================
   SchoolSafe — enchaînement des deux écrans bleus dans Rosario.

   Ce script ne fait QUE ce qu'il annonce : afficher l'accueil, ouvrir
   l'écran de connexion, revenir, montrer ou masquer le mot de passe.
   Il ne touche ni au formulaire, ni à l'authentification : le POST part
   vers index.php avec les champs natifs USERNAME et PASSWORD.
   ============================================================ */
(function () {
  "use strict";

  var host = document.getElementById("ss-blue");
  if (!host) return;

  var splash = document.getElementById("splash");
  var auth = document.getElementById("auth");
  if (!splash || !auth) return;

  function activate(target) {
    [splash, auth].forEach(function (screen) {
      screen.classList.remove("ss-blue-entering");
      screen.classList.toggle("active", screen === target);
    });
    // Rejoue l'animation d'entrée à chaque bascule.
    void target.offsetWidth;
    target.classList.add("ss-blue-entering");
  }

  function openLogin() {
    activate(auth);
    var field = document.getElementById("USERNAME");
    if (field) field.focus();
    if (window.history && window.history.replaceState) {
      window.history.replaceState(null, "", "#connexion");
    }
  }

  function openWelcome() {
    activate(splash);
    if (window.history && window.history.replaceState) {
      window.history.replaceState(null, "", "#accueil");
    }
  }

  var start = host.getAttribute("data-start") === "auth" ? auth : splash;
  activate(start);
  if (start === auth) {
    var prefill = document.getElementById("USERNAME");
    if (prefill && !prefill.value) prefill.focus();
  }

  var enter = document.getElementById("enterSplash");
  if (enter) enter.addEventListener("click", openLogin);

  var back = document.getElementById("backToSplash");
  if (back) back.addEventListener("click", openWelcome);

  var eye = document.getElementById("ssBlueEye");
  var password = document.getElementById("PASSWORD");
  if (eye && password) {
    eye.addEventListener("click", function () {
      var shown = password.getAttribute("type") === "text";
      password.setAttribute("type", shown ? "password" : "text");
      eye.setAttribute("aria-pressed", shown ? "false" : "true");
      eye.setAttribute(
        "aria-label",
        shown ? "Afficher le mot de passe" : "Masquer le mot de passe"
      );
      password.focus();
    });
  }

  // Icônes du prototype (lucide) : dessinées après coup, sans bloquer la page.
  function drawIcons() {
    if (window.lucide && typeof window.lucide.createIcons === "function") {
      window.lucide.createIcons();
    }
  }
  drawIcons();
  document.addEventListener("DOMContentLoaded", drawIcons);
  window.addEventListener("load", drawIcons);
})();
