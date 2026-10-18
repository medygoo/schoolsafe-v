/* Documentation only. Does not start servers, open sessions or call business APIs. */
const fs = require('node:fs');
const path = require('node:path');
const crypto = require('node:crypto');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const root = path.resolve(__dirname, '../../..');
const read = file => fs.readFileSync(path.join(root, file), 'utf8');
const json = file => JSON.parse(read(file).replace(/^\uFEFF/, ''));
const hash = data => crypto.createHash('sha256').update(data).digest('hex');
const write = (name, data) => fs.writeFileSync(path.join(__dirname, name), data);
const writeJson = (name, data) => write(name, JSON.stringify(data, null, 2) + '\n');
const catalog = json('docs/codex-ui-v1/LOT1_BUTTON_SCREEN_MAPPING.json');
const specs = json('docs/codex-ui-v1/LOT2_SCREEN_SPECS.json');
const menus = json('docs/codex-ui-v1/LOT0_MENUS.json');
const delivery = json('docs/codex-ui-v1/LOT2_MANIFEST.json');
const snapshot = json('../MANIFEST.json');
const original = read('../TREE.sha256').trim().split(/\r?\n/).map(line => line.split('\t'));
const sourceFiles = new Set();
const contents = new Map();
function source(file) {
  sourceFiles.add(file);
  if (!contents.has(file)) contents.set(file, read(file));
  return contents.get(file);
}
function at(file, number) {
  const excerpt = source(file).split(/\r?\n/)[number - 1];
  assert.notEqual(excerpt, undefined, file + ':' + number);
  return { file, line: number, excerpt: excerpt.trim() };
}
function evidence(id, file, anchor, meaning) {
  const text = source(file), start = text.indexOf(anchor);
  assert(start >= 0, 'Missing evidence anchor: ' + id);
  return { id, ...at(file, text.slice(0, start).split('\n').length), meaning };
}
const u = 'app/modules/codex-ui-v1/unified-interface.js';
const v = 'app/modules/codex-ui-v1/lot2-views.js';
const p = 'app/modules/codex-ui-v1/preview.js';
const w = 'app/modules/core/visual-wiring.js';
const pc = 'app/modules/core/visual-output-p2c.js';
const evidenceList = [
  evidence('E01', p, 'var active={demo:true', 'La session est explicitement fictive.'),
  evidence('E02', p, 'window.codexPreview=', 'Seuls demo/getSession/demoData sont injectes; aucun port metier reel.'),
  evidence('E03', u, 'function demo(s)', 'Le mode demo exige les deux drapeaux session et options.'),
  evidence('E04', u, "message(demo(session())?'Interaction visuelle.", 'Message emis pour toute selection de type existing en demonstration.'),
  evidence('E05', u, "if(demo(session())||typeof options.openExisting", 'Court-circuit demo OU port absent, avant appel du port.'),
  evidence('E06', u, "message(result?.opened===true?", 'Meme message si le port ne confirme pas opened:true.'),
  evidence('E07', u, 'var result=await options.openExisting', 'Le port est un point de raccordement injecte, pas le moteur Rosario.'),
  evidence('E08', u, 'if (s.demo || !s.identityKey', 'Hors demo, session et authorize explicite requis; aucun droit implicite.'),
  evidence('E09', v, "var loader=demo()?bridge.demoData?.consult", 'Les consultations de preview utilisent les fixtures.'),
  evidence('E10', v, 'Consultation non raccordee. Aucune donnee disponible.', 'Port consult absent; different d un refus ou d une erreur reseau.'),
  evidence('E11', v, 'Synthese non raccordee. Aucun chiffre disponible.', 'Port loadDashboard absent.'),
  evidence('E12', u, 'Source de donnees non raccordee.', 'Port read absent pour les six lectures.'),
  evidence('E13', v, "if(kind==='legacy')return", 'Page generique et mention non raccorde inscrite en dur.'),
  evidence('E14', v, 'Aucune operation effectuee par ce formulaire.', 'Notes des operations cartes/famille non conditionnees au raccordement.'),
  evidence('E15', v, 'function html(a)', 'Rendu par schema: une consultation ne prouve pas un parcours de creation complet.'),
  evidence('E16', v, "command('Ouvrir la fonction existante','data-command=", 'Bouton actif sur une page legacy meme en demonstration.'),
  evidence('E17', u, "case 'open-existing':openExisting();", 'Le bouton generique ne transmet ni record_id ni onglet selectionne.'),
  evidence('E18', v, 'bridge.openOperation(active,params)', 'Les formulaires cartes/famille transmettent au contraire des parametres explicites.'),
  evidence('E19', v, 'Non sauvegarde et non transmis.', 'Les brouillons pedagogiques sont locaux, pas des ecritures metier.'),
  evidence('E20', v, 'Dernieres activites', 'Activites/calendrier: zones de presentation sans chargement metier associe.'),
  evidence('E21', u, "typeof options.openJaspe!=='function'", 'Ouverture JASPE native exige un port distinct.'),
  evidence('E22', u, "Audio indisponible dans la demonstration.", 'Dialogue JASPE demo: ni conversation ni voix natives.'),
  evidence('E23', 'docs/codex-ui-v1/preview-server.cjs', "if (!['GET','HEAD'].includes", 'Serveur statique local, sans proxy API.'),
  evidence('E24', 'app/app.js', 'function openActionByBranch', 'Routeur historique present dans la copie; ne pas reconstruire un moteur.'),
  evidence('E25', w, 'if (root.SchoolSafeVisualOutputP1', 'Ordre des couches historiques a respecter avant les replis de domaine.'),
  evidence('E26', pc, 'REPORT_CARDS=BACKEND_GAP', 'Deux entrees bulletin explicitement arretees dans le code historique; pas preuve d absence universelle de fonction Rosario.'),
  evidence('E27', pc, '"pedagogy|Palmar', 'Le palmares possede deja une cible historique rankings.'),
  evidence('E28', pc, '"/native/pedagogy/rankings?"', 'Un ecran historique lit les palmares existants.'),
  evidence('E29', 'server/src/pedagogynative/routes.ts', 'app.get("/native/pedagogy/rankings"', 'Route de liste des palmares presente dans le snapshot.'),
  evidence('E30', 'app/modules/core/visual-output-p2a.js', 'function renderCreate', 'Formulaire historique de creation eleve present.'),
  evidence('E31', 'app/modules/core/visual-output-p2a.js', 'function renderImport', 'Ecran historique d import present, alors que sa destination Codex est legacy.'),
  evidence('E32', 'docs/codex-ui-v1/LOT2_CURSOR_HANDOFF.md', 'Les noms bridge sont', 'Les ports proposes ne sont pas une facade Rosario deja exposee.'),
  evidence('E33', 'app/modules/codex-ui-v1/preview.html', 'src="./demo-data.js"', 'Entree preview charge explicitement les fixtures, pas app.js.'),
  evidence('E34', 'app/modules/codex-ui-v1/read-adapter.js', 'var endpoints', 'Six lecteurs GET existent comme adaptateur, sans transport authentifie fourni dans la preview.')
];
const byId = Object.fromEntries(evidenceList.map(e => [e.id, e]));
const issueData = json('docs/codex-ui-v1/non-raccordement-v1/findings.json');
for (const issue of issueData) for (const id of issue.evidence) assert(byId[id], issue.id + ' evidence ' + id);
const runtime = { window: {} };
for (const f of ['catalog.js', 'screen-specs.js']) vm.runInNewContext(source('app/modules/codex-ui-v1/' + f), runtime, { timeout: 1000 });
assert.equal(JSON.stringify(runtime.window.SchoolSafeCodexCatalog), JSON.stringify(catalog));
assert.equal(JSON.stringify(runtime.window.SchoolSafeCodexScreenSpecs), JSON.stringify(specs));
const count = values => values.reduce((out, key) => (out[key] = (out[key] || 0) + 1, out), {});
const quote = value => '"' + String(value ?? '').replaceAll('"', '""') + '"';
const safe = value => String(value ?? '').replaceAll('|', ' / ').replace(/\s+/g, ' ');
const locations = new Map();
const fallback = {
  school: 'if (branchKey === "school") { openSchoolModule',
  pedagogy: 'if (branchKey === "pedagogy") { openPedagogyModule',
  people: 'if (branchKey === "people") { openHrModule(actionName)',
  accounting: 'if (branchKey === "accounting") { openAccountingModule(actionName)',
  inventory: 'if (branchKey === "inventory") { openInventoryModule(actionName)',
  communication: 'if (branchKey === "communication") { openCommunicationModule(actionName)',
  administration: 'if (branchKey === "administration") { openAdministrationModule(); return; }',
  reports: 'if (branchKey === "reports") { openReportsModule(actionName)',
  pilotage: 'if (branchKey === "pilotage") { openPilotageModule(actionName)',
  finance: 'if (branchKey === "finance") { openFinanceModule(actionName)',
  security: 'if (branchKey === "security") { openSecurityModule(actionName)',
  feeControl: 'if (branchKey === "feeControl") { openFeeControlModule(actionName)'
};
const destinations = catalog.actions.map(action => {
  const spec = specs.specs[action.id];
  const configuredRoles = Object.entries(catalog.roles).filter(([, role]) => role.actions.includes(action.id)).map(([id, role]) => ({ id, label: role.label, scope: role.scope }));
  const matches = menus.filter(m => m.branch === action.legacy?.branch && m.label === action.legacy?.action);
  const candidates = [];
  for (const e of matches.flatMap(m => m.evidence)) {
    if (candidates.some(c => c.file === e.file && c.line === e.line)) continue;
    candidates.push({ ...e, ...at(e.file, e.line), status: 'INDICE_STATIQUE_CANDIDAT_NON_EXECUTE' });
  }
  if (!candidates.length && action.legacy) {
    const text = source('app/app.js'), start = text.indexOf('function openActionByBranch');
    const anchor = fallback[action.legacy.branch];
    const offset = anchor ? text.indexOf(anchor, start) : -1;
    if (offset >= 0) candidates.push({ ...at('app/app.js', text.slice(0, offset).split('\n').length), target: 'Repli de domaine; sous-vue exacte NON PROUVEE', status: 'REPLI_NON_EXECUTE' });
  }
  const isExisting = action.type === 'existing';
  const screenStatus = spec.kind === 'legacy' ? 'PAGE_OUVERTURE_SEULE' : action.type === 'unavailable' ? 'INDISPONIBILITE_EXPLICITE' : action.type === 'read' ? 'LECTURE_SUR_FIXTURE' : 'CONSULTATION_SUR_FIXTURE';
  const branch = catalog.branches.find(b => b.id === action.branch);
  const location = evidence('CAT-' + action.id, 'app/modules/codex-ui-v1/catalog.js', '"id": "' + action.id + '"', 'Destination du catalogue charge par la preview.');
  locations.set(action.id, location);
  return {
    id: action.id, menu: branch.label + ' > ' + action.group + ' > ' + action.label,
    branch: action.branch, label: action.label, type: action.type, schema: spec.definition,
    configuredRoles, previewAdminCanSeeAll: true, rolesArePermissions: false,
    currentScreen: specs.definitions[spec.definition]?.title || spec.screen, screenStatus,
    businessIntent: action.label, businessIntentIsNotCompletedWorkflow: true,
    selectionMessage: isExisting ? 'Interaction visuelle. Fonction metier non raccordee.' : null,
    openButtonMessage: isExisting ? 'Fonction non raccordee. Aucune operation effectuee.' : null,
    cause: isExisting ? 'DEMO_GUARD; OPEN_EXISTING_NOT_INJECTED' : action.type === 'read' ? 'DEMO_FIXTURE_NOT_AUTHENTICATED_READ' : 'CATALOG_UNAVAILABLE',
    requiredPorts: isExisting ? (spec.kind === 'legacy' ? ['getSession','authorize','openExisting'] : ['getSession','authorize','consult','openExisting']) : action.type === 'read' ? ['getSession','authorize','read','listAuthorized_if_selection_required'] : [],
    catalogPermission: action.permission, permissionHintNotGrant: spec.permission,
    permissionResolution: action.permission ? 'CODE_EXPLICITE_PORTEE_ET_DROITS_A_VERIFIER' : 'NON_RESOLUE_DANS_CATALOGUE_CODEX_NE_PAS_ACCORDER_IMPLICITEMENT',
    legacyRoute: action.legacy || null, historicalScreenCandidates: candidates,
    historicalExecution: 'NON_VERIFIEE_DANS_CET_AUDIT',
    candidateApi: spec.contracts || [], candidateApiSource: spec.contractSource,
    apiBindingStatus: 'CANDIDATS_DE_FAMILLE_NON_APPARIES_A_CHAQUE_COMMANDE_NON_TESTES',
    observedIssueIds: isExisting ? ['NR-01','NR-02','NR-03','NR-04',...(spec.kind === 'legacy' ? ['NR-05','NR-07'] : []),...(!action.permission ? ['NR-06'] : []),...(action.id === 'rosario-152' ? ['NR-05'] : [])] : action.type === 'read' ? ['NR-02'] : ['NR-09'],
    recommendedNextStep: spec.kind === 'legacy' ? 'Verifier et reutiliser la sous-vue historique exacte; completer la presentation manquante sans changer Rosario.' : action.type === 'unavailable' ? 'Qualifier le blocage de ce snapshot avant toute proposition; aucune modification du coeur autorisee.' : action.type === 'read' ? 'Raccorder le lecteur GET existant au transport authentifie et aux selections autorisees.' : 'Verifier les actions attendues au-dela de la consultation; reutiliser les ecrans metier et normaliser les lectures autorisees.',
    evidence: [location, ...candidates], status: 'A_QUALIFIER_PAR_CURSOR', realBusinessTested: false
  };
});
assert.equal(new Set(destinations.map(d => d.id)).size, 211);
assert.equal(catalog.actions.length, 211);
assert.equal(destinations.filter(d => d.selectionMessage).length, 203);
assert.equal(destinations.filter(d => d.screenStatus === 'PAGE_OUVERTURE_SEULE').length, 87);
const stats = {
  destinations: destinations.length, existingWithGenericWarning: 203,
  specializedConsultations: destinations.filter(d => d.screenStatus === 'CONSULTATION_SUR_FIXTURE').length,
  legacyLaunchPages: 87, fixtureReads: 6, explicitUnavailable: 2,
  catalogPermissionNull: destinations.filter(d => !d.catalogPermission).length,
  branches: catalog.branches.length, presentationRoles: Object.keys(catalog.roles).length,
  legacyByBranch: count(destinations.filter(d => d.schema === 'legacy').map(d => d.branch)),
  note: 'Destinations de menu, pas boutons DOM ni total des fonctions Rosario. Les categories de permissions recoupent les categories d ecrans. 203 cas statiques possibles, pas 203 parcours cliques.'
};
assert.equal(stats.specializedConsultations, 116);
assert.equal(stats.catalogPermissionNull, 204);
function verifyOriginal(base) {
  const modified = [], missing = [];
  for (const [file, expected] of original) {
    const full = path.join(base, file);
    if (!fs.existsSync(full)) missing.push(file);
    else if (hash(fs.readFileSync(full)) !== expected) modified.push(file);
  }
  return { expected: original.length, checked: original.length - missing.length, modified, missing };
}
const sourceIntegrity = verifyOriginal(root);
const baselineIntegrity = verifyOriginal(path.join(root, '../baseline'));
const codexChanged = delivery.appFiles.filter(e => hash(fs.readFileSync(path.join(root, e.file))) !== e.sha256).map(e => e.file);
assert.equal(sourceIntegrity.modified.length + sourceIntegrity.missing.length + baselineIntegrity.modified.length + baselineIntegrity.missing.length + codexChanged.length, 0, 'Read-only integrity failed');
const controls = [
  { family: 'open-existing', variants: 1, destinationIds: destinations.filter(d => d.type === 'existing').map(d => d.id), trigger: 'data-command=open-existing', behavior: '203 destinations possibles; arret demo avant port', evidence: ['E05','E16','E17'] },
  { family: 'scanner', variants: 4, codes: ['entry','pickup','fees','verify'], trigger: 'data-lot2-mode', behavior: 'openExisting(mode); scan-fees desactive les trois autres modes; aucun moteur recree', evidence: ['E05','E07'] },
  { family: 'cards', variants: 9, codes: ['individual','class','photo','crop','preview','print','replace','lost','revoked'], trigger: 'data-lot2-operation + submit', behavior: 'Formulaire de demande d ouverture, pas mutation; meme garde demo', evidence: ['E05','E14','E18'] },
  { family: 'family', variants: 5, codes: ['photo','authorization','suspend','reactivation-request','request-status'], trigger: 'data-lot2-operation + submit', behavior: 'Ouverture requise du moteur existant; ne pas confondre demande et reactivation', evidence: ['E05','E14','E18'] },
  { family: 'pedagogy-drafts', variants: 3, codes: ['assignments','grades','lessons'], trigger: 'data-lot2-draft + submit', behavior: 'Validation locale seulement; aucune sauvegarde ou publication', evidence: ['E19'] },
  { family: 'jaspe-demo', variants: 2, codes: ['text','audio'], behavior: 'Dialogue de demonstration sans moteur; ports natifs distincts', evidence: ['E21','E22'] }
];
const browser = json('docs/codex-ui-v1/non-raccordement-v1/browser-observations.json');
const verification = {
  reference: 'CODEX_SCHOOLSAFE_NON_RACCORDEMENT_DIAGNOSTIC_V1', generatedAt: new Date().toISOString(),
  sourceBranchDeclared: snapshot.branch, sourceHeadDeclared: snapshot.head,
  snapshotIncludesUncommittedChanges: true, gitRepositoryPresent: false,
  cursorCurrentCheckoutInspected: false, remoteHeadChecked: false,
  sourceIntegrity, baselineIntegrity, codexApplicationIntegrity: { checked: delivery.appFiles.length, modified: codexChanged },
  checks: ['211 ids uniques', 'Catalogues JSON et JavaScript concordants', 'Comptages 203/116/87/6/2 et permissions nulles 204', 'Ancres de preuves presentes', 'References des constats resolues', 'Integrite TREE et manifeste applicatif LOT2'],
  browserObservationCount: browser.cases.length, automatedBrowserTestsThisAudit: false,
  backendCallsForAudit: false, authenticatedBusinessTests: false,
  status: 'PASS_DOCUMENTARY_AUDIT_NOT_BUSINESS_CERTIFICATION'
};
writeJson('destinations.json', { stats, destinations });
const columns = ['id','menu','schema','screenStatus','catalogPermission','permissionResolution','cause','legacyRoute','candidateApi','candidateApiSource','status','recommendedNextStep'];
write('destinations.csv', '\uFEFF' + [columns.map(quote).join(';'), ...destinations.map(row => columns.map(key => quote(typeof row[key] === 'object' ? JSON.stringify(row[key]) : row[key])).join(';'))].join('\r\n') + '\r\n');
writeJson('secondary-controls.json', { scope: 'Familles de controles generes; nombres non additifs aux 211 destinations. Navigation, recherche, filtres et pagination ne sont pas des mutations metier.', controls });
writeJson('evidence.json', { evidence: evidenceList, sources: [...sourceFiles].sort().map(file => ({ file, sha256: hash(fs.readFileSync(path.join(root, file))) })) });
writeJson('verification.json', verification);
const report = [
  '# Diagnostic de non-raccordement - SchoolSafe / Rosario', '',
  'Reference : CODEX_SCHOOLSAFE_NON_RACCORDEMENT_DIAGNOSTIC_V1. Audit du 11 octobre 2026. Statut : READY_FOR_CURSOR_REVIEW.', '',
  '## Conclusion', '',
  'Les deux messages viennent du shell Codex, pas d une reponse Rosario. Le mode demonstration bloque volontairement openExisting, et la preview n injecte aucun raccordement metier. Un second probleme est ergonomique : un statut generique et des boutons actifs font paraitre les consultations fictives en panne. Les pages generiques restantes ne satisfont pas la cible finale : chaque fonction autorisee doit disposer d une interaction SchoolSafe adaptee, par reutilisation ou complement visuel, sans modification de Rosario.', '',
  '## Perimetre et limites', '',
  'Snapshot declare : ' + snapshot.branch + ', HEAD ' + snapshot.head + ', avec modifications non commitees. Le dossier ne contient pas .git. Aucun dossier original de Cursor, distant Git, compte authentifie, base PostgreSQL ou service de production examine. Les constats du handoff historique ne sont pas presentes comme des essais rejoues.', '',
  'Les 211 destinations sont celles du catalogue Codex. Elles ne constituent pas toutes les fonctions internes, API ou boutons dynamiques de Rosario. Les sous-commandes sont repertoriees separement. Les contrats API de famille sont des candidats, pas des correspondances validees pour chaque libelle.', '',
  '## Synthese chiffree', '',
  '| Mesure | Nombre | Interpretation |', '| --- | ---: | --- |',
  '| Destinations | 211 | 13 branches et 15 postes de presentation |',
  '| Avertissement generique potentiel | 203 | Selection type existing en mode demo, deduit du code |',
  '| Consultations specialisees | 116 | Presentation et fixtures; pas 116 workflows complets |',
  '| Pages d ouverture seules | 87 | Pas 87 fonctions absentes de Rosario |',
  '| Lectures GET | 6 | Adaptateur present, transport reel absent dans preview |',
  '| Entrees indisponibles | 2 | Bulletins, blocage explicite du snapshot |',
  '| Permission precise absente du catalogue Codex | 204 | 202 existing et 2 unavailable; aucune conclusion sur IAM Rosario |', '',
  'Repartition des 87 pages d ouverture : ' + Object.entries(stats.legacyByBranch).map(([b,n]) => b + ' ' + n).join('; ') + '.', '',
  '## Constats et corrections proposees', '',
  ...issueData.flatMap(issue => [
    '### ' + issue.id + ' - ' + issue.title, '',
    'Priorite : ' + issue.priority + '. Nature : ' + issue.classification + '.', '',
    '**Constat :** ' + issue.finding, '', '**Impact :** ' + issue.impact, '',
    '**Action proposee :** ' + issue.recommendation, '', '**Responsable :** ' + issue.owner, '',
    '**Critere de validation :** ' + issue.acceptance, '',
    '**Preuves :** ' + issue.evidence.map(id => '`' + byId[id].file + ':' + byId[id].line + '` (' + id + ')').join('; ') + '.', ''
  ]),
  '## Reproduction navigateur', '',
  ...browser.cases.map(c => '- ' + c.id + ': ' + c.steps.join(' > ') + '. Observe : ' + c.observed + '.'), '',
  'Observation dans la preview existante uniquement. Pas de session authentifiee, pas de mutation, pas de preuve de production. Les counts sont derives des catalogues, pas d un clic sur les 211 destinations.', '',
  '## Transmission', '',
  'Lire CURSOR_HANDOFF.md puis destinations.csv/json. evidence.json fournit extraits et empreintes; secondary-controls.json couvre les commandes generees. verification.json consigne les controles effectues. Aucune correction applicative n est incluse dans ce dossier.', '',
  '## Integrite', '',
  sourceIntegrity.checked + ' fichiers sources et ' + baselineIntegrity.checked + ' fichiers baseline conformes a TREE.sha256; ' + delivery.appFiles.length + ' fichiers applicatifs Codex conformes au manifeste LOT2 precedent. Aucun fichier partage, backend, moteur, SQL ou authentification modifie.', '',
  'UI/UX Pro Max consulte : recherche locale "disabled action explanation", domaine ux, regle Disabled States. Recommandation retenue : distinguer visuellement les actions indisponibles. Ce n est ni une preuve technique de raccordement, ni une solution finale consistant a desactiver toutes les fonctions.', '',
  'ROSARIO_NEW_CHANGES=NONE', 'BUSINESS_INTEGRATION=NOT_STARTED', 'LIVE_BUSINESS_TESTS=NOT_EXECUTED', 'CURSOR_TRANSMISSION=PREPARED_NOT_SENT', ''
];
write('REPORT.md', report.join('\n'));
write('INDEX.md', ['# Dossier a remettre a Cursor', '',
  '1. [Consigne de reprise](CURSOR_HANDOFF.md)',
  '2. [Diagnostic et priorites](REPORT.md)',
  '3. [Matrice des 211 destinations](destinations.csv) et [version detaillee JSON](destinations.json)',
  '4. [Commandes generees](secondary-controls.json)',
  '5. [Preuves source](evidence.json) et [observations navigateur](browser-observations.json)',
  '6. [Verification et integrite](verification.json)',
  '7. [Manifeste des fichiers](MANIFEST.json)', '',
  'Dossier documentaire uniquement. Rosario et tous les visuels sont conserves. Aucune integration, aucun commit, push ou deploiement.', ''
].join('\n'));
const entries = fs.readdirSync(__dirname).filter(name => name !== 'MANIFEST.json' && fs.statSync(path.join(__dirname, name)).isFile()).sort();
writeJson('MANIFEST.json', {
  reference: verification.reference, generatedAt: verification.generatedAt,
  root: 'docs/codex-ui-v1/non-raccordement-v1', scope: 'DOCUMENTATION_ONLY',
  manifestExcludedFromOwnHash: true,
  files: entries.map(file => { const bytes = fs.readFileSync(path.join(__dirname, file)); return { file, bytes: bytes.length, sha256: hash(bytes) }; }),
  sourceHeadDeclared: snapshot.head, sourceIntegrity, baselineIntegrity,
  applicationChanges: [], cursorTransmission: 'PREPARED_NOT_SENT'
});
console.log(JSON.stringify({ stats, findings: issueData.length, sourceIntegrity, baselineIntegrity, codexApplicationFilesUnchanged: delivery.appFiles.length, auditFiles: entries.length + 1 }, null, 2));
