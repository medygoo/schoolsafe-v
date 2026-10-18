/* Synthetic fixtures only. Never load this file in the integrated application. */
(function(root){
  'use strict';
  var uuid=function(n){return '00000000-0000-4000-8000-'+String(n).padStart(12,'0');};
  var people=['Amira Exemple','Daniel Exemple','Lina Exemple','Noah Exemple','Sarah Exemple','Joel Exemple'];
  var students=Array.from({length:24},function(_,i){return {id:uuid(i+1),name:people[i%people.length]+' '+String(i+1).padStart(2,'0'),class_id:uuid(i%2?102:101),class:i%2?'Classe exemple B':'Classe exemple A',reference:'DEMO-'+String(i+1).padStart(3,'0'),status:i%5?'Dossier complet':'A completer'};});
  var scenario='normal';
  async function delay(signal){await new Promise(function(resolve){setTimeout(resolve,120);});if(signal?.aborted)throw new DOMException('Aborted','AbortError');if(scenario==='error')throw new Error('Erreur simulee');if(scenario==='denied')throw Object.assign(new Error('Acces refuse simule'),{denied:true});}
  function collection(kind,session){
    var base=session.role==='parent'?students.slice(0,2):session.role==='teacher'?students.filter(function(s){return s.class==='Classe exemple A';}):students;
    switch(kind){
      case 'students':case 'children':return base;
      case 'classes':return [{id:uuid(101),name:'Classe exemple A',teacher:'Mme Enseignante Exemple',subject:'Francais',status:'Affectation exemple'},{id:uuid(102),name:'Classe exemple B',teacher:'M. Enseignant Exemple',subject:'Mathematiques',status:'Affectation exemple'}].filter(function(_,i){return session.role!=='teacher'||i===0;});
      case 'family':return base.slice(0,6).map(function(s,i){return {id:uuid(201+i),name:'Responsable exemple '+(i+1),student:s.name,relationship:i%2?'Personne accreditee':'Parent principal',status:i%3===0?'Suspendue':'Autorisation exemple'};});
      case 'cards':return base.map(function(s,i){return Object.assign({},s,{status:i%4===0?'Perdue (exemple)':i%3===0?'Revoquee (exemple)':'Preparation exemple'});});
      case 'scanner':return [{id:uuid(301),name:'Portail exemple',mode:'entry / pickup / verify',status:'Moteur non raccorde'},{id:uuid(302),name:'Controle financier exemple',mode:'fees',status:'Moteur non raccorde'}];
      case 'attendance':return base.map(function(s,i){return {id:s.id,name:s.name,class:s.class,date:'11/10/2026 08:00',status:i%4?'Present (exemple)':'Absent (exemple)'};});
      case 'pickup':return base.slice(0,8).map(function(s){return {id:s.id,name:s.name,class:s.class,guardian:'Personne accreditee exemple',date:'11/10/2026',status:'En attente (fictif)'};});
      case 'balances':return base.slice(0,8).map(function(s,i){return {id:s.id,name:s.name,class:s.class,balance:i%2?'0 USD (fictif)':'40 USD (fictif)',due:'20/10/2026',status:i%2?'En ordre (exemple)':'A regulariser (exemple)'};});
      case 'cash':return [{id:uuid(951),name:'Caisse exemple',date:'11/10/2026',scope:'Agent de caisse exemple',status:'Journee fictive non ouverte'}];
      case 'subjects':return ['Francais','Mathematiques','Sciences'].map(function(s,i){return {id:uuid(400+i),name:s,class:'Classe exemple A',coefficient:'Valeur exemple',status:'Exemple'};});
      case 'assignments':return [{id:uuid(501),name:'Lecture et comprehension',class:'Classe exemple A',subject:'Francais',due:'15/10/2026',status:'Publie (exemple)'},{id:uuid(502),name:'Problemes et mesures',class:'Classe exemple B',subject:'Mathematiques',due:'16/10/2026',status:'Brouillon (exemple)'}];
      case 'grades':return base.slice(0,12).map(function(s,i){return {id:s.id,name:s.name,class:s.class,subject:'Evaluation exemple',grade:String(12+i%7)+' / 20 (fictif)',status:'Publie (exemple)'};});
      case 'lessons':return [{id:uuid(601),name:'Les fractions',class:'Classe exemple A',subject:'Mathematiques',date:'12/10/2026',status:'Preparation exemple'},{id:uuid(602),name:'Lecture expressive',class:'Classe exemple A',subject:'Francais',date:'13/10/2026',status:'A relire (exemple)'}];
      case 'fees':return [{id:uuid(701),name:'Frais de scolarite (exemple)',class:'Classe exemple A',amount:'40 USD (fictif)',due:'20/10/2026',status:'A regulariser (exemple)'},{id:uuid(702),name:'Frais scolaires (exemple)',class:'Classe exemple B',amount:'35 USD (fictif)',due:'25/10/2026',status:'En ordre (exemple)'}];
      case 'campaigns':return [{id:uuid(801),name:'Campagne scolaire exemple',class:'Classes exemples A et B',due:'20/10/2026',status:'Consultation exemple'},{id:uuid(802),name:'Campagne cantine exemple',class:'Classe exemple A',due:'25/10/2026',status:'Brouillon (exemple)'}];
      case 'payments':case 'receipts':return base.slice(0,10).map(function(s,i){return {id:uuid(900+i),name:s.name,reference:'PIECE-DEMO-'+(i+1),amount:'40 USD (fictif)',date:'10/10/2026',status:'Exemple sans paiement reel'};});
      case 'documents':return [{id:uuid(1001),name:'Attestation exemple',student:base[0].name,date:'10/10/2026',status:'Brouillon non officiel'},{id:uuid(1002),name:'Dossier scolaire exemple',student:base[1].name,date:'09/10/2026',status:'A verifier (exemple)'}];
      case 'access':case 'staff':return [{id:uuid(1101),name:'Mme Enseignante Exemple',post:'Enseignement',scope:'Classe exemple A',status:'Profil fictif'},{id:uuid(1102),name:'Agent Exemple',post:'Controle des frais',scope:'Classe exemple B',status:'Delegation fictive'}];
      case 'years':return [{id:uuid(1201),name:'Annee scolaire exemple 2026-2027',start:'01/09/2026',end:'30/06/2027',status:'Exemple'}];
      case 'alerts':return [{id:uuid(1301),name:'Dossier photographique a examiner',category:'Carte / autorisation',date:'11/10/2026',status:'A examiner (exemple)'},{id:uuid(1302),name:'Demande de reactivation exemple',category:'Famille',date:'10/10/2026',status:'En attente (exemple)'}];
      case 'overview':return [{id:uuid(1401),name:'Eleves inscrits',value:'180 (fictif)',date:'11/10/2026',status:'Demonstration'},{id:uuid(1402),name:'Eleves presents',value:'140 (fictif)',date:'11/10/2026',status:'Demonstration'}];
      default:return [];
    }
  }
  root.SchoolSafeCodexDemo={
    setScenario:function(s){scenario=s;},
    featureScreen:async function(context){
      await delay(context.signal);
      var definition=root.SchoolSafeCodexFeatureCatalog.definitions[context.key];
      if(!definition)throw new Error('Unknown preview definition');
      return {rows:scenario==='empty'?[]:definition.examples.map(function(values,i){var row={id:'feature-demo-'+context.key+'-'+(i+1)};definition.fields.forEach(function(field,index){row[field.key]=values[index]||'';});return row;})};
    },
    schoolWorkflow:async function(context){
      await delay(context.signal);
      if(scenario==='empty')return {students:[],classes:[],families:[],years:[]};
      return {students:collection('students',context.session).map(function(row,i){return Object.assign({},row,{checkpoints:[{label:'Identite',complete:true},{label:'Scolarite prevue',complete:true},{label:'Pieces du dossier',complete:i%5!==0}]});}),classes:collection('classes',context.session),families:collection('family',context.session),years:collection('years',context.session)};
    },
    choices:async function(type,s){await delay();return type==='class'?collection('classes',s).map(function(r){return {id:r.id,label:r.name};}):collection('students',s).map(function(r){return {id:r.id,label:r.name};});},
    read:async function(reader,params,context){await delay(context?.signal);if(scenario==='empty'&&reader==='scanAudit')return [];
      if(reader==='scanAudit')return Array.from({length:23},function(_,i){return {occurred_at:'2026-10-11 '+String(7+i%9).padStart(2,'0')+':30',operator_name:'Agent exemple',mode:i%2?'pickup':'entry',decision:i%3?'allowed':'denied',reason:i%3?'Controle de demonstration':'Photo non approuvee (exemple)'};});
      if(reader==='onSiteStudent')return {currently_on_site:params.student_id.endsWith('1')};
      if(reader==='onSiteClass')return {currently_on_site:18};
      if(reader==='onSiteSchool')return {currently_on_site:126};
      return reader==='teacherBoard'?{total:28,present_today:24,absent_today:4,currently_on_site:20,pre_exit:2}:{total_students:180,present_today:140,absent_today:40,currently_on_site:126,pre_exit:9,exited:14,pickups:10};
    },
    consult:async function(action,context){await delay(context.signal);var rows=scenario==='empty'?[]:collection(context.definition,context.session).filter(function(row){return !(context.session.role==='teacher'&&row.class&&row.class!=='Classe exemple A')&&!(context.session.role==='parent'&&String(row.status).includes('Brouillon'));}),fields={};
      if(context.params.record_id){var row=rows.find(function(r){return r.id===context.params.record_id;});if(!row)throw new Error('Dossier exemple absent');
        fields=Object.assign({},row);
        if(context.params.tab==='Famille'){fields={guardian:'Responsable familial exemple',contact:'Contact fictif non joignable',status:'Lien familial exemple'};}
        if(context.params.tab==='Documents'){fields={document:'Dossier scolaire exemple',status:'Aucun document officiel'};}
        if(context.params.tab==='Demandes'){fields={request_status:'En attente (exemple)',status:'Aucune reactivation effectuee'};}
        if(context.params.tab==='Photographie'){fields={photo_review:'Revue humaine requise (exemple)',status:'Aucune photographie reelle'};}
        if(context.params.tab==='Production'){fields={print_status:'Impression / ZIP non valides',status:'Aucune carte produite'};}
        if(context.params.tab==='Consigne'){fields.instruction='Exemple de consigne : lire le texte et identifier les idees principales.';}
        if(context.params.tab==='Objectifs'){fields.objective='Exemple : reconnaitre une fraction et comparer deux representations.';}
        if(context.params.tab==='Deroulement'){fields.content='Decouverte, pratique guidee et exercice de consolidation (exemple).';}
        if(context.params.tab==='Preuves'){fields={name:row.name,reason:'Preuve serveur et revue humaine requises. Aucun jeton fourni.',status:'Non raccorde'};}
      }
      return {rows:rows,fields:fields};
    },
    dashboard:async function(context){await delay(context.signal);if(scenario==='empty')return {metrics:[],items:[],alerts:[]};
      var role=context.session.role,profile=root.SchoolSafeCodexLot2.profiles[role],kinds={admin:'students',school_head:'students',teacher:'classes',parent:'children',guard:'attendance',finance:'campaigns',cashier:'payments',pedagogy:'classes',admissions:'students',secretary:'documents',hr:'staff',accountant:'documents',nurse:'documents',canteen:'classes',communication:'documents'},rows=collection(kinds[role]||'students',context.session);
      var configs={admin:[['Eleves inscrits',180],['Classes',8],['Personnel',22],['Presents aujourd\'hui',140]],school_head:[['Eleves inscrits',180],['Presents',140],['Absents',40],['Decisions en attente',3]],teacher:[['Mes classes',1],['Mes eleves',28],['Presents',24],['Devoirs a suivre',2]],parent:[['Mes enfants',2],['Informations disponibles',3],['Autorisations',2],['Notifications',1]],guard:[['Sur site',126],['Entrees du jour',140],['Sorties preparees',9],['Anomalies a examiner',2]],finance:[['Campagnes',2],['Classes autorisees',8],['Delegations',2],['Consultations a suivre',3]],cashier:[['Pieces a examiner',10],['Consultations',3],['Recus disponibles',0],['Ecarts a examiner',0]]};
      var metrics=(configs[role]||[['Dossiers a suivre',rows.length],['Notifications',2],['Elements en attente',1],['Informations disponibles',3]]).map(function(m){return {label:m[0],value:m[1],hint:'Exemple'};});
      var targets=profile.targets.filter(function(id){return context.actions.some(function(a){return a.id===id;});});
      var recordTargets={admin:'rosario-006',school_head:'rosario-127',teacher:'presence-class',parent:'rosario-194',guard:'rosario-099',finance:'rosario-049',cashier:'rosario-119',pedagogy:'rosario-140',admissions:'rosario-006',secretary:'rosario-086',hr:'rosario-018'};
      var recordTarget=context.actions.some(function(a){return a.id===recordTargets[role];})?recordTargets[role]:'';
      var schoolOverview=null;
      if(role==='admin'||role==='school_head'){
        var classNames=['Premiere maternelle','Deuxieme maternelle','Troisieme maternelle','Premiere primaire','Deuxieme primaire','Troisieme primaire','Premiere secondaire','Deuxieme secondaire'],counts=[10,15,15,25,25,30,30,30],girls=[4,7,8,12,13,15,14,16],present=[8,12,12,20,20,24,22,22];
        schoolOverview={date:'11/10/2026',classes:classNames.map(function(name,i){return {cycle:i<3?'Maternelle':i<6?'Primaire':'Secondaire',name:name,enrolled:counts[i],girls:girls[i],boys:counts[i]-girls[i],unspecified:0,present:present[i],absent:counts[i]-present[i],late:0,unverified:0};}),teachers:classNames.map(function(name,i){return {name:'Enseignant exemple '+(i+1),assignment:name,status:i===6?'Absent':i===7?'Retard':'Present',arrival:i===6?'Non renseignee':i===7?'08:25':'07:45'};})};
      }
      return {metrics:metrics,schoolOverview:schoolOverview,items:rows.slice(0,4).map(function(r){return {id:r.id,name:r.name,subtitle:r.class||r.scope||r.student||r.reference||'Information de demonstration',status:r.status,recordId:recordTarget?r.id:'',target:recordTarget||targets[0]||''};}),alerts:[{id:'demo-notice-1',name:'Information de demonstration',subtitle:'Aucune notification reelle',status:'Exemple',target:''},{id:'demo-notice-2',name:role==='parent'?'Demande familiale exemple':'Dossier a examiner',subtitle:'Decision non raccordee',status:'En attente',target:''}]};
    }
  };
})(window);
