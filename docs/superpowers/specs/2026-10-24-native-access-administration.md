# Administration native des accès — règle produit validée

## Validation et portée

Le propriétaire valide la séparation suivante : ProDéli administre les responsabilités d'accès de l'établissement ; l'administrateur scolaire attribue les accès dans son école. Il précise qu'une personne peut recevoir tous les accès concernés et que plusieurs personnes peuvent recevoir le même ensemble complet : **aucun quota de bénéficiaires, aucune exclusivité par tâche**. Cette validation concerne le fonctionnement à réaliser, pas le choix de comptes réels ni une preuve de disponibilité actuelle.

« Tous » signifie tous les accès scolaires délégables dans l'enveloppe autorisée, y compris l'ensemble des cinq commandes Scan, et non des privilèges de plateforme ou l'accès aux autres écoles. Ne pas imposer un retrait artificiel d'un droit uniquement pour rendre le bénéficiaire strictement inférieur lorsque la délégation complète scolaire est autorisée. La désignation du directeur principal reste distincte des autorisations métier : plusieurs opérateurs complets ne deviennent pas automatiquement directeurs principaux.

## Parcours attendu

1. ProDéli choisit explicitement l'établissement et son responsable existant, puis confirme la responsabilité de gestion des accès. Cette administration de métadonnées ne transforme pas le compte ProDéli en membre de l'école et ne l'autorise pas à scanner ou consulter les dossiers scolaires.
2. Le responsable scolaire choisit un compte existant de son périmètre, puis un ensemble de droits : tous les droits délégables ou une sélection. Aucun compte forcé, aucune nouvelle identité automatique et aucun compte partagé.
3. Il peut répéter cette attribution pour autant de personnes qu'il le souhaite. Une tâche déjà attribuée n'est ni indisponible ni transférée automatiquement. Une pagination ou une limite technique par requête ne constitue pas un quota métier.
4. Chaque modification présente la cible, l'école et les droits avant/après, puis exige confirmation et jeton CSRF. Un profil partagé nécessite de montrer toutes les personnes affectées ; ne jamais annoncer une modification individuelle si elle modifie les autres titulaires.
5. L'attribution et son audit sont atomiques, les autorisations courantes sont recontrôlées avant écriture, et une révocation s'applique à la requête suivante, même depuis une session déjà ouverte.

## Réemploi obligatoire

- Comptes `staff`, profils `user_profiles`, autorisations `profile_exceptions`/`staff_exceptions` selon le résolveur natif, périmètres `SCHOOLS` et `user_profiles.school_id`.
- Contrôleurs existants de gestion des comptes et profils, catalogue et permissions SchoolSafe, `CAN_USE`/`CAN_EDIT`, états de comptes/écoles et mécanismes d'audit natifs.
- Un profil nommé ne reçoit pas une exception individuelle qui serait ignorée par le résolveur. Le profil 0 est un profil nommé dans le résolveur SchoolSafe : ne pas le traiter implicitement comme une absence de profil.
- Pas de second moteur IAM, pas de nouvelle table de délégations Scan, pas de bridge laboratoire, pas de modification SQL de droits de comptes réels pour contourner une interface manquante.
- Aucune modification d'un profil global partagé pour débloquer une seule école. Les droits de plateforme restent réservés ; les protections de périmètre ne sont pas supprimées pour autoriser un ensemble scolaire complet.
- Les cinq permissions Scan restent indépendantes. « Tous les accès Scan » ne donne pas implicitement accès aux montants détaillés, aux paiements, à la comptabilité, à la configuration financière ou à la gestion des droits si ces permissions distinctes n'ont pas été sélectionnées et autorisées.

## Écarts vérifiés dans le code, non encore corrigés par cette validation

- `SchoolSafeCentralRequestAllowed()` bloque les éditeurs Profiles/Exceptions et le hub scolaire `ecole-profils.php` aux comptes ProDéli. Le correctif futur doit passer par l'administration centrale de métadonnées autorisée, pas ouvrir l'espace client.
- Le hub `ecole-profils.php` refuse un jeu identique à celui de l'acteur via `SchoolSafeHubRightsNarrower()` ; `SchoolSafeProfileDelegable()` impose une inclusion strictement inférieure pour la famille admin. Il faut qualifier le cas de l'ensemble scolaire complet sans assouplir la délégation de profils globaux/étrangers ou de droits réservés.
- Le hub écrit actuellement plusieurs lignes sans transaction explicite ; son audit dépend de la disponibilité de `sskcAudit()`. Son chemin d'enregistrement ne doit pas être annoncé qualifié sur la seule preuve GET du lot livré.
- Le raccordement Scan livré ne fait que lire les flags des profils propres à l'école et orienter vers le gestionnaire existant. Il n'est pas une attribution individuelle persistante.

## Critères de qualification du raccordement

- Une école synthétique, un responsable et plusieurs comptes distincts : donner le même ensemble complet à au moins trois personnes, relire leurs droits et exécuter les commandes autorisées ; ajouter un autre bénéficiaire sans refus de quota ni perte de droits chez les précédents.
- Donner une sélection à une autre personne ; vérifier les refus des commandes non choisies et l'indépendance des montants détaillés.
- Retirer un droit à une seule cible sans changer les autres ; si le profil est partagé, confirmation d'impact ou chemin natif adapté avant mutation.
- Refuser école étrangère, compte global, profil global partagé, droit hors enveloppe, auto-élévation, CSRF invalide, compte readonly/suspendu et schéma/autorité indisponibles.
- Injecter une panne d'écriture/audit : aucun demi-profil ni attribution partielle. Tester les modifications concurrentes et la révocation dans une session ouverte.
- Vérifier que ProDéli peut administrer les responsabilités autorisées mais reste refusé sur scanner, portail et données scolaires, avant et après attribution.
- PostgreSQL synthétique et HTTP authentifié, puis image Git exacte et livraison ciblée : les preuves historiques du lot de navigation ne remplacent pas ces nouveaux parcours.

## État à cette validation

Décision produit et analyse des propriétaires enregistrées uniquement. Aucun code runtime, droit réel, compte, fournisseur ou déploiement modifié par ce document. Production connue : recette `e17357b`, runtime `127d6cd`, image `7bedfc1e…`. La mise en service reste incomplète ; caméra physique, connexion WhatsApp, worker et impression officielle conservent leurs conditions distinctes.
