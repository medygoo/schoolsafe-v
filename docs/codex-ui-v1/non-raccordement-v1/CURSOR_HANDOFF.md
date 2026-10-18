# CURSOR - Qualification des interfaces SchoolSafe non raccordees

Reference : CODEX_SCHOOLSAFE_NON_RACCORDEMENT_DIAGNOSTIC_V1
Date : 11 octobre 2026
Statut : READY_FOR_CURSOR_REVIEW

## Mission transmise

Identifier et qualifier professionnellement les causes des mentions "Interaction visuelle. Fonction metier non raccordee." et "Fonction non raccordee. Aucune operation effectuee.". Preparer la liste des reutilisations, complements visuels et raccordements necessaires, avec preuves par destination.

Le proprietaire confirme : Rosario reste sans modification. Chaque fonction autorisee doit disposer d un ecran SchoolSafe adapte pour interagir. Un bouton desactive ou une page d attente ne constitue pas la solution finale. Preserver tous les visuels valides et la navigation a deux niveaux. Codex prepare les visuels dans son perimetre isole; Cursor reste l integrateur final.

Ce dossier est une demande de revue et de qualification, pas une autorisation de modifier le coeur, de connecter des operations sensibles, de committer ou de deployer. Presenter d abord le diagnostic actualise et le lot precis propose au proprietaire. Les prochaines corrections doivent respecter les autorisations du protocole d integration controlee.

## Source et precaution de reprise

- Snapshot : branche declaree `cursor/rosario-navigation-integration-v1`, HEAD `657aaef34c067173bd44a022d6b83a90010aea89`, avec changements non commites inclus. Le HEAD seul ne suffit pas a reconstituer cet etat.
- Racine de lecture de ce dossier : `C:\Users\PC\SchoolSafeCodex\SCHOOLSAFE_CODEX_LOCAL_SNAPSHOT_V1\workspace`.
- Controle : `MANIFEST.json` et `TREE.sha256` dans le parent du workspace; baseline en lecture seule. Le workspace Codex n est pas un checkout Git.
- Le manifeste parent signale que le dossier Cursor ouvert lors de la copie et le worktree source etaient differents. Verifier le bon checkout et les changements locaux avant toute comparaison. Codex n a ouvert ni modifie ces dossiers originaux.
- Aucune affirmation de ce dossier ne certifie une revision plus recente de Cursor. Ne pas ecraser ses changements ni copier aveuglement le workspace complet.

## Constats principaux

1. La page `modules/codex-ui-v1/preview.html` est une demonstration statique. `preview.js` injecte des fixtures et deux drapeaux demo, pas les ports metier. Le serveur 5187 n est pas une API.
2. `selectAction()` affiche le premier message pour les 203 destinations de type existing. `openExisting()` affiche le second si demo, port absent ou absence de confirmation opened:true.
3. 116 destinations ont une consultation specialisee, 87 seulement une page d ouverture, 6 un lecteur GET sur fixture, 2 une indisponibilite explicite. Ce sont 211 destinations, pas 211 fonctions Rosario distinctes et pas un inventaire exhaustif des boutons DOM.
4. Une consultation specialisee n est pas un workflow complet : Creer l eleve ouvre actuellement une liste dans la preview, alors qu un formulaire historique existe. Import massif a aussi un ecran historique a reutiliser.
5. Les 204 permissions nulles du catalogue Codex sont des correspondances non resolues, pas des droits absents du moteur. Ne jamais les convertir en autorisation automatique.
6. Le palmares historique dispose deja d un ecran et de routes. La definition du Top 10 de toute l ecole doit etre validee sans recreer son calcul dans le navigateur. Les deux entrees bulletin portent un blocage explicite du snapshot; verifier l existant avant de conclure a un besoin de moteur nouveau.

## Fichiers a examiner

- `REPORT.md` : 13 constats, priorites, responsabilites, criteres d acceptation et lignes source.
- `destinations.csv` : matrice lisible des 211 destinations.
- `destinations.json` : details, postes de presentation, permissions/hints distingues, candidats d ecrans historiques et contrats API de famille.
- `secondary-controls.json` : ouverture generique, 4 modes scanner, 9 operations cartes, 5 operations famille, 3 familles de brouillons, 2 modes JASPE. Ces nombres ne s additionnent pas aux destinations.
- `evidence.json` : extraits source et empreintes; chaque correspondance historique reste un indice statique, pas une execution validee.
- `browser-observations.json` : quatre cas reproduits dans la preview. Aucun test metier authentifie.
- `verification.json` et `MANIFEST.json` : controles et integrite du dossier.

Tous ces chemins sont relatifs au present dossier. Les fichiers applicatifs cites par les preuves sont relatifs a la racine du workspace. Le dossier ne contient pas de correctif applicatif a fusionner.

## Travail demande a Cursor

1. Confirmer la revision courante et revalider les 13 constats sur son checkout autorise. Distinguer "constat confirme", "deja corrige depuis le snapshot", "non reproduit" et "a investiguer".
2. Pour chacune des 211 destinations, verifier la fonction attendue et la sous-vue precise. Rechercher l ecran historique avant de proposer un nouvel ecran. Respecter l ordre des couches SchoolSafeVisualWiring et des routeurs existants; une page de domaine generique ne prouve pas la bonne destination.
3. Pour chaque commande interne, identifier lecture, saisie, validation, publication ou action sensible; noter son contrat effectivement existant. Les API candidates de la matrice ne doivent pas etre branchees indistinctement a tous les boutons d une famille.
4. Classer : ecran existant a reutiliser; ecran existant a harmoniser; presentation incomplete; adaptateur frontend a etablir; droit/portee a resoudre; blocage metier a qualifier. Ne pas assimiler "non raccorde" a "Rosario absent".
5. Proposer un premier lot et ses fichiers exacts. Priorite proposee : Eleves / Creer l eleve / Import massif pour verifier la chaine complete, puis classes, familles et presences. Aucun elargissement aux moteurs proteges implicite.
6. Retourner la matrice enrichie au proprietaire avant de declarer un raccordement operationnel. Codex pourra ensuite preparer les complements visuels valides; Cursor integre selon le protocole confirme.

## Contrats frontend a qualifier, sans modifier Rosario

Le montage integre propose dans LOT2_CURSOR_HANDOFF.md attend `getSession`, `authorize`, `consult`, `loadDashboard`, `read`, `listAuthorized`, `openExisting` et `openJaspe`. Ce sont des points d adaptation frontend, pas une API Rosario nouvelle deja existante.

- Session, droits et transport doivent reutiliser les mecanismes officiels. L ecole est resolue par la session, pas librement choisie dans le navigateur.
- `consult` ne doit exposer que les lignes et champs autorises par ressource et onglet. Conserver l isolation entre ecoles et les projections parent/enseignant.
- `openExisting` confirme seulement l ouverture effective. Ne jamais assimiler `{opened:true}` a une creation, un paiement, une remise ou une publication.
- Le bouton generique ne transmet pas le dossier courant; qualifier l intention "module" ou "dossier" avant toute evolution. Ne pas deviner les identifiants canoniques.
- Garder la demonstration separee. Ne pas desactiver les protections pour faire disparaitre un message. La presence d un port ne remplace pas la reautorisation serveur.

## Recette attendue apres integration autorisee

| Controle | Resultat attendu / preuve |
| --- | --- |
| Navigation | Menu > sous-menu > ecran exact; retour sans perte injustifiee des filtres et du dossier |
| Lecture | Donnees de la source officielle, bon perimetre; vide/refus/erreur distincts |
| Action | Effet officiel confirme et relu; aucune reussite deduite d un simple clic |
| Permissions | Compte autorise et non autorise, autre ecole, parent hors enfant, enseignant hors classe |
| Revocation | Changement de session/droits en cours de chargement; reponse tardive non affichee |
| Formulaires | Champs requis, erreurs utiles, confirmation sensible, pas de double soumission |
| Navigation vers un dossier | Identifiants corrects meme pour des homonymes; aucune conversion d un libelle en id |
| Etats visuels | Demo, port absent, cible non ouverte, acces refuse, chargement echoue, operation confirmee |
| Responsive | Telephone, tablette, ordinateur; clavier, focus, labels, themes blanc/bleu |
| Non-regression | Rosario, auth, IAM, SQL, moteurs cartes/scanner/remise/finance/JASPE inchanges |

Pour les parcours sensibles, conserver imperativement licence, capacites, delegations, preuve de remise, photographie approuvee et confirmation humaine. Un impaye ne doit pas bloquer l entree ou la recuperation. Ne pas prendre les resultats historiques de PostgreSQL comme une recette de ce raccordement.

## Hors perimetre absolu

Aucune modification Rosario, backend, PostgreSQL, migration SQL, authentification, IAM, moteur JASPE, cartes, scanner, recuperation ou controle financier. Aucun commit, push, merge, deploiement ou intervention VPS. Aucun faux resultat. Aucun remplacement global des fichiers partages par la copie Codex.

Si la reutilisation exige apparemment une modification du coeur : arreter ce point, expliquer le probleme, la solution envisagee, les alternatives et les risques, puis attendre l autorisation explicite du proprietaire.

## Format du retour attendu

Par destination : id, revision verifiee, probleme confirme, ecran actuel, ecran reutilisable, fonction attendue, commandes internes, contrat officiel, permission/portee, fichiers proposes, responsable, priorite, tests executes, preuves et limites. Ne marquer OPERATIONNEL qu un parcours reel effectivement teste dans le perimetre autorise.

Ce dossier est prepare pour remise manuelle au proprietaire et a Cursor. Aucune transmission automatique ni execution dans Cursor n a ete effectuee.
