# SchoolSafe — accès et présentation unique, 22 octobre 2026

## Mandat / statut

Deuxième lot graphique autorisé après `c4c75f7` : connexion, premier accès, fiche confidentielle et suspension des autres thèmes. Candidat local montré au propriétaire dans `Temp/schoolsafe-apercu-local.html`, puis validation et GO explicite de production. **Qualification locale terminée ; livraison de production autorisée mais non exécutée à la rédaction de cette section.** Le déplacement de la base de notation est exclu de cette livraison ; créer une source de release à partir du workspace déployé `8fc3546`, sans embarquer `7893bc5` implicitement.

## Implémentation bornée

Neuf runtime : `functions/User.fnc.php`, `functions/Config.fnc.php`, `ProgramFunctions/Theme.fnc.php`, `ProgramFunctions/FirstLogin.fnc.php`, `modules/Users/Preferences.php`, `modules/School_Setup/Configuration.php`, `modules/SchoolSafe/login-blue.inc.php`, `plugins/SchoolSafe_Keycloak/manage.php`, nouvelle `assets/css/schoolsafe-access.css`.

- Connexion : une CSS SchoolSafe commune remplace le chargement du bouquet de styles du prototype. Les fichiers restent sur disque ; le formulaire entier, les hooks, destinations, longueurs natives et script de navigation/œil sont identiques.
- Premier accès : cadre SchoolSafe et action explicite, champ plein format, navigation métier masquée sur cet écran seulement. Traitements, champs, règles et révocations non modifiés.
- Fiche : HTML autonome sans scripts ni imports CSS. CSP deny-by-default conservée ; seule addition `img-src 'self'` pour le logo. No-store, no-referrer, ciblage, acteur, expiration, consommation de session, audit et fichier téléchargeable identiques.
- Thème : `Preferences('THEME')` renvoie SchoolSafeCodex pour tous les rôles et l’anonyme. Les valeurs historiques restent en base. Les sélecteurs personnel/administrateur ne sont plus proposés ; les POST de thème sont ignorés au writer partagé des préférences et au contrôleur de configuration, variantes de casse et ancien CONFIG comprises. Les autres réglages restent modifiables.
- Aucun fichier FlatSIS/SchoolSafe/SchoolSafeCodex/WPadmin supprimé ; héritage interne, polices et CSS PDF conservés. Aucun changement de droits métier, fournisseur ou schéma.

## Résultats locaux

Recette Docker existante vérifiée par labels/réseau interne, PostgreSQL fictif, navigateur interdisant les destinations externes. Aucune session propriétaire ou donnée de production utilisée.

- Nouveau contrat RED : 9 PASS / 10 FAIL avant implémentation (après ajout d’un substitut UserSyear nécessaire au harnais).
- 25 contrats thème et writer PASS, compris rôles substitués, arguments invalides et valeurs historiques.
- 17 contrats de préservation PASS : traitements avant rendu et autres fonctions FirstLogin, formulaire connexion entier, handlers de compte, ciblage/acteur/expiration/consommation, document et assets sensibles inchangés.
- 124 contrats PHP au total PASS (25 thème, 33 workspace, 12 dashboard, 16 comptes, 16 intent natif, 22 notation dans la branche de travail seulement). 37 contrats PostgreSQL récupération/SMS PASS ; transports substitués, aucun envoi réel.
- `native-final` : 14 groupes PASS / cleanup true : création complète fictive, principal, renouvellement/expiration/rejeu, formulaire premier accès, refus de mot de passe faible et de contournement, révocations, reconnexions. Téléchargement réellement déclenché, nom et flux lus en mémoire, contenu comparé au secret fictif et URL officielle, fichier temporaire supprimé. Captures masquées et PDF dont DOM/URL de téléchargement sont expurgés avant génération.
- `workspace-r4` : 17 groupes PASS / cleanup true, dont formulaires forgés munis du token POST natif, absence de sélecteurs, configuration identique et ancien thème WPadmin conservé malgré les essais ; préférence DATE encore sauvegardable.
- Syntaxes PHP/JS et sept tests navigation centrale PASS. Captures bureau/mobile relues.

Échecs conservés : ancien wrapper hérité de 420 px comprimait le login desktop malgré absence d’overflow, corrigé et testé géométriquement ; breadcrumb/dock non pertinents avant accès masqués ; XPath du harnais renvoyait un dd vide malgré son contenu présent/visible, remplacé par contrôle DOM du voisin direct sans répéter une mutation. Premiers essais API des préférences omettaient le token POST natif, le header ne suffisant pas via le relais : harnais corrigé, protections inchangées.

## Limites / livraison

Qualification ci-dessus locale mutable, pas encore l’image exacte de production. Écran premier accès capturé en anglais selon locale de fixture, pas une preuve de défaut de langue production. Le PDF testé est la fiche de remise expurgée, pas tous les bulletins Rosario ni une certification d’imprimante physique. Pas d’audit WCAG exhaustif, pas de nouvel envoi externe, pas de fiche réelle ouverte. Ne pas assimiler cette validation à la conformité universelle de tous les modules.

Preuves privées : `C:/Users/PC/SchoolSafeProductionUpdate-20261021-evidence/access-theme-20261022/`, notamment `native-final`, `workspace-r4`, `contracts.json`. La livraison devra conserver sessions/cache/configuration/services et les anciens conteneurs arrêtés, avec sauvegarde, contrôles d’image exacte et vérification séparée ; aucun rollback automatique ni restauration de données autorisés.
