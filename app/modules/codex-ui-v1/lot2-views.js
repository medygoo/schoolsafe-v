(function (root) {
  'use strict';
  var logo = new URL('../../schoolsafe-logo.png', document.currentScript.src).href;
  function esc(v) { return String(v == null ? '' : v).replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];}); }
  function icon(n) { return '<i data-lucide="'+esc(n)+'" aria-hidden="true"></i>'; }
  function command(label,attrs,n) { return '<button type="button" '+attrs+'>'+icon(n||'arrow-up-right')+'<span>'+esc(label)+'</span></button>'; }
  function tool(label,attrs,n) { return '<button type="button" class="sscx-icon" aria-label="'+esc(label)+'" title="'+esc(label)+'" '+attrs+'>'+icon(n)+'</button>'; }
  var domainIcons={students:'contact-round',children:'contact-round',classes:'school',family:'users-round',cards:'credit-card',scanner:'scan-line',attendance:'calendar-check',subjects:'library',assignments:'notebook-pen',grades:'list-checks',lessons:'book-open',fees:'wallet',campaigns:'calendar-range',payments:'banknote',receipts:'receipt-text',documents:'files',access:'key-round',staff:'contact-round',years:'calendar-range',alerts:'bell',overview:'chart-no-axes-combined',pickup:'log-out',balances:'wallet',cash:'landmark'};
  function iconFor(a){return domainIcons[root.SchoolSafeCodexScreenSpecs?.specs[a.id]?.definition]||(a.type==='read'?'activity':a.type==='unavailable'?'circle-alert':'arrow-up-right');}
  function scalar(v) { return typeof v==='string' || typeof v==='boolean' || (typeof v==='number' && Number.isFinite(v)); }
  function bounded(v) { if(!scalar(v))throw new Error('Valeur invalide'); return String(v).slice(0,2000); }
  var labels={name:'Nom',class:'Classe',reference:'Reference',status:'Etat',year:'Annee scolaire',guardian:'Responsable familial',contact:'Contact autorise',document:'Document',teacher:'Enseignant',subject:'Matiere',relationship:'Lien familial',student:'Enfant',photo_review:'Revue photographique',request_status:'Demande',print_status:'Production',date:'Date',reason:'Motif',instruction:'Consigne',correction:'Correction',due:'Echeance',grade:'Note publiee',comment:'Observation',objective:'Objectifs',content:'Deroulement',amount:'Montant serveur',currency:'Devise',balance:'Solde serveur',scope:'Perimetre',expiry:'Expiration',receipt:'Recu',post:'Poste',start:'Debut',end:'Fin',category:'Categorie',value:'Valeur serveur',coefficient:'Coefficient',mode:'Mode'};
  var profiles={
    admin:{title:'Vue generale',list:'Eleves et classes',areas:['Eleves','Classes','Personnel','Presences','Alertes','Gestion scolaire','Cartes et badges'],targets:['rosario-006','rosario-007','rosario-018','presence-school','rosario-003','rosario-010','rosario-016']},
    school_head:{title:'Pilotage de l\'etablissement',list:'Suivi de l\'etablissement',areas:['Eleves','Presences et absences','Classes et enseignants','Suivi pedagogique','Decisions en attente','Operations'],targets:['rosario-127','presence-school','rosario-007','rosario-131','rosario-126','rosario-133']},
    teacher:{title:'Ma journee de classe',list:'Mes classes et mes eleves',areas:['Mes classes','Mes eleves','Appel et presences','Devoirs','Notes','Preparation des cours'],targets:['presence-class','rosario-105','presence-teacher','rosario-028','rosario-029','rosario-108']},
    parent:{title:'Le suivi de mes enfants',list:'Mes enfants',areas:['Mes enfants','Presences','Devoirs','Resultats','Personnes autorisees','Frais personnels'],targets:['presence-student','rosario-194','rosario-196','rosario-197','rosario-201','rosario-203']},
    guard:{title:'Poste de controle',list:'Derniers controles',areas:['Scanner QR','Entree / Presence','Retrait / Sortie','Verifier la carte','Personnes autorisees','Historique','Anomalies'],targets:['rosario-037','rosario-094','rosario-095','rosario-096','rosario-041','scan-history','rosario-102']},
    finance:{title:'Suivi financier',list:'Campagnes et consultations',areas:['Controle des frais','Campagnes','Classes autorisees','Consultations','Delegations','Paiements et recus'],targets:['scan-fees','rosario-049','rosario-146','rosario-120','rosario-079','rosario-048']},
    cashier:{title:'Ma journee de caisse',list:'Paiements et pieces',areas:['Paiements','Recus','Soldes','Controle des frais','Journee de caisse'],targets:['rosario-115','rosario-118','rosario-120','scan-fees','rosario-123']},
    pedagogy:{title:'Coordination pedagogique',list:'Classes et enseignants',areas:['Classes','Affectations','Presences','Preparation','Resultats'],targets:['rosario-140','rosario-141','rosario-142','rosario-144','rosario-131']},
    admissions:{title:'Scolarite et admissions',list:'Dossiers en cours',areas:['Preinscriptions','Dossiers','Eleves','Familles'],targets:['rosario-149','rosario-150','rosario-006','rosario-153']},
    secretary:{title:'Secretariat scolaire',list:'Documents et demandes',areas:['Documents','Attestations','Certificats','Archives'],targets:['rosario-086','rosario-156','rosario-158','rosario-081']},
    accountant:{title:'Suivi comptable',list:'Pieces a examiner',areas:['Journal','Pieces','Rapprochements','Etats'],targets:['rosario-056','rosario-169','rosario-170','rosario-061']},
    hr:{title:'Suivi du personnel',list:'Personnel et affectations',areas:['Personnel','Presences','Contrats','Paie'],targets:['rosario-018','rosario-175','rosario-020','rosario-023']},
    nurse:{title:'Vie et sante scolaire',list:'Suivi autorise',areas:['Passages','Dossiers sante','Alertes','Contacts'],targets:['rosario-179','rosario-182','rosario-180','rosario-181']},
    canteen:{title:'Service de restauration',list:'Service et beneficiaires',areas:['Presences repas','Service','Menus','Beneficiaires'],targets:['rosario-186','rosario-187','rosario-188','rosario-189']},
    communication:{title:'Communication de l\'ecole',list:'Publications et echanges',areas:['Messages','Notifications','Site public','Galerie'],targets:['rosario-071','rosario-072','rosario-191','rosario-192']}
  };
  var operations={cards:[['individual','Preparation individuelle'],['class','Preparation par classe'],['photo','Photographies'],['crop','Recadrage existant'],['preview','Previsualisation'],['print','Impression'],['replace','Remplacement'],['lost','Carte perdue'],['revoked','Carte revoquee']],family:[['photo','Photographies'],['authorization','Autorisations'],['suspend','Suspensions'],['reactivation-request','Demandes de reactivation'],['request-status','Etats des demandes']]};
  var tabFields={Scolarite:['name','class','reference','status','year'],Famille:['guardian','contact','status'],Documents:['document','status'],Organisation:['name','teacher','subject','year','start','end','status'],Affectations:['name','teacher','subject','scope','status'],Identite:['name','student','relationship','photo_review','status'],Autorisation:['name','student','relationship','status','photo_review'],Demandes:['name','request_status','status'],Preparation:['name','class','reference','status'],Photographie:['name','photo_review','status'],Production:['name','reference','print_status','status'],Modes:['name','mode','status'],Perimetre:['name','scope','expiry','status'],Presence:['name','class','date','status'],Historique:['name','date','status','reason','reference'],Matiere:['name','class','coefficient','status'],Consigne:['name','class','subject','due','instruction','status'],Correction:['name','correction','status'],Evaluation:['name','subject','grade','comment','status'],Publication:['name','grade','status'],Objectifs:['name','class','subject','objective','status'],Deroulement:['name','date','content','status'],Affectation:['name','class','amount','due','status'],Situation:['name','amount','currency','balance','status'],Campagne:['name','class','due','status'],Delegations:['name','scope','expiry','status'],Paiement:['name','reference','amount','currency','date','status'],Recu:['name','reference','amount','currency','date','receipt','status'],Document:['name','student','reference','category','status'],Profil:['name','post','scope','status'],Droits:['name','scope','expiry','status'],Signalement:['name','category','date','reason','status'],Suivi:['name','date','reason','status'],Source:['name','value','date','scope','status']};
  function validateData(def, value, tab) {
    if(!value || !Array.isArray(value.rows) || value.rows.length>1000)throw new Error('Liste invalide ou trop volumineuse');
    var keys=def.columns.map(function(c){return c[0];});
    return {rows:value.rows.map(function(row){
      if(!row || typeof row.id!=='string' || !row.id || row.id.length>200)throw new Error('Dossier invalide');
      var projected={id:row.id}; keys.forEach(function(k){if(row[k]!=null)projected[k]=bounded(row[k]);});if(typeof row.class_id==='string'&&/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(row.class_id))projected.class_id=row.class_id; return projected;
    }),fields:Object.fromEntries(def.fields.filter(function(k){return (tabFields[tab]||[]).includes(k) && value.fields && value.fields[k]!=null;}).map(function(k){return [k,bounded(value.fields[k])];}))};
  }
  tabFields.Dossier=['name','class','guardian','status'];
  tabFields.Preuves=['name','reason','status'];
  tabFields.Journee=['name','date','scope','status'];
  tabFields.Pieces=['name','reference','status'];
  function validateDashboard(data,allowed) {
    if(!data || !Array.isArray(data.metrics) || !Array.isArray(data.items) || !Array.isArray(data.alerts))throw new Error('Synthese invalide');
    function entries(rows){return rows.slice(0,30).map(function(r){return {id:bounded(r.id),name:bounded(r.name),subtitle:bounded(r.subtitle||''),status:bounded(r.status||''),recordId:typeof r.recordId==='string'?r.recordId.slice(0,200):'',target:allowed.some(function(a){return a.id===r.target;})?r.target:''};});}
    function count(n){if(!Number.isSafeInteger(n)||n<0)throw new Error('Effectif invalide');return n;}
    var school=null;
    if(data.schoolOverview){
      var s=data.schoolOverview;
      if(!Array.isArray(s.classes)||s.classes.length>1000||!Array.isArray(s.teachers)||s.teachers.length>1000)throw new Error('Detail scolaire invalide');
      school={date:bounded(s.date),classes:s.classes.map(function(c){
        var row={cycle:bounded(c.cycle),name:bounded(c.name),enrolled:count(c.enrolled),girls:count(c.girls),boys:count(c.boys),unspecified:count(c.unspecified),present:count(c.present),absent:count(c.absent),late:count(c.late),unverified:count(c.unverified)};
        if(row.girls+row.boys+row.unspecified!==row.enrolled||row.present+row.absent+row.late+row.unverified!==row.enrolled)throw new Error('Totaux scolaires incoherents');return row;
      }),teachers:s.teachers.map(function(t){if(!['Present','Absent','Retard','Non verifie'].includes(t.status))throw new Error('Presence invalide');return {name:bounded(t.name),assignment:bounded(t.assignment),status:t.status,arrival:bounded(t.arrival||'Non renseignee')};})};
    }
    return {metrics:data.metrics.slice(0,4).map(function(m){return {label:bounded(m.label),value:bounded(m.value),hint:bounded(m.hint||'')};}),items:entries(data.items),alerts:entries(data.alerts),schoolOverview:school};
  }
  function create(host,bridge) {
    var active=null, revision=0, controller=null, destroyed=false;
    var v={data:null,query:'',classFilter:'',statusFilter:'',page:0,selected:'',tab:'',message:'',kind:'empty',busy:false,detail:false,draft:false,draftValues:{},draftErrors:{},checked:[],operation:-1};
    var dash={data:null,message:'',kind:'loading',busy:false,tab:'students',followup:false};
    var compactOverview=root.matchMedia('(max-width: 1199px)');
    function resizeOverview(){if(!active&&!destroyed){var panel=document.activeElement?.closest('[data-overview-panel]');if(panel)dash.tab=panel.dataset.overviewPanel;redraw();}}
    compactOverview.addEventListener('change',resizeOverview);
    var specs=root.SchoolSafeCodexScreenSpecs;
    var schoolWorkflows=root.SchoolSafeCodexSchoolWorkflows?.create(host,bridge);
    var featureScreens=root.SchoolSafeCodexFeatureScreens?.create(host,bridge);
    function demo(){return bridge.demo();}
    function key(){var s=bridge.session();return JSON.stringify([s?.identityKey,s?.schoolId,s?.revision,s?.role,s?.demo]);}
    function spec(a){return specs.specs[a.id];}
    function definition(a){var s=spec(a);return specs.definitions[s.definition]||specs.definitions.legacy;}
    function recordPresenter(){var p=root.SchoolSafeCodexRecordPresentation;return active&&p?.supports(spec(active).definition)?p:null;}
    function copy(text){return recordPresenter()?.label(text)||text;}
    function source(){return demo()?'Donnees fictives':'Donnees du contrat autorise';}
    function redraw(){if(!destroyed)bridge.render();}
    function reset(){featureScreens?.reset();schoolWorkflows?.reset();revision++;controller?.abort();controller=null;active=null;v={data:null,query:'',classFilter:'',statusFilter:'',page:0,selected:'',tab:'',message:'',kind:'empty',busy:false,detail:false,draft:false,draftValues:{},draftErrors:{},checked:[],operation:-1};dash={data:null,message:'Synthese en attente.',kind:'loading',busy:false,tab:'students',followup:false};}
    async function permitted(a,p,purpose){return demo() || (await bridge.authorize(a,p,purpose))===true;}
    function notice(text,kind){v.message=text;v.kind=kind||'empty';redraw();}
    function status(msg,kind,cls){return msg?'<div class="'+(cls||'sscx-view-status')+' sscx-state--'+esc(kind)+'" role="'+(kind==='error'||kind==='denied'?'alert':'status')+'">'+icon(kind==='loading'?'loader-circle':'info')+'<span>'+esc(msg)+'</span></div>':'';}
    async function loadDashboard() {
      controller?.abort();controller=new AbortController();var signal=controller.signal, r=++revision,k=key();active=null;dash.busy=true;dash.data=null;dash.message='Chargement de la synthese.';dash.kind='loading';redraw();
      var a={id:'dashboard-overview',permission:null,branch:'pilotage',type:'presentation'};
      try{
        if(!await permitted(a,{},'dashboard'))throw Object.assign(new Error(),{denied:true});
        if(r!==revision||k!==key()||destroyed)return;
        var loader=demo()?bridge.demoData?.dashboard:bridge.loadDashboard;
        if(typeof loader!=='function'){dash.busy=false;dash.message='Synthese non raccordee. Aucun chiffre disponible.';dash.kind='unavailable';redraw();return;}
        var data=await loader({session:bridge.session(),actions:bridge.actions(),signal:signal});
        if(r!==revision||k!==key()||destroyed)return;
        if(!await permitted(a,{},'dashboard'))throw Object.assign(new Error(),{denied:true});
        if(r!==revision||k!==key()||destroyed)return;
        dash.data=validateDashboard(data,bridge.actions());dash.message=dash.data.items.length||dash.data.metrics.length?'':'Aucune information disponible.';dash.kind='empty';dash.busy=false;redraw();
      }catch(e){if(r===revision&&k===key()&&!destroyed){dash.data=null;dash.busy=false;dash.kind=e.denied?'denied':'error';dash.message=e.denied?'Synthese non autorisee.':'Synthese indisponible. Reessayez.';redraw();}}
    }
    async function load(params) {
      if(!active)return;var a=active,def=definition(a);controller?.abort();controller=new AbortController();var signal=controller.signal,r=++revision,k=key();v.busy=true;v.data=null;v.message='Chargement des informations autorisees.';v.kind='loading';redraw();
      params=params||{};
      try{
        if(!await permitted(a,params,params.record_id?'detail':'consult'))throw Object.assign(new Error(),{denied:true});
        if(r!==revision||k!==key()||destroyed)return;
        var loader=demo()?bridge.demoData?.consult:bridge.consult;
        if(typeof loader!=='function'){v.busy=false;notice('Consultation non raccordee. Aucune donnee disponible.','unavailable');return;}
        var data=await loader(a,{session:bridge.session(),params:params,signal:signal,definition:spec(a).definition});
        if(r!==revision||k!==key()||destroyed)return;
        if(!await permitted(a,params,params.record_id?'detail':'consult'))throw Object.assign(new Error(),{denied:true});
        if(r!==revision||k!==key()||destroyed)return;
        v.data=validateData(def,data,params.record_id?params.tab:undefined);v.busy=false;v.message=!v.data.rows.length&&!v.detail?'Aucune ligne disponible.':'';v.kind='empty';redraw();
      }catch(e){if(r===revision&&k===key()&&!destroyed){v.busy=false;v.data=null;notice(e.denied?'Acces refuse ou retire.':'Chargement impossible. Reessayez.',e.denied?'denied':'error');}}
    }
    async function activate(a){reset();active=a;if(!a)return loadDashboard();var schoolTask=schoolWorkflows?.activate(a);if(schoolWorkflows?.direct(a)){await schoolTask;return;}var featureTask=featureScreens?.activate(a);if(featureScreens?.direct(a)){await featureTask;return;}if(spec(a).kind==='legacy'||spec(a).kind==='unavailable'||spec(a).kind==='read')return;v.tab=definition(a).tabs[0]||'';await load();}
    function actionLink(id,label){var a=bridge.actions().find(function(a){return a.id===id;});return a?command(label||a.label,'data-action-id="'+esc(id)+'" data-ui-branch="'+esc(a.branch)+'"',iconFor(a)):'';}
    function dashboardHtml(){
      var p=profiles[bridge.session()?.role]||profiles.admin,data=dash.data;
      var metrics=data?data.metrics:[];
      function list(rows,empty){return rows.length?'<ul class="sscx-record-list">'+rows.map(function(r){var target=bridge.actions().find(function(a){return a.id===r.target;});return '<li><div><strong>'+esc(r.name)+'</strong><p>'+esc(r.subtitle)+'</p></div><span class="sscx-badge">'+esc(r.status)+'</span>'+(target?tool(r.recordId?'Consulter '+r.name:'Ouvrir '+target.label,(r.recordId?'data-lot2-destination="'+esc(r.target)+'" data-record-id="'+esc(r.recordId)+'"':'data-action-id="'+esc(r.target)+'"'),'arrow-up-right'):'')+'</li>';}).join('')+'</ul>':'<p class="sscx-empty">'+esc(empty)+'</p>';}
      if(['admin','school_head'].includes(bridge.session()?.role))return schoolOverviewHtml(data,p,list);
      return '<section class="sscx-section sscx-role-overview" data-dashboard-role="'+esc(bridge.session()?.role||'')+'"><div class="sscx-section-heading"><h2>'+esc(p.title)+'</h2>'+tool('Actualiser la synthese','data-lot2="dashboard-refresh" '+(dash.busy?'disabled':''),'refresh-cw')+'</div>'+status(dash.message,dash.kind,'sscx-dashboard-state')+
        '<div class="sscx-metrics">'+metrics.map(function(m){return '<article class="sscx-metric"><span>'+esc(m.label)+'</span><strong>'+esc(m.value)+'</strong><small>'+esc(m.hint)+' · '+source()+'</small></article>';}).join('')+'</div></section>'+
        '<section class="sscx-section"><h2>Acces rapides</h2><div class="sscx-shortcuts">'+p.targets.map(function(id,i){return actionLink(id,p.areas[i]);}).join('')+'</div></section>'+
        '<div class="sscx-overview-grid"><section class="sscx-section"><h2>'+esc(p.list)+'</h2>'+list(data?.items||[],dash.busy?'Chargement...':'Aucun dossier disponible.')+'</section><section class="sscx-section"><h2>Alertes et notifications</h2>'+list(data?.alerts||[],dash.busy?'Chargement...':'Aucune notification disponible.')+'</section></div>';
    }
    function schoolOverviewHtml(data,p,list){
      var s=data?.schoolOverview,compact=compactOverview.matches,groups=new Map();
      function total(rows){return rows.reduce(function(sum,c){Object.keys(sum).forEach(function(k){sum[k]+=c[k];});return sum;},{enrolled:0,girls:0,boys:0,unspecified:0,present:0,absent:0,late:0,unverified:0});}
      var sums=total(s?.classes||[]),staff={Present:0,Absent:0,Retard:0,'Non verifie':0};
      s?.teachers.forEach(function(t){staff[t.status]++;});s?.classes.forEach(function(c){if(!groups.has(c.cycle))groups.set(c.cycle,[]);groups.get(c.cycle).push(c);});
      var measures=s?[
        ['Eleves inscrits',sums.enrolled,sums.girls+' filles · '+sums.boys+' garcons'+(sums.unspecified?' · '+sums.unspecified+' non renseignes':''),'graduation-cap'],
        ['Classes',s.classes.length,groups.size+' cycles','school'],
        ['Eleves presents',sums.present+sums.late,sums.absent+' absents · '+sums.late+' en retard'+(sums.unverified?' · '+sums.unverified+' non verifies':''),'users'],
        ['Enseignants presents',(staff.Present+staff.Retard)+' / '+s.teachers.length,'Attendus ce jour · '+staff.Retard+' en retard'+(staff['Non verifie']?' · '+staff['Non verifie']+' non verifies':''),'user-check']
      ]:[['Eleves inscrits','—','Non disponible','graduation-cap'],['Classes','—','Non disponible','school'],['Eleves presents','—','Non disponible','users'],['Enseignants presents','—','Non disponible','user-check']];
      function panel(id,title,n,extra){return '<section id="overview-panel-'+id+'" class="sscx-overview-panel '+(extra||'')+'" data-overview-panel="'+id+'" '+(compact?'role="tabpanel" aria-labelledby="overview-tab-'+id+'" tabindex="0" '+(dash.tab!==id?'hidden':''):'aria-labelledby="overview-heading-'+id+'"')+'><div class="sscx-overview-panel-heading"><h3 id="overview-heading-'+id+'">'+icon(n)+esc(title)+'</h3></div>';}
      var columns=[['enrolled','Inscrits'],['girls','Filles'],['boys','Garcons'],['present','A l\'heure'],['absent','Absents'],['late','Retards']];
      if(sums.unspecified)columns.splice(3,0,['unspecified','Non renseigne']);if(sums.unverified)columns.push(['unverified','Non verifie']);
      function cells(c){return columns.map(function(col){return '<td data-count="'+col[0]+'">'+c[col[0]]+'</td>';}).join('');}
      var pupils=panel('students','Eleves et classes','graduation-cap');
      if(!s||!s.classes.length)pupils+='<p class="sscx-empty">Aucun detail de classe disponible.</p>';
      else{
        pupils+='<p class="sscx-overview-note">Effectifs inscrits et presence du jour</p><div class="sscx-table-scroll sscx-class-table" tabindex="0" role="region" aria-label="Effectifs et presences par classe"><table><caption class="sscx-visually-hidden">Effectifs et presences par classe · '+source()+'</caption><thead><tr><th scope="col">Classe</th>'+columns.map(function(col){return '<th scope="col">'+esc(col[1])+'</th>';}).join('')+'</tr></thead>'+Array.from(groups).map(function(pair){return '<tbody><tr class="sscx-cycle-row"><th scope="rowgroup" colspan="'+(columns.length+1)+'">'+esc(pair[0])+'<span>'+total(pair[1]).enrolled+' eleves · '+pair[1].length+' classes</span></th></tr>'+pair[1].map(function(c){return '<tr data-overview-class><th scope="row">'+esc(c.name)+'</th>'+cells(c)+'</tr>';}).join('')+'</tbody>';}).join('')+'<tfoot><tr><th scope="row">Total ecole</th>'+cells(sums)+'</tr></tfoot></table></div>';
        pupils+='<div class="sscx-mobile-classes">'+Array.from(groups).map(function(pair,i){return '<details class="sscx-cycle-group" '+(i===0?'open':'')+'><summary><strong>'+esc(pair[0])+'</strong><span>'+total(pair[1]).enrolled+' eleves</span>'+icon('chevron-down')+'</summary><ul>'+pair[1].map(function(c){return '<li><div class="sscx-class-label"><strong>'+esc(c.name)+'</strong><span>'+c.enrolled+' eleves</span></div><p>'+c.girls+' filles · '+c.boys+' garcons'+(c.unspecified?' · '+c.unspecified+' non renseignes':'')+'</p><dl><div><dt>A l\'heure</dt><dd>'+c.present+'</dd></div><div><dt>Absents</dt><dd>'+c.absent+'</dd></div><div><dt>Retards</dt><dd>'+c.late+'</dd></div>'+(c.unverified?'<div><dt>Non verifies</dt><dd>'+c.unverified+'</dd></div>':'')+'</dl></li>';}).join('')+'</ul></details>';}).join('')+'</div>';
      }
      pupils+='</section>';
      var teachers=panel('teachers','Enseignants','user-check');
      if(!s||!s.teachers.length)teachers+='<p class="sscx-empty">Aucune presence enseignante disponible.</p>';
      else teachers+='<p class="sscx-overview-note">'+s.teachers.length+' attendus ce jour</p><div class="sscx-daily-summary"><span data-presence="present">'+staff.Present+' a l\'heure</span><span data-presence="absent">'+staff.Absent+' absents</span><span data-presence="late">'+staff.Retard+' en retard</span>'+(staff['Non verifie']?'<span>'+staff['Non verifie']+' non verifies</span>':'')+'</div><div class="sscx-teacher-scroll" tabindex="0" role="region" aria-label="Presence journaliere des enseignants"><ul class="sscx-teacher-list">'+s.teachers.map(function(t){return '<li><div><strong>'+esc(t.name)+'</strong><p>'+esc(t.assignment)+'</p></div><div class="sscx-teacher-state"><span data-presence="'+(t.status==='Present'?'present':t.status==='Absent'?'absent':t.status==='Retard'?'late':'unknown')+'">'+esc(t.status)+'</span><small>Arrivee : '+esc(t.arrival)+'</small></div></li>';}).join('')+'</ul></div>';
      teachers+='</section>';
      var palmares=panel('palmares','Palmares de l\'ecole','trophy','sscx-palmares')+'<div class="sscx-palmares-placeholder"><span class="sscx-palmares-number">10</span><div><h4>Meilleurs eleves</h4><span class="sscx-badge">En attente de validation</span></div></div><p>Aucun classement disponible.</p><p class="sscx-overview-note">Periode, criteres et resultats publies a valider.</p></section>';
      function heading(title,n){return '<h3 class="sscx-overview-heading">'+icon(n)+esc(title)+'</h3>';}
      function percent(n){return sums.enrolled?Math.round(n/sums.enrolled*100):0;}
      var attendance=[['present','A l\'heure',sums.present],['late','En retard',sums.late],['absent','Absents',sums.absent],['unknown','Non verifies',sums.unverified]];
      // Aggregate only the validated daily snapshot; no hourly trend is inferred.
      var presence='<section class="sscx-snapshot" aria-label="Presences du jour">'+heading('Presences du jour','calendar-check');
      presence+=s&&sums.enrolled?'<div class="sscx-presence-total"><strong>'+percent(sums.present+sums.late)+'<small>%</small></strong><span>des eleves presents<br><b>'+(sums.present+sums.late)+' / '+sums.enrolled+'</b></span></div><div class="sscx-presence-bar" aria-hidden="true">'+attendance.filter(function(a){return a[2]>0;}).map(function(a){return '<span data-presence="'+a[0]+'" style="flex-grow:'+a[2]+'"></span>';}).join('')+'</div><dl class="sscx-presence-legend">'+attendance.map(function(a){return '<div><dt><span class="sscx-chart-key" data-presence="'+a[0]+'" aria-hidden="true"></span>'+a[1]+'</dt><dd>'+a[2]+'</dd></div>';}).join('')+'</dl>':'<p class="sscx-empty">Aucun effectif disponible pour cette journee.</p>';
      presence+='</section>';
      var cycles='<section class="sscx-cycle-snapshot" aria-label="Repartition par cycle">'+heading('Repartition par cycle','chart-no-axes-combined');
      cycles+=s&&sums.enrolled?'<ul class="sscx-cycle-distribution">'+Array.from(groups).map(function(pair,i){var n=total(pair[1]).enrolled;return '<li data-cycle-summary style="--sscx-cycle-color:var(--sscx-cycle-'+(i%3)+')"><div><strong>'+esc(pair[0])+'</strong><span><b>'+n+'</b> <small>('+percent(n)+' %)</small></span></div><div class="sscx-cycle-track" aria-hidden="true"><span style="width:'+(n/sums.enrolled*100)+'%"></span></div><small>'+pair[1].length+' classes</small></li>';}).join('')+'</ul>':'<p class="sscx-empty">Aucune repartition disponible.</p>';
      cycles+='</section>';
      var followup='<details class="sscx-overview-followup" '+(dash.followup?'open':'')+'><summary>Dossiers a consulter <span class="sscx-badge">'+(data?.items.length||0)+'</span>'+icon('chevron-down')+'</summary><div><h4>'+esc(p.list)+'</h4>'+list(data?.items||[],dash.busy?'Chargement...':'Aucun dossier disponible.')+'</div></details>';
      return '<section class="sscx-section sscx-role-overview sscx-school-overview" data-dashboard-role="'+esc(bridge.session().role)+'"><header class="sscx-overview-header"><div><h2>Vue generale de l\'ecole</h2><p>'+icon('calendar-days')+'<span>'+esc(s?.date||'Date non disponible')+'</span><span class="sscx-badge '+(demo()?'sscx-demo':'')+'">'+source()+'</span></p></div>'+tool('Actualiser la synthese','data-lot2="dashboard-refresh" '+(dash.busy?'disabled':''),'refresh-cw')+'</header>'+status(dash.message,dash.kind,'sscx-dashboard-state')+
        '<div class="sscx-overview-summary"><div class="sscx-overview-core"><div class="sscx-metrics sscx-overview-metrics">'+measures.map(function(m,i){return '<article class="sscx-metric" data-metric-kind="'+['students','classes','presence','teachers'][i]+'"><span>'+icon(m[3])+esc(m[0])+'</span><strong>'+esc(m[1])+'</strong><small>'+esc(m[2])+'</small></article>';}).join('')+'</div><div class="sscx-overview-insights">'+presence+cycles+'</div></div><aside class="sscx-overview-inbox" aria-label="Alertes et dossiers">'+heading('Alertes et notifications','bell')+list(data?.alerts||[],dash.busy?'Chargement...':'Aucune notification disponible.')+followup+'</aside></div>'+
        '<section class="sscx-overview-quick" aria-label="Acces rapides">'+heading('Acces rapides','panels-top-left')+'<div class="sscx-shortcuts">'+p.targets.map(function(id,i){return actionLink(id,p.areas[i]);}).join('')+'</div></section>'+
        (compact?'<div class="sscx-tabs sscx-overview-tabs" role="tablist" aria-label="Rubriques de la vue generale">'+[['students','Eleves'],['teachers','Enseignants'],['palmares','Palmares']].map(function(t){return '<button type="button" id="overview-tab-'+t[0]+'" role="tab" aria-controls="overview-panel-'+t[0]+'" aria-selected="'+(dash.tab===t[0])+'" tabindex="'+(dash.tab===t[0]?'0':'-1')+'" data-overview-tab="'+t[0]+'">'+t[1]+'</button>';}).join('')+'</div>':'')+
        '<div class="sscx-overview-layout">'+pupils+teachers+palmares+'</div><footer class="sscx-overview-footer sscx-overview-pending"><section aria-label="Dernieres activites">'+heading('Dernieres activites','history')+'<span class="sscx-badge">Non raccorde</span><p>Aucune activite transmise.</p></section><section aria-label="Calendrier scolaire">'+heading('Calendrier scolaire','calendar-days')+'<span class="sscx-badge">Non raccorde</span><p>Aucun evenement transmis.</p></section></footer></section>';
    }
    function recordValue(row,key){return row[key]==null||row[key]===''?'Non renseigne':String(row[key]);}
    function recordAction(row){return tool('Consulter '+recordValue(row,'name'),'data-lot2-record="'+esc(row.id)+'"','eye');}
    function recordField(def,row,key){
      var col=def.columns.find(function(c){return c[0]===key;});
      return col?'<div data-record-field="'+esc(key)+'"><dt>'+esc(copy(col[1]))+'</dt><dd>'+esc(recordValue(row,key))+'</dd></div>':'';
    }
    function recordHeading(row,n){return '<div class="sscx-record-heading">'+icon(n)+'<h3>'+esc(recordValue(row,'name'))+'</h3></div>';}
    function recordState(row){return '<span class="sscx-badge">'+esc(recordValue(row,'status'))+'</span>';}
    function domainList(def,rows){
      var kind=spec(active).definition;
      if(kind==='attendance'&&bridge.session().role==='parent')kind='children';
      if(!['classes','children','subjects','family','staff','assignments','lessons','campaigns','documents','receipts','years','alerts','overview'].includes(kind))return '';
      var tiles=['classes','children','subjects','overview'].includes(kind);
      return '<p class="sscx-record-source">'+source()+'</p><ul class="sscx-domain-list '+(tiles?'sscx-domain-tiles':'')+'" role="list" aria-label="'+esc(def.title)+'" data-presentation="'+esc(kind)+'">'+rows.map(function(row){
        var heading=recordHeading(row,domainIcons[kind]),state=recordState(row),action=recordAction(row),content='';
        if(tiles){
          content=heading+(kind==='overview'?'<strong class="sscx-record-value">'+esc(recordValue(row,'value'))+'</strong>':'')+'<dl class="sscx-record-fields">'+def.columns.filter(function(c){return !['name','status','value'].includes(c[0]);}).map(function(c){return recordField(def,row,c[0]);}).join('')+'</dl><footer>'+state+action+'</footer>';
        }else if(kind==='family'){
          content='<div class="sscx-person">'+heading+'<p>'+esc(recordValue(row,'relationship'))+'</p></div><dl class="sscx-record-fields sscx-family-child">'+recordField(def,row,'student')+'</dl><div class="sscx-record-end">'+state+action+'</div>';
        }else if(['assignments','lessons','campaigns'].includes(kind)){
          var dateKey=kind==='lessons'?'date':'due';
          content='<dl class="sscx-record-date">'+recordField(def,row,dateKey)+'</dl><div>'+heading+'<dl class="sscx-record-fields">'+recordField(def,row,'class')+recordField(def,row,'subject')+'</dl></div><div class="sscx-record-end">'+state+action+'</div>';
        }else if(kind==='years'){
          content=heading+'<dl class="sscx-record-period">'+recordField(def,row,'start')+recordField(def,row,'end')+'</dl><div class="sscx-record-end">'+state+action+'</div>';
        }else{
          content='<div>'+heading+'<dl class="sscx-record-fields">'+def.columns.filter(function(c){return !['name','status','amount'].includes(c[0]);}).map(function(c){return recordField(def,row,c[0]);}).join('')+'</dl></div><div class="sscx-record-end">'+(kind==='receipts'?'<dl class="sscx-record-amount">'+recordField(def,row,'amount')+'</dl>':'')+state+action+'</div>';
        }
        return '<li class="sscx-domain-item">'+content+'</li>';
      }).join('')+'</ul>';
    }
    function register(def,rows){
      var cards=spec(active).kind==='cards';
      return '<div class="sscx-table-scroll" tabindex="0" role="region" aria-label="'+esc(copy(def.title))+'" data-presentation="register"><table><caption>'+esc(copy(def.title))+' · '+source()+'</caption><thead><tr>'+(cards?'<th scope="col">Selection</th>':'')+def.columns.map(function(c){return '<th scope="col" data-field="'+esc(c[0])+'">'+esc(copy(c[1]))+'</th>';}).join('')+'<th scope="col">Dossier</th></tr></thead><tbody>'+rows.map(function(row){return '<tr>'+(cards?'<td><input type="checkbox" data-lot2-select="'+esc(row.id)+'" aria-label="Selectionner '+esc(row.name)+'" '+(v.checked.includes(row.id)?'checked':'')+'></td>':'')+def.columns.map(function(c){return '<td data-field="'+esc(c[0])+'">'+(c[0]==='status'?recordState(row):esc(recordValue(row,c[0])))+'</td>';}).join('')+'<td>'+recordAction(row)+'</td></tr>';}).join('')+'</tbody></table></div>';
    }
    function records(def,rows){
      var subset=rows.filter(function(row){return def.columns.some(function(c){return String(row[c[0]]??'').toLocaleLowerCase().includes(v.query.toLocaleLowerCase());})&&(!v.classFilter||row.class===v.classFilter)&&(!v.statusFilter||row.status===v.statusFilter);});
      var max=Math.max(0,Math.ceil(subset.length/8)-1);v.page=Math.min(v.page,max);
      var pageRows=subset.slice(v.page*8,v.page*8+8);
      return (subset.length?(domainList(def,pageRows)||register(def,pageRows)):'<p class="sscx-empty" role="status">Aucune ligne correspondant aux filtres.</p>')+
        '<div class="sscx-pagination">'+tool('Page precedente','data-lot2-page="-1" '+(v.page===0?'disabled':''),'chevron-left')+'<span>Page '+(v.page+1)+' / '+(max+1)+' · '+subset.length+' lignes chargees</span>'+tool('Page suivante','data-lot2-page="1" '+(v.page===max?'disabled':''),'chevron-right')+'</div>';
    }
    function tabs(def){return '<div class="sscx-tabs" role="tablist" aria-label="Rubriques du dossier">'+def.tabs.map(function(tab,i){return '<button type="button" role="tab" id="lot2-tab-'+i+'" aria-controls="lot2-tabpanel" aria-selected="'+(v.tab===tab)+'" tabindex="'+(v.tab===tab?'0':'-1')+'" data-lot2-tab="'+esc(tab)+'">'+esc(copy(tab))+'</button>';}).join('')+'</div>';}
    function draftFields(kind){return kind==='lessons'?[['name','Titre de la seance'],['objective','Objectifs'],['content','Deroulement']]:kind==='assignments'?[['name','Titre du devoir'],['due','Echeance'],['instruction','Consigne']]:[['name','Evaluation'],['grade','Note'],['comment','Observation']];}
    function draftIssue(input){if(!input.value.trim())return 'Ce champ est requis.';if(!input.validity.valid)return input.name==='grade'?'Saisir un nombre positif ou nul, avec au plus deux décimales.':'Vérifier la valeur saisie.';return '';}
    function draftHtml(kind){
      var fields=draftFields(kind),errors=Object.keys(v.draftErrors);
      function field(f){var key=f[0],long=['objective','content','instruction','comment'].includes(key),id='lot2-draft-'+key,attrs='id="'+id+'" name="'+key+'" required '+(v.draftErrors[key]?'aria-invalid="true" aria-describedby="'+id+'-error"':'');return '<div class="sscx-draft-field"><label for="'+id+'">'+esc(copy(f[1]))+' <span aria-hidden="true">*</span></label>'+(long?'<textarea '+attrs+' rows="5" maxlength="1000">'+esc(v.draftValues[key]||'')+'</textarea>':'<input '+attrs+' '+(key==='grade'?'type="number" min="0" step="0.01"':key==='due'?'type="date"':'type="text" maxlength="1000"')+' value="'+esc(v.draftValues[key]||'')+'">')+(v.draftErrors[key]?'<p class="sscx-field-error" id="'+id+'-error">'+esc(v.draftErrors[key])+'</p>':'')+'</div>';}
      return '<form class="sscx-form sscx-local-draft" data-lot2-draft novalidate><h2>Brouillon visuel</h2><p class="sscx-draft-notice">Non transmis. Aucune publication ni sauvegarde métier.</p>'+(errors.length?'<div class="sscx-draft-errors" role="alert" tabindex="-1" aria-label="Champs à corriger"><h3>Champs à corriger</h3><ul>'+fields.filter(function(f){return v.draftErrors[f[0]];}).map(function(f){return '<li>'+command(copy(f[1])+': '+v.draftErrors[f[0]],'data-lot2-field="'+f[0]+'"','circle-alert')+'</li>';}).join('')+'</ul></div>':'')+'<fieldset><legend>Repères</legend><div class="sscx-draft-grid">'+fields.filter(function(f){return ['name','due','grade'].includes(f[0]);}).map(field).join('')+'</div></fieldset><fieldset><legend>Contenu</legend>'+fields.filter(function(f){return !['name','due','grade'].includes(f[0]);}).map(field).join('')+'</fieldset><div class="sscx-toolbar"><button type="submit">'+icon('check')+'<span>Vérifier le brouillon</span></button>'+command('Fermer','data-lot2="draft-close"','x')+'</div></form>';
    }
    function operationHtml(kind){
      var op=operations[kind]?.[v.operation];if(!op)return '';
      var rows=v.data?.rows||[],classMode=op[0]==='class',choices=classMode?rows.filter(function(r,i){return r.class_id&&rows.findIndex(function(x){return x.class_id===r.class_id;})===i;}).map(function(r){return {id:r.class_id,name:r.class};}):rows;
      return '<section class="sscx-operation"><div class="sscx-section-heading"><h2>'+esc(op[1])+'</h2>'+tool('Fermer cette operation','data-lot2="operation-close"','x')+'</div><form class="sscx-form" data-lot2-operation-form><label for="lot2-operation-record">'+(classMode?'Classe autorisee':'Dossier autorise')+'</label><select name="'+(classMode?'class_id':'record_id')+'" id="lot2-operation-record" required><option value="">Selectionner</option>'+choices.map(function(r){return '<option value="'+esc(r.id)+'" '+(v.checked.includes(r.id)?'selected':'')+'>'+esc(r.name||r.student||r.id)+'</option>';}).join('')+'</select>'+(['lost','replace','revoked','suspend','reactivation-request'].includes(op[0])?'<label for="lot2-operation-reason">Motif</label><input name="reason" id="lot2-operation-reason" required maxlength="500">':'')+'<p class="sscx-domain-note">Non raccorde. '+(op[0]==='print'?'Impression / ZIP non valides. ':op[0]==='reactivation-request'?'Une demande ne reactive pas une autorisation. ':'')+'Aucune operation effectuee par ce formulaire.</p><div class="sscx-toolbar"><button type="submit">'+icon('arrow-up-right')+'<span>Ouvrir dans le moteur existant</span></button></div></form></section>';
    }
    function operationGroups(kind){
      var groups=kind==='cards'?[['Preparation',[0,1,2,3]],['Production',[4,5]],['Suivi des cartes',[6,7,8]]]:[['Personnes',[0]],['Autorisations',[1,2]],['Demandes',[3,4]]];
      var names=kind==='cards'?['Preparation individuelle','Preparation par classe','Photographies','Recadrage existant','Previsualisation','Impression','Remplacement','Carte perdue','Carte revoquee']:['Photographies','Autorisations','Suspensions','Demandes de reactivation','Etats des demandes'];
      var icons=kind==='cards'?['user-round','school','image','crop','eye','printer','replace','circle-help','circle-slash']:['image','shield-check','circle-pause','rotate-ccw','clipboard-list'];
      return '<div class="sscx-operation-groups">'+groups.map(function(group){return '<section aria-label="'+esc(group[0])+'"><h3>'+esc(group[0])+'</h3><div class="sscx-shortcuts">'+group[1].map(function(i){return command(names[i],'data-lot2-operation="'+i+'"',icons[i]);}).join('')+'</div></section>';}).join('')+'</div>';
    }
    function html(a){
      var featureHtml=featureScreens?.html();if(featureHtml)return featureHtml;
      var schoolHtml=schoolWorkflows?.html();if(schoolHtml)return schoolHtml;
      var s=spec(a),def=definition(a),kind=s.kind;
      if(['read','unavailable'].includes(kind))return '';
      if(kind==='legacy')return '<section class="sscx-section sscx-legacy-target"><h2>'+esc(a.label)+'</h2><p>'+esc(a.group)+' · '+esc(root.SchoolSafeCodexCatalog.branches.find(function(b){return b.id===a.branch;}).label)+'</p><dl class="sscx-data-list"><dt>Destination existante</dt><dd>'+esc(a.legacy?.branch)+' / '+esc(a.legacy?.action)+'</dd><dt>Etat</dt><dd>Non raccorde dans la preview</dd><dt>Limite</dt><dd>'+esc(bridge.familyMessage(a))+'</dd></dl><div class="sscx-toolbar">'+command('Ouvrir la fonction existante','data-command="open-existing"')+'</div></section>';
      var top='<div class="sscx-section-heading"><h2 class="sscx-domain-title">'+icon(domainIcons[s.definition]||'folder')+'<span>'+esc(copy(def.title))+'</span></h2>'+tool('Actualiser','data-lot2="refresh" '+(v.busy?'disabled':''),'refresh-cw')+'</div>';
      var specific=(schoolWorkflows?.entry(a)||'')+(featureScreens?.entry(a)||'');
      if(kind==='scanner')specific='<div class="sscx-scanner-modes">'+[['entry','Entree / Presence','scan-line'],['pickup','Retrait / Sortie','log-out'],['fees','Controle des frais','badge-check'],['verify','Verifier la carte','shield-check']].map(function(mode){return command(mode[1],'data-lot2-mode="'+mode[0]+'" '+(a.id==='scan-fees'&&mode[0]!=='fees'?'disabled':''),mode[2]);}).join('')+'</div><p class="sscx-domain-note">Le scanner existant reste requis. Aucune camera, lecture QR ni decision serveur dans la preview.</p>';
      if(kind==='cards')specific='<div class="sscx-card-stage"><img src="'+logo+'" alt="SchoolSafe"><div><strong>Preparation des cartes scolaires</strong><p>Aucun QR ni carte officielle genere ici.</p></div></div>'+operationGroups(kind)+'<p class="sscx-domain-note">Impression et ZIP non valides. Operations a ouvrir dans le studio existant.</p>';
      if(kind==='family')specific+=operationGroups(kind);
      if(['assignments','lessons','grades'].includes(kind)&&!v.draft)specific=command(copy('Preparer un brouillon'),'data-lot2="draft"','square-pen');
      if(kind==='payments'||kind==='receipts')specific='<p class="sscx-domain-note">Aucun encaissement, annulation ni recu officiel produit ici. Le contrat et les confirmations existantes sont requis.</p>';
      if(kind==='access')specific='<p class="sscx-domain-note">Creation native des comptes et droits IAM inchanges.</p>';
      if(kind==='pickup')specific='<p class="sscx-domain-note">La remise exige la preuve serveur valide, la photographie approuvee et la confirmation humaine du moteur existant. Aucune remise confirmee ici.</p>';
      if(kind==='cash')specific='<p class="sscx-domain-note">Aucune ouverture ni cloture de caisse effectuee ici. Etats et montants doivent provenir du serveur.</p>';
      var body='';
      if(v.detail){var fields=v.data?.fields||{},presentation=recordPresenter()?.detail(s.definition,fields,Object.assign({},labels,Object.fromEntries(def.columns)));body=command(copy('Retour a la liste'),'data-lot2="list"','arrow-left')+tabs(def)+'<section id="lot2-tabpanel" role="tabpanel" aria-labelledby="lot2-tab-'+def.tabs.indexOf(v.tab)+'"><h2'+(presentation?' class="sscx-visually-hidden"':'')+'>'+esc(copy(v.tab))+'</h2>'+(presentation||'<dl class="sscx-data-list">'+Object.entries(fields).map(function(pair){return '<dt>'+esc(labels[pair[0]]||pair[0])+'</dt><dd>'+esc(pair[1])+'</dd>';}).join('')+'</dl>')+(!v.busy&&!Object.keys(fields).length?'<p class="sscx-empty">Aucune information autorisee pour cette rubrique.</p>':'')+'</section>';
      }else{
        var rows=v.data?.rows||[],classes=Array.from(new Set(rows.map(function(r){return r.class;}).filter(Boolean))),statuses=Array.from(new Set(rows.map(function(r){return r.status;}).filter(Boolean)));
        body='<div class="sscx-list-filters"><label>Recherche<input type="search" data-lot2-search value="'+esc(v.query)+'" placeholder="'+(recordPresenter()?'Nom, référence ou information':'Nom, reference ou information')+'"></label>'+(def.columns.some(function(c){return c[0]==='class';})?'<label>Classe<select data-lot2-filter="class"><option value="">'+(recordPresenter()?'Toutes les classes':'Toutes les classes autorisees')+'</option>'+classes.map(function(c){return '<option '+(v.classFilter===c?'selected':'')+'>'+esc(c)+'</option>';}).join('')+'</select></label>':'')+'<label>'+esc(copy('Etat'))+'<select data-lot2-filter="status"><option value="">'+(recordPresenter()?'Tous les états':'Tous les etats')+'</option>'+statuses.map(function(c){return '<option '+(v.statusFilter===c?'selected':'')+'>'+esc(c)+'</option>';}).join('')+'</select></label></div>'+(recordPresenter()&&(v.query||v.classFilter||v.statusFilter)?'<div class="sscx-filter-actions">'+command('Effacer les filtres','data-lot2="clear-filters"','filter-x')+'</div>':'')+(v.data?records(def,rows):'')+(kind==='cards'?'<p>'+v.checked.length+' dossier(s) selectionne(s) · Selection visuelle uniquement</p>':'');
      }
      var related=def.links.map(function(id){return actionLink(id);}).join('');
      return '<section class="sscx-section sscx-priority-screen" data-screen-kind="'+esc(kind)+'" '+(recordPresenter()?'data-record-presentation':'')+'>'+top+specific+status(v.message,v.kind)+'<div class="sscx-screen-body" data-screen-surface="'+(v.operation>=0?'operation':v.draft?'draft':v.detail?'detail':'list')+'">'+(v.operation>=0?operationHtml(kind):v.draft?draftHtml(kind):body)+'</div>'+(related?'<section class="sscx-linked-functions"><h3>Fonctions liées</h3><div class="sscx-related-actions">'+related+'</div></section>':'')+'<div class="sscx-toolbar">'+command('Ouvrir la fonction existante','data-command="open-existing"')+'</div></section>';
    }
    function focus(name){bridge.icons();host.querySelector(name)?.focus();}
    function openDetail(id){v.selected=id;v.detail=true;v.draft=false;v.tab=definition(active).tabs[0];load({record_id:id,tab:v.tab}).then(function(){focus('[data-lot2="list"]');});}
    function onClick(b){
      if(featureScreens?.onClick(b))return true;
      if(schoolWorkflows?.onClick(b))return true;
      if(b.dataset.lot2Field&&draftFields(spec(active).kind).some(function(f){return f[0]===b.dataset.lot2Field;})){focus('#lot2-draft-'+b.dataset.lot2Field);return true;}
      if(b.dataset.overviewTab&&['students','teachers','palmares'].includes(b.dataset.overviewTab)){dash.tab=b.dataset.overviewTab;redraw();focus('[data-overview-tab="'+dash.tab+'"]');return true;}
      if(b.dataset.lot2Destination){bridge.openRecord(b.dataset.lot2Destination,b.dataset.recordId);return true;}
      if(b.dataset.lot2Record){openDetail(b.dataset.lot2Record);return true;}
      if(b.dataset.lot2Page){v.page+=Number(b.dataset.lot2Page);redraw();focus('[data-lot2-page="'+b.dataset.lot2Page+'"]');return true;}
      if(b.dataset.lot2Tab){v.tab=b.dataset.lot2Tab;load({record_id:v.selected,tab:v.tab}).then(function(){focus('[data-lot2-tab="'+v.tab+'"]');});return true;}
      if(b.dataset.lot2Mode){bridge.openMode(active,b.dataset.lot2Mode);return true;}
      if(b.dataset.lot2Operation!=null){v.operation=Number(b.dataset.lot2Operation);v.message='';redraw();focus('[data-lot2-operation-form] select');return true;}
      switch(b.dataset.lot2){
        case 'dashboard-refresh':loadDashboard();return true;
        case 'refresh':load(v.detail?{record_id:v.selected,tab:v.tab}:{});return true;
        case 'list':v.detail=false;load().then(function(){focus('[data-lot2-search]');});return true;
        case 'draft':v.draft=true;v.message='';redraw();focus('[data-lot2-draft] input');return true;
        case 'draft-close':v.draft=false;v.draftValues={};v.draftErrors={};v.message='';redraw();focus('[data-lot2="draft"]');return true;
        case 'clear-filters':v.query='';v.classFilter='';v.statusFilter='';v.page=0;redraw();focus('[data-lot2-search]');return true;
        case 'operation-close':var index=v.operation;v.operation=-1;redraw();focus('[data-lot2-operation="'+index+'"]');return true;
      }
      return false;
    }
    function onInput(el){
      if(featureScreens?.onInput(el))return true;
      if(schoolWorkflows?.onInput(el))return true;
      if(el.closest('[data-lot2-draft]')&&el.name){
        v.draftValues[el.name]=el.value;v.message='';host.querySelector('.sscx-view-status')?.remove();
        if(v.draftErrors[el.name]){
          var issue=draftIssue(el),error=host.querySelector('#'+el.id+'-error'),link=host.querySelector('[data-lot2-field="'+el.name+'"]');
          if(issue){v.draftErrors[el.name]=issue;if(error)error.textContent=issue;var field=draftFields(spec(active).kind).find(function(f){return f[0]===el.name;});if(link&&field)link.querySelector('span').textContent=copy(field[1])+': '+issue;}
          else{delete v.draftErrors[el.name];el.removeAttribute('aria-invalid');el.removeAttribute('aria-describedby');error?.remove();link?.closest('li')?.remove();if(!Object.keys(v.draftErrors).length)host.querySelector('.sscx-draft-errors')?.remove();}
        }
        return true;
      }
      if(!el.matches('[data-lot2-search]'))return false;var pos=el.selectionStart;v.query=el.value;v.page=0;redraw();var input=host.querySelector('[data-lot2-search]');input?.focus();input?.setSelectionRange(pos,pos);return true;
    }
    function onChange(el){if(featureScreens?.onChange(el))return true;if(schoolWorkflows?.onChange(el))return true;if(el.dataset.lot2Filter){v[el.dataset.lot2Filter==='class'?'classFilter':'statusFilter']=el.value;v.page=0;redraw();focus('[data-lot2-filter="'+el.dataset.lot2Filter+'"]');return true;}if(el.dataset.lot2Select){v.checked=v.checked.filter(function(id){return id!==el.dataset.lot2Select;});if(el.checked)v.checked.push(el.dataset.lot2Select);redraw();focus('[data-lot2-select="'+el.dataset.lot2Select+'"]');return true;}return false;}
    function onSubmit(form){
      if(featureScreens?.onSubmit(form))return true;
      if(schoolWorkflows?.onSubmit(form))return true;
      if(form.matches('[data-lot2-operation-form]')){if(form.reportValidity()){var params=Object.fromEntries(new FormData(form)),op=operations[spec(active).kind]?.[v.operation];if(op){params.operation=op[0];params.selection_kind=op[0]==='class'?'class':'record';bridge.openOperation(active,params);}}return true;}
      if(!form.matches('[data-lot2-draft]'))return false;
      v.draftValues=Object.fromEntries(new FormData(form));v.draftErrors={};
      draftFields(spec(active).kind).forEach(function(f){var issue=draftIssue(form.elements.namedItem(f[0]));if(issue)v.draftErrors[f[0]]=issue;});
      if(Object.keys(v.draftErrors).length){v.message='';redraw();focus('.sscx-draft-errors');}else{notice('Brouillon verifie visuellement. Non sauvegarde et non transmis.','empty');focus('[data-lot2-draft] button[type="submit"]');}return true;
    }
    function onKey(event){var selector=event.target.matches('[data-overview-tab]')?'[data-overview-tab]':'[data-lot2-tab]';if(!event.target.matches(selector)||!['ArrowLeft','ArrowRight','Home','End'].includes(event.key))return;event.preventDefault();var tabs=Array.from(host.querySelectorAll(selector)),i=tabs.indexOf(event.target),next=event.key==='Home'?0:event.key==='End'?tabs.length-1:(i+(event.key==='ArrowRight'?1:-1)+tabs.length)%tabs.length;tabs[next].click();}
    return {html:html,unavailableHtml:function(){return featureScreens?.html()||'';},dashboardHtml:dashboardHtml,activate:activate,reset:reset,openRecord:function(id){if(v.data?.rows.some(function(r){return r.id===id;}))openDetail(id);},onToggle:function(el){if(host.contains(el)&&el.matches('.sscx-overview-followup'))dash.followup=el.open;},onClick:onClick,onInput:onInput,onChange:onChange,onSubmit:onSubmit,onKey:onKey,destroy:function(){destroyed=true;compactOverview.removeEventListener('change',resizeOverview);reset();},spec:spec};
  }
  root.SchoolSafeCodexLot2={create:create,profiles:profiles,validateData:validateData,validateDashboard:validateDashboard,iconFor:iconFor};
})(window);
