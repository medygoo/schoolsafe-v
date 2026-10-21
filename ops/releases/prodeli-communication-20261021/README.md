# Communication ProDéli — release ciblée

Périmètre : deux pages de réglages et une feuille CSS uniquement. Pas de migration, de mutation de comptes ou d'envoi externe ; organisation Studio/notifications/fournisseurs conservée. Voir `docs/qualification/PRODELI_COMMUNICATION_RELEASE_20261021.md`.

Base immuable : `sha256:9177067d048e865785b57af67571140dcb5cfa6ca688ba9ca8ef9ccc45c15d12` ; tag local vérifié `schoolsafe-rosario:prodeli-dashboard-388564451430`. Ne pas utiliser un `FROM sha256:…` non qualifié par le registre.

Préparer un répertoire privé `/opt/schoolsafe-releases/prodeli-communication-20261021` avec ces quatre fichiers de recette, le payload des trois fichiers à partir du commit Git publié, `release.json` (commit, files SHA256, base_files SHA256 ou ABSENT), les tests PHP `contracts.php`, `dashboard-contracts.php`, `communication-contracts.php`, les preuves locales `communication-ui-tests.json` et `scope-proof.json`. Sauvegarder les deux sources actuellement servies dans `source-before/`. Ne pas inclure de credentials, captures, sessions ou configuration privée dans le contexte Docker.

1. `python3 prepare.py` : contrôle base/payload Git, build sans réseau, comparaison complète des fichiers, lints, 56 contrats sur image exacte sans DB. Écrit une preuve et l'ID candidat ; ne bascule rien.
2. Revoir la preuve et l'absence de dérive.
3. `python3 deploy.py` : vérifie Config/HostConfig et source, prépare le checkout exact publié, conserve l'ancien conteneur, transfère les sessions, démarre le candidat et contrôle santé/droits/source/services indépendants.
4. Vérifier indépendamment la source Git, l'image, les refus anonymes et le hash du CSS public, les réglages et l'état des autres services.

Retour arrière préparé : `python3 /opt/schoolsafe-releases/prodeli-communication-20261021/deploy.py --rollback`. Ancien conteneur `schoolsafe-rosario-pre-prodeli-communication-20261021`, image base ci-dessus. Transfère les sessions actuelles et neutralise les anciennes ; bloque si des données inscriptibles non préservées sont détectées. Pas de restauration DB et pas de suppression des anciens conteneurs/images. Cette procédure préparée n'est pas une preuve de rollback exercé en production.
