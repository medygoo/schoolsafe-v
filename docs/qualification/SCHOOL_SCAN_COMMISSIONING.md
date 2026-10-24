# Mise en service du Scan — état et décisions nécessaires

## Administration native des accès livrée — mise en service humaine encore distincte

Après GOétape3 : **recette/VPS44e246f, runtime5cf4c9c, image1b3843de/digestc1ed8014 déployés**. Cinq runtime ciblés seulement ;22guardsrelease et préflight schéma/privilèges service read-only PASS. Sauvegarde PG récente privée0600 de685809octets validée, jamais restaurée. Bascule8,68s,83sessions et cache préservés, configuration/environnement/HMAC et cinq voisins inchangés, ancien conteneur arrêté/conservé. Pas de migration/GRANT/compte/droit réel modifié.

Contrôle indépendant PASS :71sources+3dashboard, checkoutproprebranchedeployed/school-native-access-20261024 etimage saine ;HostConfig/env/config identiques ;0session nonexpirée perdue ;9entrées cache intactes ;HTTPS login/récupération200, scanner/famille anonymes401 ;aucun worker dispatch/recover. Dernier rapatriement SSHschema-proof expiré après ce PASS : statut live recontrôlé PASS et seul fichier manquant récupéré, pas de redéploiement. [Rapport complet et preuves](SCHOOL_NATIVE_ACCESS_20261024.md), [recette](../../ops/releases/school-native-access-20261024/README.md).

Qualification finale conservée :387contrats+37recovery/3suites exactes/58HTTPScan+49HTTPaccès-partagés/55PG/21Chrome fragment PASS. **L'initialisation centrale des responsabilités et l'attribution scolaire native ont désormais du code livré et des preuves synthétiques de sauvegarde/révocation, pas seulement des liens GET.** ProDéli reste sans accès scolaire/Scan. Choisir explicitement école/administrateur/opérateurs/droits/points ; caméra physique, WhatsApp connecté/worker et impression officielle restent non qualifiés. Ne pas annoncer tout Scan opérationnel ni choisir des personnes pour tester la production.

## Livraison antérieure — navigation des profils, historique

Livraison ciblée effectuée : recette/VPS `e17357bbcf59068d8276c260314d3ffa8a1733a2`, runtime source `127d6cdfcebf6cd284ea5a0a663f20664a9baabf`, image `sha256:7bedfc1e37289136d9a54ac87f82603784053a47bd45c90c1b1d3c159dc7c628`, digestebebf34ea4ecea50b51457c3d083b349d477413ee755a4b8bb1f9d036ea5e14d. Delta4runtime (3modifications+1helper), **aucune migration ni attribution réelle**. Bascule8,8s ;144sessions et cache Chromium préservés, environnement complet/HMAC durable et configuration inchangés, cinq services voisins inchangés, ancien conteneur `schoolsafe-rosario-pre-school-scan-profile-owner-20261024` arrêté/conservé. Backup PG privé685797octets0600, archive validée, SHA256 `2cc3c559627556c4a7a026ad27cef982f3aa80e9f3330bcbd3d645470ede3055`, jamais restauré.

Contrôle indépendant PASS :66hashes Scan+3dashboard protégés, checkout propre, aucune session non expirée manquante,9entrées cache Chromium, configuration/runtime/HMAC identiques, routes HTTPS publiques200 et scanner/famille anonymes401, hash JSfamille public conforme. Worker détenu inactif, aucun message/scan réel, aucune rotation/lecture du fichier d'escrow. Le premier compteur indépendant de cache valait0 à cause du préfixe tar `./` ; contrôle normalisé rejoué,9entrées vérifiées (pas cache supprimé/recréé). Preuves privées `school-scan-20261023/delegation-exact-image-r2/production-{deployment,backup,schema}-proof.json` et `production-independent-verification-proof.json` ; recette versionnée [ici](../../ops/releases/school-scan-profile-owner-20261024/README.md).

**Ce qui est livré : consultation bornée des droits des profils scolaires et orientation explicite vers leur gestionnaire existant. Ce qui n'est PAS livré par ce correctif : attribution individuelle depuis ce formulaire, enregistrement de droits dans le hub qualifié de bout en bout, initialisation ProDéli des premiers droits, choix des comptes/points réels, caméra physique, connexion WhatsApp, worker opérationnel ou impression officielle.** Le logiciel est en ligne, pas une mise en service complète. La section ci-dessous conserve le contrôle de reprise antérieur sur d4359b7.

## Contrôle de reprise

Agent pi, seul rédacteur ; branche `feat/presence-scan-qr-20261023`, départ `77ce060ef7f0472bc96eb724ab3399b3390c5ecc` (distant relu identique). Lot documentaire uniquement. Brouillons dashboard/palmarès/DECISIONS conservés hors lot.

Contrôle SSH de lecture seule effectué : checkout `/opt/app-src` propre, recette `ec59446f4eeb5738256a72ea506cbfee652443f1`, conteneur `schoolsafe-rosario` running/healthy, image `sha256:d4359b7b3b174f70ea02aef6202c98e41381814960dedcf2df576994dac10b9d`. Aucun compte, droit, passage, réglage ou fournisseur modifié. Ce contrôle ne requalifie pas les parcours authentifiés et ne mesure pas la disponibilité caméra/WhatsApp.

## Blocage précis des habilitations

Le constat de zéro attribution SchoolSafe est celui du contrôle postdéploiement précédent ; il n'a pas été relu en base pendant cette reprise. Le code explique le chemin d'administration :

- `modules/Users/Profiles.php` et `modules/Users/Exceptions.php` exigent `SchoolSafeGlobalAdmin()` dès leur entrée. Ce sont les éditeurs centraux, pas une console scolaire accessible automatiquement au directeur.
- `modules/SchoolSafe/RightsCatalog.php::SchoolSafeRightsEditorMenu()` publie les clés SchoolSafe dans ces éditeurs sans activation ni attribution automatique.
- `modules/SchoolSafe/Permissions.php::SchoolSafeNativePermission()` lit `profile_exceptions` pour un profil nommé (y compris ID0). `staff_exceptions` est lu seulement en l'absence de profil nommé. Une exception individuelle ajoutée à un compte ayant un profil nommé ne serait donc pas un moyen valide de lui donner accès.
- Une modification de profil partagé touche ses autres titulaires. Identifier le profil et ses utilisateurs avant toute attribution ; ne pas modifier un profil global partagé pour débloquer une seule école.
- Les actions `management/grants` et `management/delegate` existent dans Catalog/Gateway et le formulaire `schoolsafe-card-bridge/family-management.js`. **Elles ne sont pas traitées dans la branche sans bridge de `scanner-relay.php`** : elles aboutissent à `CONFIG_REQUIRED` (réponse générique503), une fois les contrôles d'accès franchis. La voie bridge est explicitement réservée au laboratoire local. Leur présence n'est pas une délégation native de production opérationnelle.

Conséquence : ne pas annoncer que le directeur peut déjà tout attribuer depuis Gestion des familles. L'initialisation centrale reste à vérifier de bout en bout : la frontière `SchoolSafeCentralRequestAllowed()` refuse les modules Profiles/Exceptions à une session ProDéli et n'autorise pas directement `ecole-profils.php`. Leur garde GlobalAdmin locale ne prouve donc pas que ces pages sont atteignables. Ne pas contourner cette frontière ni promettre un chemin central opérationnel sur la seule lecture de ces éditeurs ; elle ne répare pas le raccordement des deux actions de délégation. Leur correction devra réutiliser les propriétaires de droits existants, protéger les profils partagés et être qualifiée séparément avant livraison. Aucun octroi SQL de contournement, compte personnalisé forcé ou moteur d'autorisations parallèle.

## Qualification avant livraison du correctif de navigation native (historique)

Reprise après GO, agent pi seul rédacteur, base `a9f0f97e0c4935812c56f96bb8579023d734856f`. Le propriétaire existant `ecole-profils.php` gère déjà les profils propres à l'école via `user_profiles.school_id` (migration034), les flags natifs et le plafond des droits de l'acteur. Le nouveau raccordement **n'ajoute pas un écrivain de délégations** : `grants` lit uniquement les flags des profils de l'école ; `delegate` prépare le lien `/ecoles/{école serveur}/profils`. Les deux gardes natives restent indépendantes et CSRF est conservé. Les anciens payloads bridge/UUID sont rejetés. Le client retire les champs d'attribution inopérants en mode natif, explique l'impact sur les détenteurs du profil et n'annonce jamais une permission modifiée. Le bridge laboratoire reste inchangé. Le lecteur n'énumère ni profils globaux, ni profils parents, ni exceptions individuelles ; un résultat vide est décrit précisément, pas comme une absence de toute habilitation.

Preuves locales : RED pur fonction manquante puis19contrats PASS ;41contrôles Chrome réels markup/JS à transport entièrement intercepté et42régressions formulaires PASS. PostgreSQL17.11 jetable :257contrôles PASS dont6nouveaux (scope profils/flags, lien sans mutation, droits séparés, ancien payload refusé, toutes lignes de droits inchangées) et251régressions. Ressources possédées nettoyées, aucun compte/envoi/droit réel. Preuve privée `school-scan-20261023/delegation-owner-pg-r1/result.json`. Cela ne qualifie pas l'enregistrement dans le hub, un bénéficiaire individuel ou une initialisation ProDéli. Qualification suivante PASS sur image exacte `sha256:7bedfc1e37289136d9a54ac87f82603784053a47bd45c90c1b1d3c159dc7c628`, source127d6cd, digestebebf34ea4ecea50b51457c3d083b349d477413ee755a4b8bb1f9d036ea5e14d :372contrats+37PG recovery et58HTTP authentifiés fictifs dont8nouveaux, lien vers gestionnaire existant réellement GET200 en session scolaire, CSRF/retraitedit/refus ancienpayload/droitsinchangés. Native/recovery/concurrency navigateur exacts PASS avec tests Git127d6cd (pas brouillons). Verify restitution exacte fixture/production inchangée PASS puis nettoyage nonce37aeee0a0018 PASS. Première préparation r1 arrêtée avant création containers par ancien garde attendant production877 au lieu de d435 ; garde corrigé strictement, pas affaibli. Les3suites navigateur ont réussi mais le runner historique a ensuite renvoyé1 lors du second nettoyage, car Docker dit désormais « no such object » en minuscules ; absence effective du navigateur possédé vérifiée séparément, erreur de housekeeping conservée. Recette quatre runtime sans migration préparée ;13contrats offline PASS. Cette qualification précédait la bascule désormais effectuée en tête du document. Les quatre runtime du correctif sont helper, relay, formulaire PHP et JS ; dashboard/palmarès/notation hors lot.

## Choix humain ciblé avant toute attribution réelle

Obtenir seulement les références non secrètes :

1. École concernée et compte scolaire qui doit assurer la configuration.
2. Comptes opérateurs et tâches explicitement autorisées pour chacun : arrivée, remise, contrôle des frais, cantine, vérification. Aucune tâche n'entraîne les autres.
3. Nom réel de chaque point de passage, sens entrée/sortie/les deux et état actif. Ne pas créer un portail fictif pour terminer la recette.

Ne demander ni mot de passe, ni cookie, ni QR d'enfant. Vérifier ensuite profil actuel, portée scolaire et impact sur les autres titulaires avant de soumettre une modification native confirmée. L'autorisation générale de poursuivre le développement ne choisit pas ces bénéficiaires.

## Droits à considérer selon la responsabilité choisie

Cette liste décrit les prérequis du code, PAS une attribution recommandée en bloc :

| Responsabilité | Clés et gardes existantes |
| --- | --- |
| Configurer les points | ScanLocationsRead/ScanLocationsManage ; profil admin ; édition native School_Setup/Configuration.php |
| Arrivée | ScanArrival ; point actif, bonne école/année/sens ; contrôle financier courant quand activé |
| Remise | ScanPickup ; point adapté, personne/photo autorisées, preuve et confirmation humaine |
| Contrôler les frais | ScanFees ; détail monétaire séparé par Student_Billing/StudentFees.php |
| Modèles financiers | FeeModelsRead/FeeModelsManage ; profil admin et édition Student_Billing/StudentFees.php ; save n'active pas |
| Cantine | ScanCanteen ; module Food_Service ; détail séparé Food_Service/Accounts.php ; lecture seule |
| Vérification | ScanVerify ; aucun droit d'arrivée/remise induit |
| Préparer/émettre un QR | Studio/IssueCard ; identité et inscription natives ; impression officielle distincte |
| Gestion familiale | FamilyRead/Register/Approve/Replace selon tâches ; parent principal explicite, consentement/photo, aucun compte créé implicitement |

Les clés abrégées ci-dessus portent toutes le préfixe `SchoolSafe/` et le suffixe `.php`. Pour un administrateur, une commande mutante exige CAN_USE et CAN_EDIT ; suspension/lecture seule reste prioritaire. Les gardes métier et la portée scolaire subsistent même après attribution.

## Validations matérielles et fournisseur

- Téléphone : connexion personnelle dans l'école autorisée, HTTPS `https://app.shoolsafe.tech/scanner-native.php`, ouverture puis fermeture de la caméra **sans QR dans le champ**. Relever téléphone/navigateur et résultat de prévisualisation. Ne pas soumettre un scan réel pour tester l'affichage. BarcodeDetector est la dépendance actuelle ; les tests de médias interceptés ne garantissent pas son support sur chaque téléphone.
- WhatsApp : le propriétaire habilité connecte son expéditeur réel dans le Hub ProDéli et effectue la vérification de session/identité prévue. Aucun secret dans le compte rendu, aucun message externe d'essai implicite. Le worker reste arrêté/non planifié tant que connexion, consentements, autorité et activation contrôlée ne sont pas prouvés.
- Aucun claim incertain n'est relâché ; aucun renvoi, fallback ou délai automatique pour une acceptation inconnue.
- Impression officielle : contrôler ses prérequis Control et données manquantes séparément ; un QR natif émis ne prouve pas une carte imprimée qualifiée.

## Niveau de preuve et suite

Reprise : lecture du code, égalité Git distante et métadonnées de santé production uniquement ; `git diff --check` sans erreur (avertissements de fins de ligne sur brouillons préexistants). Aucun nouveau test produit, navigateur connecté ou PostgreSQL exécuté pour ce document. Les qualifications exactes antérieures restent détaillées dans `SCHOOL_PRESENCE_SCAN_QR_20261023.md`.

Statut maintenu : **logiciel déployé, mise en service incomplète**. Prochaine action humaine : désigner école/compte configurateur/tâches et points réels ; prochaine tâche technique : qualifier le raccordement natif manquant de consultation/attribution des délégations sans élargissement de droits. Top10/classes actives/notation restent des lots séparés ; ne pas les embarquer dans ce correctif.
