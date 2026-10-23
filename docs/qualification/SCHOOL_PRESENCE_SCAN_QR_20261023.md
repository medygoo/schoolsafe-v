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

## Ordre de continuation et arrêt de sécurité

1. Finir la revue des invariants du moteur natif restauré (booléens, trois+trois, autorité, école/année, concurrence) avec régressions rouges/vertes.
2. Définir l'habilitation par opérateur sans confondre permissions de navigation et autorité de récupérer un enfant ; refus serveur sans habilitation.
3. Réutiliser le calcul financier exact natif et la politique existante minimum/tolérance, administrables dans Finances, et bloquer réellement l'enregistrement d'entrée rouge. Pas d'absence pédagogique automatique.
4. Raccorder l'entrée Actions tous profils et l'interface de référence aux endpoints existants, avec boutons distincts, états indisponibles/permission caméra/QR invalide et garde de double confirmation.
5. Fixture PostgreSQL isolée, migrations réversibles qualifiées, tests HTTP/permissions/famille/argent/concurrence, captures téléphone/ordinateur et tests caméra HTTPS ; qualification de l'image et décision explicite de livraison avant toute installation en production.

Ne pas présenter ce premier sous-lot comme « Scan terminé ». Le dashboard livré n'est ni redéployé ni modifié par cette préparation.
