(function() {
  'use strict';
  var records = [{name:'Élève exemple A',level:'Exemple 1'}, {name:'Élève exemple B',level:'Exemple 2'}];
  if (document.body.dataset.previewList === 'users') {
    records = [{name:'Utilisateur exemple A',level:'Enseignant'},
      {name:'Utilisateur exemple B',level:'Administrateur'},
      {name:'Utilisateur exemple C',level:'Parent'},
      {name:'Utilisateur exemple D',level:'Sans accès'}];
  }
  var query = document.getElementById('query'), level = document.getElementById('level');
  var detail = document.getElementById('detail'), trigger;
  function render() {
    detail.hidden = true;
    var body = document.getElementById('rows'); body.replaceChildren();
    var filtered = records.filter(function(row) {
      return (!level.value || row.level === level.value) && row.name.toLocaleLowerCase('fr').includes(query.value.toLocaleLowerCase('fr'));
    });
    filtered.forEach(function(row) {
      var tr = document.createElement('tr');
      [row.name,row.level].forEach(function(value) {var cell=document.createElement('td');cell.textContent=value;tr.append(cell);});
      var cell = document.createElement('td'), button = document.createElement('button');
      button.type='button'; button.className='button'; button.textContent='Consulter';
      button.setAttribute('aria-label','Consulter '+row.name);
      button.addEventListener('click',function() {
        trigger=button; document.getElementById('detail-name').textContent=row.name;
        document.getElementById('detail-level').textContent=row.level; detail.hidden=false; detail.focus();
      });
      cell.append(button);tr.append(cell);body.append(tr);
    });
    if(!filtered.length) {var tr=document.createElement('tr'),cell=document.createElement('td');cell.colSpan=3;cell.textContent='Aucun exemple correspondant.';tr.append(cell);body.append(tr);}
    document.getElementById('count').textContent=filtered.length+' dossier(s) fictif(s)';
  }
  document.getElementById('close').addEventListener('click',function(){detail.hidden=true;if(trigger&&trigger.isConnected)trigger.focus();});
  query.addEventListener('input',render);level.addEventListener('change',render);render();
  if(window.lucide)window.lucide.createIcons();
})();
