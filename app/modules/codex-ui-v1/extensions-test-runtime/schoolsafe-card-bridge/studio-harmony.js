/* Preserve every control and handler, including after the native preview rerenders. */
(function () {
  function organize() {
    var panel = document.getElementById('PC');
    if (!panel) return;
    Array.from(panel.children).forEach(function (node) {
      var group = 'personalization';
      if (node.id === 'ss-model-manager') group = 'models';
      else if (node.id === 'ss-card-studio' || node.id === 'ss-type-label') group = 'preview';
      else if (node.classList.contains('ss-production')) group = 'production';
      else if (node.querySelector('#ss-layout-physical')) group = 'layout';
      else if (node.classList.contains('controls') && node.querySelector('button')) group = 'catalogue';
      node.setAttribute('data-studio-group', group);
    });
    var order = {models:10, catalogue:20, preview:30, personalization:40, layout:50, production:60};
    var children = Array.from(panel.children);
    var sorted = children.slice().sort(function (a, b) {
      return order[a.getAttribute('data-studio-group')] - order[b.getAttribute('data-studio-group')];
    });
    if (sorted.some(function (node, index) { return node !== children[index]; })) {
      sorted.forEach(function (node) { panel.appendChild(node); });
    }
  }
  function start() {
    document.body.classList.add('ss-studio-harmonized');
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
