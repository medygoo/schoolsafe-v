const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname, '../..');
const catalog = JSON.parse(fs.readFileSync(path.join(__dirname, 'LOT1_BUTTON_SCREEN_MAPPING.json'), 'utf8'));
const definitions = {
  students: {title:'Dossiers des eleves',columns:[['name','Eleve'],['class','Classe'],['reference','Matricule'],['status','Dossier']],tabs:['Scolarite','Famille','Documents'],fields:['name','class','reference','status','year','guardian','contact','document'],links:['presence-student','rosario-016'],api:['GET /native/students','GET /native/students/:id/record'],source:'server/src/studentsnative/routes.ts:56; server/src/studentrecordnative/routes.ts:96',permission:'school.student.read'},
  children: {title:'Mes enfants',columns:[['name','Enfant'],['class','Classe'],['reference','Matricule'],['status','Dossier']],tabs:['Scolarite','Famille','Documents'],fields:['name','class','reference','status','year','guardian','contact','document'],links:['presence-student','rosario-201','rosario-197','rosario-203'],api:['GET /native/parent/children','GET /native/parent/children/:studentId/record'],source:'server/src/studentrecordnative/routes.ts:345',permission:'Parent rattache; own_children'},
  classes: {title:'Classes et affectations',columns:[['name','Classe'],['teacher','Enseignant'],['subject','Matiere'],['status','Affectation']],tabs:['Organisation','Affectations'],fields:['name','teacher','subject','status','year'],links:['presence-class','rosario-105'],api:['GET /native/pedagogy/classes','GET /native/pedagogy/teacher-assignments'],source:'server/src/pedagogynative/routes.ts:36,59',permission:'school.class.read / pedagogy.assignment.read; portee affectations'},
  family: {title:'Familles et personnes autorisees',columns:[['name','Personne'],['student','Enfant'],['relationship','Lien'],['status','Autorisation']],tabs:['Identite','Autorisation','Demandes'],fields:['name','student','relationship','status','photo_review','request_status','contact'],links:['presence-student','rosario-201'],api:['GET /native/family/students/:studentId/pickup-authorizations/mine','GET /native/family/students/:studentId/pickup-authorizations/school'],source:'server/src/familynative/routes.ts:162-174,238-279',permission:'Droits famille exacts + own_children / ecole; decision distincte'},
  cards: {title:'Cartes scolaires',columns:[['name','Eleve'],['class','Classe'],['reference','Demande'],['status','Carte']],tabs:['Preparation','Photographie','Production'],fields:['name','class','reference','status','photo_review','print_status'],links:['presence-student'],api:['GET /native/cards/print-requests','GET /native/cards/students/:studentId/credential'],source:'server/src/cardsnative/routes.ts:114,227',permission:'Droits cartes / capacites / licence par operation'},
  scanner: {title:'Scanner et controles',columns:[['name','Point de controle'],['mode','Mode'],['status','Disponibilite']],tabs:['Modes','Perimetre'],fields:['name','mode','status','scope','expiry'],links:['scan-history'],api:['POST /native/scan-control/scan','GET /native/scan-control/locations','GET /native/scan-control/capabilities'],source:'server/src/scancontrol/routes.ts:10,48,52,102',permission:'SCAN_ENTRY / SCAN_PICKUP / SCAN_FEES / SCAN_VERIFY; par mode'},
  attendance: {title:'Presences et passages',columns:[['name','Eleve'],['class','Classe'],['date','Date'],['status','Presence']],tabs:['Presence','Historique'],fields:['name','class','date','status','reason'],links:['presence-student','presence-class','presence-school','scan-history'],api:[],source:'app/modules/security/scan-control.js; six lectures dans read-adapter.js',permission:'Resoudre le droit de la destination historique'},
  subjects: {title:'Matieres',columns:[['name','Matiere'],['class','Classe'],['coefficient','Coefficient'],['status','Etat']],tabs:['Matiere'],fields:['name','class','coefficient','status'],links:['rosario-029'],api:['GET /native/pedagogy/subjects'],source:'server/src/pedagogynative/routes.ts:42',permission:'pedagogy.subject.read/manage'},
  assignments: {title:'Devoirs et corrections',columns:[['name','Devoir'],['class','Classe'],['subject','Matiere'],['due','Echeance'],['status','Publication']],tabs:['Consigne','Correction'],fields:['name','class','subject','due','status','instruction','correction'],links:['rosario-029'],api:['GET /native/pedagogy/assignments'],source:'server/src/pedagogynative/routes.ts:100-160',permission:'pedagogy.assignment.read/manage; affectations; publication parent'},
  grades: {title:'Evaluations et resultats',columns:[['name','Eleve'],['class','Classe'],['subject','Evaluation'],['grade','Note publiee'],['status','Publication']],tabs:['Evaluation','Publication'],fields:['name','class','subject','grade','status','comment'],links:['rosario-028','rosario-031'],api:['GET /native/pedagogy/assignments/:id/grades','GET /native/pedagogy/parent/grades/:studentId','GET /native/pedagogy/students/:studentId/averages'],source:'server/src/pedagogynative/routes.ts:141,211,218',permission:'pedagogy.grade.read/manage; affectations / own_children'},
  lessons: {title:'Preparation pedagogique',columns:[['name','Seance'],['class','Classe'],['subject','Matiere'],['date','Date'],['status','Preparation']],tabs:['Objectifs','Deroulement'],fields:['name','class','subject','date','status','objective','content'],links:['rosario-028'],api:['GET /native/pedagogy/lesson-plans'],source:'server/src/pedagogynative/routes.ts:167',permission:'pedagogy.lesson-plan.read/manage; affectations'},
  fees: {title:'Frais scolaires',columns:[['name','Frais'],['class','Classe / perimetre'],['amount','Montant serveur'],['due','Echeance'],['status','Etat']],tabs:['Affectation','Situation'],fields:['name','class','amount','due','status','currency','balance'],links:['scan-fees','rosario-048'],api:['GET /native/finance/fee-structures','GET /native/finance/students/:studentId/fees'],source:'server/src/financenative/routes.ts:36,55',permission:'finance.fee.read/manage; portee eleve/classe'},
  campaigns: {title:'Controle des frais',columns:[['name','Campagne'],['class','Classes autorisees'],['due','Echeance'],['status','Etat']],tabs:['Campagne','Delegations'],fields:['name','class','due','status','scope','expiry'],links:['scan-fees'],api:['GET /native/finance/fee-control/campaigns','GET /native/scan-control/fees/delegations'],source:'server/src/financenative/routes.ts:138; server/src/scancontrol/routes.ts:65',permission:'finance.control.scan/manage; delegation et portee'},
  payments: {title:'Paiements et caisse',columns:[['name','Eleve'],['reference','Reference'],['amount','Montant serveur'],['date','Date'],['status','Etat']],tabs:['Paiement','Recu'],fields:['name','reference','amount','date','status','currency','receipt'],links:['rosario-048'],api:['GET /native/finance/students/:studentId/payments','GET /native/finance/reports/daily'],source:'server/src/financenative/routes.ts:79,85,102,131',permission:'finance.payment.record / finance.report.read; caisse et eleve'},
  receipts: {title:'Recus',columns:[['name','Eleve'],['reference','Paiement'],['amount','Montant serveur'],['date','Date'],['status','Recu']],tabs:['Recu'],fields:['name','reference','amount','date','status','currency'],links:[],api:['GET /native/finance/receipts/:paymentId'],source:'server/src/financenative/routes.ts:111',permission:'finance.receipt.read; paiement reel'},
  documents: {title:'Documents et dossiers',columns:[['name','Document'],['student','Dossier'],['date','Date'],['status','Etat']],tabs:['Document','Historique'],fields:['name','student','date','status','reference','category'],links:[],api:['POST /documents/create','POST /documents/:id/issue'],source:'server/src/documents/routes.ts:23,57; app/modules/core/visual-output-closure-a.js',permission:'Droit documentaire exact a confirmer'},
  access: {title:'Utilisateurs et acces',columns:[['name','Profil'],['post','Poste'],['scope','Perimetre'],['status','Etat']],tabs:['Profil','Droits'],fields:['name','post','scope','status','expiry'],links:[],api:['GET /native/access/profiles','GET /native/access/roles'],source:'server/src/accessnative/routes.ts:87,93,128',permission:'Droits IAM precis; aucune creation locale'},
  staff: {title:'Personnel',columns:[['name','Personnel'],['post','Poste'],['scope','Affectation'],['status','Etat']],tabs:['Profil','Affectation'],fields:['name','post','scope','status','date'],links:[],api:[],source:'app/modules/hr/hr-demo.js; app/modules/core/visual-wiring.js',permission:'staff.read; contrats RH a confirmer'},
  years: {title:'Organisation scolaire',columns:[['name','Annee / cycle'],['start','Debut'],['end','Fin'],['status','Etat']],tabs:['Organisation'],fields:['name','start','end','status'],links:[],api:[],source:'app/modules/core/visual-output-closure-a.js; app/app.js:676',permission:'school.manage; contrat individuel a confirmer'},
  alerts: {title:'Alertes et decisions',columns:[['name','Sujet'],['category','Categorie'],['date','Date'],['status','Etat']],tabs:['Signalement','Suivi'],fields:['name','category','date','status','reason'],links:[],api:[],source:'server/src/pilotage/alerts/routes.ts; server/src/pilotage/approvals/routes.ts',permission:'pilotage.alerts.read / pilotage.approvals.read; decision distincte'},
  overview: {title:'Synthese et indicateurs',columns:[['name','Indicateur'],['value','Valeur serveur'],['date','Date de lecture'],['status','Source']],tabs:['Source'],fields:['name','value','date','status','scope'],links:['presence-school','presence-school-count'],api:[],source:'server/src/pilotage/dashboard/routes.ts; app/app.js:675',permission:'pilotage.dashboard.read; sources officielles'},
  legacy: {title:'Ecran existant a reutiliser',columns:[],tabs:[],fields:[],links:[],api:[],source:'LOT0_MENUS.json; LOT0_RACCORDEMENTS.md',permission:'Non etablie; refus jusqu a resolution'}
};
definitions.pickup={title:'Remises et sorties',columns:[['name','Eleve'],['class','Classe'],['guardian','Personne autorisee'],['date','Preparation'],['status','Remise']],tabs:['Dossier','Preuves','Suivi'],fields:['name','class','guardian','date','status','reason'],links:['scan-history','rosario-041'],api:['POST /native/scan-control/scan (mode pickup)'],source:'server/src/scancontrol/routes.ts:102; app/modules/security/scan-control.js',permission:'SCAN_PICKUP; preuve serveur et confirmation humaine'};
definitions.balances={title:'Situation personnelle des frais',columns:[['name','Eleve'],['class','Classe'],['balance','Solde serveur'],['due','Echeance'],['status','Situation']],tabs:['Situation'],fields:['name','class','balance','currency','due','status'],links:['scan-fees','rosario-048'],api:['GET /native/finance/students/:studentId/fees'],source:'server/src/financenative/routes.ts:55',permission:'finance.fee.read; portee eleve / own_children'};
definitions.cash={title:'Journees de caisse',columns:[['name','Caisse'],['date','Journee'],['scope','Responsable'],['status','Etat serveur']],tabs:['Journee','Pieces'],fields:['name','date','scope','status','reference'],links:['rosario-048'],api:['GET /native/finance/reports/daily','POST /native/finance/cash-register/open','POST /native/finance/cash-register/close'],source:'server/src/financenative/routes.ts:119-131',permission:'finance.report.read; ouverture/cloture distinctes'};
function normalize(s) { return s.normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase(); }
function classify(a) {
  const l=normalize(a.label);
  if(a.type==='read')return 'read';
  if(a.type==='unavailable')return 'unavailable';
  if(a.id==='scan-fees'||a.id==='rosario-037'||/enregistrer une entree|verifier l.identite/.test(l))return 'scanner';
  if(/studio des cartes/.test(l))return 'cards';
  if(/personnes autorisees|parents et tuteurs|parent principal|consentements/.test(l))return 'family';
  if(a.branch==='school'){
    if(/documents/.test(l))return 'documents';
    if(/annee scolaire/.test(l))return 'years';
    if(/classes|affectations/.test(l)&&!/eleves/.test(l))return 'classes';
    if(/presence/.test(l))return 'attendance';
    if(/devoir/.test(l))return 'assignments';
    if(/resultat/.test(l))return 'grades';
    if(/personnel/.test(l))return 'staff';
    if(/eleve|inscription|dossiers|identite|creer l/.test(l))return 'students';
  }
  if(a.branch==='pedagogy'){
    if(/matiere/.test(l))return 'subjects';
    if(/devoir/.test(l))return 'assignments';
    if(/note|moyenne|resultat/.test(l))return 'grades';
    if(/preparation|cahier/.test(l)&&!/epreuve/.test(l))return 'lessons';
    if(/classes|affectation/.test(l))return 'classes';
    if(/presence|absence|retard/.test(l))return 'attendance';
  }
  if(a.branch==='security')return /personnes/.test(l)?'family':/sortie/.test(l)?'pickup':/alerte|incident/.test(l)?'alerts':'attendance';
  if(a.branch==='finance')return /recu/.test(l)?'receipts':/controle|regulariser/.test(l)?'campaigns':/solde|impaye/.test(l)?'balances':/caisse|cloture|journee|rapports|exports|statistique|tableau financier/.test(l)?'cash':/frais|echeance/.test(l)?'fees':/rechercher un eleve/.test(l)?'students':'payments';
  if(a.branch==='administration')return /documents|archives|attestation|certificat|convocation|rechercher|centre documentaire/.test(l)?'documents':/comptes|droits|autorisations/.test(l)?'access':/cycles/.test(l)?'years':'legacy';
  if(a.branch==='people')return /enseignant|personnel|affectations/.test(l)?'staff':'legacy';
  if(a.branch==='pilotage')return /alerte|approbation/.test(l)?'alerts':'overview';
  return 'legacy';
}
const specs={};
for(const a of catalog.actions){
 const kind=classify(a),def=definitions[kind];
 specs[a.id]={kind,screen:a.label,definition:kind,contracts:def?.api||[],contractSource:def?.source||a.source,permission:a.permission||def?.permission||'A confirmer',legacy:a.legacy||null,status:kind==='read'?'GET adapter, live non teste':kind==='unavailable'?'Moteur indisponible':kind==='legacy'?'Reutilisation existant, raccordement requis':'Interaction visuelle, consult/openExisting a raccorder'};
}
const payload={definitions,specs};
fs.writeFileSync(path.join(root,'app/modules/codex-ui-v1/screen-specs.js'),'(function(root){"use strict";root.SchoolSafeCodexScreenSpecs='+JSON.stringify(payload,null,2)+';})(window);\n');
fs.writeFileSync(path.join(__dirname,'LOT2_SCREEN_SPECS.json'),JSON.stringify(payload,null,2)+'\n');
console.log(Object.values(specs).reduce((r,s)=>(r[s.kind]=(r[s.kind]||0)+1,r),{}));
