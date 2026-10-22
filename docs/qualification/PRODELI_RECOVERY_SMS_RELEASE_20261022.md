# Livraison récupération/SMS ProDéli — 22 octobre 2026

## Résultat

**DÉPLOYÉ et contrôlé techniquement après GO explicite du propriétaire.**

- Source applicative : `9d56feea54b7696783c49fc3444d7ce806eb670e`.
- Source/recette livrée : `68de604de0973bf9a22b2873cf791899f822dbe1`.
- Branche publiée : `work/prodeli-completion-20261021`, SHA distant vérifié avant livraison. Aucun merge vers main/production.
- Checkout VPS propre : `deployed/prodeli-recovery-sms-20261022`, commit `68de604` ; les commits documentaires suivants ne changent pas ce runtime.
- Image active : `sha256:01c194f4817731cecbec65476926585c58dced9118423bf7bbbff2ef10c73c34`.
- Empreinte du payload : `2745039fd3e3362b15c6e4545b5f96ba40436727df0148bc33e667d0b7176339`.
- Procédure stop/transfert/démarrage/contrôles : **9,61 secondes** ; ce chiffre est la durée de la procédure, pas une mesure indépendante de l'indisponibilité perçue.

## Périmètre livré

Cinq sources seulement sous `app/modules/codex-ui-v1/extensions-test-runtime/` : `PasswordReset.php`, `compte-recuperation.php`, `plateforme-prodeli-sms.php`, `ProgramFunctions/SchoolSafeRecovery.fnc.php`, `ProgramFunctions/SchoolSafeSms.fnc.php`. **2 755 autres fichiers de l'image identiques**, contenu/mode/UID/GID. Tableau de bord, assistant de création d'école, rattachement du principal et premier accès déjà livrés conservés, pas reconstruits.

- Le code est émis avant envoi au numéro enregistré normalisé ; une panne ne remet pas de défi utilisable à la session.
- Usage unique, expiration et cinq essais de l'OTP atomiques ; code absent de l'historique SMS en clair.
- Lien natif consommé une seule fois même sous concurrence ; changement du mot de passe de toutes les années et révocation des sessions transactionnels, rollback aux échecs de révocation.
- Premier accès incomplet et identités gérées exclus de cette récupération native ; non-énumération et liens officiels du hub corrigés.
- Test SMS réellement attaché à la ligne confirmée, sans bascule silencieuse vers une autre ; routage ordinaire, quotas et organisation des notifications conservés.

La source Windows et les blobs Git différaient par normalisation des fins de ligne. L'image worktree initiale n'a pas été livrée : reconstruction depuis les blobs publiés, qualification complète rejouée, puis correspondance Git/image exigée avant sauvegarde et bascule. Aucun historique publié réécrit.

## Qualification et préflight

- Sur **l'image exacte livrée** : 92 contrats renderer, 37 contrôles PostgreSQL réels fictifs, dix groupes navigateur récupération/SMS, course HTTP native (deux demandes simultanées, un seul succès/une seule époque) et deux pannes de révocation avec rollback, dix groupes navigateur création/principal/premier accès PASS.
- 24 contrats offline de recette : refus des mauvais image/payload/périmètre/preuves, conservation des paramètres, garde des écritures, cache Chromium, absence de rollback automatique et source canonique Git.
- QA éphémère interne sans port public, web en lecture seule et base entièrement fictive ; onze tables fictives contrôlées identiques après tests. Conteneurs/réseau de recette retirés avec garde d'étiquette/nonce.
- Préflight production : état/image/source/branche/configuration, colonnes/types des neuf tables requises, contraintes OTP et absence de configuration de simulation vérifiés. Inspection SQL **READ ONLY**, aucune nouvelle migration ni DDL.
- **Sauvegarde PostgreSQL privée : 645 529 octets**, archive custom vérifiée par liste et empreinte, mode `0600` dans un répertoire `0700`. Cinq sources précédentes sauvegardées et contrôlées. Aucune restauration d'essai de cette sauvegarde sur production, aucun transfert de celle-ci vers la QA.

## Préservation et contrôle après bascule

**Contrôle séparé PASS**, sans réutiliser uniquement le verdict de déploiement : image/source Git/hashes, anciennes sources, sauvegarde/modes, paramètres, services et routes publiques relus.

- **118 fichiers de session préservés** : contenu/mode/UID/GID vérifiés lors du transfert. Archive comparée séparément au conteneur précédent arrêté ; aucune ancienne session réintroduite après usage normal de la nouvelle application.
- Cache Chromium d'impression conservé et vérifié au transfert ; **10 entrées** de cache dans l'archive conservée. Aucun test d'impression réel déclenché.
- Configuration, variables, montages, ports, limites, commande, entrée et healthcheck inchangés. Autres services : mêmes conteneurs et mêmes démarrages.
- HTTPS : `/index.php`, `/compte/recuperation`, `/PasswordReset.php` → **200**. Hub sans simulation, cache `no-store`, bonnes destinations de formulaire/retour ; aucune soumission réelle de récupération.
- Anonyme : administrateurs → **302**, console et réglages SMS/WhatsApp → **403**.
- Aucune migration, aucun compte/école réel créé ou manipulé pour essai, aucun SMS/courriel/WhatsApp réel envoyé, aucune ligne activée. Keycloak reste en pause ; fournisseurs et communication existante conservés.

## Préservation de l'ancienne version et incidents

Ancien conteneur conservé **arrêté** : `schoolsafe-rosario-pre-prodeli-recovery-sms-20261022`, image `36a9e2fb…`. Aucun rollback exercé et aucun fallback automatique vers les failles corrigées. Ne pas le redémarrer comme test ou supprimer des données pour permettre un retour arrière. En incident, diagnostic et correctif compatible ; tout retour en arrière ou maintenance ciblée nécessite une procédure séparée. **Aucun DROP/restore/reset d'époque ou effacement d'onboarding/OTP.**

## Preuves et suite

- Release privée VPS : `/opt/schoolsafe-releases/prodeli-recovery-sms-20261022/` (`release.json`, sauvegardes protégées, preuves de schéma/backup/déploiement et contrôle séparé).
- Preuves locales privées : `C:/Users/PC/SchoolSafeProductionUpdate-20261021-evidence/recovery-sms/`, qualification finale `exact-image-git-r1/`.
- Détails de qualification et limites : [rapport](PRODELI_RECOVERY_SMS_20261022.md).
- Recette : [procédure](../../ops/releases/prodeli-recovery-sms-20261022/README.md).

**Reste la validation connectée du propriétaire.** La réception réelle n'est pas déclarée testée : elle exige un canal opérationnel et un destinataire expressément autorisé. `accepted` SMSGate ne prouve ni envoi physique ni réception. Pas de prétendue clôture de tous les modules SchoolSafe ni de correction du RED CI sécurité historique par ce seul lot.
