# LOT 0 - Rapport au proprietaire

Reference : CODEX_ROSARIO_UI_AUDIT_CLOSURE_V1. Date : 2026-10-11.

CODEX_LOT0=READY_FOR_OWNER_REVIEW
CODEX_LOT1=NOT_AUTHORIZED
CODEX_INTEGRATION=NON_COMMENCEE
CURSOR_INTEGRATOR=CONFIRME

## Conclusion et portee des preuves

Le registre source et les propositions sont remis pour examen. Cet audit est statique : aucune connexion a une ecole, aucun appel API, aucun test PostgreSQL et aucun parcours navigateur authentifie n'ont ete executes. Aucun bouton n'est declare operationnel sur la seule presence d'un gestionnaire. Les resultats anciens du handoff restent des preuves historiques, pas une validation de l'etat courant.

L'inventaire couvre les occurrences HTML et les modeles dynamiques detectables dans les sources applicatives, les generateurs communs, les catalogues de roles et les couches de routage. Il ne constitue pas un denombrement exhaustif des instances DOM possibles. Les callbacks passes par variable, les conditions d'execution et les templates assembles peuvent exiger un examen navigateur. Les correspondances non etablies entre bouton, permission exacte et API sont indiquees explicitement dans les matrices, et non inventees. Le statut READY_FOR_OWNER_REVIEW signifie pret a examiner avec ces limites; il ne certifie pas tous les parcours metier.

Source : snapshot de cursor/rosario-navigation-integration-v1, HEAD declare 657aaef34c067173bd44a022d6b83a90010aea89. Les changements ulterieurs de Cursor n'ont pas ete consultes. Les dossiers historiques de Cursor n'ont pas ete ouverts. Le workspace ne fournit pas d'historique Git permettant une comparaison de branches.

## Synthese chiffree

| Mesure | Nombre / interpretation |
| --- | --- |
| Occurrences/modeles de boutons extraits | 517 : 186 HTML, 331 dynamiques/helpers |
| Entrees de menus par profil | 274, couvrant 15 profils |
| Actions de menu distinctes | 207 couples branche/libelle |
| Boutons mal places confirmes | 1 famille : acces scanner du controleur financier; blocage de navigation rapporte |
| Fonctions existantes sans ecran directement raccorde | 6 lectures scan-control, detail dans LOT0_FONCTIONS_SANS_ECRAN.md |
| Candidats SQL sans reference serveur detectee | 48; ce ne sont pas 48 fonctions utilisateur sans ecran |
| Ecrans incomplets | 12 familles detaillees; pas 12 ecrans DOM individuels |
| Boutons sans action confirmes dans l'apercu Codex | 28 instances sur les 3 configurations, dont 3 modes JASPE repetes par configuration |
| Boutons sans action dans l'application principale | Indetermine : un gestionnaire delegue peut ne pas citer l'identifiant |
| Fonctions de menu non verifiees en execution | 205 actions distinctes; les 2 libelles de bulletin sont classes non raccordes |
| Contrats HTTP reperes | 275 declarations, incluant fonctions techniques et Control |
| Fonctions SQL api.* distinctes reperees | 262; fonctions internes et versions historiques comprises |

Ces nombres ne s'additionnent pas : un menu reutilise un modele de bouton; une API peut servir plusieurs ecrans et une fonction SQL peut etre interne. Ils sont reproductibles par lot0-audit.cjs et LOT0_COUNTS.json. Les 517 occurrences sont un comptage d'extraction, pas une affirmation d'exhaustivite semantique.

## Rapports et matrices

- LOT0_MATRICE_BOUTONS.md et LOT0_BUTTONS.json : emplacement source, modele/libelle, attributs, indices de gestionnaire, statut et destination proposee. Les permissions/API non etablies sont signalees.
- LOT0_ORGANISATION_ROLES.md et LOT0_MENUS.json : 274 entrees principal > sous-menu > cible pour chaque poste; permissions de visibilite de branche, portee et preuves de routage.
- LOT0_RACCORDEMENTS.md : contrats precis des parcours prioritaires, sources officielles et tests a effectuer.
- LOT0_API_RPC.json : routes, references serveur aux RPC, declarations SQL et candidats sans facade detectee.
- LOT0_FONCTIONS_SANS_ECRAN.md : six fonctions existantes sans consommateur frontend direct detecte, separees des moteurs absents.
- LOT0_ECRANS_INCOMPLETS.md : familles incompletes et preuves.
- LOT0_COMPOSANTS_EXISTANTS.md : compatibilite et corrections des composants Codex conserves.
- LOT0_SOURCES.json : fichiers applicatifs examines, lignes, empreintes et indice de chargement depuis index.html.
- LOT0_VERIFICATION.md : controles executes et limites.

## Navigation et tableaux de bord proposes

Conserver les 13 branches de app/app.js:650 et le panneau workspaceContextNav de app/app.js:3358. Les actions restent dans le panneau de la branche active. Les cartes et raccourcis ouvrent les memes cibles, sans recreer un moteur. La barre mobile garde Tableau de bord, JASPE, Menu (app/index.html:914).

Le contrat de session demeure l'autorite : app/modules/core/access.js:18 contient la visibilite par permission de branche; les portees et les DENY doivent etre controles pour l'action precise. Les 15 postes de demonstration ne sont pas 15 identites applicatives souveraines. Les quatre experiences adultes validees restent Direction, Enseignant, Gardien et Parent; administrateur et services financiers gardent leurs fonctions et postes selon les droits composes (docs/DECISIONS.md, decision du 13 septembre).

Administrateur : Pilotage > indicateurs/alertes/approbations; Ecole > admissions/eleves/structure/familles/cartes; Administration > identite/annees/cycles/acces/licence; autres domaines uniquement autorises.

Direction : Pilotage > synthese/decisions; Ecole > suivi; Pedagogie > resultats; Securite > supervision; Finance > rapports en lecture. Ne pas deduire l'autorisation d'encaisser d'un acces aux rapports.

Enseignant : Pedagogie > mes classes/appel/devoirs/notes/preparation; Ecole > eleves affectes; Communication > contacts autorises. Remplacer apres validation les libelles fixes 6e A et 5e A par des affectations serveur (app/app.js:708 et visual-output-p2a.js:18).

Parent : Ecole > mes enfants/presences/personnes autorisees; Pedagogie > resultats/devoirs autorises; Finance > frais/recus propres; Communication > notifications. Toutes les lectures restent bornees a own_children.

Gardien : Securite > scanner/entrees/remises/verification/anomalies/historique. Photo approuvee, preuve serveur et confirmation humaine sont conservees. Le controle de frais ne devient pas une condition de remise.

Services financiers : Finance > frais/affectations/paiements/recus/caisse/rapports; Controle des frais > campagnes/consultation; Administration > autorisations scanner pour gestionnaires autorises. Caisse, supervision et comptabilite restent des fonctions distinctes.

RH, secretariat, admissions, communication, infirmerie et cantine : conserver les sous-menus repertories par role; afficher les limites de demonstration et n'exposer des actions reelles qu'apres validation de leurs contrats. La comptabilite et le stock ne deviennent pas operationnels par harmonisation visuelle.

## Corrections proposees, par priorite

| Priorite | Correction proposee | Preuve / consequence |
| --- | --- | --- |
| P0 | Apres autorisation, supprimer les affirmations reelles dans l'apercu Codex et signaler uniformement chaque donnee fictive | preview.js:20,51,75 : Reel, Raccordee, Reelle sans session |
| P0 | Faire verifier par Cursor la navigation du controleur financier et la projection SCAN_FEES | CURRENT_HANDOFF.md:1-14 : API possible, navigateur bloque; permission finance.control.scan et capacite distinctes |
| P0 | Conserver le refus de remise hors ligne, photo non approuvee et preuve absente | scancontrol/service.ts:473,500; aucune simplification visuelle de ces controles |
| P1 | Proposer les six lectures sans interface sur tableaux de bord et historique | LOT0_FONCTIONS_SANS_ECRAN.md; moteurs deja presents |
| P1 | Faire traiter par Cursor les blocages comptes personnel et impression cartes | Handoff courant : 503 identite externe, erreur schema app et ZIP; ce sont des blocages metier |
| P1 | Rendre les boutons Codex explicites par contrat d'action et permission; ne pas simuler de succes | codex-ui-v1.js:59,78,91; aucun gestionnaire metier |
| P1 | Adapter les tableaux de bord direction/gardien/finance apres validation | preview.js ne contient que admin, teacher, parent |
| P2 | Corriger chemin image JASPE et rattachement au panneau existant | codex-ui-v1.js:88 : chemin relatif au HTML, faux depuis preview.html |
| P2 | Aligner rayons, semantique des themes et navigation sur l'existant | CSS rayon 16px et composants paralleles; aucun menu principal fixe dans dashboard() |
| P2 | Valider chaque cible de repli et retirer les noms de classe fixes | app.js:3087-3154; une route par domaine ne prouve pas la bonne sous-fonction |

Le probleme d'acces financier est compte comme une famille mal placee/inaccessible, pas comme la preuve qu'un bouton particulier est mal positionne dans tous les profils. Les autres propositions de regroupement sont des choix ergonomiques, pas des bugs confirmes.

## Risques et modifications envisagees

Conflits potentiels : couches P1/P2/Closure prioritaires avant VISUAL_WIRING; facade native distincte des clients historiques /finance et /school; modifications de Cursor apres snapshot; donnees de demonstration dans roleCatalog.today; visibilite de branche moins precise que l'autorisation de chaque action.

Les corrections envisagees pour LOT 1 concernent uniquement les composants isoles apres validation. Cursor devra verifier les contrats, les permissions et les changements recents avant toute copie. Aucun ecrasement de app.js ou index.html n'est propose.

Aucune modification du coeur Rosario n'est identifiee comme indispensable par cet audit visuel. Le choix de creation locale des comptes personnel releve du proprietaire et de Cursor; la 503 ne justifie pas un changement d'authentification par Codex. Les bulletins sans moteur et les fonctions reglementaires restent des sujets metier separes.

Les fichiers visuels existants sont conserves. Les anciennes instructions de raccordement dans CURSOR_HANDOFF.md et les statuts dans preview.js ne constituent aucune autorisation actuelle. Ce rapport prevaut pour la livraison LOT 0.

Le rapport post-integration ne peut pas etre produit comme une preuve tant que Cursor n'a pas integre et teste les composants apres autorisation. Arret du travail a la remise pour revue du proprietaire.
