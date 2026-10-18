# SchoolSafe Codex UI V1 - Inventaire visuel

Date d'inventaire : 2026-10-11.
Snapshot : `SCHOOLSAFE_CODEX_LOCAL_SNAPSHOT_V1`, branche source declaree `cursor/rosario-navigation-integration-v1`, HEAD `657aaef34c067173bd44a022d6b83a90010aea89`.

## Sources lues

- `MANIFEST.json` et `TREE.sha256` a la racine du snapshot.
- `app/index.html`
- `app/app.js`
- `app/modules/**`
- `app/styles/**`
- `shared/permissions.json`
- `docs/PROJECT_CONTEXT.md`, `docs/DECISIONS.md`, `docs/CURRENT_HANDOFF.md`

Le dossier `workspace` n'est pas un checkout Git actif. Les changements sont donc livres comme fichiers additifs, sans commit, push, merge ni deploiement.

## Navigation principale existante

La navigation validee a deux niveaux existe deja dans `app/app.js` :

- niveau 1 : `workspaceNav`, menu principal fixe;
- niveau 2 : `workspaceContextNav`, panneau independant par branche;
- mobile : `workspaceBottomNav`, bouton Menu, bouton JASPE, panneau contextuel;
- filtrage live : `SchoolSafeAccess.filterBranches` quand une session reelle existe;
- demo : `roleCatalog` fournit le modele de navigation.

Branches principales declarees :

| Branche | Libelle | Etat UI | Observations |
|---|---|---:|---|
| `pilotage` | Pilotage | Existe | Dashboard et alertes. API pilotage disponible si session et permission. |
| `school` | Ecole | Existe | Ecole, staff, eleves, structure, familles, dossiers, cartes. |
| `pedagogy` | Pedagogie | Existe | Devoirs, notes, cahiers, palmares, epreuves. Certaines sorties restent backend gap. |
| `security` | Securite et controle | Existe | Scanner, entree, retrait, verification carte, lockdown, personnes autorisees. |
| `finance` | Finance | Existe | Frais, paiements, recus, rapports, caisse, controle des frais. |
| `feeControl` | Controle des frais | Existe | Surface dediee controle QR des frais. |
| `accounting` | Comptabilite | Existe demo | Projection tresorerie, journal, rapprochements, etats. |
| `people` | Personnel | Existe demo | RH, contrats, presences, paie en surface frontend. |
| `inventory` | Stock / Inventaire | Existe demo | Catalogue, mouvements, achats, receptions. |
| `communication` | Communication | Existe demo | Messages, annonces, notifications, email, site public. |
| `care` | Vie et bien-etre | Existe demo | Infirmerie et cantine selon role. |
| `administration` | Administration | Existe | Roles/acces, documents, parametrage, licence, scanner delegations. |
| `reports` | Controle et rapports | Existe demo | Historiques, audit, rapports, exports. |

## Profils et roles visibles

Profils presents dans le select de connexion demo et dans `roleCatalog` :

| Role | Tableau de bord | Branches visibles principales | Permissions demo notables |
|---|---|---|---|
| `admin` | Administrateur principal | toutes les branches metier + systeme | `school.manage`, `roles.manage`, `security.card.create`, `finance.report.read`, `safe.assistant.use` |
| `school_head` | Chef d'etablissement | pilotage, school, pedagogy, security, finance lecture, reports | `finance.report.read`, `reports.financial.read` |
| `pedagogy` | Responsable pedagogique | pedagogy, finance statut, reports | pedagogie en classes/matieres affectees |
| `admissions` | Responsable administratif et admissions | school, administration | `school.student.create`, `school.student.read` |
| `secretary` | Secretaire scolaire | administration, communication | surfaces documentaires demo |
| `finance` | Responsable financier | finance, accounting | `finance.*`, `finance.control.*`, `reports.operational.read` |
| `cashier` | Agent de caisse | finance encaissement | `finance.payment.record`, `finance.receipt.read` |
| `accountant` | Comptable | accounting | `reports.financial.read`, `finance.report.read` |
| `hr` | Responsable RH | people | `staff.read`, `staff.manage`, `staff.attendance.read` |
| `teacher` | Enseignant | school, pedagogy, communication | classes et matieres affectees |
| `guard` | Agent de controle d'acces | security | `security.scan`, `security.pickup.manage` |
| `nurse` | Infirmier | care sante | `infirmary.manage` dans catalogue permissions |
| `canteen` | Responsable cantine | care cantine | `canteen.manage` dans catalogue permissions |
| `communication` | Responsable communication et site | communication | `communication.message.send`, `communication.announcement.manage`, `email.send` |
| `parent` | Parent ou responsable legal | school, finance, communication | `own_children` pour enfants rattaches |

## Ecrans et boutons principaux

### Accueil et authentification

- Splash : `Commencer`.
- Connexion : e-mail/telephone, mot de passe, afficher mot de passe, mot de passe oublie, rester connecte, OTP, continuer, demo.
- Choix de profil : liste de profils, retour a la connexion.
- Onboarding ecole : 7 etapes, retour, continuer, activation, upload logo local.

### Workspace commun

- Topbar : recherche, notifications, messages, profil, JASPE.
- Sidebar : tableau de bord, branches autorisees, centre documents, parametres, roles et acces, audit, integrations.
- Theme : Blanc / Bleu.
- Navigation mobile : Dashboard, JASPE, Menu.
- Actions rapides : menu `+` avec actions bornes.

### Modules existants

- `pedagogyModule` : tabs pedagogie et rendu natif/demos.
- `financeModule` : tabs finance, paiements, frais, rapports.
- `securityModule` : scanner et securite.
- `scanControl` : moteur scanner quatre modes.
- `pilotageModule` : dashboard, alertes.
- `feeControlModule` : controle frais QR.
- `schoolModule` : mon ecole, equipe, eleves, structure.
- `cardsStudio` : classe, eleves, photo, production, preview, impression, perte, remplacement, reimpression, distribution.
- `accessConsole` : roles, portee, permissions, donnees.
- `documentCenterModule`, `reportsModule`, `administrationModule`, `accounting`, `hr`, `inventory`, `communication`.
- `jaspePanelOverlay` : ecrit/audio, input, micro, ecoute reponse.

## Fonctionnalites Rosario deja visibles

Les lots Rosario recents indiquent des surfaces deja fermees visuellement sur moteurs presents :

- admissions, famille/tuteurs, personnel, classe nommee, affectations enseignants, approbations;
- registre passages, lockdown, personnes autorisees, scanner et remise, synchronisation;
- studio cartes, matieres, devoirs, cahiers, notes, moyennes, palmares, frais, encaissements, impayes, alertes;
- edition tuteur, contacts urgence, identite eleve, confirmations, consentements, cycles, console acces, documents, email, push, licence;
- affectations et titulaire corriges par lecture serveur de `is_tutor`.

## Interfaces manquantes ou raccordement non etabli

| Element | Etat | Preuve observee |
|---|---|---|
| Creation native production des comptes personnel | Bloquee | `/native/access/users` retourne 503 `DEPENDENCY_UNAVAILABLE` sans identite externe. |
| Menu scanner pour controleur financier staff | Partiel | L'API prouve la consultation frais, mais le profil staff n'ouvre pas le scanner dans l'interface. |
| Impression officielle cartes | Bloquee | `permission denied for schema app`, lot ZIP 500. |
| Production cartes toute ecole | Manquante | Bouton desactive `Toute l'ecole - Non disponible`. |
| Recadrage logo/photo cartes | Non execute | Documente dans handoff. |
| Remise familiale navigateur complete | Partielle | Parcours reels prouves, mais recadrage/repositionnement photo non executes. |
| Camera physique | Non execute | `CAMERA_PHYSICAL=NOT_EXECUTED`. |
| Bulletins | Backend gap | `REPORT_CARDS=BACKEND_GAP`. |
| Convocations reelles | Backend later | Permission dediee requise, brouillons seulement. |
| Push reel / WebSync / publication site | Backend later | Preferences ou previews locales. |
| RH, inventaire, comptabilite legale | Demo frontend | Marques `DÉMONSTRATION` ou `BACKEND_LATER`. |

## JASPE

Existe :

- moteur 2D/2,5D `app/modules/jaspe2d/**`;
- connexion, bandeau dashboard, panneau plein ecran;
- modes ecrit/audio visuels;
- gouvernance `jaspe-governance.js` et routeur de capacites;
- loi d'autorite : JASPE n'a jamais plus de droits que le profil connecte.

Codex UI V1 ajoute uniquement une surface visuelle harmonisee dans `jaspePanel()` pour aider Cursor a aligner JASPE avec le shell SchoolSafe. Aucun moteur, asset, permission ou comportement JASPE n'a ete modifie.

## Tests responsive observes dans les documents

Les handoffs indiquent :

- 1440, 1024 et 390 px sans debordement pour les sorties visuelles Rosario A/B/P1/P2;
- captures existantes dans `app/qa-output` et `docs/design/**`;
- `qa-responsive-visual-system`, `qa-navigation-two-level`, `qa-no-guardian-screen` ont ete utilises dans les lots precedents.

Codex UI V1 fournit CSS responsive pour desktop, tablette et mobile. La validation navigateur de ces nouveaux fichiers est a faire par Cursor lors du raccordement, car ils ne sont pas branches a `index.html`.
