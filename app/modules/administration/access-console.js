/* Consultation du bootstrap natif. Les décisions métier restent côté serveur. */
(function (root) {
  "use strict";

  var DOMAINS = {
    school: "École · SchoolSafe ID", staff: "Personnel · StaffID", roles: "Rôles et accès",
    security: "Sécurité · Pass / Guardian", finance: "Finance", pedagogy: "Pédagogie",
    palmarques: "Pédagogie · Palmarès", pilotage: "Pilotage", reports: "Contrôle et rapports",
    cards: "Cartes · SchoolSafe Control", safe: "JASPE", session: "Session · SchoolSafe ID",
    email: "Communication", notification: "Communication", canteen: "Cantine", infirmary: "Infirmerie"
  };
  var SCOPES = {
    school: "École", own: "Soi-même", own_children: "Enfants rattachés",
    assigned_classes: "Classes affectées", assigned_subjects: "Matières affectées",
    assigned_portal: "Portail affecté", device_managed: "Appareils gérés", none: "Sans périmètre"
  };
  function escape(value) {
    return String(value == null ? "" : value).replace(/[&<>"']/g, function (char) {
      return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" }[char];
    });
  }
  function permissionScopes(user, code) {
    var access = root.SchoolSafeAccess;
    var exceptions = (user.permissionExceptions || []).filter(function (item) {
      return item.permission === code && item.effect === "allow";
    });
    var scopes = access.normalizeScopes(user.scopes).filter(function (scope) { return scope.permission === code; });
    exceptions.forEach(function (item) { scopes = scopes.concat(access.normalizeScopes(item.scopes).filter(function (scope) { return scope.permission === code; })); });
    return scopes.filter(function (scope, index, all) {
      return all.findIndex(function (other) { return other.type === scope.type && other.target === scope.target; }) === index;
    });
  }
  function render(container, user, catalog, refresh) {
    var access = root.SchoolSafeAccess;
    var codes = new Set(catalog.map(function (permission) { return permission.code; }));
    // Un droit inconnu du catalogue ne disparaît pas silencieusement de la lecture.
    var permissions = catalog.slice();
    (user.permissions || []).concat(user.deniedPermissions || [], (user.permissionExceptions || []).map(function (item) { return item.permission; })).forEach(function (code) {
      if (!codes.has(code)) { permissions.push({ code: code, label: "Permission hors catalogue local" }); codes.add(code); }
    });
    var rows = permissions.map(function (permission) {
      var denied = access.explicitDeny(user, permission.code);
      var granted = access.canAccess(user, permission.code);
      var scopes = permissionScopes(user, permission.code);
      var domain = permission.code.split(".")[0];
      var state = denied ? "denied" : granted ? "granted" : "absent";
      return { code: permission.code, label: permission.label, domain: DOMAINS[domain] || domain,
        state: state, status: denied ? "Refus explicite" : granted ? "Attribuée" : "Non attribuée",
        scopes: denied || !granted ? "—" : scopes.map(function (scope) {
          return (SCOPES[scope.type] || scope.type) + (scope.target ? " · " + scope.target : "");
        }).join(" / ") || "Aucun périmètre transmis" };
    });
    container.innerHTML = '<div class="native-access-summary"><div><span>Compte connecté · lecture serveur</span><h3>' +
      escape(user.profile.display_name) + '</h3><p>' + escape(user.school && user.school.name) + '</p><p>Rôles : ' +
      (user.roles || []).map(escape).join(", ") + '</p></div><button type="button" id="refreshNativeAccess" class="ss-button ss-button--secondary">Actualiser les droits</button></div>' +
      '<p>Les rôles regroupent les permissions. Chaque permission possède un périmètre ; un refus explicite reste prioritaire. Le serveur vérifie aussi les conditions propres à chaque action.</p>' +
      '<p class="native-access-boundary">Consultation des droits du compte connecté. La modification des attributions n’est pas encore disponible.</p>' +
      '<div class="native-access-filters"><label>Rechercher un droit ou un module<input id="accessPermissionSearch" type="search" autocomplete="off"></label>' +
      '<label>État<select id="accessPermissionState"><option value="all">Tous les droits</option><option value="granted">Attribués</option><option value="denied">Refus explicites</option><option value="absent">Non attribués</option></select></label></div>' +
      '<p id="accessPermissionCount" role="status"></p><div class="native-access-table"><table><caption>Permissions du compte dans l’école courante</caption><thead><tr><th>Module / service</th><th>Permission</th><th>État</th><th>Périmètres transmis</th></tr></thead><tbody id="nativeAccessRows"></tbody></table></div>';
    function filterRows() {
      var query = container.querySelector("#accessPermissionSearch").value.trim().toLocaleLowerCase("fr");
      var state = container.querySelector("#accessPermissionState").value;
      var visible = rows.filter(function (row) {
        return (state === "all" || row.state === state) && (!query || [row.code, row.label, row.domain, row.scopes].join(" ").toLocaleLowerCase("fr").includes(query));
      });
      container.querySelector("#accessPermissionCount").textContent = visible.length + " permission(s) affichée(s)";
      container.querySelector("#nativeAccessRows").innerHTML = visible.length ? visible.map(function (row) {
        return '<tr data-access-permission="' + escape(row.code) + '"><td data-label="Module">' + escape(row.domain) + '</td><td data-label="Permission"><b>' + escape(row.label) + '</b><code>' + escape(row.code) + '</code></td><td data-label="État"><span class="native-access-state" data-state="' + row.state + '">' + row.status + '</span></td><td data-label="Périmètres">' + escape(row.scopes) + '</td></tr>';
      }).join("") : '<tr><td colspan="4">Aucun droit ne correspond à ces filtres.</td></tr>';
    }
    container.querySelector("#refreshNativeAccess").addEventListener("click", refresh);
    container.querySelector("#accessPermissionSearch").addEventListener("input", filterRows);
    container.querySelector("#accessPermissionState").addEventListener("change", filterRows);
    filterRows();
  }
  root.SchoolSafeNativeAccessConsole = { render: render };
})(window);
