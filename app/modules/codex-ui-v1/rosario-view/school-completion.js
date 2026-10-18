(() => {
  'use strict';
  const main=document.getElementById('school-content');
  function node(tag,text,cls){const el=document.createElement(tag);if(text)el.textContent=text;if(cls)el.className=cls;return el;}
  function input(label,type='text',disabled=false){const wrap=node('label',label);const el=node(type==='select'?'select':type==='textarea'?'textarea':'input');if(el.tagName==='INPUT')el.type=type;el.disabled=disabled;if(type==='select')el.append(node('option','Non chargé'));wrap.append(el);return wrap;}
  function section(title){const el=node('fieldset');el.append(node('legend',title));return el;}
  function action(label,icon,handler){const el=node('button',null,'button');el.type='button';const glyph=node('i');glyph.dataset.lucide=icon;glyph.setAttribute('aria-hidden','true');el.append(glyph,document.createTextNode(label));el.addEventListener('click',handler);return el;}
  function beforeFooter(el){main.querySelector('footer').before(el);}
  function drafts(kind,columns){
    const group=section('Liste éditable · brouillons visuels');group.append(node('p','Prévisualisation locale uniquement · aucun élément Rosario chargé ou enregistré.','demo'));
    const wrap=node('div',null,'table-wrap');wrap.tabIndex=0;wrap.setAttribute('role','region');wrap.setAttribute('aria-label','Brouillons '+kind);
    const table=node('table');const head=node('thead');const header=node('tr');[...columns.map(c=>c[0]),'Actions'].forEach(title=>{const th=node('th',title);th.scope='col';header.append(th);});head.append(header);const body=node('tbody');table.append(head,body);wrap.append(table);
    const empty=node('p','Aucun brouillon local.','empty');const status=node('p');status.setAttribute('role','status');let count=0,page=1,query='';
    const tools=node('div',null,'draft-tools');const search=input('Rechercher dans les brouillons','search');const size=input('Lignes par page','select');size.lastChild.replaceChildren();
    for(const value of [5,10,25]){const option=node('option',String(value));option.value=String(value);size.lastChild.append(option);}
    const paging=node('div',null,'preview-tools');const pageLabel=node('span');pageLabel.setAttribute('aria-live','polite');
    function filtered(){return [...body.children].filter(row=>!query||[...row.querySelectorAll('input,textarea')].some(control=>control.value.toLocaleLowerCase('fr').includes(query)));}
    function refresh(){const rows=filtered(),limit=Number(size.lastChild.value),pages=Math.max(1,Math.ceil(rows.length/limit));page=Math.min(Math.max(1,page),pages);[...body.children].forEach(row=>{row.hidden=true;});rows.slice((page-1)*limit,page*limit).forEach(row=>{row.hidden=false;});empty.hidden=rows.length>0;empty.textContent=body.children.length?'Aucun brouillon correspondant. Modifiez ou effacez la recherche.':'Aucun brouillon local.';pageLabel.textContent='Page '+page+' / '+pages+' · '+rows.length+' brouillon(s)';prev.disabled=page===1;next.disabled=page===pages;}
    const prev=action('Page précédente','chevron-left',()=>{page--;refresh();});const next=action('Page suivante','chevron-right',()=>{page++;refresh();});paging.append(prev,pageLabel,next);
    const find=action('Rechercher','search',()=>{query=search.lastChild.value.trim().toLocaleLowerCase('fr');page=1;refresh();});const clear=action('Effacer la recherche','x',()=>{search.lastChild.value='';query='';page=1;refresh();search.lastChild.focus();});tools.append(search,size,find,clear);
    search.lastChild.addEventListener('keydown',event=>{if(event.key==='Enter'){event.preventDefault();find.click();}});size.lastChild.addEventListener('change',()=>{page=1;refresh();});
    function add(){count++;const row=node('tr');row.dataset.draft=String(count);
      columns.forEach(([label,type,max])=>{const td=node('td');const control=input(label+' · brouillon '+count,type,type==='select');const el=control.lastChild;if(max)el.maxLength=max;td.append(control);row.append(td);});
      const td=node('td');const remove=action('Retirer le brouillon '+count,'trash-2',()=>{row.remove();refresh();status.textContent='Brouillon local retiré. Aucune donnée Rosario supprimée.';addButton.focus();});remove.title='Retirer ce brouillon local';td.append(remove);row.append(td);body.append(row);query='';search.lastChild.value='';page=Math.ceil(body.children.length/Number(size.lastChild.value));refresh();row.querySelector('input').focus();if(window.lucide)lucide.createIcons();
    }
    const addButton=action('Ajouter un brouillon','plus',add);group.append(tools,addButton,empty,wrap,paging,status);beforeFooter(group);refresh();
  }
  function periods(){
    const group=section('Hiérarchie des périodes');group.append(node('p','Structure Rosario · identifiants et périodes de l’école non chargés.','demo'));
    for(const [code,title] of [['FY','Année'],['SEM','Semestre'],['QTR','Trimestre'],['PRO','Période de progression']]){
      const details=node('details',null,'period-branch');details.dataset.term=code;const summary=node('summary',title);details.append(summary);const fields=node('div',null,'fields');
      fields.append(input('Période parente','select',true),input('Nom'),input('Nom court'),input('Ordre','number'),input('Début','date'),input('Fin','date'),input('Notée','checkbox',true),input('Commentaires','checkbox',true),input('Début de saisie des notes','date',true),input('Fin de saisie des notes','date',true));
      fields.querySelectorAll('input')[0].maxLength=50;fields.querySelectorAll('input')[1].maxLength=10;details.append(fields);group.append(details);
    }
    beforeFooter(group);
  }
  function calendarExtras(){
    const group=section('Événements et devoirs');const fields=node('div',null,'fields');fields.append(input('Événement sélectionné','select',true),input('Cours','select',true),input('Enseignant','select',true),input('Date d’attribution','date',true),input('Échéance','date',true),input('Notes du devoir','textarea',true));
    group.append(node('p','Aucun événement ou devoir chargé. La remise des devoirs dépend du rôle et des droits Rosario.','empty'),fields);beforeFooter(group);
  }
  function translations(){
    const group=section('Libellés multilingues');group.append(node('p','Brouillons de traduction · langues actives et libellés Rosario non chargés.','demo'));const fields=node('div',null,'fields');fields.append(input('Libellé français'),input('Libellé anglais'));group.append(fields);beforeFooter(group);
  }
  function plugins(){
    const extensions=document.getElementById('config-panel-8');
    for(const [name,specs] of [
      ['Moodle',[['URL Moodle','text'],['Protocole API','select'],['Jeton Moodle','password'],['Identifiant du rôle parent','text'],['Profil à importer','select'],['Niveau scolaire','select'],['Calendrier','select'],['Options de passage / maintien','select'],['Début de présence','date'],['Code d’inscription','select']]],
      ['Content Security Policy',[['Programme','select'],['Du','date'],['Au','date'],['JavaScript (script-src)','textarea'],['CSS (style-src)','textarea'],['AJAX (connect-src)','textarea'],['Formulaires (form-action)','textarea']]]
    ]){
      const details=node('details',null,'plugin-branch');details.append(node('summary',name),node('p','Plugin présent dans le code local · activation et paramètres non vérifiés. Aucun secret chargé, aucune opération exécutée.','caution'));
      const fields=node('div',null,'fields');specs.forEach(([label,type])=>fields.append(input(label,type,true)));details.append(fields);
      if(name==='Moodle'){const actions=node('div',null,'preview-tools');for(const text of ['Tester la connexion','Importer des utilisateurs']){const button=node('button',text,'button');button.type='button';button.disabled=true;actions.append(button);}details.append(actions);}
      else details.append(node('p','Rapports de violation non chargés. Politique de sécurité inchangée.','empty'));
      extensions.append(details);
    }
  }
  function enhance(){
    const key=location.hash.slice(1);
    if(key==='calendar')calendarExtras();
    if(key==='marking')periods();
    if(key==='levels')drafts('niveaux',[['Nom','text',50],['Nom court','text',3],['Ordre','number'],['Niveau suivant','select']]);
    if(key==='periods')drafts('créneaux',[['Nom','text',100],['Nom court','text',10],['Ordre','number'],['Durée','number'],['Bloc','text',10]]);
    if(key==='notes')drafts('annonces',[['Titre','text'],['Nom court','text',10],['Ordre','number'],['Contenu','textarea']]);
    if(key==='fields')translations();
    if(key==='school'){const group=section('Champs personnalisés de l’établissement');group.append(node('p','Champs, types, obligations et valeurs Rosario non chargés.','empty'));beforeFooter(group);}
    if(key==='configuration')plugins();
    if(window.lucide)lucide.createIcons();
  }
  window.addEventListener('hashchange',enhance);enhance();
})();
