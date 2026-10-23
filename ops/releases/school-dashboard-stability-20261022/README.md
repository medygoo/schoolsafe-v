# Livraison — stabilité du tableau de bord scolaire

Autorisation du propriétaire : conserver son menu personnalisé, les treize vues et leurs contenus ; analyse, correction, tests, stabilisation, puis production. Aucun nouveau design, notation, accès réel ou raccordement Watch.

## Provenance et périmètre

- Base production : `1e8259e4…`, checkout `d87947bf…`, branche `deployed/prodeli-school-boundary-20261022`.
- Candidat immuable : `sha256:b271dcc5c4e069623b278d327af6f71013b5fcc88c3bc90cee53c7f3bffe12e7`.
- Runtime : `a69e745d5b4bd36efc60dad4317583a0af037a4a` ; digest `257f7b7913586eecd1b789429e17e300f931babecf496f8c981b475496cdf07d`.
- Cinq fichiers autorisés, 2759 inchangés : PHP photo (cache), CSS photo, JS photo, JS SchoolSafeCodex, garde des sources optionnelles `schoolsafe-attendance/ArrivalRead.php`.
- `deploy.py` réemploie les mécanismes déjà livrés de sauvegarde, sessions, cache, configuration et préservation Docker. Aucun schéma, calcul de notes, changement d’identité ou envoi externe.

## Gates obligatoires

166 contrats PHP, 37 PG, campagne DOM 122/0 à huit largeurs, 25 groupes natifs authentifiés, dix récupérations, deux rollbacks de révocation et course de deux sessions avec un gagnant/un epoch. Treize vues réellement rendues, même menu autorisé à1280/390/768 sur deux comptes fictifs, destinations GET200 sans erreur PHP/SQL. QA exact-r7 : navigateur Chromium temporaire côté VPS, seul répertoire d’artefacts QA partagé ; bibliothèques Playwright existantes copiées en privé, aucune installation. Profils, dumps, tokens, captures et fichiers de remise restent privés. Vérification de snapshot et nettoyage des seuls conteneurs du nonce PASS, production inchangée.

Le réglage central, les frontières ProDéli/écoles, le CSRF, l’accès initial, la récupération et la notation ne sont pas altérés. Source disponible/absente est distinguée d’un vrai compteur zéro ; Watch reste honnêtement non raccordé. Ces tests certifient la navigation et les parcours décrits, pas toutes les opérations métier possibles.

## Exécution après publication et contrôle de SHA

Stager les sources de recette publiées (`deploy.py`, `recovery.py`, `preservation.py`) et les preuves exactes dans `/opt/schoolsafe-releases/school-dashboard-stability-20261022/` en0700 ; `release.json` contient les SHA de recette/runtime/image/digest et des cinq fichiers avant/après.

1. `python3 deploy.py prepare` : préflight en lecture seule, archive PG0600 récente/listée/vérifiée, cinq sources antérieures sauvegardées, aucun arrêt.
2. `python3 deploy.py deploy` : exige la qualification et une sauvegarde de moins de15minutes, conserve paramètres/env/configuration, sessions et cache ; ancien service conservé arrêté. Aucune restauration automatique.
3. `python3 deploy.py status`, puis vérification distincte des hashes/configuration/backup/runtime/auth/protection publique et de la conservation des sessions non expirées. Ne pas réinjecter les sessions normalement collectées par PHP.

Ne pas faire de login/reset réel, copier une école, créer une identité de production, envoyer un message ou lancer une migration pour vérifier cette livraison. Ne jamais supprimer les prédécesseurs ou autres conteneurs. En cas d’erreur après arrêt, préserver les ressources et demander la décision appropriée ; aucun rollback implicite.
