/* Preserve every control and handler, including after the native preview rerenders. */
(function () {
  function organize() {
    var panel = document.getElementById('PC');
    if (!panel) return;
    if (!document.getElementById('ss-design-toggle')) {
      var toggle = document.createElement('button');
      toggle.type = 'button'; toggle.id = 'ss-design-toggle';
      toggle.addEventListener('click', function () { document.body.classList.toggle('ss-design-closed'); label(); });
      panel.appendChild(toggle);
    }
    label();
    Array.from(panel.children).forEach(function (node) {
      var group = 'personalization';
      if (node.id === 'ss-design-toggle') group = 'designbar';
      else if (node.querySelector && node.querySelector('#ss-student-sel')) group = 'selector';
      else if (node.id === 'ss-model-manager') group = 'models';
      else if (node.id === 'ss-card-studio' || node.id === 'ss-type-label') group = 'preview';
      else if (node.classList.contains('ss-production')) group = 'production';
      else if (node.querySelector('#ss-layout-physical')) group = 'layout';
      else if (node.classList.contains('controls') && node.querySelector('button')) group = 'catalogue';
      node.setAttribute('data-studio-group', group);
    });
    // Telephone d'abord : choisir l'eleve, voir la carte, preparer le dossier ; le design est replie.
    var order = {selector:10, preview:20, production:30, designbar:40, models:50, catalogue:60, personalization:70, layout:80};
    var children = Array.from(panel.children);
    var sorted = children.slice().sort(function (a, b) {
      return order[a.getAttribute('data-studio-group')] - order[b.getAttribute('data-studio-group')];
    });
    if (sorted.some(function (node, index) { return node !== children[index]; })) {
      sorted.forEach(function (node) { panel.appendChild(node); });
    }
  }
  function label() {
    var toggle = document.getElementById('ss-design-toggle');
    if (toggle) {
      var closed = document.body.classList.contains('ss-design-closed');
      toggle.textContent = closed ? 'Design de la carte ▸' : 'Design de la carte ▾';
      toggle.setAttribute('aria-expanded', closed ? 'false' : 'true');
    }
  }
  function tuckAdvanced() {
    // Outils d'essai (dossiers JSON, photo locale, export) : conserves, replies en bas de page.
    var file = document.getElementById('data-file');
    var row = file && file.closest('.controls');
    var main = document.querySelector('main');
    if (!row || !main || row.closest('#ss-studio-advanced')) return;
    var details = document.createElement('details');
    details.id = 'ss-studio-advanced';
    var summary = document.createElement('summary');
    summary.textContent = 'Outils avancés';
    details.appendChild(summary);
    var lookup = document.getElementById('native-lookup');
    var exportButton = document.getElementById('export-png');
    if (exportButton && lookup) { exportButton.type = 'button'; lookup.insertBefore(exportButton, document.getElementById('native-credential-status')); }
    details.appendChild(row);
    main.appendChild(details);
  }
  function start() {
    document.body.classList.add('ss-studio-harmonized');
    if (window.matchMedia && window.matchMedia('(max-width: 700px)').matches) document.body.classList.add('ss-design-closed');
    tuckAdvanced();
    organize();
    var panel = document.getElementById('PC');
    if (panel) new MutationObserver(organize).observe(panel, {childList:true});
    var header = document.querySelector('body > header');
    if (header && !document.getElementById('ss-studio-home')) {
      var home = document.createElement('a');
      home.id = 'ss-studio-home'; home.href = '/Modules.php?modname=misc/Portal.php';
      home.title = 'Retour au tableau de bord'; home.setAttribute('aria-label', home.title);
      var arrow = document.createElement('img');
      arrow.src = '/assets/themes/SchoolSafeCodex/navigation-icons/ArrowLeft.svg'; arrow.alt = '';
      home.appendChild(arrow); header.appendChild(home);
    }
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
  else start();
})();
