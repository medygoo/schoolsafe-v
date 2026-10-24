# Administration native des accès — étape 1 HTTP validée, NON DÉPLOYÉ

## Porte propriétaire la plus récente

Traiter les étapes une par une, informer après chaque étape finie puis attendre un GO. **Étape 1 terminée ; étape 2 et déploiement interdits sans nouveau GO.** Cette instruction remplace la poursuite automatique du mandat précédent.

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

## Étapes suivantes — EN ATTENTE DU GO

2. Qualification complète de l'image Git/régressions exactes, notamment parcours navigateur natifs/récupération/concurrence et contrôles de livraison. L'image utilisée comme fixture existe, mais cette étape n'est pas déclarée terminée.
3. Après un GO distinct et uniquement si qualifié : sauvegarde privée récente, bascule ciblée et contrôle indépendant. Choix des vrais bénéficiaires toujours humain et explicite.

Outils : `scripts/school-access-native-{qa.py,pg.php,http.php}`, `school-access-policy-contracts.php`, `school-access-image-qa.py`, option `--access` du lanceur HTTP existant, `app/qa-school-native-access.cjs`. Le script HTTP a été exécuté avec succès uniquement dans la fixture synthétique isolée.

Production relue en SSH, sans mutation : checkout propre `e17357bbcf59068d8276c260314d3ffa8a1733a2`, image `sha256:7bedfc1e37289136d9a54ac87f82603784053a47bd45c90c1b1d3c159dc7c628` running/healthy. Aucun droit réel attribué. Caméra physique, connexion WhatsApp, worker et impression officielle gardent leurs conditions distinctes. Ne pas annoncer toute la mise en service Scan terminée.
