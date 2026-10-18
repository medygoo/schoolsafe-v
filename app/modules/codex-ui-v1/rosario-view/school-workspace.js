(() => {
  'use strict';
  const field = (label, type = 'text', extra = {}) => ({label, type, ...extra});
  const pending = label => field(label, 'select', {disabled:true, options:['Non chargé']});
  const check = label => field(label, 'checkbox', {disabled:true});
  const order = () => field('Ordre', 'number', {min:-9999, max:9999});
  const section = (title, fields, icon = 'clipboard-list') => ({title,fields,icon});
  const publication = () => section('Publication', [field('Début de publication','date'),field('Fin de publication','date'),pending('Profils destinataires')], 'send');
  const copyItems = ['Configuration de l’école','Périodes scolaires','Créneaux de la journée','Niveaux scolaires','Échelles de notation','Codes de commentaires des bulletins','Activités d’éligibilité','Codes de présence'];
  const screens = [
    {id:'school',title:'Informations de l’école',module:'Schools',icon:'school',group:'Organisation',existing:'school-form.html'},
    {id:'calendar',title:'Calendriers',module:'Calendar',icon:'calendar-days',group:'Organisation',sections:[
      section('Calendrier', [field('Nom','text',{maxlength:100}),pending('Copier un calendrier'),check('Calendrier par défaut')],'calendar-days'),
      section('Jours scolaires',[field('Du','date'),field('Au','date'),field('Minutes','number',{min:1,max:998}),...['Dimanche','Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi'].map(check)],'calendar-check'),
      section('Événement',[field('Titre','text',{maxlength:50}),field('Date','date'),field('Répétition en jours','number',{min:0,max:300}),field('Notes','textarea',{wide:true})],'calendar-plus')],columns:['Date','Événement','Notes']},
    {id:'marking',title:'Périodes scolaires',module:'MarkingPeriods',icon:'layers',group:'Organisation',existing:'marking-periods.html'},
    {id:'periods',title:'Créneaux de la journée',module:'Periods',icon:'clock',group:'Organisation',sections:[section('Créneau',[field('Nom','text',{maxlength:100}),field('Nom court','text',{maxlength:10}),order(),field('Durée en minutes','number',{min:-99999,max:99999}),field('Bloc','text',{maxlength:10})],'clock')],columns:['Nom','Nom court','Ordre','Durée','Bloc','Cours associés']},
    {id:'levels',title:'Niveaux scolaires',module:'GradeLevels',icon:'graduation-cap',group:'Organisation',existing:'grade-levels.html'},
    {id:'notes',title:'Annonces du portail',module:'PortalNotes',icon:'megaphone',group:'Communication',sections:[section('Annonce',[field('Titre'),field('Nom court','text',{maxlength:10}),order(),field('Contenu','textarea',{wide:true})],'megaphone'),publication(),section('Pièce jointe',[field('Fichier','file',{disabled:true}),field('Lien intégré','text',{disabled:true})],'paperclip')],columns:['Titre','Publication','Profils','Pièce jointe']},
    {id:'polls',title:'Sondages du portail',module:'PortalPolls',icon:'list-checks',group:'Communication',sections:[section('Sondage',[field('Titre'),field('Nom court','text',{maxlength:10}),order()],'list-checks'),publication(),section('Résultats',[check('Afficher les résultats')],'chart-column')],questions:true,columns:['Titre','Publication','Votes','Résultats']},
    {id:'fields',title:'Champs de l’école',module:'SchoolFields',icon:'sliders-horizontal',group:'Paramétrage',caution:'La création ou la suppression d’un champ peut modifier la structure des données. Aucune opération n’est exécutée ici.',sections:[section('Définition',[field('Nom du champ','text',{maxlength:200}),field('Type','select',{disabled:true,options:['Non chargé','Liste','Liste automatique','Liste export','Choix multiples','Texte','Texte long','Case à cocher','Nombre','Date','Fichiers']}),field('Options','textarea',{wide:true,disabled:true}),field('Valeur par défaut','text',{disabled:true}),check('Obligatoire'),order()],'sliders-horizontal')],columns:['Nom','Type','Ordre']},
    {id:'configuration',title:'Configuration',module:'Configuration',icon:'settings',group:'Paramétrage',caution:'Les réglages système et de sécurité restent inchangés. Les valeurs affichées ne représentent pas la configuration active.',sections:[
      section('Application',[field('Nom du programme','text',{maxlength:20,disabled:true}),field('Titre du programme','text',{disabled:true}),pending('Thème par défaut'),check('Forcer le thème'),pending('Affichage des noms')],'settings'),
      section('Inscription publique',[check('Création de comptes utilisateurs'),check('Création de comptes élèves'),check('Activation automatique des élèves'),pending('École par défaut'),check('Forcer l’école par défaut'),pending('Champ email élève')],'user-plus'),
      section('Sécurité',[field('Limite des tentatives échouées','number',{min:2,max:99,disabled:true}),field('Force minimale du mot de passe','number',{min:0,max:4,disabled:true}),check('Changement de mot de passe à la première connexion')],'shield-check'),
      section('École',[check('Année scolaire sur deux années civiles'),field('Jours de rotation','number',{min:2,max:7,disabled:true}),field('Logo','file',{disabled:true}),field('Symbole monétaire','text',{maxlength:8,disabled:true}),pending('Séparateur des milliers'),pending('Séparateur décimal'),pending('Sélecteur de cours')],'school'),
      section('Élèves',[check('Afficher l’adresse postale'),check('Transport scolaire coché par défaut'),check('Contacts historiques'),check('Limiter contacts et adresses à cette école'),check('Commentaires semestriels')],'users'),
      section('Notes',[pending('Mode de notation'),check('Masquer certains commentaires hors cours de présence'),check('Modification des notes après clôture'),check('Modification du carnet des trimestres passés'),check('Statistiques anonymes parents et élèves'),check('Statistiques anonymes administrateurs et enseignants')],'notebook-pen'),
      section('Présences',[field('Minutes de la journée complète','number',{min:0,max:999,disabled:true}),field('Jours de modification avant la date','number',{min:1,max:99,disabled:true}),field('Jours de modification après la date','number',{min:1,max:99,disabled:true})],'calendar-check'),
      section('Restauration',['Seuil d’avertissement','Solde minimum','Solde cible'].map(label=>field(label,'number',{disabled:true,step:'0.01',min:-999999999999,max:999999999999})),'utensils'),
      section('Extensions',[pending('Modules installés'),pending('Plugins installés')],'puzzle')]},
    {id:'copy',title:'Copier une école',module:'CopySchool',icon:'copy',group:'Outils administratifs',caution:'Copier une école crée un nouvel établissement à partir de l’école courante. La configuration est toujours copiée. Aucune copie n’est exécutée ici.',sections:[section('Établissement',[pending('École source'),field('Nom de la nouvelle école','text',{disabled:true})],'school'),section('Éléments à copier',copyItems.map(check),'copy')]},
    {id:'rollover',title:'Passage à l’année suivante',module:'Rollover',icon:'calendar-arrow-up',group:'Outils administratifs',caution:'La reprise peut remplacer des données déjà présentes l’année suivante. La vérification des dépendances et la confirmation doivent rester dans Rosario. Aucun passage d’année n’est exécuté ici.',sections:[section('Années',[pending('Année actuelle'),pending('Année suivante')],'calendar-days'),section('Données à reprendre',['Écoles','Utilisateurs','Créneaux','Périodes scolaires','Calendriers','Échelles de notation','Codes de présence','Cours','Codes d’inscription','Élèves','Commentaires des bulletins','Configuration'].map(check),'copy'),section('Modules complémentaires',[pending('Activités, restauration et discipline selon les modules actifs')],'puzzle')]},
    {id:'log',title:'Journal des connexions',module:'AccessLog',icon:'shield-check',group:'Outils administratifs',caution:'Le module natif peut purger automatiquement les entrées de plus d’un an à son ouverture. Le journal ne liste pas toutes les pages consultées. Aucun journal n’est chargé ou effacé ici.',sections:[section('Période de consultation',[field('Du','date'),field('Au','date')],'calendar-days')],columns:['Date','Identifiant','Profil','Résultat','Adresse IP','Navigateur']}
  ];
  const nav = document.getElementById('school-nav');
  const main = document.getElementById('school-content');
  function element(tag,text,className){const node=document.createElement(tag);if(text)node.textContent=text;if(className)node.className=className;return node;}
  function icon(name){const node=element('i');node.dataset.lucide=name;node.setAttribute('aria-hidden','true');return node;}
  function control(spec,id){
    const label=element('label',spec.label,spec.wide?'wide':'');label.htmlFor=id;
    const input=element(spec.type==='textarea'?'textarea':spec.type==='select'?'select':'input');input.id=id;
    if(input.tagName==='INPUT')input.type=spec.type;
    if(spec.type==='select')for(const text of spec.options||['Non chargé'])input.append(element('option',text));
    for(const key of ['min','max','maxlength','step'])if(spec[key]!==undefined)input.setAttribute(key,spec[key]);
    input.disabled=!!spec.disabled;
    if(spec.type==='checkbox'){label.className='school-choice';label.prepend(input);}else label.append(input);
    return label;
  }
  let previousGroup='';
  for(const screen of screens){
    if(screen.group!==previousGroup){nav.append(element('h3',screen.group));previousGroup=screen.group;}
    const link=element('a');link.href='#'+screen.id;link.dataset.screen=screen.id;link.dataset.branch=screen.group;link.append(icon(screen.icon),element('span',screen.title));nav.append(link);
  }
  function render(){
    const key=location.hash.slice(1);const screen=screens.find(item=>item.id===key)||screens[0];
    document.title='SchoolSafe - '+screen.title;
    main.replaceChildren(element('div','École / '+screen.title,'breadcrumb'),element('h1',screen.title),element('p','Prévisualisation · aucune donnée Rosario chargée · aucun enregistrement métier.','demo'));
    for(const link of nav.querySelectorAll('a')){if(link.dataset.screen===screen.id)link.setAttribute('aria-current','page');else link.removeAttribute('aria-current');}
    if(screen.caution)main.append(element('p',screen.caution,'caution'));
    if(screen.existing){const link=element('a','Ouvrir le formulaire SchoolSafe','button');link.href=screen.existing;main.append(link);}
    for(const [index,item] of (screen.sections||[]).entries()){
      const group=element('fieldset');const legend=element('legend');legend.append(icon(item.icon),document.createTextNode(item.title));const fields=element('div',null,'fields');
      for(const [fieldIndex,spec] of item.fields.entries())fields.append(control(spec,screen.id+'-'+index+'-'+fieldIndex));group.append(legend,fields);main.append(group);
    }
    if(screen.questions){
      const group=element('fieldset');group.append(element('legend','Questions'));const list=element('div');let count=0;
      function addQuestion(){count++;const block=element('section',null,'question');block.append(element('h3','Question '+count));const fields=element('div',null,'fields');
        const specs=[field('Intitulé','text',{maxlength:255}),field('Type de réponse','select',{options:['Choix unique','Choix multiple']}),field('Options','textarea',{wide:true})];
        specs.forEach((spec,i)=>fields.append(control(spec,'question-'+count+'-'+i)));block.append(fields);list.append(block);
      }
      const add=element('button',null,'button');add.type='button';add.append(icon('plus'),document.createTextNode('Ajouter une question'));add.addEventListener('click',()=>{addQuestion();list.lastElementChild.querySelector('input').focus();});
      group.append(list,element('div',null,'preview-tools'));group.lastElementChild.append(add);main.append(group);addQuestion();
    }
    if(screen.columns){const group=element('fieldset');group.append(element('legend',screen.id==='log'?'Connexions':'Liste'));const wrap=element('div',null,'table-wrap');wrap.tabIndex=0;wrap.setAttribute('role','region');wrap.setAttribute('aria-label',screen.title+' : liste');const table=element('table');table.append(element('caption','Données Rosario non chargées'));const head=element('thead');const row=element('tr');screen.columns.forEach(title=>{const th=element('th',title);th.scope='col';row.append(th);});head.append(row);const body=element('tbody');const empty=element('td','Aucune donnée chargée dans cette prévisualisation.','empty');empty.colSpan=screen.columns.length;const tr=element('tr');tr.append(empty);body.append(tr);table.append(head,body);wrap.append(table);group.append(wrap);main.append(group);}
    const actions=element('footer',null,'actions');const link=element('a',null,'button primary');
    link.href='/Modules.php?modname=School_Setup/'+screen.module+'.php';link.append(icon('external-link'),document.createTextNode('Ouvrir dans Rosario'));actions.append(link);main.append(actions);
    if(window.lucide)window.lucide.createIcons();
  }
  window.addEventListener('hashchange',render);render();
})();
