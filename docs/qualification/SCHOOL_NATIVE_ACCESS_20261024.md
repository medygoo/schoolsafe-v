# Administration native des accès — étapes 1, 2 et 3 terminées, logiciel DÉPLOYÉ

## Porte propriétaire la plus récente

Traiter les étapes une par une, informer après chaque étape finie puis attendre un GO. **Étapes 1 et 2 validées puis GO explicite reçu pour l'étape 3. Déploiement et contrôle indépendant terminés.** Aucun GO ne sélectionne implicitement de vrais bénéficiaires ou n'active un provider. Cette instruction remplace la poursuite automatique du mandat précédent.

## Périmètre

Règle propriétaire : déléguer un ensemble scolaire complet ou partiel à plusieurs comptes, sans quota de bénéficiaires ni exclusivité par tâche. ProDéli administre les responsabilités depuis sa console, sans entrer dans l'espace client. [Contrat validé](../superpowers/specs/2026-10-24-native-access-administration.md).

Cinq runtime ciblés : `admin-console.php`, `ecole-profils.php`, `SchoolSafeSchoolScope.fnc.php` et nouveaux `SchoolSafeSchoolAccess.fnc.php` / `SchoolSafeSchoolAccessView.fnc.php` sous `ProgramFunctions`. Aucune nouvelle table, identité, permission ou route. Pas de bridge laboratoire, de profil global partagé modifié, de mot de passe, leadership ou compte parent transféré.

## Réemploi et protections du candidat

- Console ProDéli existante : sélection explicite d'un administrateur scolaire existant, école/année et droits à confirmer ; aucune ouverture d'un contrôleur scolaire global.
- Hub scolaire existant : comptes admin/teacher du périmètre exact ; consultation et gestion séparées. Attribution personnelle exige native `DelegationManage` use+edit et `Users/User.php` use+edit. Détails financiers/configuration restent des cases distinctes.
- Écritures dans les profils, exceptions et comptes natifs. Une modification personnelle crée une version de profil propre au compte/école ; elle préserve les droits natifs hors catalogue, les autres titulaires et le modèle global. Aucune exception individuelle n'est ajoutée derrière un profil nommé. Profil zéro nommé et véritable absence de profil sont distingués.
- Transaction avec verrou d'école, acteur/cible/profils, autorité vivante, empreinte de version, confirmation et CSRF. Audit natif `schoolsafe_account_audit`, avec acteur/cible/école/année/profils/droits avant-après, dans la même transaction. Aucun message/outbox émis.
- Aucun quota ; ensemble égal autorisé pour les profils propres à l'école seulement, plafonds globaux stricts conservés. Auto-modification, compte global/étranger, profil source réservé/incohérent, droits hors plafond, readonly/suspension/école bloquée, contexte ancien et panne d'audit sont refusés.
- Ancien éditeur partagé conservé : création/édition/suppression désormais encadrées par transaction et audit obligatoires, confirmation d'impact, version incluant tous les détenteurs. Profil propre à l'acteur protégé. Suppression d'un profil encore attribué refusée plutôt que conversion silencieuse en comptes Custom.

## Preuves locales effectivement obtenues

- RED initial helper absent, puis **15 contrats purs PASS**.
- **50 contrôles PostgreSQL jetables PASS**, `access-native-pg-r8` : bootstrap explicite, quatre destinataires complets, révocation personnelle sans effet sur les autres, profils globaux inchangés, zéro/custom, CSRF/confirmation, retrait d'autorité, module inactif, version ancienne, rollback d'audit, contrôle du hub partagé. Deux processus réels : une écriture/un refus de version ; révocations mutuelles concurrentes : un gestionnaire effectif reste. Authentification et états de comptes substitués ; ce n'est PAS du HTTP.
- **21 contrôles Chromium PASS** à1280/390 sur fragment PHP synthétique, `access-native-browser-r3` : sélection complète, retrait, dépendance Use/Edit, confirmation, version et destination scolaire. Ce n'est PAS un navigateur authentifié sur serveur.
- **62 contrats d'accès Scan et84 de frontière ProDéli/école PASS**, six lints ciblés PASS, diff-check PASS.
- Les bases/réseaux PG jetables possédés ont été supprimés après chaque passage. Premiers essais r1/r2 ont échoué sur le harnais (multi-statements via pg_query_params, interpolation PHP des dollars PL/pgSQL) ; aucune de ces tentatives n'est présentée comme preuve produit réussie.

## Étape 1 — HTTP authentifié : 38 contrôles PASS

Candidat Git `56e16c8`, image de fixture `sha256:d6dc20fa7bc79c649e2ccd986afbeb542b22d03eceff4663bf7f07c19b6ad588`, digest `c47d63f8263e9117ab119757f8a11cdf5102c54c074ce210b220a55701817083`. Réseau interne, web readonly, aucun port publié ; aucun déploiement. Préparation de la fixture :387contrats et37recovery PASS,71sources attendues et2731inchangées. Cette préparation technique ne constitue pas la qualification complète de l'étape 2.

Preuve privée `access-http-image-r1/access-authenticated-http-r1.private.json` : **38 contrôles HTTP réellement exécutés PASS**, logins natifs/mots de passe synthétiques, sessions cookie et contrôleurs réels. Console centrale → attribution confirmée à un administrateur scolaire existant → hub scolaire → trois opérateurs, chacun avec les cinq tâches Scan indépendantes. Sessions ouvertes AVANT attribution ; capacités reconnues après attribution. Révocation ciblée par POST natif ; la même session perd l'accès immédiatement, une autre le garde. CSRF central invalide, confirmation manquante et formulaire ancien ne modifient pas les droits. Audits natifs : cinq écritures attendues, pas d'audit de refus/préparation. Profil partagé d'origine conservé. ProDéli ne peut ouvrir le hub scolaire ni le scanner, avant OU après initialisation. Aucun compte réel créé/modifié, aucun passage d'enfant ni envoi externe.

`image-verify-proof.json` PASS : empreintes des tables de fixture identiques à l'état initial après retrait des seules lignes synthétiques ; conteneurs de production inchangés. `image-cleanup-proof.json` REMOVED : ressources éphémères possédées supprimées et production toujours inchangée. Nonce110d3a93c386. Première commande avec SHA court refusée avant création de ressources, relancée avec SHA complet ; pas un échec produit.

## Étape 2 — qualification image et régressions : PASS

**Candidat final : runtime `5cf4c9cbfaf379a252afc27f76fac17bc6c45d04`, image `sha256:1b3843de24caa121243f231b147ddf0a9597b155deca7271ed699994f306fb86`, digest `c1ed80141175ab4ecc2c5110839a6b48a75a66e13eea79d27dc61936386cdded`.** L'image d6dc de l'étape 1 n'est PAS le candidat final.

La revue a reproduit trois refus manquants : schéma natif des états de compte indisponible, schéma des états d'école indisponible, état cible inconnu. RED conservé `access-step2-schema-red/pg.log` :53contrôles,3échecs. Correction bornée au nouvel owner : les deux writers refusent si les sondes natives Ready échouent ; cible limitée aux états natifs active/readonly. Puis **55PG jetables PASS**, dont les deux contrôles de schéma dans l'éditeur partagé, rollback/audit et concurrence ; authentification/états substitués dans cette preuve, pas une simulation d'auth HTTP.

Sur le candidat final :

| Contrôle | Résultat et preuve privée |
| --- | --- |
| Préparation immuable hors checkout sale |71sources/2731inchangées ;387contrats et37recovery PASS ; `access-step2-image-r2/image-prepare-proof.json` |
| Parcours natifs Chromium |PASS exit0 ; `access-step2-image-r2/exact-integration-result.json` et artefacts privés distants |
| Récupération de compte Chromium |PASS exit0 ; même preuve |
| Concurrence des réinitialisations |PASS exit0 ; même preuve |
| HTTP authentifié Scan historique |58PASS : QR réel synthétique, arrivée/pickup/idempotence, finances/cantine sans fuite, famille, notifications historiques/révocation ; `card-authenticated-http-scan-regression-r1.private.json` |
| HTTP accès rejoué après correction |38PASS ; `access-authenticated-http-access-regression-r1.private.json` |
| HTTP accès + écritures de profils partagés |49PASS ; `access-step2-shared-http-r1/access-authenticated-http-shared-writes-r1.private.json` |
| Formulaire PHP synthétique Chromium1280/390 |21PASS ; `access-step2-browser-final/result.json` ; pas un navigateur authentifié sur les nouveaux formulaires |
| Restitution des fixtures |PASS après chaque série ; tables identiques à leur empreinte initiale, `image-verify-proof.json` dans les deux fixtures finales |
| Nettoyage + frontière de livraison |REMOVED puis contrôle indépendant PASS : aucun conteneur/réseau des trois nonces, delta5runtime exact,3hashes dashboard protégés, production propre/saine/image inchangée ; `candidate-provenance-and-final-cleanup-proof.json` |

L'extension de test HTTP vient du commit **`80739b7b21c7940057ef3e849354aec38814ca77`**, sans modification runtime supplémentaire. Elle prouve les POST réels de création scolaire, retrait Edit en gardant Use, refus de formulaire ancien, refus de suppression d'un profil attribué sans conversion en Custom, révocation partagée et perte dans une session ouverte, suppression seulement sans détenteur, audit natif obligatoire pour chaque écriture et aucun audit de refus. Deux détenteurs partagés sont préparés par SQL uniquement comme fixture synthétique ; leur attribution initiale n'est pas présentée comme une nouvelle preuve du contrôleur Users.

Nonces nettoyés :34ad532a86d7 (premier candidat),7c84919d7564 (candidat corrigé et suites complètes),4f744d7270c0 (extension HTTP partagé). Réseaux internes, web readonly, aucun port publié, aucun provider externe. Le premier candidat avait aussi passé ses suites ; elles ont toutes été rejouées sur le candidat corrigé. Le runner privé reprend le harnais qualifié et normalise seulement le refus Docker `no such object/container` sans casse lors du second nettoyage.

**Limites à la clôture de l'étape 2 :** pas de pourcentage de couverture instrumenté revendiqué ; pas de caméra physique, WhatsApp connecté, impression officielle ou droits réels. La recette et la sauvegarde n'étaient alors pas exécutées ; leurs résultats réels figurent désormais à l'étape 3.

## Étape 3 — déploiement ciblé et contrôle indépendant : PASS

Recette publiée et déployée **`44e246fdd037318c647ee25e8447bb83e3b88337`**, branche VPS `deployed/school-native-access-20261024`, [recette](../../ops/releases/school-native-access-20261024/README.md). Runtime final5cf4c9c et image1b3843de/digestc1ed8014 ci-dessus inchangés. **Cinq fichiers runtime seulement**, sans migrations ni attribution réelle.22contrats de release offline PASS ; scripts et deux moteurs de préservation comparés à Git. Préflight schéma, tables et séquences natifs/autorité/privilèges du service en lecture seule PASS ; aucun GRANT SQL ou propriétaire modifié.

Sauvegarde PG fraîche privée0600,685809octets, archive custom validée par `pg_restore --list`, SHA256 **`d1a6bf4e8de2c52b0147e14532a468460072e3b16fe87aa97819e1dd3fd71532`**. Jamais restaurée. Release privée0700 `/opt/schoolsafe-releases/school-native-access-20261024/`.

Bascule **8,68secondes**,83fichiers de session transférés, cache Chromium préservé ; configuration complète et environnement incluant le HMAC durable inchangés. Ancien conteneur `schoolsafe-rosario-pre-school-native-access-20261024` arrêté/conservé ; cinq services voisins inchangés. Aucun worker/provider lancé, aucun message externe ni mutation de compte réel testé.

Contrôle indépendant **PASS** :71hashes source+3dashboard, identité source/digest de l'image, checkout et branche VPS propres/conformes, HostConfig complet et environnement identiques, hash de configuration identique, voisins ID/start inchangés,0session non expirée manquante et0collectée expirée,9entrées de cache Chromium préservées. HTTPS login/récupération200, famille/scanner anonymes401, JS famille public au hash attendu. Aucun worker dispatch/recover ; caméra physique/provider toujours non qualifiés.

Preuves privées `access-deployment-r1/production-{deployment,backup,schema}-proof.json` et `production-independent-verification-proof.json`. Dernier téléchargement SSH du seul schema-proof expiré à30secondes APRÈS vérification indépendante réussie ; fichiers locaux inspectés (autres preuves déjà reçues), statut live recontrôlé PASS, seul fichier manquant rapatrié par appel borné. Aucun redéploiement. Cause transport exacte non établie ; `proof-fetch-recovery.json` documente ce défaut de collecte, pas une panne produit.

### Utilisation disponible, sans attribution implicite

ProDéli : console existante `/admin-console.php`, lien **« Accès — [école] »**, choisir un administrateur existant et confirmer explicitement les droits. Cet écran n'ouvre pas le workspace scolaire à ProDéli. Administrateur scolaire autorisé : `/ecoles/{id}/profils`, section **« Accès personnels de l’école »** ; choisir un compte puis tous les droits proposés ou un sous-ensemble. Les montants détaillés, la configuration et l'administration restent des droits séparés. Plusieurs personnes peuvent porter le même ensemble autorisé ; elles ne deviennent pas automatiquement principales de direction.

### Limite de clôture

Le mécanisme logiciel d'administration des accès est livré. Aucun vrai bénéficiaire, école/configurateur/tâche/point ou réglage financier/familial n'a été choisi par l'agent. La mise en service réelle du Scan reste soumise à ces choix, à la caméra HTTPS physique, à la connexion WhatsApp/activation contrôlée et à l'impression officielle séparées. Arrêter après notification de fin d'étape ; ne pas déduire une autorisation d'attribution réelle.

Outils : `scripts/school-access-native-{qa.py,pg.php,http.php}`, `school-access-policy-contracts.php`, `school-access-image-qa.py`, option `--access` du lanceur HTTP existant, `app/qa-school-native-access.cjs`. Le script HTTP a été exécuté avec succès uniquement dans la fixture synthétique isolée.

État de départ historique relu en SSH avant bascule : checkout propre `e17357bbcf59068d8276c260314d3ffa8a1733a2`, image `sha256:7bedfc1e37289136d9a54ac87f82603784053a47bd45c90c1b1d3c159dc7c628` running/healthy. État désormais remplacé par l'étape 3 ci-dessus. Aucun droit réel attribué. Caméra physique, connexion WhatsApp, worker et impression officielle gardent leurs conditions distinctes. Ne pas annoncer toute la mise en service Scan terminée.
