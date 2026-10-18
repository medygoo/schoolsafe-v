const fs=require('node:fs'),path=require('node:path'),crypto=require('node:crypto');
const root=path.resolve(__dirname,'../..');
const read=f=>fs.readFileSync(path.join(root,f),'utf8');
const catalog=JSON.parse(read('docs/codex-ui-v1/LOT1_BUTTON_SCREEN_MAPPING.json'));
const specs=JSON.parse(read('docs/codex-ui-v1/LOT2_SCREEN_SPECS.json'));
const previous=JSON.parse(read('docs/codex-ui-v1/LOT1_MANIFEST.json'));
const fullResults=JSON.parse(read('docs/codex-ui-v1/lot2-tests/results.json'));
const finalResults=JSON.parse(read('docs/codex-ui-v1/lot2-tests/final-check.json'));
const overviewResults=JSON.parse(read('docs/codex-ui-v1/lot2-tests/overview-unified-results.json'));
const compositionResults=JSON.parse(read('docs/codex-ui-v1/overview-composition-tests/results.json'));
const recordResults=JSON.parse(read('docs/codex-ui-v1/record-tests/results.json'));
if(recordResults.status!=='PASS'||recordResults.errors.length||recordResults.apiRequests.length)throw new Error('Fiches et formulaires non conformes');
if(compositionResults.status!=='PASS'||compositionResults.errors.length||compositionResults.apiRequests.length)throw new Error('Composition Vue generale non conforme');
const proMaxResults=JSON.parse(read('docs/codex-ui-v1/pro-max-tests/results.json'));
const domainResults=JSON.parse(read('docs/codex-ui-v1/domain-tests/results.json'));
const branchResults=JSON.parse(read('docs/codex-ui-v1/branch-tests/results.json'));
if(branchResults.status!=='PASS'||branchResults.errors.length||branchResults.apiRequests.length)throw new Error('Presentation des branches non conforme');
if(domainResults.status!=='PASS'||domainResults.errors.length||domainResults.apiRequests.length)throw new Error('Presentations par fonction non conformes');
if(proMaxResults.status!=='PASS'||proMaxResults.errors.length||proMaxResults.apiRequests.length)throw new Error('Controle UI/UX Pro Max non conforme');
const currentResults=fullResults.results.some(r=>r.name.startsWith('Version finale'))?fullResults.results:[...fullResults.results,...finalResults.results];
if(overviewResults.status!=='PASS'||overviewResults.errors.length||overviewResults.nativeRequests.length)throw new Error('Vue generale non conforme');
if([...fullResults.results,...finalResults.results].some(r=>r.status!=='PASS')||fullResults.errors.length||finalResults.errors.length||fullResults.apiRequests.length||finalResults.apiRequests.length)throw new Error('Tests non conformes');
const captureNames=fs.readdirSync(path.join(__dirname,'lot2-tests')).filter(f=>f.endsWith('.png'));
fs.writeFileSync(path.join(__dirname,'LOT2_BROWSER_TEST_RESULTS.md'),[
 '# LOT 2 - Rapport des tests navigateur','',
 'Preview HTTP effectivement ouverte dans le navigateur Codex : '+fullResults.preview+'. Le parcours tableau de bord vers dossier a aussi ete examine dans ce navigateur. Tests automatises : Chromium fourni par le runtime local, aucune dependance installee.','',
 '## Passe UI/UX Pro Max','',
 'La passe actuelle applique UI/UX Pro Max au perimetre isole. '+proMaxResults.checks.length+' groupes de controles supplementaires passent : contrastes, lisibilite/controles, navigation clavier mobile, portrait/paysage et texte a 200%, mouvements reduits et JASPE. Voir UI_UX_PRO_MAX_REVIEW.md, UI_UX_PRO_MAX_DESIGN_SYSTEM.md et pro-max-tests/results.json.','',
 '## Presentation par fonction','',
 'Fiches et formulaires : '+recordResults.schemas+' schemas, '+recordResults.responsiveVariants+' variantes de fiches et '+recordResults.checks.length+' groupes cibles. Les trois brouillons restent locaux, sans sauvegarde ni transmission. Voir RECORD_PRESENTATION_HANDOFF.md et record-tests/results.json.','',
 'Pendant cette passe des contenus interieurs, la suite LOT 2 a ete reexecutee; les derniers ajustements de validation au fil de la saisie et de libelles par schema ont ensuite ete verifies par record-presentation-tests et les controles Pro Max. Les controles de composition Vue generale ont aussi ete reexecutes. Les resultats domain-tests, branch-tests et overview-unified-results restent des preuves des revisions precedentes, pas de nouvelles executions de cette passe.','',
 domainResults.checks.length+' groupes cibles passent sur '+domainResults.schemas+' schemas, avec '+domainResults.responsiveVariants+' combinaisons de largeur/theme, puis les parcours parent, les etats de refus et les donnees malveillantes. Les colonnes, identifiants et contrats sont conserves. La suite complete LOT 2, les controles Pro Max et ceux de Vue generale ont aussi ete reexecutes pendant la revision des branches. Voir UI_UX_PRO_MAX_DOMAIN_PRESENTATION.md et domain-tests/results.json.','',
 '## Accents et organisation des branches','',
 branchResults.checks.length+' groupes cibles passent : '+branchResults.branches+' branches, '+branchResults.destinations+' destinations, '+branchResults.responsiveVariants+' combinaisons branche/largeur/theme et '+branchResults.contrasts.length+' mesures texte/fond, plus les icones, etats actifs, groupes de commandes, texte agrandi, couleurs forcees et navigation mobile. Voir UI_UX_PRO_MAX_BRANCH_PRESENTATION.md et branch-tests/results.json.','',
 '## Revision Vue generale unifiee','',
 'Etape 2, composition de reference : '+compositionResults.responsiveVariants+' combinaisons responsive et '+compositionResults.checks.length+' groupes cibles passent. Voir OVERVIEW_COMPOSITION_HANDOFF.md et overview-composition-tests/results.json. Les valeurs graphiques proviennent du DTO existant; aucune courbe horaire, activite ou calendrier invente.','',
 'La revision actuelle est verifiee par overview-detail-tests.cjs : '+overviewResults.responsiveVariants+' variantes responsive, groupes par cycle, totaux, onglets et clavier, actualisation, redimensionnement, acces au dossier exact, DTO absent/incoherent. Resultat : '+overviewResults.status+'. Captures overview-unified-*.png examinees. Voir OVERVIEW_DETAIL_HANDOFF.md.','',
 '## Resultats LOT 2 reexecutes','',
 '| Groupe | Resultat |','| --- | --- |',
 ...currentResults.map(r=>'| '+r.name.replaceAll('|',' / ')+' | '+r.status+' |'),'',
 '150 combinaisons dashboards (15 postes x 5 largeurs x 2 themes), 180 combinaisons ecrans prioritaires (18 destinations x 5 largeurs x 2 themes), verification des 211 destinations, plus les parcours clavier, erreurs et autorisations. Largeurs : 320, 390, 768, 1280, 1920; captures principales a 1440. Les tableaux defilent dans leur propre conteneur, pas la page.','',
 'La suite complete LOT 2 a ete reexecutee pendant la passe UI/UX Pro Max, apres correction du repliement asynchrone de Dossiers et notifications. Les resultats actuels couvrent aussi la double ouverture. Les 14 groupes LOT 1 et final-check.json sont des preuves historiques conservees, pas des executions supplementaires revendiquees pour cette passe. Les derniers ajustements de contraste du menu et de JASPE ont ete verifies par le controle cible Pro Max.','',
 'Erreurs pageerror : '+fullResults.errors.length+'. Appels natifs Rosario observes dans la preview : '+fullResults.apiRequests.length+'. Aucun resultat substitue ne constitue un test de production.','',
 '## Captures','',
 ...captureNames.map(n=>'- ['+n+'](lot2-tests/'+n+')'),'',
 'Les images sont des captures de demonstration. La capture mobile plein-page montre la barre fixe a la position du premier viewport; en usage elle reste au bas de l ecran, pas entre les lignes d un tableau. Les ecrans eleves, cartes, familles, scanner et pedagogie ont ete captures apres ouverture de leur propre surface.','',
 '## Reproduction','',
 'Depuis le workspace, lancer le serveur local puis :','',
 '```powershell','node docs/codex-ui-v1/record-presentation-tests.cjs','node docs/codex-ui-v1/overview-composition-tests.cjs','node docs/codex-ui-v1/overview-detail-tests.cjs','node docs/codex-ui-v1/lot2-browser-tests.cjs','node docs/codex-ui-v1/ui-ux-pro-max-tests.cjs','node docs/codex-ui-v1/domain-presentation-tests.cjs','node docs/codex-ui-v1/branch-presentation-tests.cjs','```','',
 'Le chemin du runtime Playwright dans le harness correspond a la machine Codex. Cursor devra adapter uniquement ce chemin si necessaire, puis reproduire les tests sur son integration autorisee. La variable SCHOOLSAFE_PREVIEW_URL permet de choisir une autre adresse locale.','',
 '## Non execute / risques restants','',
 'Aucun compte authentifie, PostgreSQL, preuve de remise, photo reelle, camera, voix JASPE, paiement, recu officiel, publication, impression ou ZIP teste. Les 87 ouvertures historiques ne sont pas les ecrans metier integres. Les droits individuels inconnus, normalisations de DTO et pagination serveur restent a resoudre par Cursor. Les tests de non-debordement ne constituent pas une certification WCAG exhaustive; lecteur d ecran et appareils physiques non testes.',''
].join('\n'));
const endpoints={teacherBoard:'/native/scan-control/board/teacher',schoolBoard:'/native/scan-control/board/school',onSiteStudent:'/native/scan-control/on-site?student_id',onSiteClass:'/native/scan-control/on-site?class_id',onSiteSchool:'/native/scan-control/on-site',scanAudit:'/native/scan-control/audit?student_id'};
const safe=v=>String(v??'').replaceAll('|',' / ').replace(/\s+/g,' ');
const rows=catalog.actions.map(a=>{
 const s=specs.specs[a.id],roles=Object.entries(catalog.roles).filter(([,r])=>r.actions.includes(a.id)).map(([,r])=>r.label);
 const interactions=s.kind==='read'?'Selection/perimetre, lecture, actualisation; audit: filtres, pagination, detail':s.kind==='unavailable'?'Etat indisponible explicite':s.kind==='legacy'?'Destination precise, limites, ouverture du module existant':s.kind==='cards'?'Selection, filtres, 9 formulaires d ouverture, details et retour':s.kind==='family'?'Recherche, filtres, dossiers, 5 operations d ouverture, retour':s.kind==='scanner'?'4 modes officiels, demande d ouverture, aucun scan nouveau':['assignments','grades','lessons'].includes(s.kind)?'Liste, recherche, filtres, details, onglets, brouillon visuel valide sans publication':'Liste, recherche, filtres, pagination, details, onglets, actualisation, retour';
 return {destination:a.id,button:a.label,screen:a.label+' / '+s.kind,branch:a.branch,roles,permission:a.permission||'Non resolue pour cette entree; authorize explicite requis',permissionHint:s.permission,interaction:interactions,data:s.kind==='legacy'||s.kind==='unavailable'?'Aucune donnee metier simulee pour cette ouverture':s.kind==='read'?'Fixture marquee / port GET LOT1':'Fixture marquee / consult normalise; aucune donnee reelle lue',contracts:a.type==='read'?['GET '+endpoints[a.reader]]:s.contracts,contractSource:s.contractSource,legacy:a.legacy||null,status:s.status};
});
fs.writeFileSync(path.join(__dirname,'LOT2_BUTTON_SCREEN_FUNCTIONAL_MATRIX.json'),JSON.stringify(rows,null,2)+'\n');
const matrix=['# LOT 2 - Matrice des 211 destinations','',
 'Couverture du catalogue LOT 1 conserve. Les roles ci-dessous sont des postes de presentation, pas des grants IAM. Les API observees sont candidates au raccordement du DTO; leur association exacte a chaque bouton historique reste a verifier. Permission non resolue = refus, jamais droit herite de la branche. Les indices de permission et routes/source sont detailles dans le JSON et LOT2_SCREEN_SPECS.json. Aucun contrat reel teste.','',
 '| Destination | Branche / bouton | Ecran | Postes | Permission precise | Interaction creee | Donnees | Contrat candidat / ouverture existante | Etat |','| --- | --- | --- | --- | --- | --- | --- | --- | --- |',
 ...rows.map(r=>'| '+[r.destination,r.branch+' / '+r.button,r.screen,r.roles.join(', '),r.permission,r.interaction,r.data,(r.contracts.join('; ')||'Lecture non etablie')+'; '+(r.legacy?JSON.stringify(r.legacy):'')+'; preuve '+r.contractSource,r.status].map(safe).join(' | ')+' |')].join('\n')+'\n';
const secondary=[
 ['cards / individual','Preparation individuelle','Studio existant; POST /native/cards/students/:studentId/print candidat','Droits cartes / licence; lecture de la carte officielle requise'],
 ['cards / class','Preparation par classe','Studio existant; POST /native/cards/batches candidat','class_id officiel; droit cartes de la classe'],
 ['cards / photo','Photographies','Recadrage/photographie existants; POST /native/students/:id/photo candidat','Dossier/photo autorises'],
 ['cards / crop','Recadrage existant','Moteur de recadrage existant, non recree','Permission et contrat de sauvegarde a confirmer'],
 ['cards / preview','Previsualisation','Studio existant; aucun QR reconstruit','Lecture de carte officielle et identifiants canoniques'],
 ['cards / print','Impression','Studio existant; impression et ZIP non valides','cards.request.print / capacites et licence a verifier'],
 ['cards / replace','Remplacement','POST /native/cards/replace candidat, ouverture seule ici','CARD_REPLACE et perimetre officiel'],
 ['cards / lost','Carte perdue','POST /native/cards/loss-report candidat, ouverture seule ici','CARD_LOST et motif officiel'],
 ['cards / revoked','Carte revoquee','Moteur cartes/scan-control existant, contrat individuel a verifier','CARD_REVOKE; aucune revocation depuis le formulaire'],
 ['family / photo','Photographies','POST /native/family/guardians/:guardianId/portrait candidat','Droit photo; revue humaine inchangee'],
 ['family / authorization','Autorisations','GET /native/family/students/:studentId/pickup-authorizations/mine ou /school','Lien principal / own_children / droits direction precis'],
 ['family / suspend','Suspensions','POST /native/family/pickup-authorizations/:id/parent-suspend candidat','Droit parent principal et enfant rattache'],
 ['family / reactivation-request','Demandes de reactivation','POST /native/family/pickup-authorizations/:id/reactivation-request candidat','Demande seulement, aucun droit de reactivate'],
 ['family / request-status','Etats des demandes','Lecture familiale officielle a resoudre','Autorisation de lecture sur enfant et autorisation'],
 ['scanner / entry','Entree / Presence','POST /native/scan-control/scan mode entry via moteur existant','SCAN_ENTRY; lieu et carte actifs'],
 ['scanner / pickup','Retrait / Sortie','POST /native/scan-control/scan mode pickup via moteur existant','SCAN_PICKUP; preuve serveur, photo, confirmation humaine'],
 ['scanner / fees','Controle des frais','POST /native/scan-control/scan mode fees via moteur existant','SCAN_FEES / finance.control.scan; delegation/perimetre'],
 ['scanner / verify','Verifier la carte','POST /native/scan-control/scan mode verify via moteur existant','SCAN_VERIFY; aucune remise ni encaissement'],
 ['pedagogy / draft','Verifier le brouillon','Aucun appel. Validation locale en memoire.','Aucune publication ou sauvegarde metier']
];
fs.writeFileSync(path.join(__dirname,'LOT2_BUTTON_SCREEN_FUNCTIONAL_MATRIX.md'),matrix+'\n## Commandes secondaires creees\n\nToutes sont des interactions visuelles ou demandes d ouverture, non raccordees dans la preview. Les ID record/eleve/personne/carte doivent etre resolus par la facade officielle; ils ne sont pas interchangeables.\n\n| Commande | Bouton / ecran | Contrat candidat | Autorisation / limite |\n| --- | --- | --- | --- |\n'+secondary.map(r=>'| '+r.map(safe).join(' | ')+' |').join('\n')+'\n');
function entry(file){const b=fs.readFileSync(path.join(root,file));return {file,bytes:b.length,sha256:crypto.createHash('sha256').update(b).digest('hex')};}
function list(dir){return fs.readdirSync(path.join(root,dir)).filter(n=>fs.statSync(path.join(root,dir,n)).isFile()).map(n=>dir+'/'+n);}
const appFiles=list('app/modules/codex-ui-v1').filter(f=>/\.(html|js)$/.test(f)).concat(list('app/styles/codex-ui-v1').filter(f=>f.endsWith('.css')));
const docs=list('docs/codex-ui-v1').filter(f=>f.split('/').pop().startsWith('LOT2_')&&!f.endsWith('LOT2_MANIFEST.json')).concat(['build-lot2-specs.cjs','lot2-browser-tests.cjs','lot2-final-check.cjs','preview-server.cjs','package-lot2.cjs','overview-detail-tests.cjs','OVERVIEW_DETAIL_HANDOFF.md','ui-ux-pro-max-tests.cjs','UI_UX_PRO_MAX_DESIGN_SYSTEM.md','UI_UX_PRO_MAX_REVIEW.md','domain-presentation-tests.cjs','UI_UX_PRO_MAX_DOMAIN_PRESENTATION.md','branch-presentation-tests.cjs','UI_UX_PRO_MAX_BRANCH_PRESENTATION.md'].map(n=>'docs/codex-ui-v1/'+n));
const tests=list('docs/codex-ui-v1/lot2-tests').filter(f=>f.endsWith('.png')||f.endsWith('results.json')||f.endsWith('final-check.json')).concat(...['pro-max-tests','domain-tests','branch-tests'].map(dir=>list('docs/codex-ui-v1/'+dir).filter(f=>f.endsWith('.json')||f.endsWith('.png'))));
const original=read('../TREE.sha256').trim().split(/\r?\n/).map(s=>s.split('\t'));
docs.push('docs/codex-ui-v1/OVERVIEW_COMPOSITION_HANDOFF.md','docs/codex-ui-v1/overview-composition-tests.cjs');
docs.push('docs/codex-ui-v1/RECORD_PRESENTATION_HANDOFF.md','docs/codex-ui-v1/record-presentation-tests.cjs');
tests.push(...list('docs/codex-ui-v1/record-tests').filter(f=>f.endsWith('.json')||f.endsWith('.png')));
tests.push(...list('docs/codex-ui-v1/overview-composition-tests').filter(f=>f.endsWith('.json')||f.endsWith('.png')));
function verify(base){let checked=0,modified=[],missing=[];for(const [f,h]of original){const p=path.join(base,f);if(!fs.existsSync(p)){missing.push(f);continue;}checked++;if(crypto.createHash('sha256').update(fs.readFileSync(p)).digest('hex')!==h)modified.push(f);}return {checked,modified,missing};}
const sourceIntegrity=verify(root),baselineIntegrity=verify(path.join(root,'../baseline'));
if(sourceIntegrity.modified.length||sourceIntegrity.missing.length||baselineIntegrity.modified.length||baselineIntegrity.missing.length)throw new Error('Reference modifiee');
const old=new Map(previous.appFiles.map(e=>[e.file,e.sha256]));
const changes=appFiles.map(entry).map(e=>({...e,change:!old.has(e.file)?'NEW':old.get(e.file)!==e.sha256?'MODIFIED_CODEX_ONLY':'UNCHANGED_LOT1'}));
const oldArtifacts=[...previous.documentation,...previous.tests].filter(e=>fs.existsSync(path.join(root,e.file))&&entry(e.file).sha256!==e.sha256).map(e=>e.file);
const kinds=Object.values(specs.specs).reduce((r,s)=>(r[s.kind]=(r[s.kind]||0)+1,r),{});
const results=JSON.parse(read('docs/codex-ui-v1/lot2-tests/results.json'));
const manifest={reference:'CODEX_SCHOOLSAFE_INTERACTIVE_DASHBOARDS_LOT2_V1',sourceHead:previous.sourceHead,preview:results.preview,scope:'isolated_frontend_only',integration:'NOT_STARTED',changesRosario:'NONE',realBackendTested:false,sourceIntegrity,baselineIntegrity,appFiles:changes,documentation:docs.map(entry),tests:tests.map(entry),lot1ArtifactsUpdatedByRegression:oldArtifacts,counts:{destinations:rows.length,branches:13,presentationPosts:15,specializedDestinations:116,existingModuleLaunchers:87,reads:6,unavailable:2,kinds},statuses:{CODEX_LOT2_UI:'GREEN',PREVIEW_OPENED:'YES',DASHBOARDS_COMPLETE:'YES',ROSARIO_BUTTON_MATRIX:'COMPLETE',PRIORITY_SCREENS:'PARTIAL',SIX_READ_SCREENS:'GREEN',JASPE_PRESENTATION:'GREEN',RESPONSIVE:'GREEN',ROSARIO_LIVE_E2E:'NOT_EXECUTED',ROSARIO_NEW_CHANGES:'NONE',CURSOR_INTEGRATION:'NOT_STARTED'}};
manifest.uiUxProMax={reference:'SCHOOLSAFE_CODEX_UI_UX_PRO_MAX_V1',status:'PASS_ISOLATED_UI',fullSuiteGroups:currentResults.length,additionalGroups:proMaxResults.checks.length,overviewResponsiveVariants:overviewResults.responsiveVariants,skill:'C:/Users/PC/.codex/skills/ui-ux-pro-max/SKILL.md',realBusinessIntegration:false};
manifest.domainPresentation={status:domainResults.status,schemas:domainResults.schemas,customLayouts:domainResults.customLayouts,responsiveVariants:domainResults.responsiveVariants,checks:domainResults.checks.length,realBusinessIntegration:false};
manifest.branchPresentation={status:branchResults.status,branches:branchResults.branches,destinations:branchResults.destinations,responsiveVariants:branchResults.responsiveVariants,contrastPairs:branchResults.contrasts.length,checks:branchResults.checks.length,realBusinessIntegration:false};
manifest.overviewComposition={status:compositionResults.status,responsiveVariants:compositionResults.responsiveVariants,checks:compositionResults.checks.length,newBusinessContracts:false,realBusinessIntegration:false};
manifest.recordPresentation={status:recordResults.status,schemas:recordResults.schemas,responsiveVariants:recordResults.responsiveVariants,checks:recordResults.checks.length,newBusinessContracts:false,realBusinessIntegration:false};
fs.writeFileSync(path.join(__dirname,'LOT2_MANIFEST.json'),JSON.stringify(manifest,null,2)+'\n');
console.log(JSON.stringify({appFiles:changes.length,changes:changes.map(e=>[e.file,e.change]),sourceIntegrity,baselineIntegrity,counts:manifest.counts},null,2));
