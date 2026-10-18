(() => {
  'use strict';
  const model=window.SchoolSafeDetailsModel;
  const main=document.getElementById('school-content');
  document.querySelector('.skip-link').addEventListener('click',event=>{event.preventDefault();main.focus();});
  const navToggle=document.getElementById('school-nav-toggle');
  navToggle.addEventListener('click',()=>{const expanded=navToggle.getAttribute('aria-expanded')!=='true';navToggle.setAttribute('aria-expanded',String(expanded));navToggle.closest('aside').dataset.expanded=String(expanded);});
  document.getElementById('school-nav').addEventListener('click',event=>{if(event.target.closest('a')&&window.matchMedia('(max-width:700px)').matches){navToggle.setAttribute('aria-expanded','false');navToggle.closest('aside').dataset.expanded='false';main.focus();}});
  function el(tag,text,cls){const node=document.createElement(tag);if(text)node.textContent=text;if(cls)node.className=cls;return node;}
  function icon(name){const node=el('i');node.dataset.lucide=name;node.setAttribute('aria-hidden','true');return node;}
  function button(name,iconName,action){const node=el('button',null,'button');node.type='button';node.append(icon(iconName),document.createTextNode(name));node.addEventListener('click',action);return node;}
  function disabledField(label,type='text'){const wrap=el('label',label);const input=el(type==='select'?'select':type==='textarea'?'textarea':'input');if(input.tagName==='INPUT')input.type=type;input.disabled=true;if(type==='select')input.append(el('option','Non chargé'));wrap.append(input);return wrap;}
  function blankTable(columns,message){const wrap=el('div',null,'table-wrap');wrap.tabIndex=0;wrap.setAttribute('role','region');wrap.setAttribute('aria-label',columns.join(', '));const table=el('table');const head=el('thead');const row=el('tr');columns.forEach(title=>{const th=el('th',title);th.scope='col';row.append(th);});head.append(row);const body=el('tbody');const tr=el('tr');const td=el('td',message,'empty');td.colSpan=columns.length;tr.append(td);body.append(tr);table.append(head,body);wrap.append(table);return wrap;}
  function panel(title){const group=el('fieldset');group.append(el('legend',title));return group;}
  function calendar(){
    const group=panel('Vue mensuelle');group.classList.add('month-view');
    const now=new Date();let year=now.getFullYear(),month=now.getMonth(),selected='';
    const toolbar=el('div',null,'month-toolbar');const title=el('h2');title.setAttribute('aria-live','polite');
    const grid=el('div',null,'month-grid');grid.setAttribute('role','group');grid.setAttribute('aria-label','Dates du calendrier');
    const dayPanel=panel('Détail de la journée');const dayTitle=el('h2','Aucune date sélectionnée');const fields=el('div',null,'fields');
    ['Journée complète','Minutes de présence','Bloc','Numéro de rotation'].forEach((name,index)=>fields.append(disabledField(name,index===0?'checkbox':index===2?'select':'number')));
    dayPanel.append(dayTitle,el('p','Données de journée, événements et devoirs non chargés.','empty'),fields);
    function draw(){
      title.textContent=new Intl.DateTimeFormat('fr',{month:'long',year:'numeric'}).format(new Date(year,month,1));grid.replaceChildren();
      for(const day of ['Lun','Mar','Mer','Jeu','Ven','Sam','Dim'])grid.append(el('div',day,'weekday'));
      for(const item of model.monthDays(year,month)){
        const cell=el('button',String(item.day),'day'+(item.inMonth?'':' outside'));cell.type='button';cell.dataset.date=item.iso;
        cell.setAttribute('aria-label',new Intl.DateTimeFormat('fr',{dateStyle:'full'}).format(new Date(item.iso+'T12:00:00')));
        cell.setAttribute('aria-pressed',String(item.iso===selected));
        cell.addEventListener('click',()=>{selected=item.iso;dayTitle.textContent=new Intl.DateTimeFormat('fr',{dateStyle:'full'}).format(new Date(selected+'T12:00:00'));draw();grid.querySelector('[data-date="'+selected+'"]').focus();});grid.append(cell);
      }
    }
    function shift(delta){const date=new Date(year,month+delta,1);year=date.getFullYear();month=date.getMonth();draw();}
    const prev=button('Mois précédent','chevron-left',()=>shift(-1));prev.classList.add('icon-button');prev.setAttribute('aria-label','Mois précédent');prev.title='Mois précédent';prev.lastChild.remove();
    const next=button('Mois suivant','chevron-right',()=>shift(1));next.classList.add('icon-button');next.setAttribute('aria-label','Mois suivant');next.title='Mois suivant';next.lastChild.remove();
    toolbar.append(prev,title,next);group.append(el('p','Prévisualisation des dates uniquement · aucune présence déduite.','empty'),toolbar,grid,dayPanel);
    // Keep the existing native-derived forms below the month navigator.
    main.querySelector('fieldset').before(group);draw();
  }
  function fields(){
    const type=main.querySelector('#fields-0-1');const options=main.querySelector('#fields-0-2');const defaultValue=main.querySelector('#fields-0-3');
    type.disabled=false;type.replaceChildren(el('option','Choisir un type'));type.options[0].value='';
    model.fieldTypes.forEach(([value,label])=>{const option=el('option',label);option.value=value;type.append(option);});
    const specimen=panel('Aperçu du champ');const mount=el('div',null,'fields');specimen.append(mount);main.querySelector('fieldset').after(specimen);
    function draw(){
      const value=type.value;options.closest('label').hidden=!model.hasOptions(value);options.disabled=false;defaultValue.disabled=false;
      mount.replaceChildren();if(!value){mount.append(el('p','Aucun type sélectionné.'));return;}
      const label=el('label','Valeur de prévisualisation');const input=el(value==='textarea'?'textarea':['select','autos','exports','multiple'].includes(value)?'select':'input');
      if(input.tagName==='INPUT')input.type=({numeric:'number',date:'date',radio:'checkbox',files:'file'})[value]||'text';
      if(input.tagName==='SELECT'){input.multiple=value==='multiple';const entries=options.value.split(/\r?\n/).map(text=>text.trim()).filter(Boolean);(entries.length?entries:['Aucune option saisie']).forEach(text=>input.append(el('option',text)));}
      input.disabled=value==='files';label.append(input);mount.append(label);
    }
    type.addEventListener('change',draw);options.addEventListener('input',draw);draw();
    const info=el('p','Aperçu local uniquement · aucune colonne créée ou modifiée.','demo');specimen.prepend(info);
  }
  function configuration(){
    const original=[...main.querySelectorAll(':scope > fieldset')];const names=['Application','Inscription publique','Sécurité','École','Élèves','Notes','Présences','Restauration','Extensions'];
    const tabs=el('div',null,'detail-tabs');tabs.setAttribute('role','tablist');tabs.setAttribute('aria-label','Rubriques de configuration');
    function activate(index){original.forEach((panel,i)=>{panel.hidden=i!==index;});[...tabs.children].forEach((tab,i)=>{tab.setAttribute('aria-selected',String(i===index));tab.tabIndex=i===index?0:-1;});}
    original.forEach((panel,index)=>{
      panel.id='config-panel-'+index;panel.setAttribute('role','tabpanel');panel.setAttribute('aria-labelledby','config-tab-'+index);
      const tab=el('button',names[index]);tab.type='button';tab.id='config-tab-'+index;tab.setAttribute('role','tab');tab.setAttribute('aria-controls',panel.id);tab.addEventListener('click',()=>activate(index));
      tab.addEventListener('keydown',event=>{let target=index;if(event.key==='ArrowRight')target=(index+1)%original.length;else if(event.key==='ArrowLeft')target=(index+original.length-1)%original.length;else if(event.key==='Home')target=0;else if(event.key==='End')target=original.length-1;else return;event.preventDefault();activate(target);tabs.children[target].focus();});tabs.append(tab);
    });original[0].before(tabs);activate(0);
    const extensions=original[8];extensions.querySelector('.fields').remove();
    for(const kind of ['Modules','Plugins']){
      extensions.append(el('h2',kind),blankTable(kind==='Plugins'?['Nom','État','Configuration','Actions']:['Nom','État','Actions'],'Aucune extension Rosario chargée.'));
      const upload=disabledField('Archive '+kind.toLowerCase()+' (.zip)','file');upload.querySelector('input').accept='.zip';extensions.append(upload);
    }
    extensions.append(el('p','Activation, désactivation, suppression, import et configuration nécessitent les droits et contrôles natifs.','caution'));
  }
  function operation(screen){
    const labels={copy:'Aperçu de la copie',rollover:'Aperçu du passage d’année',log:'Aperçu de la suppression du journal',notes:'Aperçu de la publication',polls:'Aperçu de la publication'};
    if(!labels[screen])return;
    const dialog=el('dialog',null,'operation-dialog');const title=el('h2',labels[screen]);title.id='operation-title';dialog.setAttribute('aria-labelledby',title.id);
    const message=el('p','Prévisualisation uniquement. Les données et les permissions Rosario ne sont pas chargées ; aucune opération ne peut être confirmée.');
    const requirements={copy:'École source, titre et éléments à copier : non chargés ou non validés.',rollover:'Années, dépendances, données existantes et sauvegarde : non vérifiées.',log:'Périmètre et politique de conservation : non validés.',notes:'Contenu, dates et destinataires : non validés.',polls:'Questions, options, dates et destinataires : non validés.'};
    const result=el('p','Aucun enregistrement, effacement, vote ou transfert effectué.','demo');
    const close=button('Fermer','x',()=>dialog.close());dialog.append(title,message,el('p',requirements[screen]),result,close);main.append(dialog);
    const open=button(labels[screen],'eye',()=>dialog.showModal());main.querySelector('footer').prepend(open);
    dialog.addEventListener('close',()=>open.focus());
  }
  function enhance(){
    const screen=location.hash.slice(1);
    if(screen==='calendar')calendar();
    if(screen==='fields')fields();
    if(screen==='configuration')configuration();
    operation(screen);
    if(window.lucide)window.lucide.createIcons();
  }
  window.addEventListener('hashchange',enhance);enhance();
})();
