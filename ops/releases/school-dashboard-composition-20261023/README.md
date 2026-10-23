# Livraison — tableau de bord composé, 23/10/2026

Le propriétaire autorise explicitement de terminer et mettre en production ce dashboard avant d'attaquer le lot Scan. Les références ordinateur/téléphone et les ajustements Actions/Notifications, anniversaires en haut, détails en bas et effets visuels sont validés. Le menu accepté reste protégé.

## Périmètre immuable

- Base : image `sha256:b271dcc5c4e069623b278d327af6f71013b5fcc88c3bc90cee53c7f3bffe12e7`, VPS `f1dcb3bddec98830446483ad4becb2c88a41c6de`, branche `deployed/school-dashboard-stability-20261022`.
- Source candidat : `b10d8d3279349b293f0c1cec19f7c61e5a7da782`.
- Image : `sha256:8770153af9759e423008028268248973a230c47327c76b371f76a1f12028cac6`.
- Digest : `9a84afb7f386d2d628aad6a6f8ed949055bbcc8c6641cd841e06da637b6b7136`.
- Trois fichiers : PhotoDashboard PHP/CSS/JS, 2761 autres fichiers runtime inchangés.

Aucun changement du thème/menu source, des droits, du scan, des familles, de la notation, de ProDéli, de l'authentification ou des fournisseurs. Aucune migration/restauration ni test sur compte réel. Le prochain lot Scan n'entre pas dans cette image.

## Gates bloquants

La recette refuse toute livraison sans les preuves suivantes, toutes reliées à la même image/source : 186 contrats PHP dont 20 renderer de composition, 37 PostgreSQL récupération, 126 contrôles navigateur locaux, 25 groupes natifs authentifiés sur PostgreSQL de fixture, dix groupes récupération, deux rollbacks et course de deux sessions avec un gagnant/un epoch. Pour deux écoles fictives : composition sans pager/parking, sections accessibles, anniversaires visibles, format téléphone à quatre indicateurs et deux commandes, menu identique selon droits, destinations GET200 sans erreur SQL/PHP. Snapshot fixture intact, production inchangée pendant QA, ressources éphémères du nonce retirées.

La présence de cette recette ne constitue pas un PASS de ces gates : leurs JSON réels sont obligatoires et vérifiés par `qualification()`.

## Exécution contrôlée

Publier la recette sur la branche de développement, vérifier le SHA distant, puis stager les seuls scripts/proofs privés dans `/opt/schoolsafe-releases/school-dashboard-composition-20261023/` (0700). `release.json` distingue commit de recette et source runtime.

1. `python3 deploy.py prepare` : préflight lecture seule, base exacte/saine et checkout propre, backup PostgreSQL 0600 valide, trois sources antérieures sauvegardées, configuration et autres conteneurs inventoriés. Aucun arrêt.
2. `python3 deploy.py deploy` : exige backup de moins de 15 minutes, crée/stage avant arrêt, conserve configuration et paramètres Docker, sessions et cache Chromium. Nouveau checkout `deployed/school-dashboard-composition-20261023` ; ancien conteneur préservé arrêté sous `schoolsafe-rosario-pre-school-dashboard-composition-20261023`.
3. `python3 deploy.py status`, puis vérification distincte des sources, sources protégées, backup, configuration, conteneurs, routes publiques et sessions non expirées. Ne pas réinjecter les sessions normalement collectées par PHP.

Toute erreur après arrêt exige inspection et décision explicite. Aucun rollback automatique, suppression de prédécesseur, restauration de base ou envoi externe. Les configurations et sauvegardes privées ne sont jamais publiées dans Git.
