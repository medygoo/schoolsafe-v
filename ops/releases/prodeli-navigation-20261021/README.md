# Navigation ProDéli — release ciblée

Base vérifiée `schoolsafe-rosario:prodeli-communication-90f6238cfee0`, image `sha256:3daf00a1c5990f6e7bac1564d6a47bdc322746c05f1e8db06fa7144f74e883fb`. Cinq fichiers existants seulement, aucune migration ni provisioning. `Side.php` reste identique.

Répertoire privé VPS `/opt/schoolsafe-releases/prodeli-navigation-20261021` : payload des cinq chemins du Dockerfile extrait du commit publié, recettes de ce dossier, `release.json` (commit/files/base_files SHA256), quatre contrats PHP (comptes/dashboard/communication/navigation), preuves `navigation-ui-tests.json`, `communication-ui-tests.json`, `dashboard-ui-tests.json`, `accounts-ui-tests.json`, `scope-proof.json`. Sources avant livraison sous `source-before/` en conservant leurs chemins relatifs. Pas de secret ou de capture dans le contexte Docker.

1. `python3 prepare.py` : base/payload Git, build réseau none, image exacte/comparaison complète, trois lints, 76 contrats sans DB.
2. Revoir la preuve ; `python3 deploy.py` : refuse les dérives de source/runtime, conserve ancien conteneur, transfère les sessions et vérifie santé/source/droits/services.
3. Vérifier indépendamment image, source propre, environnement et réglages communication inchangés, hash CSS public et sauvegardes. Pas d'essai mutant sur comptes réels.

Retour arrière préparé : `python3 /opt/schoolsafe-releases/prodeli-navigation-20261021/deploy.py --rollback`. Ancien conteneur `schoolsafe-rosario-pre-prodeli-navigation-20261021`. Sessions courantes transférées, anciennes neutralisées ; blocage si données nouvelles non préservées. Pas de restore DB, de suppression d'images ou d'exécution implicite du rollback.

Rapport : `docs/qualification/PRODELI_NAVIGATION_RELEASE_20261021.md`. Ce lot ne clôture pas création/rattachement/premier accès/récupération ; Keycloak reste en pause.
