# Livraison récupération/SMS — 22 octobre 2026

**DÉPLOYÉ — CONTRÔLES TECHNIQUES PASS, VALIDATION CONNECTÉE PROPRIÉTAIRE ATTENDUE.** GO explicite suivi de sauvegarde et préflight PASS, bascule avec source/recette `68de604de0973bf9a22b2873cf791899f822dbe1`. 118 sessions et cache Chromium préservés, autres services/configuration inchangés, image healthy et routes contrôlées séparément. Aucun SMS réel, migration ou fallback destructif. [Rapport de livraison](../../../docs/qualification/PRODELI_RECOVERY_SMS_RELEASE_20261022.md).

## Candidat vérifié

- Base précédente, conservée dans le conteneur arrêté : `sha256:36a9e2fbc24159f25f65247cb516f84a43df49b410e35ee208ef3d25e3cf0c78`.
- Image livrée canonique : `sha256:01c194f4817731cecbec65476926585c58dced9118423bf7bbbff2ef10c73c34`.
- Empreinte source (base + hashes des cinq fichiers) : `2745039fd3e3362b15c6e4545b5f96ba40436727df0148bc33e667d0b7176339`.
- Source applicative publiée : `9d56feea54b7696783c49fc3444d7ce806eb670e`. Image construite à partir des **blobs Git**, jamais des octets Windows du worktree. La première image `b7c276be…` provenait du worktree : après normalisation des fins de ligne lors du commit, elle a été remplacée par ce candidat canonique et la totalité des contrôles a été rejouée avec succès. Aucun changement fonctionnel supplémentaire, aucune sixième source runtime. Le commit de recette associé doit contenir les mêmes cinq blobs ; toute modification impose une nouvelle qualification.
- Cinq chemins sous `app/modules/codex-ui-v1/extensions-test-runtime/` : `PasswordReset.php`, `compte-recuperation.php`, `plateforme-prodeli-sms.php`, `ProgramFunctions/SchoolSafeRecovery.fnc.php`, `ProgramFunctions/SchoolSafeSms.fnc.php`.
- **2 755 autres fichiers identiques**, y compris contenu, mode, UID et GID. Aucun ajout/suppression de fichier runtime.
- 92 contrats renderer + 37 contrats PG ; dix groupes de récupération/SMS, course HTTP native et deux pannes de révocation injectées, dix groupes de création/premier accès PASS sur l'image exacte.
- Le candidat a été construit hors réseau sur le même hôte que sa base, puis exécuté uniquement en QA temporaire interne : PostgreSQL fictif, aucun port publié, web en lecture seule, aucun montage/configuration/secret de production. Snapshot des onze tables fictives contrôlées identique après recette. Services de production et empreintes de leurs environnements inchangés ; ressources QA retirées avec contrôle d'étiquette.

Outil de reproduction : `scripts/prodeli-recovery-image-qa.py prepare --source-commit 9d56feea54b7696783c49fc3444d7ce806eb670e --evidence <nouveau-dossier-prive>`, puis `verify|diagnose|cleanup --evidence <meme-dossier>`. Le SHA est figé pour ne pas réétiqueter l'image avec un commit documentaire ultérieur. Préparation seule, aucune commande de déploiement ni de publication. Le dossier de preuves doit être nouveau ; le nettoyage ne vise que les conteneurs/réseau temporaires possédant l'étiquette et le nonce attendus. Aucun transfert de l'image entière : l'archive de l'ancien essai était tronquée et n'a pas été réutilisée.

Rapport : `docs/qualification/PRODELI_RECOVERY_SMS_20261022.md`. Preuves privées finales : `C:/Users/PC/SchoolSafeProductionUpdate-20261021-evidence/recovery-sms/exact-image-git-r1/`.

## Préconditions de livraison — à exécuter seulement après autorisation spécifique

1. Revoir et committer le lot cohérent, sans embarquer les preuves privées ; contrôler que le manifeste des cinq sources reste celui de l'image qualifiée. Publication sur la branche de travail autorisée, jamais de merge implicite sur main. Vérifier le SHA réellement publié.
2. Relire l'image active, les montages, options, variables et services, sans afficher les secrets ; arrêter sur toute dérive par rapport à la base ci-dessus. Inventorier les sessions/cache Chromium et données persistantes à préserver, comme pour la livraison native précédente.
3. Sauvegarde préalable de la base et des cinq sources, privée et protégée ; vérifier la liste de l'archive et ses empreintes. Ne pas faire une restauration d'essai sur la production. Conserver l'image précédente et les sauvegardes, sans les démarrer pour une simple vérification.
4. Pas de nouvelle migration dans ce lot. Inspection **métadonnées seulement, transaction READ ONLY**, réalisée le 22/10 : tables OTP/SMS/quota, état/audit de compte, liens d'identité et premier accès présentes sur la cible. Les colonnes/contraintes attendues doivent encore être comparées lors du préflight final ; pas de DDL automatique pour masquer une dérive.
5. Recette dédiée `deploy.py prepare|deploy|status`, restreinte à cette image et testée hors ligne par `scripts/prodeli-recovery-deploy-contracts.py`, préservant toutes les variables/montages/ports, les sessions courantes et le cache d'impression. `prepare` vérifie les colonnes/contraintes attendues et écrit les sauvegardes privées ; `deploy` exige ces preuves et une sauvegarde fraîche, sans migration ni fallback automatique. Aucun renouvellement de credentials, activation de ligne, conversion d'identité ou changement de politique de premier accès.
6. Livrer uniquement l'image qualifiée avec son attestation de source. Vérifier santé, routes anonymes protégées et hashes des cinq sources ; contrôle après bascule distinct. Ne créer aucun compte réel et ne déclencher aucun envoi externe pour ces contrôles.
7. Recette connectée du propriétaire. Un test de réception réelle ne se fait qu'avec un canal configuré et un destinataire expressément autorisé. `accepted` SMSGate n'est pas une preuve de réception.

## Retour arrière : compatible et sans restauration de données

**Non exercé. Aucun retour arrière automatique vers l'ancienne image.** Elle conserve les défauts connus de code OTP, de routage/texte en clair et de double consommation du lien. La remettre en service sans garde réintroduirait ces défauts.

- Garder la sauvegarde, l'image précédente et l'image qualifiée ; ne jamais retirer les lignes d'OTP, les époques de session, les états de compte ou le premier accès pour faire passer un garde.
- En incident, préserver les données et l'image qualifiée, diagnostiquer puis privilégier une image corrective compatible gardant la consommation atomique, la révocation transactionnelle et le masquage des codes.
- Si un arrêt temporaire de récupération est nécessaire, faire valider une mise en maintenance ciblée des deux points d'entrée de récupération, sans couper la connexion normale ni l'application scolaire. Cette mesure n'est ni installée ni exercée par cette préparation.
- Un retour vers une version antérieure exige une procédure séparée, approuvée après revue du risque de réintroduction de faille ; ce candidat n'en propose pas une commande dangereuse par défaut.
- Aucun DROP, reset de compte, effacement d'onboarding/OTP ou restore automatique de la base. Les sessions déjà révoquées doivent le rester.
