# Tableau de bord composé — qualifié exact-r2, livraison autorisée en préparation

## Qualification exacte et GO de livraison du 23/10

Le propriétaire demande explicitement de finir et mettre en production le dashboard avant de poursuivre le lot Scan. Source runtime `b10d8d3279349b293f0c1cec19f7c61e5a7da782`, harnais `84f526829f5929bbc7f008ef859f0338c01822dc`, image `sha256:8770153af9759e423008028268248973a230c47327c76b371f76a1f12028cac6`, digest `9a84afb7f386d2d628aad6a6f8ed949055bbcc8c6641cd841e06da637b6b7136`. Trois sources modifiées, 2761 autres fichiers runtime identiques à l'image livrée b271dcc5.

Exact-r2 PASS : 186 contrats PHP/37 PostgreSQL, 25 groupes natifs authentifiés dont deux écoles fictives et leurs compositions aux largeurs1280/390/768, menus autorisés identiques, destinations GET200 et corps complets, dix groupes récupération, deux rollbacks de révocation, deux sessions concurrentes, un gagnant/un epoch. 126 contrôles navigateur locaux PASS avec le harnais publié. Captures authentifiées ordinateur/téléphone ouvertes et revues. Snapshot fixture inchangé et aucun compte/schéma de test restant ; conteneurs production inchangés pendant QA ; seuls objets éphémères du nonce retirés.

Exact-r1 était rouge après sept groupes (composition authentifiée wizard déjà PASS) : le test historique cherchait Configurer mon école dans le rôle accessible alors que le lien est maintenant dans Actions fermé sur téléphone. Correction du seul harnais pour ouvrir Actions, puis mêmes assertions de href absolu, vrai clic, CSRF et isolation. Aucun changement du runtime ni assertion métier retirée. R1 vérifié/nettoyé PASS, R2 réutilise le même ID d'image sur fixture fraîche. Un défaut d'échappement dans le script privé de collecte a ensuite été corrigé via PurePath.as_posix(), sans refaire les suites ou modifier le produit.

Recette dédiée `ops/releases/school-dashboard-composition-20261023/` : exige ces preuves exactes, sauvegarde PostgreSQL récente et privée, sessions/cache/configuration/conteneurs préservés, contrôle distinct après bascule. À ce point du rapport, QA terminée et production pas encore basculée. Les mentions locales ci-dessous sont historiques, remplacées par cette qualification.

## Mandat courant (remplace le découpage paginé)

Le propriétaire a validé le menu livré, mais rejeté la présentation du tableau de bord. Il a confirmé que le compteur changeait : le besoin est de réunir les informations des 13 anciennes vues, pas de réparer leur pagination. GO de réalisation reçu, puis références distinctes ordinateur et téléphone et validation des ajustements suivants :

- ordinateur : accueil/actions, indicateurs, grille de modules, graphiques, alertes ;
- téléphone : accueil compact, quatre indicateurs sur une ligne, huit accès rapides en grille 4 × 2 selon droits, alertes ;
- anniversaires dans la partie haute, jamais relégués en bas ;
- informations détaillées également en bas sur téléphone ; indicateurs secondaires et synthèse d'encaissements conservés ;
- animations discrètes, ombres teintées et touches lumineuses sur les deux formats, sans clignotement et avec prise en compte de `prefers-reduced-motion` ;
- téléphone : deux commandes en haut, Notifications et Actions. Actions regroupe les contrôles existants, profil, thème et actions du dashboard ;
- pas de personnage féminin ajouté dans la composition. Les commandes JASPE/Watch existantes du menu/dock ne sont pas reconstruites ni supprimées.

Les deux images personnelles restent privées et ne sont pas ajoutées au dépôt. Leurs chiffres, pourcentages de croissance, météo et exemples ne deviennent pas des données métier.

## Propriété et périmètre

Agent pi seul écrivain sur `fix/school-dashboard-stability-20261022`, base locale/distance `716545c327800b4aadec23160a750ca6ff0a5267`, worktrees inspectés. Trois fichiers produit : `ProgramFunctions/SchoolSafePhotoDashboard.fnc.php`, `assets/js/schoolsafe-photo-dashboard.js`, `assets/css/schoolsafe-photo-dashboard.css` dans le runtime `app/modules/codex-ui-v1/extensions-test-runtime/`.

Les sources du thème/menu validé ne changent pas. Le regroupement mobile déplace les vrais éléments DOM avec leurs gestionnaires, IDs et href ; il ne copie pas les boutons, ne fabrique pas de destinations et ne change pas les permissions. Le passage ordinateur restaure leur emplacement ; le démontage restaure les éléments et retire les écouteurs.

Le rendu PHP des anniversaires déjà autorisés est séparé du fragment de vie scolaire et placé une seule fois avant les modules, avec les informations ouvertes. Aucune seconde requête de personnes ni nouveau périmètre d'accès. Les autres informations de vie scolaire demeurent disponibles. Les listes d'anniversaires longues défilent horizontalement dans leur rubrique.

## Conservation des informations des anciennes vues

| Anciennes vues | Destination composée |
| --- | --- |
| 1 — Accueil | Accueil en haut ; commandes regroupées sur téléphone |
| 2–3 — Indicateurs | Ligne principale ; matières/recettes/alertes en indicateurs complémentaires sur téléphone |
| 4 — Encaissements | Synthèse jour/mois/année/dépenses au-dessus des dossiers |
| 5–6 — Modules | Une grille de tous les modules autorisés |
| 7 — Fréquentation | Graphique existant sous les modules |
| 8 — Synthèse financière | Diagramme financier existant, mêmes sources |
| 9 — Top alertes | Carte de synthèse accessible dans le flux |
| 10–11 — Vie scolaire | Anniversaires remontés ; autres rubriques conservées dans le flux |
| 12 — Alertes importantes | Rail ordinateur / après accès rapides sur téléphone |
| 13 — Dossiers détaillés | Corps serveur complet en bas ; onglets, liens et filtres natifs |

Le JavaScript ne crée plus de `.ss-fixed-view`, pager ou parking masqué. Les anciens liens `dashboard_view` deviennent des raccourcis de section et ne cachent aucune autre information. Les liens et formulaires de dossiers gardent leurs fragments/filtres serveur. Les calculs et requêtes métier ne sont pas réécrits.

## Vérifications et limites

- Nouveau test initial de composition : 25 PASS / 74 FAIL sur l'ancien pager. Le contrôle d'empreinte brut échouait aussi à cause des fins de ligne Windows ; comparaison corrigée vers le blob Git sans modification du menu.
- Première composition : 98 PASS / 1 FAIL ; fixture enrichie avec une liste longue pour représenter un dossier réel, plutôt que d'exiger un défilement avec trop peu de contenu synthétique. Puis 99/0.
- Extension mobile : 119 PASS / 5 FAIL ; correction de la fermeture clavier/focus du panneau Actions et alignement du conteneur d'actions de la fixture avec le vrai PHP. Puis 124/0.
- Rendu PHP réel avec données/permissions substituées : **20 contrats PASS**, SQL limité à SELECT, rôles non administrateurs non rendus par ce composant, métriques autorisées, valeurs de fixture, graphiques natifs, anniversaires uniques en haut, dossiers complets et état GET restauré.
- Navigateur avec vrais assets : **126 contrôles PASS** sur 360/390/700/701/768/900/901/1280, dont deux captures issues du vrai renderer PHP avec données substituées, dispositions, regroupement/restauration, contrôle de débordement, focus Escape, filtres, accès limité, réduction des animations et absence d'erreur JS. Les captures des modes clair/nuit et Actions sont privées. Ce niveau n'est PAS un parcours HTTP authentifié PostgreSQL.
- Contrats de frontière ProDéli/écoles : **84 PASS**, syntaxe PHP/JS et `git diff --check` PASS. Aucun changement des sources du menu/thème, de l'authentification ou des requêtes d'isolation.
- `app/qa-school-dashboard-navigation.cjs` lance désormais les critères de composition par défaut ; ses critères paginés restent explicitement historiques via `SCHOOL_NAV_LEGACY_PAGER=1`, pour l'ancien runtime uniquement.
- `app/qa-school-dashboard-http.cjs` a été adapté aux nouveaux critères. Depuis, exécuté dans la fixture authentifiée exact-r2 : PASS pour deux écoles ; le PASS exact-r7 de l'ancienne livraison n'est pas réutilisé comme preuve de ce candidat.

Preuves locales privées : `school-dashboard-composition-20261022/{red,green-r1,green-r2,mobile-r1,mobile-r2,mobile-r3,php-render,rendered-r1,final-local}`. Les essais rouges sont conservés.

## Statut historique avant qualification exacte

**À l'étape locale précédente seulement.** Aucune nouvelle image de production, connexion de compte réel, soumission métier réelle, migration, restauration, message externe ou bascule VPS. Menu livré et ProDéli restent protégés ; notation non livrée, Keycloak en pause, Watch honnêtement non raccordé.

Avant clôture : revue visuelle propriétaire et qualification HTTP/PG sur candidat immuable si livraison demandée. Ne pas annoncer l'acceptation du dashboard ou un déploiement sur la base des seuls tests synthétiques.
