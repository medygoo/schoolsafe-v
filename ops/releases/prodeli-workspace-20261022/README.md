# ProDéli workspace — livraison du 22 octobre 2026

## Mandat et état

**DÉPLOYÉ, CONTRÔLES TECHNIQUES ET NAVIGATION CONNECTÉE PASS.** GO explicite du propriétaire : finir ProDéli et déployer en production. Image `sha256:59fb6b25e57ea4f53237ff36dbdc0ae1122fdf57be34a6ab4fe5b3bf6fc5bb63`, source immuable `a3fbffb158ce057d4d7742803bb394f38678bcc4`, recette livrée `8fc3546bc47d23395d5802a9f612185e9193acaa` (mêmes 16 blobs applicatifs). Sauvegarde et contrôle séparé PASS, 91 sessions et dix entrées cache Chromium préservées. Navigation réelle desktop/mobile contrôlée, acceptation du propriétaire distincte. [Rapport](../../../docs/qualification/PRODELI_WORKSPACE_RELEASE_20261022.md). La récupération/SMS déjà active reste la base immuable `sha256:01c194f4817731cecbec65476926585c58dced9118423bf7bbbff2ef10c73c34`.

16 sources runtime ciblées, dont deux ajouts : arrivée native globale, dashboard central distinct, retour scolaire visible, réglages/console/résultat de création habillés SchoolSafe, revue de portée lisible et limite de mot de passe cohérente. Contrôleurs, droits, données Rosario et fournisseurs réutilisés. Aucun changement de schéma, nouvelle identité, conversion Keycloak ou envoi externe.

## Qualification et livraison

1. Commit/push sur `work/prodeli-completion-20261021`, comparaison SHA distant. Aucun merge main.
2. `python scripts/prodeli-workspace-image-qa.py prepare --source-commit <SHA> --evidence <dossier-prive-neuf>` : blobs Git canoniques, build hors réseau sur l’hôte de la base. Deux ajouts et 14 modifications seulement, 2 746 autres fichiers strictement identiques (hash/mode/UID/GID). Rejoue 123 contrats renderer/contrôleur et 37 récupération PostgreSQL.
3. Exécuter les quatre suites navigateur/HTTP workspace, native, recovery et concurrence, uniquement contre les ressources QA étiquetées/nonce internes. Pas de secrets de production, ports publiés ou communication externe ; web en lecture seule. Vérifier puis nettoyer par `verify` et `cleanup` du même script. Les preuves d’intégration et de nettoyage sont exigées avant livraison.
4. Constituer la release privée `/opt/schoolsafe-releases/prodeli-workspace-20261022`, avec source-manifest, preuves exactes, résultats workspace/native, release.json, script publié et deux dépendances canoniques : `recovery.py` depuis la recette récupération/SMS et `preservation.py` depuis la recette native. Ne jamais importer de secret ou preuve privée dans Git.
5. `python3 deploy.py prepare` : vérifie source/Git/image/qualification, métadonnées en lecture seule, sauvegarde custom DB privée (mode 0600, liste valide et SHA), 14 sources préexistantes, état des services/configuration. Aucun DDL.
6. `python3 deploy.py deploy` : exige une sauvegarde de moins de 15 minutes et des preuves inchangées. Crée le candidat à l’arrêt avant de stopper l’ancien runtime, conserve options/montages/variables/ports et transfère sessions/cache Chromium. Ancien conteneur conservé arrêté. Aucun rollback automatique ni restore DB.
7. `python3 deploy.py status` et contrôle séparé : image/source/Git, routes, services, configuration, sessions/cache et sauvegardes. Recette connectée du propriétaire desktop/mobile distincte, sans lecture de son mot de passe.

## Limites et récupération

Ne pas annoncer tous les boutons validés sur la seule présence des formulaires. Les actions sensibles sur comptes/écoles existants et les réceptions externes non autorisées ne sont pas exécutées en production. L’école fictive ID 2 et son administrateur ID 5 déjà créés avec accord restent conservés ; aucune suppression.

Retour arrière non exercé et non automatique. La base de cette release contient déjà les corrections de récupération, mais une régression se traite après diagnostic et validation d’une procédure compatible, en préservant données/sessions/époques/onboarding. Ne jamais démarrer les anciens conteneurs historiques vulnérables, supprimer l’onboarding ni restaurer une base pour forcer une compatibilité.
