(function () {
    'use strict';
    var catalog = document.getElementById('jaspe-resources');
    if (!catalog) return;
    var entries = JSON.parse(catalog.textContent).resources;
    var select = document.getElementById('jaspe-resource');
    var info = document.getElementById('jaspe-resource-info');
    var link = document.getElementById('jaspe-resource-link');
    var title = document.getElementById('jaspe-title');
    var content = document.getElementById('jaspe-content');
    var status = document.getElementById('jaspe-status');
    function chosen() { return entries.find(function (r) { return r.id === select.value; }); }
    function update() {
        var r = chosen();
        info.textContent = r.subject + ' | ' + r.level + ' | ' + r.objective;
        link.href = r.url;
    }
    entries.forEach(function (r) {
        var option = document.createElement('option');
        option.value = r.id; option.textContent = r.title; select.appendChild(option);
    });
    select.addEventListener('change', update);
    document.getElementById('jaspe-resource-draft').addEventListener('click', function () {
        var r = chosen();
        if ((title.value.trim() || content.value.trim()) && !window.confirm('Remplacer le brouillon actuel ?')) return;
        title.value = r.title;
        content.value = r.draft + '\n\nRessource facultative (site externe) : ' + r.url +
            '\nSans Internet : realise les representations sur papier.';
        title.dispatchEvent(new Event('input', {bubbles:true}));
        content.dispatchEvent(new Event('input', {bubbles:true}));
        status.textContent = 'Brouillon prepare. Validation de l\'enseignant requise avant publication.';
    });
    update();
})();
