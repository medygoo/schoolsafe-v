# ProDéli — répertoire des écoles et navigation des tableaux de bord

## Mandat et périmètre

Le propriétaire demande de finir cette partie des tableaux de bord. Périmètre annoncé : tableau de bord central ProDéli, ses commandes et passage vers l'espace scolaire natif, pas une refonte des quatre profils scolaires. SchoolSafe/logo original, pas de nouveau menu ou application ; communication conservée et Keycloak en pause.

Départ local `5d53e07a58fc6316c9dae6b0c8f55545c05401b8`. Base de production `90f6238cfee03f81af19a0c3ff4f902f9c134c3e`, image `3daf00a1…`. Branche publiée `work/prodeli-completion-20261021`.

**Ne clôture pas l'achèvement fonctionnel global de ProDéli :** création/rattachement d'administrateur, premier accès et récupération restent ouverts dans [PRODELI_COMPLETION.md](PRODELI_COMPLETION.md). Ces traitements ne sont ni corrigés ni annoncés opérationnels par ce lot de navigation. Le parcours de création existant dépend encore du fournisseur en pause.

## Défauts corrigés et réemploi

- Le tableau de bord tronquait la liste aux huit premiers établissements autorisés, sans moyen depuis leurs lignes d'ouvrir les autres écoles. Répertoire complet, filtrable par nom avec accents ignorés, compteur accessible, état vide et commandes clavier/mobile.
- Chaque école autorisée possède un bouton d'ouverture. **Aucun nouveau moteur de contexte** : POST vers le contrôleur central, validation de CSRF/acteur/identifiant/école/année, puis délégation au `Side.php` natif. Celui-ci conserve ses contrôles supplémentaires et sa mise à jour de `UserSchool`, `CURRENT_SCHOOL_ID`, période et sélection de personne.
- Seuls `sidefunc=update` et l'identifiant d'école validé sont transmis au contrôleur natif ; aucun paramètre client d'année, personnel, élève ou module n'est repris. Redirection 303 vers `misc/Portal.php` après vérification du contexte obtenu.
- Le formulaire ne poste pas directement vers la route historique de changement d'école : la garde native de CSRF est limitée aux requêtes `modfunc`, d'où le garde explicite de cette nouvelle commande.
- Retour au tableau de bord ProDéli depuis le portail scolaire, réservé au global avec droit de consultation des utilisateurs. Aucun lien central ajouté aux portails des administrateurs limités.
- Accès direct aux comptes centraux depuis le dashboard réservé au global. Communication et liste scolaire existantes conservées.
- Ancres de retour vers les sections centrales conservées lors de la normalisation de l'URL. Cache CSS/JS central v15.

## Fichiers applicatifs — cinq seulement

- `plugins/SchoolSafe_Keycloak/Wizard.php` : répertoire et garde de la commande ; corps de l'assistant de création et confirmations existantes identiques.
- `plugins/SchoolSafe_Keycloak/manage.php` : raccord de navigation contrôlé, autres handlers identiques.
- `plugins/SchoolSafe_Keycloak/central-ui.{js,css}` : recherche et présentation mobile/clavier.
- `modules/misc/Portal.php` : seul ajout d'un lien de retour pour le global.

14 composants protégés par `scripts/prodeli-dashboard-navigation-scope.py`, dont `Side.php`, création d'école, gestion des identités, compte, première connexion, récupération et transports/notifications. Aucun changement de fournisseur, droit attribué, règle d'envoi, schéma ou compte réel.

## Preuves locales

- `scripts/prodeli-dashboard-navigation-contracts.php` : **20 contrats**, 10 PASS/10 FAIL avant, 20 PASS après ; renderer réel, identité et base substituées. Dix écoles permises sur douze fictives, échappement, droits d'affichage, champs de POST et absence de menu ajouté.
- **Huit groupes navigateur HTTP/PHP/PostgreSQL fictif réels PASS** : répertoire de dix écoles temporaires au-delà de huit, recherche accentuée/vide/réinitialisée, accès comptes centraux et retour à 1280/390 ; clavier ; POST contrôlé vers le vrai changement d'école, portail et retour, sélection persistante ; mauvais/absent CSRF, école inconnue/malformée refusés sans changement de contexte ; acteur limité ne voyant que son école, pas les commandes centrales ; falsification inter-écoles/année/personnel refusée ; portail scolaire limité inchangé.
- Dix écoles fictives et deux comptes temporaires supprimés après tests ; données du modèle conservées. Aucun envoi externe, compte réel ou provisioning géré testé.
- Régressions réexécutées sur le candidat : cinq groupes dashboard, six groupes présentation des comptes, huit groupes réglages communication PASS. Tests de communication restaurent leurs seules données fictives modifiées.
- PHP (Wizard/manage/Portal) et JS syntaxe PASS ; garde de périmètre, compilation Python et diff check PASS ; capture mobile relue.

Niveaux de preuve distincts : le renderer substitué ne remplace pas les parcours SQL/HTTP ; le candidat QA est une copie source sur image cache, pas l'image release exacte. L'image exacte est qualifiée séparément par la recette.

### Incidents de qualification contenus

- Le harnais initial lisait `INSERT … RETURNING` avec les statuts psql et produisait des IDs NaN. Correction `-qAt`, dix écoles fictives de cet essai nettoyées explicitement par préfixe/IDs vérifiés ; aucun test de production.
- La variable `$school` du raccord était réutilisée par le fichier natif inclus, empêchant la confirmation finale. Renommée `$dashboardSchool` ; parcours natif/retour ensuite PASS.
- Essai navigateur JavaScript désactivé non concluant avec le shell natif actuel ; pas de compatibilité globale sans JavaScript ou offline annoncée. Aucune correction du shell hors lot.

## Livraison ciblée

**DÉPLOYÉ et contrôle indépendant PASS.** Source `3eb2e9e35984de9c40e22c571b212e727b13a2bb`, image `sha256:c8d10345fc7018098c87bcb56551fc41f253dbe4f1d578a96733382c55afe45b`, tag `schoolsafe-rosario:prodeli-navigation-3eb2e9e35984`. `/opt/app-src` propre sur `deployed/prodeli-navigation-20261021`. Les commits documentaires suivants ne sont pas la source de l'image.

Recette `ops/releases/prodeli-navigation-20261021/` : base immuable de la livraison Communication, allowlist cinq fichiers, comparaison complète contenu/mode/propriétaire : **2 753 autres fichiers identiques**, aucun ajout/suppression applicatif. **76 contrats PASS** (16 comptes + 12 dashboard + 28 communication + 20 navigation) et trois lints PHP PASS, réseau none et aucune DB durant les tests d'image. Sources avant livraison, état Docker et sessions sauvegardés en privé ; pas de pull global/main.

**163 fichiers de session préservés** avec contrôle contenu/mode/propriétaire. Environnement/mounts/ports conservés, autres services mêmes identifiants et démarrages. Procédure **14,61 s**, pas une mesure exacte d'interruption. Aucune migration ni manipulation de compte/école réelle ; aucun envoi externe.

Contrôle indépendant PASS : santé PHP, image/source propre, login public HTTPS 200, réglages SMS/WhatsApp refusés aux anonymes ; hashes des cinq sources servies, CSS public v15 et sauvegardes conformes. Empreintes privées de l'environnement, des lignes SMS, des sessions WhatsApp et des consentements identiques avant/après. Essai de changement d'école réel de production non effectué (pas de compte/école de test autorisé) ; le parcours est qualifié sur HTTP/PG fictifs, et la source/image servie est prouvée exacte.

Répertoire privé VPS `/opt/schoolsafe-releases/prodeli-navigation-20261021`, sources sous `source-before/`. Ancien conteneur conservé `schoolsafe-rosario-pre-prodeli-navigation-20261021`, image précédente `3daf00a1…`. Retour arrière préparé **non exercé** : `python3 /opt/schoolsafe-releases/prodeli-navigation-20261021/deploy.py --rollback` ; sessions courantes transférées et anciennes neutralisées, gardes de données nouvelles, pas de restore DB. Les anciens rollbacks restent intacts.

Preuves `prepare-proof.json`, `deployment-proof.json`, `independent-before.json`, `independent-proof.json`, `scope-proof.json`, `browser-result.json`, résultats de contrats et captures hors Git dans le répertoire local ci-dessous.

Preuves privées : `C:/Users/PC/SchoolSafeProductionUpdate-20261021-evidence/dashboard-navigation/`. QA 8096 et aperçu 8094 conservés, worktrees/WhatsApp/captures/rollbacks historiques intacts. Validation connectée humaine distincte de la livraison technique. CI sécurité RED et autres réserves historiques restent ouvertes.
