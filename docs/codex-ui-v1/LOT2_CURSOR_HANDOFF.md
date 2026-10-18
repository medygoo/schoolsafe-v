# LOT 2 - Livraison pour examen, integration non commencee

Reference : CODEX_SCHOOLSAFE_INTERACTIVE_DASHBOARDS_LOT2_V1.

Revision fiches/formulaires : lire RECORD_PRESENTATION_HANDOFF.md. Neuf schemas (51 destinations existantes) recoivent des fiches regroupees; trois brouillons pedagogiques locaux recoivent des champs multilignes et erreurs accessibles. Charger record-presentation.js avant lot2-views.js et record-presentation.css en dernier. Contrats et droits inchanges.

Revision Vue generale, etape 2 : lire OVERVIEW_COMPOSITION_HANDOFF.md. Synthese journaliere et repartition par cycle derivees des DTO existants, rail alertes/dossiers, acces rapides et details conserves. Calendrier/activites explicitement non raccordes. Charger overview-composition.css en dernier. Aucun nouveau contrat ni raccordement.

Revision UI/UX Pro Max : lire UI_UX_PRO_MAX_DESIGN_SYSTEM.md et UI_UX_PRO_MAX_REVIEW.md. Style commun ajoute, navigation mobile clavier corrigee, disclosure Dossiers conservee au chargement, presentation opt-in JASPE ajustee. Suite complete LOT 2 rejouee ; preuves et fichiers dans LOT2_MANIFEST.json. Le catalogue et les contrats metier restent inchanges.

Revision branches : lire UI_UX_PRO_MAX_BRANCH_PRESENTATION.md. Treize accents discrets et organisation interieure des fonctions; conserver les dispositions de UI_UX_PRO_MAX_DOMAIN_PRESENTATION.md. Ajouter branch-presentation.css apres ui-ux-pro-max.css. Les nouveaux attributs de presentation et le resolveur iconFor ne modifient aucun droit ni route.

## Preview verifiee

Adresse : http://127.0.0.1:5187/modules/codex-ui-v1/preview.html

Le serveur est limite a 127.0.0.1. Il sert uniquement les fichiers statiques de app en GET/HEAD, sans proxy backend. Port 5187 choisi apres controle des ports utilises, notamment 8787; aucun processus Cursor arrete. Le navigateur Codex a effectivement ouvert la page et un dossier depuis le dashboard. Le serveur reste disponible pendant l'examen, tant que son processus local reste actif.

Demarrage depuis le workspace :

```powershell
node docs/codex-ui-v1/preview-server.cjs 5187
```

Utiliser un autre port libre si 5187 est occupe. Le fichier reste aussi consultable en file local. Aucun serveur de production et aucune exposition reseau publique. Les scenarios et le selecteur de poste appartiennent uniquement a la demonstration.

## Fichiers

Nouveaux modules : screen-specs.js (specifications des 211 destinations), lot2-views.js (dashboards, listes, dossiers, operations et brouillons), demo-data.js (fixtures exclusivement preview). Nouvelle feuille : lot2-screens.css.

Fichiers Codex modifies : unified-interface.js (extension, ports de lecture, historique de navigation, focus mobile, filtres audit), preview.html (chargement et scenarios), preview.js (utilisation des fixtures). Aucun nouveau role applicatif. catalog.js et ses 211 destinations, read-adapter.js, composants de base, CSS LOT 1 et feuille JASPE sont conserves.

Les generateurs, tests, serveur et rapports sont dans docs/codex-ui-v1. LOT2_MANIFEST.json donne la liste exhaustive, hashes, differences depuis LOT 1 et verification des 1017 fichiers source et baseline. Les captures LOT 1 ont ete actualisees par la reexecution de son harness de regression; son manifeste historique n'est pas le manifeste du paquet courant. Ne pas relancer package-lot1.cjs pour requalifier ce lot.

## Installation proposee, non realisee

Ordre : Lucide existant, codex-ui-v1.js, catalog.js, read-adapter.js, screen-specs.js, record-presentation.js, lot2-views.js, unified-interface.js. Feuilles : codex-ui-v1.css puis lot2-screens.css puis ui-ux-pro-max.css puis branch-presentation.css puis overview-composition.css puis record-presentation.css apres les styles partages. Le moteur JASPE reste celui de l'application; les instructions LOT1_CURSOR_INTEGRATION.md s'appliquent a sa feuille opt-in. Ne pas charger preview.js ni demo-data.js dans l'application finale.

Le montage LOT 1 fonctionne encore sans extension LOT 2. Avec l'extension, injecter les deux nouveaux ports ci-dessous, sans reprendre les fixtures.

```javascript
const ui = SchoolSafeCodexUiV1.mountApplication(container, {
  getSession: bridge.getSession,
  authorize: bridge.authorize,
  listAuthorized: bridge.listAuthorized,
  read: SchoolSafeCodexReads.create(bridge.authenticatedGet),
  consult: bridge.consult,
  loadDashboard: bridge.loadDashboard,
  openExisting: bridge.openExisting,
  openJaspe: bridge.openJaspe
});
await ui.ready;
```

Les noms bridge sont des contrats frontend proposes, pas des fonctions natives exposees par Rosario. Cursor doit les implementer explicitement apres validation du proprietaire.

## Consultations normalisees

consult(action, context) retourne une Promise de {rows, fields}. context contient session, params, signal et definition. Liste : params vide. Dossier : params.record_id et params.tab. Les contrats observes et leurs sources figurent dans LOT2_SCREEN_SPECS.json et dans la matrice. Ils sont des points de raccordement candidats, pas une preuve que le bouton historique appelle deja cette route.

rows contient au maximum 1000 lignes; id est l'identifiant officiel opaque du dossier, les colonnes sont celles de la definition. Les valeurs visibles sont des scalaires. Une class_id UUID peut accompagner une ligne carte pour la preparation par classe. Ne jamais convertir un libelle de classe en identifiant, ni un identifiant de demande en identifiant d'eleve. Si une correspondance officielle manque, refuser l'operation. Les champs supplementaires sont elimines, y compris les champs familiaux renvoyes dans l'onglet scolaire.

fields contient uniquement les informations explicitement autorisees pour l'onglet demande. Les noms admis sont donnes par definitions.fields et par tabFields dans lot2-views.js. Pour Scolarite : name, class, reference, status, year; pour Famille : guardian, contact, status; pour Documents : document, status. La liste ne conserve aucun champ detail. Les autres onglets disposent de leur propre projection.

Le contrat natif n'est pas transforme automatiquement : Cursor doit normaliser chaque payload officiel, verifier la portee et ne pas demander les autres sous-dossiers (famille, sante, finances) pour remplir une fiche scolaire. Une definition reutilisee pour plusieurs boutons n'autorise pas a utiliser le meme endpoint sans examiner leurs fonctions exactes. Les sources historiques/documentaires qui n'ont pas de lecture prouvee restent non raccordees.

La pagination et les filtres portent sur les lignes chargees seulement; ce libelle est visible. Ce n'est pas une pagination serveur. Pour les gros etablissements, Cursor devra fournir une strategie autorisee de chargement borné ou ajouter un contrat de pagination valide, avant de qualifier la consultation d'exhaustive.

## Dashboards

Extension Vue generale administration/direction : schoolOverview optionnel, documente dans OVERVIEW_DETAIL_HANDOFF.md. Les totaux de presentation sont derives uniquement des listes completes de ce DTO valide, distinct des listes de consultation paginees. Sans ce contrat, afficher indisponible. Palmares : emplacement reserve sans classement, aucune destination Rosario ajoutee.

loadDashboard({session, actions, signal}) retourne {metrics, items, alerts}. metrics : au plus quatre objets {label,value,hint}. items/alerts : {id,name,subtitle,status,target,recordId?}. target doit etre un identifiant autorise du catalogue. recordId est facultatif : il ouvre le dossier exact si la consultation renvoie cet identifiant, ou une lecture eleve/classe si la liste autorisee le contient. Aucun compteur n'est calcule a partir des listes.

Le port est soumis a authorize sur {id:'dashboard-overview',permission:null,type:'presentation',branch:'pilotage'}, purpose dashboard. Il faut resoudre cette autorisation pour le profil/perimetre et chaque source de widget; elle n'est pas synonyme de pilotage.dashboard.read pour tous les postes. Sans resolution explicite, la synthese est refusee et ne montre aucun chiffre. Le service officiel reste responsable des donnees et de leurs portees. Les 15 postes sont uniquement des configurations de presentation.

## Autorisations et session

authorize(action, session, params, purpose) doit retourner strictement true pour navigation, read, execute, consult, detail ou dashboard. En detail, examiner record_id et tab. Pour les operations cartes/famille, examiner operation, record_id ou class_id, selection_kind et reason. Pour le scanner, examiner mode et capability. Les droits n'etant pas resolves dans le catalogue restent refuses; les hints de screen-specs.js ne sont jamais une attribution de droit.

Le shell recontrole avant/apres chaque lecture. Une modification de session doit toujours appeler ui.refreshSession() : les donnees, selections, brouillons et l'historique sont effaces, les requetes annulees et les reponses tardives ignorees. Appeler destroy avant remontage. Cette protection n'autorise pas une liste trop large; listAuthorized/consult/loadDashboard doivent deja appliquer les portees serveur.

## Ouverture des moteurs existants

openExisting(route, session, action, params) retourne {opened:true} seulement apres ouverture effective. Ce resultat ne signifie jamais paiement, remise, suspension, impression ou publication reussis. Conserver l'ordre du routeur SchoolSafeVisualWiring, les droits natifs et les confirmations existantes.

Les modes officiels entry/pickup/fees/verify sont transmis a {branch:'security',action:'Scanner un QR',mode}. Le mode fees est expose aux sessions staff autorisees sans leur attribuer de retrait d'enfant. Le parcours scan-fees bloque les trois autres modes. Pour les autres entrees scanner, la facade doit verifier la capacite du mode, pas seulement le droit du bouton general.

Cartes : route conserve Studio des cartes et ajoute operation parmi individual, class, photo, crop, preview, print, replace, lost, revoked. Famille : photo, authorization, suspend, reactivation-request, request-status. params transporte la selection et le motif; aucun POST n'est emis par ce paquet. Ces codes sont des demandes d'ouverture frontend, pas des noms de RPC ni une facade metier deja implementee. Cursor doit ouvrir la sous-vue exacte du moteur existant, ou retourner opened:false. Ne pas executer directement une mutation depuis cette ouverture et ne pas donner de droit de reactivation a une demande parentale.

Les brouillons devoirs/notes/preparation n'existent qu'en memoire de la vue. Leur validation verifie les champs requis; elle ne calcule ni note, moyenne ou bulletin et ne publie rien. Une vraie saisie/persistence devra passer par l'ecran existant et son contrat natif valide. Le classement Palmares reste absent, seul son emplacement visuel existe.

## Limites et blocages

116 destinations ont une surface de consultation specialisee, 87 restent des ouvertures detaillees de reutilisation, six sont les lectures GET et deux sont indisponibles (bulletins). Les 87 reutilisations couvrent notamment les surfaces historiques RH/paie, comptabilite, stock, communication, rapports et autres fonctions sans contrat complet etabli; elles ne sont pas reconstruites ni declarees operationnelles. Les nouvelles tables de consultation sont des DTO visuels, pas de nouveaux moteurs.

Restent notamment : impression officielle et ZIP cartes, production toute ecole, tests recadrage, dependance de creation native de compte, moteur bulletin, portees financieres et services RH/comptabilite/inventaire non valides. Les contacts, photos et etats autorisation de la preview sont fictifs. Aucun jeton de preuve, QR canonique, recu ou document officiel genere. Conversation, voix et animation native JASPE non testees en session reelle.

## Verification apres integration autorisee

Reproduire le harness LOT 2, puis les tests authentifies et refus croises par enfant/classe/ecole, projections familiales par onglet, changements de compte et revocations en vol. Verifier les affectations enseignant, dossiers parent, delegations fees expirees et absence de droit de retrait/encaissement implicite. Reproduire les quatre modes scanner, photo approuvee/preuve expiree/rejouee et confirmation humaine. Verifier les operations cartes et dossiers avant toute mutation, les retours entre modules, les erreurs reseau, les compteurs officiels, les grandes listes et JASPE native. Les tests Codex n'utilisent aucun backend reel.

Attendre la validation du proprietaire. CURSOR_INTEGRATION=NOT_STARTED.
