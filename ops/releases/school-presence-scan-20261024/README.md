# Présence & Scan QR — bascule logicielle contrôlée

Runtime source `6e996637d3397fbc174e8bde01685c1f05a0ac09`, image `sha256:d4359b7b3b174f70ea02aef6202c98e41381814960dedcf2df576994dac10b9d`, base protégée8770153. Le checkout Git fournit seulement la recette ; l’image qualifiée contient65fichiers allowlistés dont35ajouts,2734autres fichiers inchangés. Aucun draft dashboard/palmarès/grading dans le payload.

## Preuves et portée

-353contrats image+37recovery PostgreSQL,50HTTP natifs authentifiés, native/recovery/concurrency navigateur réels sur l’image exacte : PASS. Fixture restaurée puis seuls conteneurs/réseaux nonce possédés supprimés.
-16contrats purs de recette contrôlent les paramètres, la clé et l’enveloppe réelle des preuves HTTP (exit/stdout/stderr). Le premier prepare a arrêté avant toute sauvegarde/migration/bascule sur une confusion de forme de cette enveloppe ; correction stricte, pas un assouplissement des50contrôles.
-251tests PostgreSQL du moteur natif,4tests du véritable filtre SQL Rosario pour téléphone facultatif,4tests caméra interceptés et10Studio+60scanner régressions : PASS. Les preuves de photographie et confirmation pickup des fixtures sont synthétiques, pas des contrôles physiques.
-La bascule **logicielle** ne connecte pas WhatsApp, n’active aucun worker et ne prouve ni une caméra physique ni l’impression officielle. Ne jamais annoncer ces fonctions opérationnelles sans leurs preuves humaines. Aucun message externe ni scan d’enfant réel pour tester cette livraison.

## Avant la bascule

Créer `/opt/schoolsafe-releases/school-presence-scan-20261024` en0700. Depuis Git uniquement, embarquer deploy.py, les helpers historiques exacts recovery.py/preservation.py, le manifest et les preuves exact-image/HTTP/browser/cleanup. Le release.json lie commit de recette, source6e99663, image, digest,65hashes et hashes/absences des65chemins dans la base877. Aucun build depuis un checkout sale, aucun transfert de l’image complète.

`python3 deploy.py prepare` : vérifie Git et source/image/QA, runtime sain, configuration bind inchangée, absence de vieux dispatchers/processus/cron/unités déclarées, et sauvegarde PG privée0600 validée par pg_restore --list. **Jamais de restauration sur la production.** Le préflight actuel trouve0worker/0cron, MODULES.SchoolSafe désactivé et aucune clé HMAC native déclarée. Les clés ne sont jamais imprimées.

Pour le registre natif vide seulement, prepare provisionne une clé aléatoire256bits dans `/opt/schoolsafe-secrets/card-hmac.hex`, dossier0700/fichier0600. Une clé préexistante est conservée ; clé incorrecte/escrow divergent/registre déjà peuplé sans clé => arrêt, jamais rotation implicite. La clé doit être conservée dans chaque futur runtime ; aucun zéro de fixture, aucune clé dans l’image/Git/docs. Les variables existantes sont conservées byte pour byte ; seul cet ajout est permis et contrôlé.

`python3 deploy.py migrate` : exige sauvegarde fraîche et drain avant020–026/031/037/039–043. Additif, aucune réaffectation de données ou reprise de destinataires historiques, aucune libération de claims. Si une étape échoue, arrêter et inspecter : ne pas restaurer le dump ni enlever les expansions.

`python3 deploy.py deploy` : réutilise la préservation de configuration/ports/réseaux/ressources/services voisins/sessions/cache Chromium. Garde l’ancien conteneur arrêté. Vérifie santé et routes publiques/anonymes. Ensuite active seulement le bit global SchoolSafe, via CAS sur la valeur courante, sans modifier les autres modules, les overrides école ou les droits de profils. Un échec d’activation est explicitement MODULE_PENDING ; activate-module est une reprise ciblée, pas un rejeu de déploiement. Le statut final est SOFTWARE_DEPLOYED_PROVIDER_HELD, pas « tout opérationnel ».

## Après la bascule

`python3 deploy.py status`, comparaison des hashes source et paramètres conservés, état des cinq services voisins et des sessions. Les tâches restent indépendantes et exigent les droits natifs existants ; aucun droit universel automatique. Une école configure ses vrais lieux, son modèle financier et ses habilitations/consentements/photos dans les circuits natifs : ne pas inventer lieux, seuils, parents ou comptes.

La personne habilitée valide ensuite, sans scanner de vrai QR, la caméra HTTPS sur son appareil et sa session/identité d’expéditeur WhatsApp depuis le Hub. Ne pas envoyer de message externe de test. Les providers actuellement déclarés déconnectés restent déconnectés. Aucune planification du nouveau dispatcher tant que ses prérequis d’activation ne sont pas démontrés ; queued/unknown ne signifie pas envoyé. Impression officielle reste un circuit distinct et ses contrôles ne sont pas contournés.

## Arrêt et récupération

Une erreur après arrêt/rename est un incident à inspecter, pas un prétexte à redéployer automatiquement l’ancien worker. Conserver ancien/nouveau conteneurs, sessions les plus récentes, configuration, clé escrow, dump privé et expansions. **Ne jamais vider dispatch_claim_token, relâcher un claim incertain par TTL, restaurer le dump par-dessus des écritures réelles, ni réactiver un ancien dispatcher ignorant043.** Tout retour de runtime conserve la clé et interdit les workers historiques ; l’incertitude reste durable jusqu’à réconciliation explicite. Keycloak/Watch, comptes réels, données pédagogiques et autres lots ne sont pas reconfigurés.
