# Présence & Scan QR — en cours, moteur natif récupéré, NON DÉPLOYÉ

## Mandat et protection de la livraison précédente

Après livraison vérifiée du dashboard, le propriétaire donne GO pour Scan. Seul écrivain : agent pi ; branche dédiée `feat/presence-scan-qr-20261023`, départ `9d5cfad163c1427d367e80a0a5cab25f896e5d2d`. Production reste image8770153a / recette4065f94 / runtimeb10d8d3, dashboard/menu acceptés protégés. Pas d'installation ou migration de production autorisée par la récupération locale ci-dessous.

Attendus : entrée « Présence & Scan QR » dans Actions visible à tous profils, mais exécution seulement selon habilitation accordée par l'administrateur scolaire ; commandes distinctes entrée/récupération/frais/cantine/vérification. Référence privée du19/10, bleu profond/caméra/cadre lumineux, état réel de connexion/scan et portrait reconnu ; ne jamais recopier personnes/chiffres/presences de la photographie. Contrôle financier activé depuis Finances, minimum payé et tolérance configurables : rouge refuse, jaune tolérance, bleu autorise. Autorisation d'opérateur et droit de collecter un enfant restent distincts.

## Vérification de l'installation — lecture seule

Sur le conteneur de production, les cinq fonctions référencées par scanner-relay.php sont absentes : CardVerify, EntryScan, FeeScan, PickupManage, PickupScan. RosarioCardRead est présent. Aucun relay-config bridge présent ; aucun service de cartes Node/base cartes distinct ne figure parmi les conteneurs existants. Les tables natives entry_exit sont présentes. Inventaire information_schema en transaction READ ONLY : aucune relation schoolsafe de cartes, pickup, habilitations ou politique financière détectée par les motifs recherchés. Aucune personne, carte réelle, opération financière ou secret applicatif n'a été consulté.

Une tentative initiale de commande psql par variables d'environnement a échoué avant requête (rôle root inexistant, échappement SSH/Windows). Reprise via le connecteur de maintenance déjà installé, sans exposer sa configuration : inventaire lecture seule réussi. Ce constat ne certifie pas les autres bases possibles ; il caractérise le chemin natif actuellement configuré.

## Réutilisation retrouvée dans Git

Le commit canonique `8f81b8fc09a39092aa2498f3003a40d9288d223f`, présent sur main et les worktrees de qualification, publie précisément les dépendances manquantes. Il n'est pas ancêtre du checkout de présentation livré. Pas besoin de créer un nouveau moteur ou d'installer à l'aveugle le bridge de laboratoire : récupération ciblée de cinq fonctions PHP, quatre migrations additives et sept fichiers de tests. Aucun merge global de main, aucune source du dashboard/thème/auth/ProDéli modifiée. Pas de restauration ni exécution des migrations, même en local à ce stade.

Ces sources ne sont pas déclarées sûres/prêtes par leur seule provenance. Écarts à traiter avant qualification :

- EntryScan écrivait attendance_day automatiquement ; ce n'est pas une preuve de présence pédagogique. Écriture supprimée après test rouge, le passage natif reste enregistré.
- FeeScan reste une consultation, sans barème ni verrou d'entrée. Le bridge possède une politique minimum payé/tolérance mais ne constitue pas un service production installé. Raccorder la politique au propriétaire natif et à l'entrée, sans paiement inventé ni simple changement de libellé.
- Les frais doivent reprendre exonérations/remboursements natifs ; aucune donnée ou lecture indisponible ne devient une tolérance réelle, un impayé ou un accord.
- Le catalogue autorise actuellement admin/teacher pour les scans, tandis que la visibilité demandée concerne tous profils. Définir séparément visibilité, délégation individuelle/école/année et exécution ; ne pas ouvrir les routes à tous en supprimant le gateway.
- Contrôler à nouveau les booléens PostgreSQL (`'f'` ne vaut pas un booléen false en PHP empty), photos, plafonds trois familiaux/trois externes, principal et autorisations non principales, suspension/réactivation, expirations et preuve de remise.
- Vérifier verrouillage concurrent, périmètre école/année des points de passage et doublons. Les tables entry_exit natives n'ont pas de school_id ; ne pas considérer une simple liste globale comme scoping sûr.
- Secrets/cartes et stockage requis non installés : aucun état « scanner actif », arrivée confirmée, personne reconnue ou activation financière simulé.

## Preuves locales de ce premier sous-lot

Conteneur PHP existant6c1a8715597e, montage dépôt read-only, réseau none, secret exclusivement synthétique pour les tests. Baseline du moteur d'entrée retrouvé : PASS historique (deux écritures passage+attendance). Nouvelle exigence arrivée≠présence : trois assertions rouges, puis suppression de l'écriture attendance et de sa lecture de période inutilisée ; vert. L'arrivée ne crée pas une absence non plus.

Quatre suites unitaires sur données substituées : CardVerify, EntryScan, FeeScan, PickupScan ; **103 assertions PASS**, syntaxe des cinq fonctions PASS. Pas de preuve PostgreSQL, HTTP authentifiée ou caméra physique pour ce lot. Test DB d'entrée adapté pour comparer la présence avant/après sans la supprimer. Les trois scripts DB récupérés exigent désormais le marqueur dédié de qualification et la base fictive schoolcef_fixture avant connexion ; ils n'ont pas été exécutés. Première commande globale arrêtée sur secret synthétique manquant, puis relancée avec une valeur de test explicite ; aucun secret réel lu.

Preuves privées : `school-scan-20261023/installed-relations.json`, `entry-baseline.log`, `entry-red.log`, `native-unit-green.log`. Tests ne constituent pas une autorisation de livraison de ce lot incomplet.

## Sous-lot sécurité remise — booléens, familial non principal et preuve verrouillée

GO renouvelé ; le propriétaire demande expressément de finir Scan avant de reprendre le palmarès en matrice et les cinq classes les plus actives. Les brouillons palmarès locaux sont conservés et exclus de ce sous-lot.

PickupManage : conversion stricte des booléens DB (`true/t/1` seulement ; `f`, inconnu ou absent refusent). Père/mère/tuteur se classent comme famille indépendamment d'IS_PRIMARY ; seuls les externes requièrent une accréditation externe. Photo rejetée non exposée par la liste. Approbation/revue sur personne inactive et photo non validée refusées. Réactivation d'une accréditation suspendue par son demandeur parent vérifié, avec les mêmes contrôles photo/date/place, et non une validation staff d'une demande pending. Lecture de l'autorisation de liste désormais bornée école/enfant et plus récente d'abord.

PickupScan : les familiaux autorisés non principaux deviennent des candidats sans usurper le drapeau principal. La jointure d'accréditation lie guardian+école+enfant. Une préparation sans preuve effectivement insérée refuse et rollback. La confirmation commence sa transaction AVANT de lire/verrouiller la preuve FOR UPDATE, vérifie l'opérateur ayant préparé, puis verrouille personne/accréditation avant nouvelle lecture d'éligibilité et écriture de sortie. Le rejeu d'une preuve consommée ne refait pas la sortie ; un enregistrement disparu ne devient pas une confirmation fictive. Toutes les sorties de fonction/erreurs ferment la transaction.

TDD :39contrats de gestion (19PASS/20FAIL avant correctif,39PASS après) ;13contrôles supplémentaires de scan, dont10RED avant correctif, puis vert. Quatre suites natives maintenant **116 assertions PASS**, ajout gestion39 => **155 assertions sur substituts PASS**,5lint PASS.

**PostgreSQL réel local :12contrôles PASS**, scripts school-pickup-proof-qa.py / school-pickup-proof-pg.php. Base NEUVE éphémère schoolcef_fixture, identités exclusivement synthétiques, vraies migrations021/022 et vrai PHP SchoolSafePickupConfirm, images déjà locales, réseau interne sans port publié. Deux processus PHP/connexions indépendants : premier bloqué volontairement après acquisition de la preuve, second observé en attente Lock dans pg_stat_activity, puis libération ; un succès et un rejeu, même record_id, exactement UNE sortie PostgreSQL. Refus opérateur/école/enfant différents, preuve expirée, photo rejetée, personne inactive et habilitation familiale retirée sans sortie. Ressources supprimées uniquement après contrôle du label nonce propriétaire. Preuves privées proof-pg-r1/result.json, pickup-safety-green.log, pickup-proof-red.log, pickup-proof-green.log, pickup-regression-green.log.

Limites : preuve de concurrence pour **la même preuve**, pas encore deux preuves différentes du même enfant ; plafonds familiaux/externes et activation concurrente à compléter. Les voies parent et le champ REQUESTED_BY du code récupéré doivent encore être réconciliés avec le parent principal réellement lié : un test de propriété synthétique ne certifie pas l'enrôlement réel. Le scoping annuel/points de passage, délégation opérateur, barrière financière, HTTP, caméra et image restent ouverts. Les migrations n'ont été exécutées que dans cette nouvelle base jetable, jamais production. Ne pas réutiliser les preuves dashboard comme qualification Scan.

## Sous-lot suivant — plafonds3+3 concurrents, après89961ed

Reproduction PostgreSQL réel avant correctif : deux validations concurrentes de pending33/34 avec deux accréditations déjà actives donnaient **quatre actives**, toutes deux en slot3. Deux inscriptions familiales concurrentes avec deux personnes déjà actives donnaient également **quatre personnes familiales actives**. Preuve privée limits-pg-red/limits-observed.json ; aucun cas d'enfant réel.

Correctif dans le propriétaire natif PickupManage : transaction commune sérialisée par enfant (verrou students FOR NO KEY UPDATE, compatible avec les références FK des passages), inscription école/année verrouillée FOR SHARE. Registre familial limité à trois personnes actives ; une place peut rester vide, aucun compte obligatoire ajouté. Activation/réactivation externe calcule le plafond et le slot dans cette transaction ; verrouille personne puis autorisation comme la confirmation de remise, relit l'état/photo/date/propriété avant écriture. Un parent dont REQUESTED_BY a changé pendant la demande est refusé. Suppression de la réécriture inutile de REQUESTED_BY après COMMIT, reproduite par un contrat rouge distinct puis corrigée. Les rôles admissibles existants ne sont pas élargis ; leur délégation fine reste à faire.

Gates finaux : **116 assertions natives +43 contrats gestion =159PASS**,3lint PASS. **18contrôles PostgreSQL réel PASS** (limits-pg-final-r2), dont12précédents,3+3 en compétition réelle et refus d'année absente/autre école/photo rejetée sans réserver de slot. Les deux concurrents se terminent : une inscription/validation et un LIMIT_REACHED, au maximum trois places et trois slots distincts. Seules les connexions fictives et ressources nonce créées par ce test ont été employées puis supprimées. Ni migration production ni nouvelle infrastructure conservée.

Portée : limites garanties via les fonctions natives testées ; pas une preuve d'une contrainte SQL globale face à un écrivain DB extérieur à ces fonctions. Deux preuves distinctes de remise pour le même enfant restent à examiner ; cela n'est pas couvert par l'idempotence d'une seule preuve. La vraie filiation principal/REQUESTED_BY, les scopes annuels/checkpoints, délégation, finance, HTTP et caméra restent ouverts. Les nouveaux contrats avec substituts sur propriété ne constituent pas un parcours parent authentifié. Aucune modification des brouillons palmarès/dashboard dans ce sous-lot.

## Sous-lot cycles de présence et scoping natif — après8b8e158

Reproduction réelle : deux tokens différents pour une même arrivée créaient deux sorties (distinct-pg-red-r2/distinct-proofs-observed.json). La première tentative distinct-pg-red s'était arrêtée sur une quote PHP du fixture, sans atteindre le métier ; fixture corrigé avant la reproduction valide. Contrat supplémentaire : un portail au mauvais sens rendait ALREADY_PRESENT si l'élève était déjà dedans ; RED puis refus avant l'idempotence.

Le moteur reste entry_exit_student_records, pas un second registre ni attendance_day. Migration additive **039-scan-passage-scope.sql**, appliquée UNIQUEMENT en DB QA neuve : colonnes scolaires/année sur les passages, école/année/enabled sur les checkpoints, année/entry_record_id sur les preuves, index. Aucun attribut historique deviné ni réécrit ; anciens passages/preuves non attribués, checkpoints existants désactivés par défaut pour le scanner. Deux applications de migration et conservation d'un vrai enregistrement natif synthétique antérieur vérifiées dans le fixture.

SchoolSafeScanPassage fournit les gardes de disponibilité et le dernier événement du bon périmètre ; un événement global plus récent non attribué/étranger invalide l'ancien cycle au lieu d'en inférer une présence. Arrivée, préparation et confirmation utilisent le verrou par enfant déjà qualifié. Preuve liée à l'arrivée ET à l'année ; après une sortie, une seconde preuve est refusée ; après réentrée native réelle, une nouvelle preuve peut confirmer une nouvelle sortie, l'ancienne preuve non utilisée devient PROOF_STALE. Rejeu d'une preuve consommée renvoie seulement son ancien enregistrement. Une arrivée historique sans école/année ne devient jamais une présence certaine. Checkpoint revérifié sous verrou partagé lors des écritures d'entrée et de confirmation ; mauvais sens refusé même si déjà présent. Aucune absence/présence pédagogique automatique.

Gates : **160assertions substituées** (117natives+43gestion),8lint PASS ; **39contrôles PostgreSQL réel PASS** (cycles-scope-pg-final), vrais CardVerifyQr/registre020/HMAC synthétique, vrais EntryScan et PickupScan pour le cycle entrée→remise→réentrée→remise ; transactions concurrentes indépendantes, migration répétée/données anciennes préservées, refus scope/expiration/photo/année/point/infrastructure absente. La confirmation humaine est une entrée de test explicite, pas un test avec opérateur/caméra physique. Ressources nonce seules supprimées après vérification de propriété. Anciennes suites scan-entry-db/scan-pickup-db adaptées pour checkpoint explicitement borné et arrivée préalable, cleanup limité au checkpoint créé ; LINT seulement, pas de parcours fil parent/ArrivalRead revendiqué. Le driver jetable est la preuve PostgreSQL de ce sous-lot.

Compatibilité/retour : anciens lecteurs natifs continuent de lire leurs colonnes ; anciens écrivains peuvent encore écrire des lignes NULL non attribuées, qui ne valent pas une arrivée pour le scanner. Fenêtre mixte de ces lecteurs/écrivains à qualifier avant livraison, notamment ArrivalRead/NotificationFeed et listes/configuration des checkpoints. Retour opérationnel envisagé sans suppression de métadonnées : désactiver le scanner et conserver colonnes/historique, puis décision propriétaire sur l'image de retour ; aucun DROP/restauration/rollback automatique autorisé ou exécuté. Qualification d'expansion seulement, pas contraction. Ne pas déployer ces sources sans039/provisionnement/configuration et leurs preuves exact-image.

Restent en priorité : vrai parent principal/REQUESTED_BY, délégation opérateur et gestion/liste des points de passage bornées, policy financière bloquante depuis Finances, UI et lecteurs compatibles, HTTP/image/caméra. Le dashboard et les brouillons palmarès restent séparés. Aucune installation ou migration production.

## Ordre de continuation et arrêt de sécurité

1. Finir la revue des invariants du moteur natif restauré (booléens, trois+trois, autorité, école/année, concurrence) avec régressions rouges/vertes.
2. Définir l'habilitation par opérateur sans confondre permissions de navigation et autorité de récupérer un enfant ; refus serveur sans habilitation.
3. Réutiliser le calcul financier exact natif et la politique existante minimum/tolérance, administrables dans Finances, et bloquer réellement l'enregistrement d'entrée rouge. Pas d'absence pédagogique automatique.
4. Raccorder l'entrée Actions tous profils et l'interface de référence aux endpoints existants, avec boutons distincts, états indisponibles/permission caméra/QR invalide et garde de double confirmation.
5. Fixture PostgreSQL isolée, migrations réversibles qualifiées, tests HTTP/permissions/famille/argent/concurrence, captures téléphone/ordinateur et tests caméra HTTPS ; qualification de l'image et décision explicite de livraison avant toute installation en production.

Ne pas présenter ce premier sous-lot comme « Scan terminé ». Le dashboard livré n'est ni redéployé ni modifié par cette préparation.
