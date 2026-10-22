# Frontière ProDéli / écoles et accès initial — 22 octobre 2026

## Statut

**Correctif qualifié localement, pas déployé.** Branche `fix/prodeli-school-boundary-20261022`, issue de `e1d2974` (présentation effectivement livrée, sans le déplacement de la notation). L’image de production reste `sha256:a4a3cbf821083f60629031075c46dafdec8615fb91d591835408fa4afb260bf3` ; aucun accès VPS ni changement de production dans ce lot.

La demande du propriétaire remplace l’ancienne navigation globale vers une école : ProDéli prépare les établissements et leurs accès, mais ne constitue pas une appartenance à leur espace de travail. L’administrateur scolaire utilise son propre accès temporaire, choisit son mot de passe personnel, puis accède à son école.

## Constatations et corrections

- Les entrées `dashboard_school`, portail scolaire, configuration scolaire et fiches utilisateurs proposées au global sont retirées. Le changement natif de contexte via `Side.php` est également refusé au global, y compris lorsque ce fichier est inclus depuis un contrôleur central.
- `Warehouse.php` applique désormais une frontière serveur avant les contrôleurs scolaires : liste explicite des routes centrales conservées ; `/Modules.php` uniquement pour la préparation native contrôlée et les préférences propres à l’opérateur. Refus des autres modules, endpoints inconnus et loaders natifs `bottomfunc` print/help. Les autorisations scolaires existantes continuent à s’appliquer aux comptes scolaires ; `SchoolSafeSchoolAllowed()` reste l’autorité de gestion des métadonnées/provisionnement, pas une entrée dans l’espace client.
- Les liens de langue du pied de page et de l’en-tête pouvaient réellement cibler `Users/User.php` à cause du contexte interne de rendu. Ils conservent maintenant la route centrale, sans recopier de jeton, secret ou paramètre arbitraire. Ce constat explique les premières assertions navigateur rouges ; il ne s’agissait pas simplement d’un bouton de fiche mal identifié.
- La console historique ne crée/réinitialise plus de mots de passe globaux par ses anciens POST : renvoi vers le parcours natif de remise confidentielle. Les opérations de gestion d’établissements et d’état de compte existantes ne sont pas supprimées ; le ciblage global de la console est limité aux administrateurs/comptes désactivés.
- Un compte natif dont le premier accès n’est pas terminé affiche **À activer**, même en présence de `LAST_LOGIN`. Le statut **Actif** du nouveau parcours ne découle qu’ensuite de la réussite du mot de passe personnel ; suspensions et restrictions restent prioritaires. Un temporaire expiré demande son renouvellement.
- Pour un ancien compte **administrateur, une seule école, jamais connecté, sans identité gérée ni dossier de premier accès**, le gestionnaire peut explicitement préparer un accès initial. Confirmation, CSRF, nonce, droits/cible/année, remise unique et expiration restent ceux du parcours natif. Insertion du suivi, nouveau hash et audit sont atomiques. Aucune inscription ou réinitialisation automatique : comptes déjà connectés sans suivi, comptes au premier accès terminé, équipe centrale, multi-écoles et identités gérées sont exclus.
- Le texte des comptes déjà activés ne propose plus un bouton temporaire inexistant : récupération personnelle en cas d’oubli. Keycloak reste en pause ; le bouton de liaison gérée n’est plus présenté comme une méthode de génération du temporaire natif.

La panne rapportée sur un compte réel n’a pas été reproduite avec ce compte : aucun mot de passe/secret réel n’a été demandé ou consulté. Les cas de création, renouvellement, expiration et ancien compte sans suivi ont été exercés sur des données fictives.

## Périmètre runtime — dix fichiers

Sous `app/modules/codex-ui-v1/extensions-test-runtime/` :

1. `ProgramFunctions/SchoolSafeSchoolScope.fnc.php`
2. `Warehouse.php`
3. `Side.php`
4. `ProgramFunctions/SchoolSafeWorkspace.fnc.php`
5. `Bottom.php`
6. `plugins/SchoolSafe_Keycloak/Wizard.php`
7. `plugins/SchoolSafe_Keycloak/manage.php`
8. `plugins/SchoolSafe_Keycloak/Management.php`
9. `ProgramFunctions/SchoolSafeNativeOnboarding.fnc.php`
10. `admin-console.php`

Aucune modification des moteurs de récupération/SMS, du formulaire/traitement du mot de passe personnel, des migrations, des fournisseurs, des thèmes ou du lot de notation. Les nouvelles possibilités d’accès initial restent des opérations explicites sur la cible choisie, et non une migration des comptes existants.

## Vérifications locales

| Contrôle | Résultat |
| --- | --- |
| Frontière centrale / éligibilité / état d’activation | 42 contrats PHP PASS |
| Présentation des comptes | 16 contrats PASS |
| Intentions natives et remise | 16 contrats PASS |
| Tableau de bord, logique de présentation | 12 contrats PASS |
| Communications existantes | 28 contrats PASS |
| **Total contrats PHP avec substituts** | **114 PASS** |
| PostgreSQL réel, création/conflits/premier accès/ancien compte et rollbacks | **57 contrôles PASS**, schéma isolé supprimé |
| PostgreSQL récupération/SMS, concurrence OTP et routage | **37 contrôles PASS**, transport substitué, aucun envoi réel |
| Navigateur + HTTP réels, comptes et écoles fictifs | **20 groupes PASS**, nettoyage confirmé |
| Syntaxe des PHP modifiés et du harnais Node, `git diff --check` | PASS |

Les 20 groupes incluent : routes et POST forgés refusés au global, absence de liens scolaires, non-mutation via les anciens POST de console, préférences personnelles et SMS/WhatsApp centraux toujours servis, wizard complet, temporaire expiré puis renouvelé, replay refusé, connexion temporaire encore **À activer**, choix personnel et reconnexion longue, ancien compte sans suivi préparé puis activé, absence de nouveau temporaire pour les comptes terminés. Les captures/fiche imprimée sont expurgées ; les secrets fictifs téléchargés restent en mémoire puis sont supprimés.

Le nouveau contrôle PostgreSQL provoque un échec d’audit pendant la première préparation d’un ancien compte : aucun secret retourné, aucun dossier de suivi conservé, hash original intact. Le contrôle confirme aussi les refus de conversion d’une identité gérée, d’un compte central ou d’un ancien compte déjà utilisé. La concurrence de création et les refus de renouvellement après activation restent couverts.

## Incidents du harnais conservés

- Assertions initiales rouges sur les liens de langue : corrigées dans le produit après identification de la vraie destination scolaire.
- Une assertion supposait `LAST_LOGIN` renseigné dès la connexion temporaire ; ce n’est pas le comportement natif observé. Le test injecte explicitement une connexion enregistrée sur son seul compte fictif pour vérifier que ce champ ne peut pas simuler l’activation.
- Les suites qui lancent leurs propres workers doivent être exécutées comme fichiers PHP, pas sur stdin (`__FILE__`). Le contrat communication a été rejoué ainsi avec ses 28 succès.
- L’image locale ne contient pas nécessairement les SQL d’installation : les deux migrations canoniques inchangées sont fournies hors du web root aux tests PostgreSQL, via `PRODELI_NATIVE_MIGRATION_ROOT`. Elles ne sont appliquées que dans le schéma isolé du test, jamais sur les tables publiques ni en production.

Preuves privées : `school-boundary-20261022/`, sous la racine de qualification privée habituelle. Parcours final : `local-final-r2/native-result.json`. Logs des sept suites PHP/PG voisins ; échecs antérieurs non présentés comme PASS.

## Limites et prochaine étape

Cette qualification utilise le conteneur local historique **patché** `schoolsafe-prodeli-presentation-qa`, étiqueté `schoolcef.qualification=harmonization-20261021`. Ce n’est pas une qualification de son image immuable d’origine, ni une livraison de production.

Les anciens tests de navigation qui autorisent volontairement le global à entrer dans une école expriment l’ancienne politique : ils ne sont pas des critères valides de la nouvelle frontière. Avant toute livraison, préparer une recette/image dédiée et ses critères actualisés (notamment ne pas reprendre telle quelle la qualification workspace de l’ancienne release), qualifier les octets Git canoniques, vérifier le périmètre et les contrôles de préservation, puis sauvegarder et vérifier séparément la production après une bascule autorisée. Ne pas fusionner main/production ni embarquer la notation.

La preuve porte sur les routes natives contrôlées par `Warehouse.php` et les parcours cités, pas sur une certification de tous les éventuels services/API extérieurs à ce bootstrap. Aucune réception réelle, activation humaine ou conformité visuelle universelle n’est déduite des tests fictifs. Les vrais comptes existants ne sont pas réparés automatiquement.
