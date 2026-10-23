# Analyse scolaire — accueil, menus et tableaux de bord

## Mandat et niveau de preuve

Analyse demandée après clôture de ProDéli, avant de décider les corrections. Base `2f8b9f10ed3026ff6f5a3ced4452058d8222881f`, branche `audit/school-dashboard-navigation-20261022`. **Aucun code produit modifié, aucun déploiement, login de compte réel, changement de droits, migration ou envoi externe.**

Preuves privées : `school-dashboard-analysis-20261022/{diagnose.cjs,diagnostic-result.json,deployed-source-proof.json,menu-1280.png,menu-768.png,menu-390.png}`. Diagnostic Playwright sur DOM **fictif**, vrais JS/CSS canoniques et fonctions natives `isMobileMenu`/`expandMenu` extraites sans modification. Ce n’est pas un parcours HTTP authentifié ni une recette métier complète. Quatre hashes d’assets de production correspondent aux blobs Git : JS photo, CSS photo, JS SchoolSafeCodex, JS natif warehouse. La capture fictive de la recette précédente `exact-r17/native/direct-school-390.png` confirme le vrai rendu scolaire « Accueil 1 / 13 » sur l’image livrée ; ce n’est pas une nouvelle visite d’un compte réel.

## 1. Ce qui se cache derrière Accueil

Le compteur **1 / 13 ne désigne pas treize applications ou profils**. Dans `assets/js/schoolsafe-photo-dashboard.js:177–212`, une seconde couche JavaScript découpe le même accueil administrateur :

- Accueil (bandeau et actions) ;
- Indicateurs, par groupes de trois ;
- Encaissements ;
- Modules, par groupes de quatre ;
- Fréquentation, synthèse financière, alertes ;
- Vie scolaire ;
- Dossiers détaillés.

Le nombre dépend des blocs effectivement présents, donc des données/droits/rendus. Le script déplace ces éléments dans des panneaux, masque tous les panneaux sauf le courant et appelle toujours `show(0)`. Le reste est placé dans `.ss-fixed-park`, caché par CSS. La coque fixe impose `overflow:hidden` sur html/body/wrap/body-content et le défilement seulement dans le panneau actif (`schoolsafe-photo-dashboard.css:264–280`).

**Conséquences reproduites :** les indicateurs et rubriques ne sont pas directement visibles à l’arrivée ; sélectionner « Modules 2 » ne change pas l’URL ; recharger revient à « Accueil 1 / 13 ». Ce comportement est programmé, pas une preuve de données perdues. Le corps des informations détaillées peut rester dans le parc caché, tandis que seule sa navigation est extraite vers le panneau « Dossiers détaillés » ; les liens serveur `details_tab` restent une autre voie d’accès.

## 2. Problème de menu reproduit — ne pas généraliser abusivement

Trois propriétaires gèrent le même état :

1. Coque scolaire photo : `#menu` caché, affiché seulement avec certaines classes CSS ; cartes de modules qui appellent `native.click()` et seuil mobile à **900 px** (`photo-dashboard.js:52–64`).
2. Thème SchoolSafeCodex : ouverture principale/contexte, fermeture au clic extérieur, focus et `inert`, seuil mobile à **700 px** (`scripts.js:584–710, 836–848, 1105–1121`).
3. Rosario natif : `expandMenu`, classes `slide/hide`, mode mobile calculé depuis la largeur réelle du menu (`warehouse.js:105–109, 1045–1087`).

Résultat reproductible, sans erreur JavaScript :

| Largeur | Bouton Menu d’en-tête après clic | Carte École du panneau Modules | Après rechargement de Modules 2 |
|---|---|---|---|
| 1280 | menu visible | branche `school` ouverte dans un menu **invisible** | Accueil 1/13 |
| 768 | menu **invisible**, classes `slide hide` | état `primary` mais menu **invisible**, footer inert | Accueil 1/13 |
| 390 | menu visible | retour immédiat à l’état fermé, menu invisible | Accueil 1/13 |

**Hypothèse écartée :** « le bouton Menu est toujours cassé sur ordinateur ». Le test 1280 l’ouvre correctement. Le défaut dépend du chemin (bouton ou carte) et du format.

### Mécanismes expliqués par le code

- **Ordinateur :** la carte annule sa navigation native puis ouvre la branche du menu ; le contrôleur desktop ne rend pas le conteneur caché visible. Le sous-menu peut être déclaré ouvert sans être consultable.
- **Intermédiaire 701–900 px :** la carte applique la logique photo mobile alors que le thème applique sa logique desktop ; l’état annoncé, les classes visibles et les restrictions de focus divergent.
- **Téléphone :** la carte ouvre le menu par clic programmatique, mais son clic d’origine continue à remonter. Le gestionnaire de clic extérieur du thème ne classe pas la carte photo comme déclencheur interne et referme le menu. Le diagnostic constate l’état final fermé ; la séquence des gestionnaires dans les sources explique ce résultat.

Il faut corriger la coordination des contrôleurs, pas supprimer des permissions ou débloquer les écoles depuis ProDéli.

## 3. Cartographie des tableaux de bord existants

Entrée commune : `modules/misc/Portal.php` → `Dashboard()` (synthèses des modules natifs activés/autorisés) → `SchoolSafeOverview()`.

| Ensemble | Sources / sélection | Particularité à préserver |
|---|---|---|
| Accueil administrateur scolaire | `SchoolSafeOverview` → `SchoolSafeReferenceDashboard` → `SchoolSafePhotoDashboard` si `dashboard_tab=overview` | coque fixe, pager client ; principal défaut reproduit |
| Rubriques administrateur | `SchoolSafeDashboardTabs`, `SchoolSafeReferenceDashboard`, `dashboard_tab` | élèves, personnel, parents, finances, activités, synthèses ; visibilité selon droits et CSS |
| Informations détaillées | `SchoolSafeDashboardDetails`, `details_tab` | annuaires, présences/scans, finances, calendrier, synthèses ; deuxième système de sélection, liens réécrits vers overview + details_tab |
| Enseignant | branche `teacher` de `SchoolSafeOverview`, Stats/Details/PersonalOverview | cours/élèves attribués, présence et raccourcis autorisés ; pas le pager photo administrateur |
| Parent | branche `parent`, Stats/Details/PersonalOverview | enfants liés, frais, suivi ; pas un tableau global d’école |
| Élève | branche `student`, Stats/Details/PersonalOverview | propre dossier/suivi ; garder le contexte élève |
| Synthèses Rosario par module | `Dashboard`, `DashboardModule`, `SchoolSafeNativeDashboardSources` | modules activés + menu natif + autorisation du programme ; ne pas les remplacer par des compteurs décoratifs |
| Autres adaptateurs historiques | `SchoolSafeDashboardView`, fonctions Direction/Teacher/Parent/Tech de `SchoolSafeDashboards.fnc.php` | aucun appel direct de production retrouvé pour le premier ; fonctions U5 retrouvées dans leurs tests. Ne pas les traiter comme des écrans actifs ni annoncer leur disponibilité sur leur seule présence |

`schoolsafe-dashboard-reference.css` filtre réellement les rubriques personnelles selon `data-dashboard-tab` : ne pas déduire du seul rendu PHP commun que tous les onglets affichent nécessairement la même chose. Les données, montants, droits effectifs, performances SQL et comportements authentifiés des quatre profils **ne sont pas intégralement certifiés par cette analyse UI**.

## 4. Stabilisation proposée — petits lots, pas une réécriture

### A. Menu partagé, premier correctif recommandé

- Un seul contrat pour ouvrir/fermer une branche, utilisable par en-tête, dock et cartes ; visibilité du conteneur et état ARIA/focus synchronisés.
- Même seuil de présentation partout ; supprimer la divergence 700/900 et l’interprétation différente par largeur calculée.
- Empêcher le clic déclencheur de provoquer immédiatement la fermeture extérieure ; conserver les vrais liens comme repli si le menu est absent ou le JS échoue.
- Vérifier ouverture/fermeture, Échap, clavier, clic extérieur et retour depuis un module à 360/390/700/701/768/900/901/1280 px.
- Cibles probables : JS photo, JS SchoolSafeCodex et règles CSS de coque ; ne pas réécrire l’auth ou le moteur natif Rosario.

### B. Accueil et sélection de rubrique

Conserver toutes les données et rubriques. Deux options à arbitrer : garder le découpage avec état de vue explicite dans l’URL, ou revenir à une synthèse défilante et des rubriques nommées. Recommandation : un accueil de synthèse réellement utile et des rubriques clairement accessibles, sans imposer un retour systématique sur un bandeau presque seul. Ne pas créer treize nouveaux dashboards ni supprimer les treize sections existantes.

Distinguer proprement `dashboard_tab` (rubrique serveur), `details_tab` (détail serveur) et la vue client. Rechargement, précédent/suivant et retour navigateur doivent retrouver la rubrique attendue ; les panneaux détaillés ne doivent pas rester cachés sans destination explicite. Vérifier montage/démontage AJAX pour ne pas laisser coque fixe, `inert` ou gestionnaires d’un ancien écran sur un module suivant.

### C. Validation par profil et source de données

Sur données fictives et sans compte réel : administrateur complet et limité, enseignant, parent avec enfants liés, élève ; année/école courante et autre école refusée. Vérifier les liens réellement affichés, données vides/erreurs honnêtes, recherche/filtres, absence d’élargissement des droits et séparation ProDéli conservée. Parcourir ensuite les rubriques de synthèse métiers une par une ; la stabilité de navigation ne certifie pas tous leurs calculs.

## Conclusion

La première cause crédible est **un conflit d’état de navigation et de présentation dans l’accueil scolaire**, auquel s’ajoute une pagination client qui masque l’essentiel et oublie la rubrique au rechargement. L’analyse n’établit ni disparition de données ni nécessité de refaire les comptes/base. Aucun correctif livré dans ce lot ; ProDéli demeure sur sa livraison qualifiée et la notation reste hors périmètre.
