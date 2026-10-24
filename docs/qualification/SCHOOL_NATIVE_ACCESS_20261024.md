# Administration native des accès — candidat en qualification, NON DÉPLOYÉ

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

## Restant obligatoire avant livraison

HTTP authentifié synthétique ProDéli → administrateur scolaire → au moins trois opérateurs complets → révocation dans une session déjà ouverte ; contrôles d'audit, refus et restitution exacte des fixtures. Puis image Git immuable (71fichiers Scan cumulés, dont5 du présent delta), refus/régressions exactes, sauvegarde privée récente, bascule ciblée et contrôle indépendant.

Outils : `scripts/school-access-native-{qa.py,pg.php,http.php}`, `school-access-policy-contracts.php`, `school-access-image-qa.py`, option `--access` du lanceur HTTP existant, `app/qa-school-native-access.cjs`. Le script HTTP est écrit/linté mais NON ENCORE EXÉCUTÉ à ce point.

Production relue en SSH, sans mutation : checkout propre `e17357bbcf59068d8276c260314d3ffa8a1733a2`, image `sha256:7bedfc1e37289136d9a54ac87f82603784053a47bd45c90c1b1d3c159dc7c628` running/healthy. Aucun droit réel attribué. Caméra physique, connexion WhatsApp, worker et impression officielle gardent leurs conditions distinctes. Ne pas annoncer toute la mise en service Scan terminée.
