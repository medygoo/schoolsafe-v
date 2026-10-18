(function () {
  "use strict";

  var root = window;
  var assetBase = new URL('.', document.currentScript.src);

  function esc(value) {
    return String(value == null ? "" : value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#39;");
  }

  function attrs(map) {
    return Object.keys(map || {}).filter(function (key) {
      return map[key] !== false && map[key] != null;
    }).map(function (key) {
      if (map[key] === true) return esc(key);
      return esc(key) + '="' + esc(map[key]) + '"';
    }).join(" ");
  }

  function icon(name) {
    return '<i data-lucide="' + esc(name || "circle") + '" aria-hidden="true"></i>';
  }

  function button(label, options) {
    options = options || {};
    var className = "sscx-button" + (options.variant ? " sscx-button--" + options.variant : "");
    var attr = attrs(Object.assign({ type: "button", class: className, "data-codex-action": options.key || label }, options.attrs || {}));
    return "<button " + attr + ">" + (options.icon ? icon(options.icon) : "") + "<span>" + esc(label) + "</span></button>";
  }

  function badge(label, tone) {
    return '<span class="sscx-badge sscx-badge--' + esc(tone || "neutral") + '">' + esc(label) + "</span>";
  }

  function metricCard(item) {
    return '<article class="sscx-metric sscx-metric--' + esc(item.tone || "blue") + '">' +
      '<span class="sscx-metric__icon">' + icon(item.icon || "gauge") + "</span>" +
      "<div><small>" + esc(item.label) + "</small><strong>" + esc(item.value) + "</strong><span>" + esc(item.detail || "") + "</span></div>" +
      (item.status ? badge(item.status, item.statusTone || "neutral") : "") +
      "</article>";
  }

  function statusRow(item) {
    return '<tr><td><span class="sscx-row-title">' + icon(item.icon || "dot") + esc(item.label) + "</span></td>" +
      "<td>" + esc(item.owner || "") + "</td>" +
      "<td>" + badge(item.status || "A verifier", item.tone || "neutral") + "</td>" +
      "<td>" + esc(item.note || "") + "</td></tr>";
  }

  function subnav(groups) {
    return '<aside class="sscx-subnav" aria-label="Sous-fonctions"><header><span>Sous-fonctions</span><strong>Navigation du module</strong></header>' +
      (groups || []).map(function (group) {
        var actions = (group.actions || []).map(function (action) {
          return button(action.label, { icon: action.icon, variant: action.active ? "primary" : "ghost", attrs: { "data-codex-action": action.key || action.label } });
        }).join("");
        return '<section><h3>' + esc(group.label) + "</h3>" + actions + "</section>";
      }).join("") + "</aside>";
  }

  function dashboard(config) {
    config = config || {};
    var metrics = (config.metrics || []).map(metricCard).join("");
    var groups = subnav(config.groups || []);
    var rows = (config.rows || []).map(statusRow).join("");
    return '<section class="sscx-app sscx-dashboard sscx-role-' + esc(config.roleKey || "generic") + '">' +
      '<header class="sscx-hero"><div><span>' + esc(config.eyebrow || "SchoolSafe") + "</span><h1>" + esc(config.title || "Tableau de bord") + "</h1><p>" + esc(config.description || "") + "</p></div>" +
      '<div class="sscx-hero__actions">' + (config.actions || []).map(function (action) { return button(action.label, action); }).join("") + "</div></header>" +
      '<div class="sscx-layout">' + groups + '<main class="sscx-main"><section class="sscx-metrics">' + metrics + '</section>' +
      '<section class="sscx-panel"><header><div><span>' + esc(config.panelEyebrow || "Controle") + "</span><h2>" + esc(config.panelTitle || "Etat des surfaces") + "</h2></div>" + badge(config.boundary || "A raccorder", "info") + "</header>" +
      '<div class="sscx-table-wrap"><table><thead><tr><th>Ecran</th><th>Responsable</th><th>Etat</th><th>Limite</th></tr></thead><tbody>' + rows + "</tbody></table></div></section></main></div></section>";
  }

  function jaspePanel(config) {
    config = config || {};
    return '<section class="sscx-app sscx-jaspe"><div class="sscx-jaspe__stage">' +
      '<img src="' + esc(config.image || new URL('../../assets/jaspe2d/attente-v13/adossee-detouree.png', assetBase).href) + '" alt="JASPE">' +
      '<span>' + esc(config.school || "Logo ecole a raccorder") + "</span></div>" +
      '<div class="sscx-jaspe__content"><h2>' + esc(config.title || "Assistante SchoolSafe") + "</h2><p>" + esc(config.copy || "Conversation disponible selon votre acces.") + "</p>" +
      '<div class="sscx-jaspe__modes">' + button("Ecrit", { key: "jaspe-text", icon: "message-circle", variant: "primary" }) + button("Audio", { key: "jaspe-audio", icon: "mic", variant: "ghost" }) + button("Fermer", { key: "jaspe-close", icon: "x", variant: "ghost" }) + "</div></div></section>";
  }

  function mount(target, html, handlers) {
    var el = typeof target === "string" ? document.querySelector(target) : target;
    if (!el) return null;
    el.innerHTML = html;
    if (el._codexHandler) el.removeEventListener("click", el._codexHandler);
    el._codexHandler = async function (event) {
      var btn = event.target.closest("[data-codex-action]");
      if (!btn || !el.contains(btn) || btn.disabled) return;
      var key = btn.getAttribute("data-codex-action");
      var status = el.querySelector("[data-codex-status]");
      if (!status) { status = document.createElement("p"); status.setAttribute("role", "status"); status.setAttribute("data-codex-status", ""); el.appendChild(status); }
      if (!handlers || typeof handlers[key] !== "function") { status.textContent = "Fonction non raccordee. Aucune operation effectuee."; return; }
      btn.disabled = true;
      try { await handlers[key](btn); status.textContent = ""; }
      catch (error) { status.textContent = "Interaction indisponible."; }
      finally { if (btn.isConnected) btn.disabled = false; }
    };
    el.addEventListener("click", el._codexHandler);
    if (root.lucide && typeof root.lucide.createIcons === "function") root.lucide.createIcons();
    return el;
  }

  root.SchoolSafeCodexUiV1 = {
    button: button,
    badge: badge,
    metricCard: metricCard,
    subnav: subnav,
    dashboard: dashboard,
    jaspePanel: jaspePanel,
    mount: mount
  };
}());
