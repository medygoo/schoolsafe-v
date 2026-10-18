(function () {
  'use strict';
  var parent = document.getElementById('parent');
  var search = document.getElementById('search');
  var selected = new Set();
  var fixtures = [{ id:'example-a', name:'Élève exemple A', level:'Exemple 1' },
    { id:'example-b', name:'Élève exemple B', level:'Exemple 2' }];
  function render() {
    var rows = document.getElementById('student-rows');
    rows.replaceChildren();
    var list = parent.value ? fixtures.filter(function(row) {
      return row.name.toLocaleLowerCase('fr').includes(search.value.toLocaleLowerCase('fr'));
    }) : [];
    list.forEach(function(row) {
      var tr = document.createElement('tr');
      var choice = document.createElement('td');
      var label = document.createElement('label');
      var input = document.createElement('input');
      input.type = 'checkbox'; input.checked = selected.has(row.id);
      input.setAttribute('aria-label', 'Sélectionner ' + row.name);
      input.addEventListener('change', function() {
        if(input.checked) selected.add(row.id); else selected.delete(row.id);
        updateCount();
      });
      label.append(input); choice.append(label); tr.append(choice);
      [row.name,row.level].forEach(function(value) {
        var cell = document.createElement('td'); cell.textContent = value; tr.append(cell);
      });
      rows.append(tr);
    });
    if(!list.length) {
      var tr = document.createElement('tr'), cell = document.createElement('td');
      cell.colSpan = 3;
      cell.textContent = parent.value ? 'Aucun exemple correspondant.' : 'Sélectionnez d’abord un parent.';
      tr.append(cell); rows.append(tr);
    }
  }
  function updateCount() {
    document.getElementById('selection').textContent = selected.size + ' élève(s) sélectionné(s) · démonstration';
  }
  parent.addEventListener('change', function() {
    selected.clear(); search.value = ''; search.disabled = !parent.value;
    document.getElementById('parent-summary').textContent = parent.value ? 'Parent de démonstration · compte fictif' : 'Aucun parent sélectionné';
    document.getElementById('associated').textContent = parent.value ? 'Aucune association dans cet exemple.' : 'Sélectionnez un parent.';
    render(); updateCount();
  });
  search.addEventListener('input', render);
  if(window.lucide) window.lucide.createIcons();
})();
