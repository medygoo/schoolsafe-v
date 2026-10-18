/* Isolated preview only. No business writes, storage, or API calls. */
(function (root) {
  'use strict';
  var routes = {'rosario-152':'create','rosario-012':'import','rosario-155':'import','rosario-151':'review'};
  var entries = {'rosario-149':['review','Examiner un dossier'],'rosario-150':['review','Examiner un dossier'],'rosario-007':['classes','Consulter la classe'],'rosario-008':['family','Préparer les coordonnées'],'rosario-153':['family','Préparer les coordonnées']};
  function esc(value) { return String(value == null ? '' : value).replace(/[&<>"']/g, function (c) {return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];}); }
  function icon(name) { return '<i data-lucide="'+name+'" aria-hidden="true"></i>'; }
  function button(label, action, name) { return '<button type="button" data-school-command="'+action+'">'+icon(name||'arrow-right')+'<span>'+esc(label)+'</span></button>'; }
  var fieldLabels = {reference:'Matricule',first_name:'Prénom',middle_name:'Deuxième prénom',last_name:'Nom',date_of_birth:'Date de naissance',gender:'Sexe',academic_year_id:'Année scolaire',planned_class_id:'Classe prévue',enrollment_starts_on:'Début de scolarité',filename:'Nom du fichier',file_content:'Contenu du fichier',full_name:'Nom complet',guardian_type:'Lien familial',phone:'Téléphone',email:'Adresse e-mail',address:'Adresse',note:'Observation'};
  var identity = ['reference','first_name','middle_name','last_name','date_of_birth','gender'];
  var schooling = ['academic_year_id','planned_class_id','enrollment_starts_on'];
  var familyFields = ['full_name','guardian_type','phone','email','address'];
  var titles = {create:'Nouveau dossier élève',import:'Préparation de l’import',review:'Revue du dossier',classes:'Organisation de la classe',family:'Coordonnées familiales'};
  function create(host, bridge) {
    var action = null, state, epoch = 0, controller = null, disposed = false;
    function reset() { epoch++;controller?.abort();controller=null;action=null;state={kind:'',data:null,loading:false,error:'',selected:'',step:0,values:{},errors:{},notice:''}; }
    reset();
    function enabled(a) { return !disposed && bridge.schoolWorkflows === true && bridge.demo() && bridge.actions().some(function (x) {return x.id===a?.id;}); }
    function direct(a) { return enabled(a) && !!routes[a.id]; }
    function active() { return enabled(action) && !!state.kind; }
    function render(selector) { bridge.render();bridge.icons();if(selector)host.querySelector(selector)?.focus(); }
    function entry(a) { return enabled(a) && entries[a.id] ? '<div class="sscx-toolbar">'+button(entries[a.id][1],'start','notebook-pen')+'</div>' : ''; }
    async function start(a, kind) {
      reset();action=a;if(!enabled(a))return;
      state.kind=kind;state.loading=true;var token=epoch;
      controller=new AbortController();render();
      try {
        if(typeof bridge.demoData?.schoolWorkflow !== 'function')throw new Error('Missing preview fixture');
        var data=await bridge.demoData.schoolWorkflow({session:bridge.session(),signal:controller.signal});
        if(token!==epoch||!enabled(a))return;
        state.data=data;state.loading=false;render('[data-school-heading]');
      } catch (error) {
        if(token!==epoch||!enabled(a))return;
        state.loading=false;state.data=null;state.error=error.denied?'Accès refusé dans ce scénario.':'Chargement de la démonstration impossible.';render('[data-school-error]');
      }
    }
    function activate(a) { reset();action=a;if(direct(a))return start(a,routes[a.id]); }
    function options(rows,label) { return rows.map(function (row) {return [row.id,row[label||'name']];}); }
    function field(key, required, choices) {
      var id='school-field-'+key,value=state.values[key]||'',issue=state.errors[key];
      var attrs='id="'+id+'" name="'+key+'" '+(required?'required ':'')+(issue?'aria-invalid="true" aria-describedby="'+id+'-error" ':'');
      var input;
      if(choices)input='<select '+attrs+'><option value="">Sélectionner</option>'+choices.map(function (pair) {return '<option value="'+esc(pair[0])+'" '+(value===pair[0]?'selected':'')+'>'+esc(pair[1])+'</option>';}).join('')+'</select>';
      else if(['file_content','address','note'].includes(key))input='<textarea '+attrs+' rows="'+(key==='file_content'?8:4)+'" maxlength="'+(key==='file_content'?2097152:1000)+'">'+esc(value)+'</textarea>';
      else input='<input '+attrs+' type="'+(['date_of_birth','enrollment_starts_on'].includes(key)?'date':key==='email'?'email':key==='phone'?'tel':'text')+'" maxlength="200" value="'+esc(value)+'" autocomplete="off">';
      return '<div class="sscx-school-field"><label for="'+id+'">'+esc(fieldLabels[key])+(required?' <span aria-hidden="true">*</span>':'')+'</label>'+input+(issue?'<p class="sscx-field-error" id="'+id+'-error">'+esc(issue)+'</p>':'')+'</div>';
    }
    function errors() {
      var keys=Object.keys(state.errors);return keys.length?'<div class="sscx-school-errors" role="alert" tabindex="-1"><h3>Champs à corriger</h3><ul>'+keys.map(function (key) {return '<li><button type="button" data-school-field="'+key+'">'+esc(fieldLabels[key])+': '+esc(state.errors[key])+'</button></li>';}).join('')+'</ul></div>':'';
    }
    function progress(labels) { return '<ol class="sscx-school-steps" aria-label="Étapes du dossier">'+labels.map(function (label,index) {return '<li '+(state.step===index?'aria-current="step"':'')+'><span aria-hidden="true">'+(index+1)+'</span><strong>'+label+'</strong>'+(index<state.step?icon('check'):'')+'</li>';}).join('')+'</ol>'; }
    function facts(pairs) { return '<dl class="sscx-school-facts">'+pairs.map(function (pair) {return '<div><dt>'+esc(pair[0])+'</dt><dd>'+esc(pair[1]||'Non renseigné')+'</dd></div>';}).join('')+'</dl>'; }
    function submit(label) {return '<button type="submit">'+icon('check')+'<span>'+label+'</span></button>';}
    function form(body,label) {return '<form data-school-workflow-form class="sscx-school-form" novalidate>'+errors()+body+'<div class="sscx-toolbar">'+(state.step?button('Précédent','previous','arrow-left'):'')+submit(label)+'</div></form>';}
    function selected(kind) { return state.data?.[kind]?.find(function (r) {return r.id===state.selected;}); }
    function selector(kind,label) {
      return '<div class="sscx-school-picker"><label for="school-record">'+label+'</label><select id="school-record" data-school-select="'+kind+'"><option value="">Sélectionner</option>'+state.data[kind].map(function (row) {return '<option value="'+esc(row.id)+'" '+(state.selected===row.id?'selected':'')+'>'+esc(row.name)+(row.reference?' · '+esc(row.reference):'')+'</option>';}).join('')+'</select></div>';
    }
    function createHtml() {
      var data=state.data,body='';
      if(state.step===0)body='<fieldset><legend>Identité de l’élève</legend><div class="sscx-school-grid">'+identity.map(function (key) {return field(key,['reference','first_name','last_name'].includes(key),key==='gender'?[['F','Fille'],['M','Garçon']]:null);}).join('')+'</div></fieldset>';
      if(state.step===1)body='<fieldset><legend>Scolarité prévue</legend><div class="sscx-school-grid">'+field('academic_year_id',true,options(data.years))+field('planned_class_id',true,options(data.classes))+field('enrollment_starts_on',true)+'</div></fieldset>';
      if(state.step===2) {
        var values=Object.assign({},state.values,{academic_year_id:data.years.find(function (r) {return r.id===state.values.academic_year_id;})?.name,planned_class_id:data.classes.find(function (r) {return r.id===state.values.planned_class_id;})?.name,gender:state.values.gender==='F'?'Fille':state.values.gender==='M'?'Garçon':''});
        body='<h3>Récapitulatif du brouillon</h3>'+facts(identity.concat(schooling).map(function (key) {return [fieldLabels[key],values[key]];}));
      }
      return progress(['Identité','Scolarité','Récapitulatif'])+form(body,state.step===2?'Vérifier le brouillon local':'Continuer');
    }
    function importHtml() {
      var body=state.step===0?'<fieldset><legend>Source à préparer</legend>'+field('filename',true)+field('file_content',true)+'</fieldset>':'<h3>Source préparée</h3>'+facts([['Fichier',state.values.filename],['Caractères',String(state.values.file_content.length)],['Contrôle des lignes','En attente du moteur existant']])+'<pre class="sscx-school-source" tabindex="0" aria-label="Extrait brut du fichier">'+esc(state.values.file_content.slice(0,4000))+'</pre>'+(state.values.file_content.length>4000?'<p>Extrait limité aux 4 000 premiers caractères.</p>':'');
      return progress(['Source','Relecture'])+'<p class="sscx-school-boundary">Aucun fichier envoyé. Les colonnes, doublons et lignes devront être vérifiés par le moteur d’import existant.</p>'+form(body,state.step?'Vérifier la préparation locale':'Relire la source');
    }
    function reviewHtml() {
      var row=selected('students'),body=selector('students','Dossier élève');
      if(!row)return body+'<p class="sscx-empty">Aucun dossier sélectionné.</p>';
      body+=facts([['Élève',row.name],['Matricule',row.reference],['Classe prévue',row.class],['État de démonstration',row.status]]);
      body+='<section class="sscx-school-review"><h3>Points à examiner</h3><ul>'+row.checkpoints.map(function (item) {return '<li>'+icon(item.complete?'circle-check':'circle-alert')+'<span>'+esc(item.label)+'</span><strong>'+esc(item.complete?'Présent (exemple)':'À compléter (exemple)')+'</strong></li>';}).join('')+'</ul></section>';
      body+=form(field('note',false),'Relire l’observation locale');
      return body+'<p class="sscx-school-boundary">Décision non disponible : aucune route « accepter ou refuser » identifiée dans la source examinée. Aucune admission ni aucun refus effectué ici.</p>';
    }
    function classesHtml() {
      var row=selected('classes'),body=selector('classes','Classe');if(!row)return body+'<p class="sscx-empty">Aucune classe sélectionnée.</p>';
      var roster=state.data.students.filter(function (s) {return s.class_id===row.id;});
      return body+facts([['Classe',row.name],['Enseignant',row.teacher],['Matière',row.subject],['Dossiers de démonstration',String(roster.length)]])+'<section><h3>Élèves de la classe</h3><ul class="sscx-school-roster">'+roster.map(function (s) {return '<li>'+icon('user-round')+'<strong>'+esc(s.name)+'</strong><span>'+esc(s.reference)+'</span></li>';}).join('')+'</ul></section>';
    }
    function familyHtml() {
      var row=selected('families'),body=selector('families','Responsable familial');if(!row)return body+'<p class="sscx-empty">Aucun responsable sélectionné.</p>';
      return body+facts([['Responsable',row.name],['Élève lié',row.student]])+form('<fieldset><legend>Identité et contact</legend><div class="sscx-school-grid">'+familyFields.map(function (key) {return field(key,['full_name','guardian_type'].includes(key));}).join('')+'</div></fieldset>','Vérifier les coordonnées locales')+'<p class="sscx-school-boundary">Aucune modification du parent principal, des autorisations de retrait ou des photographies.</p>';
    }
    function html() {
      if(!active())return '';
      var content;
      if(state.loading)content='<p role="status">Chargement des exemples…</p>';
      else if(state.error)content='<div role="alert" tabindex="-1" data-school-error><p>'+esc(state.error)+'</p>'+button('Réessayer','retry','refresh-cw')+'</div>';
      else if(!state.data?.classes.length)content='<p role="status">Aucune donnée de démonstration disponible dans ce scénario.</p>';
      else content=({create:createHtml,import:importHtml,review:reviewHtml,classes:classesHtml,family:familyHtml}[state.kind])();
      return '<section class="sscx-section sscx-school-workflow" data-school-workflow="'+state.kind+'"><div class="sscx-section-heading"><h2 tabindex="-1" data-school-heading>'+icon({create:'user-plus',import:'file-input',review:'clipboard-check',classes:'school',family:'contact-round'}[state.kind])+'<span>'+titles[state.kind]+'</span></h2>'+(entries[action.id]?button('Retour à la liste','close','arrow-left'):'')+'</div><p class="sscx-school-boundary">Démonstration · Données fictives · Saisie temporaire, effacée en quittant cet écran. Aucun enregistrement métier.</p>'+content+(state.notice?'<p class="sscx-school-notice" role="status" tabindex="-1">'+esc(state.notice)+'</p>':'')+'<div class="sscx-toolbar sscx-school-existing"><button type="button" data-command="open-existing">'+icon('arrow-up-right')+'<span>Ouvrir la fonction existante</span></button></div></section>';
    }
    function onClick(b) {
      if(b.dataset.schoolCommand==='start'&&enabled(action)&&entries[action.id]){start(action,entries[action.id][0]);return true;}
      if(!active())return false;
      if(b.dataset.schoolField&&fieldLabels[b.dataset.schoolField]){host.querySelector('#school-field-'+b.dataset.schoolField)?.focus();return true;}
      switch(b.dataset.schoolCommand) {
        case 'close':var a=action;reset();action=a;render('[data-school-command="start"]');return true;
        case 'retry':start(action,state.kind);return true;
        case 'previous':state.step=Math.max(0,state.step-1);state.errors={};state.notice='';render('[data-school-workflow-form] input');return true;
      }
      return false;
    }
    function issue(input) {
      if(input.required&&!input.value.trim())return 'Ce champ est requis.';
      if(!input.validity.valid)return input.name==='email'?'Saisir une adresse e-mail valide.':'Vérifier la valeur saisie.';
      if(input.name==='file_content'&&(input.value.length<10||input.value.length>2097152))return 'Le contenu doit comporter entre 10 et 2 097 152 caractères.';
      return '';
    }
    function onInput(el) {
      if(!active()||!el.closest('[data-school-workflow-form]')||!fieldLabels[el.name])return false;
      state.values[el.name]=el.value;state.notice='';host.querySelector('.sscx-school-notice')?.remove();
      if(state.errors[el.name]) {
        var problem=issue(el),text=host.querySelector('#'+el.id+'-error'),link=host.querySelector('[data-school-field="'+el.name+'"]');
        if(problem){state.errors[el.name]=problem;if(text)text.textContent=problem;if(link)link.textContent=fieldLabels[el.name]+': '+problem;}
        else{delete state.errors[el.name];el.removeAttribute('aria-invalid');el.removeAttribute('aria-describedby');text?.remove();link?.closest('li')?.remove();if(!Object.keys(state.errors).length)host.querySelector('.sscx-school-errors')?.remove();}
      }
      return true;
    }
    function onChange(el) {
      if(!active())return false;
      if(el.matches('[data-school-select]')) {
        var kind=el.dataset.schoolSelect;if(!['classes','students','families'].includes(kind))return false;
        state.selected=state.data[kind].some(function (r) {return r.id===el.value;})?el.value:'';
        var row=selected(kind);state.values=kind==='families'&&row?{full_name:row.name,guardian_type:row.relationship}:{};state.errors={};state.notice='';render('#school-record');return true;
      }
      return onInput(el);
    }
    function onSubmit(formEl) {
      if(!active()||!formEl.matches('[data-school-workflow-form]'))return false;
      state.errors={};state.notice='';
      Array.from(formEl.querySelectorAll('input,select,textarea')).forEach(function (input) {state.values[input.name]=input.value;var problem=issue(input);if(problem)state.errors[input.name]=problem;});
      if(Object.keys(state.errors).length){render('.sscx-school-errors');return true;}
      if(state.kind==='create'&&state.step<2||state.kind==='import'&&state.step<1){state.step++;render('[data-school-heading]');return true;}
      state.notice=state.kind==='review'?'Observation relue localement. Non sauvegardée, aucune décision effectuée.':'Champs vérifiés localement. Non sauvegardés et non transmis.';render('.sscx-school-notice');return true;
    }
    return {direct:direct,activate:activate,reset:reset,entry:entry,html:html,onClick:onClick,onInput:onInput,onChange:onChange,onSubmit:onSubmit,destroy:function () {disposed=true;reset();}};
  }
  root.SchoolSafeCodexSchoolWorkflows={create:create};
})(window);
