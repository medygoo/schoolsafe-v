# SchoolSafe — premier lot graphique après audit, 22 octobre 2026

## Mandat et statut

GO du propriétaire pour corriger la récupération, les formulaires de configuration, les en-têtes et l’encombrement mobile central/wizard. **Implémenté et testé localement ; NON DÉPLOYÉ.** Base `7893bc5d3a5eab7b778170620e2fcca935b23811`, branche `work/prodeli-completion-20261021`. Aucun accès au navigateur propriétaire ou au VPS pour ce lot. Le déplacement de la base de notation, déjà publié dans la base de travail, reste lui aussi non déployé et distinct de ces corrections.

## Changements bornés

Huit fichiers runtime :

- `compte-recuperation.php` et nouvelle `assets/css/schoolsafe-recovery.css` : logo existant, marque, retour connexion, titres, cartes courriel/SMS, champs et boutons de 46 px avec texte 16 px, focus visible, une colonne mobile. Aucun script global ajouté.
- `assets/css/schoolsafe-native-workspace.css` : champs de 44 px minimum, zones natives cliquer-pour-modifier agrandies, navigation et espacement mobile. Contrôleur de configuration et préférence de langue inchangés ; aucune traduction native remplacée ou langue forcée.
- `plugins/SchoolSafe_Keycloak/central-ui.css` : emblème ramené au format du workspace, liens de session tactiles, résumés et préambules mobiles resserrés, progression à six étapes conservée.
- `assets/css/schoolsafe-photo-dashboard.css` : mot SchoolSafe maintenu sur mobile global ; retour à ProDéli sur une seconde ligne, compte et menu visibles, contenu sous le nouvel en-tête. Pas de refonte des données ni des vues du portail.
- `ProgramFunctions/SchoolSafeWorkspace.fnc.php`, `SchoolSafePhotoDashboard.fnc.php`, `plugins/SchoolSafe_Keycloak/Wizard.php` : seules les clés de cache CSS changent (respectivement v2, presentation-20261022 et v17). JavaScript et handlers identiques.

Les formulaires de récupération, leurs attributs et leurs branches PHP sont comparés à la base Git. Le traitement avant le HTML est identique : CSRF, OTP, délais, limites, réponses génériques et destinations natives conservés. Aucun fournisseur, droit, paramètre réel ou schéma modifié. Les fondations FlatSIS sont conservées.

## Vérifications exécutées

Recette locale existante, PostgreSQL fictif réel, réseau Docker interne et contrôle du label de qualification avant usage ; navigateur Playwright bloquant les destinations externes. Fichiers candidats copiés seulement dans le conteneur de recette existant. **Pas une image Git immuable qualifiée ni une validation de production.**

| Contrôle | Résultat |
|---|---|
| Nouveau test navigateur avant correction | RED : marque absente en récupération ; nettoyage des comptes fictifs exécuté |
| Suite workspace enrichie | 16 groupes PASS, nettoyage des comptes fictifs confirmé |
| Présentation réelle locale | récupération, central, configuration, portail global scolaire, wizard : 1440×844 et 390×844, captures relues |
| Géométrie | pas de débordement horizontal ; titre du répertoire central et premier champ wizard dans le premier viewport mobile ; marque/menu/compte/retour dans l’en-tête sans chevauchement du contenu |
| Récupération CSRF invalide | message d’erreur visible, aucun passage au code OTP ni envoi |
| Contrats de préservation du lot | 14 PASS (traitements/formulaires, cache CSS seul, sources sensibles inchangées, liste de fichiers autorisée) |
| Contrats PHP avec substituts | 83 PASS : workspace 33, dashboard 12, présentation comptes 16, notation 22 |
| Contrats récupération/SMS PostgreSQL fictif | 37 PASS ; schéma temporaire nettoyé, transports substitués, aucun envoi réel |
| Navigation JS centrale | 7 PASS |
| Syntaxe | quatre PHP modifiés valides, helpers JS valides ; `git diff --check` PASS |

Le premier passage visuel a révélé que la règle d’en-tête mobile était dominée par une règle CSS plus spécifique : marque visible mais compte rogné. Correction de cette spécificité, ajout de contrôles des rectangles du menu/marque/compte/retour et de leur relation au contenu ; second passage 16 groupes PASS, capture mobile relue. L’ancien test de cache attendait CSS et JS au même numéro ; remplacé par trois assertions exactes séparant les nouvelles versions CSS des JS inchangés, puis 33/33 PASS. Les échecs initiaux sont conservés dans les preuves privées.

## Limites et suite

- Ce lot n’accomplit **pas** l’unification intégrale de tous les écrans ou la suspension des autres sélecteurs de thème.
- Login public, premier accès et fiche confidentielle après étape 6 ne sont pas redessinés ici. Aucune fiche réelle ouverte. Leur harmonisation doit conserver CSP/isolement/no-global-scripts et être validée avec des fixtures fictives.
- PDF/impression, tablette, contraste WCAG exhaustif, performance et réception réelle ne sont pas requalifiés. L’identité graphique du portail scolaire complet n’est pas refondue.
- Les étiquettes et choix de langue natifs sont conservés ; changer le dessin n’autorise ni changement de langue de comptes réels ni modification de paramètres métier.
- **Aucun déploiement autorisé par ce GO.** Une éventuelle livraison nécessite son périmètre explicite, la qualification d’image exacte et les protections de production existantes. Ne pas livrer implicitement le lot notation contenu dans l’historique de cette branche.

Preuves privées : `C:/Users/PC/SchoolSafeProductionUpdate-20261021-evidence/design-correction-20261022/` (`red`, `green-r1`, `green-r2`, `php-contracts-r1.json`, `php-contracts.json`). Aucun secret de compte réel ni fiche de remise dans les captures. Les captures et résultats d’audit de production antérieurs sont des références, pas une validation de ce candidat.
