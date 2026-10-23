## Mission courante — terminer Scan puis palmarès, classes actives et notation ; dashboard livré protégé

### Périmètre étendu explicitement validé par le propriétaire

Le propriétaire ajoute au mandat de finition/livraison : **Top10 école et classes en matrice près des anniversaires, confidentialité des noms pour les parents ; cinq classes les plus actives avec activités et enseignants ; barème de notation préparé séparément à terminer/qualifier/livrer**. Ce n'est plus une exclusion du mandat global : ces lots restent séquentiels APRÈS Scan, pas mélangés dans une livraison non qualifiée. Bouton Actions et écran Scan de la photo font partie du Scan à finir. Les six points Scan déjà listés par le propriétaire restent tous au périmètre. Aucune nouvelle demande de GO général. Les brouillons palmarès actuels ne satisfont pas encore cette spécification : ne pas les publier tels quels.

Après clôture Scan : reprendre les propriétaires natifs notes/moyennes/classe/activité, préserver les dossiers d'autres enfants, confirmer la période de comptage des classes actives à partir des filtres natifs (elle n'a pas été fixée par le propriétaire), qualifier séparément la notation avant bascule. Pas de formule de moyenne inventée, pas d'activation implicite de publication. Même règle lire → réutiliser → corriger → tester → image exacte → livraison contrôlée pour chacun.

### Ordre exécutable confirmé — inspecter, réutiliser, corriger, tester

**Mandat suivant du propriétaire : terminer tout le lot Présence & Scan QR puis mettre en production ce qui est terminé et qualifié. Autorisation de livraison enregistrée, sans nouveau GO général ; elle ne transforme pas les sous-lots locaux en qualification de production. Source native → corrections/refus → PostgreSQL/HTTP synthétiques → image exacte/caméra/services → sauvegarde et bascule contrôlée. Aucun checkout global avec palmarès différé, aucun faux résultat ni envoi de test à des personnes réelles.**

**Ordre validé expressément par le propriétaire. Règle obligatoire à chaque point : lire le code interne concerné et suivre ses appels, propriétaires de données, permissions et tests existants AVANT toute correction, raccordement ou lancement de scénario. Vérifier les effets des scripts avant exécution. Réutiliser le natif ; corriger seulement les manques/écarts prouvés, puis tester. Ne pas changer l'ordre ni reconstruire un système déjà présent.**

1. **Vérification des accès natifs : faite sur code +62contrats locaux PASS.** Tableau Users/Profiles et droits personnalisés, affectation du profil nommé à la création, filtres de délégabilité, Catalog/Permissions/Gateway. Aucun nouveau moteur de permissions ni élargissement ProDéli/école. Ce n'est pas un parcours HTTP de création de profil.
2. **Finir le raccordement natif des routes Scan existantes** : liste opérateur corrigée après RED SQL réel (scope école/année/actif/direction), puis page/opération administrative raccordées aux vrais checkpoints (nom/sens/activation, ancien bridge conservé) sans nouvelles tables/permissions ;271assertions locales/7lintPHP,55contrôles PostgreSQL et29contrôles navigateur à transport intercepté PASS. Pas encore HTTP authentifié/image/production. Lecteurs ArrivalRead/NotificationFeed ensuite bornés au scope039 et lien parent natif, probe PostgreSQL corrigé :298contrats locaux/5lint et61PG PASS, pas HTTP/image/prod. Parent principal/REQUESTED_BY ensuite raccordés nativement après RED PostgreSQL : liaison facultative explicite à l’approbation familiale avec consentement/photo et compteparent/SJU vérifiés, aucun compte créé ; demandeur externe=principal lié et non agent, transaction et recontrôle relation/propriétaire, pas de transfert historique automatique.84PG/42navigateur transportintercepté/298locaux PASS, pas HTTP/image/prod. Restent raccordement des autres commandes déjà cataloguées et qualification globale. Tester les refus, ne pas confondre clé publiée, configuration installée et opération fonctionnelle.
3. **Barrière financière** : commencer par un RED de conformité du solde FeeScan aux montants natifs d'exonération (natif : montant négatif additionné ; code récupéré : annulation de tout le frais dès référence, fixture positif douteux). Corriger seulement l'écart prouvé, puis reprendre modèles/minimum/tolérance existants et vraies écritures de facturation, préserver exonérations/remboursements et devises ; appliquer rouge avant arrivée, jaune toléré/bleu en ordre, indisponible distinct. Aucun paiement fabriqué.
4. **Messages automatiques complets** : enfant/école/classe, frais ou contrôle nommé, année/période, devise, exigible net, payé effectif, restant/crédit, échéance disponible, seuil/tolérance/raison/date/heure/action. Sources et modèles Studio/Notify/WhatsApp existants, destinataires parents autorisés, champs inexistants jamais inventés, montants non exposés aux opérateurs sans droit. Après persistance métier, idempotence/reprise et tests sans envoi externe. Ancien notify-u0 non exécuté car DB/migrations/dispatcher non gardés.
5. **Interface puis qualification/livraison séparée** : Actions visible à tous sans ouvrir l'exécution, modes et états réels/portrait/caméra, HTTP fictif multiécole puis image exacte et caméra HTTPS. Décision explicite avant migrations/provisionnement/production. Palmarès et classes actives seulement après Scan.

Précontrôle production après mandat : image Rosario8770153 healthy, WAHA/Evolution tournent, mais agrégats natifs **0session active+online** pour chacun (pas de test fournisseur). Administration WhatsApp ProDéli : connexion/vérification/activation humaine requise, sans secrets au chat ni envoi de test parent. Aucun déploiement/migration réalisé. Preuve privée release-preflight-readonly ; rapportScan détaillé. L'autorisation de livraison reste valable, ne pas demander un nouveau GO général.

Les étapes ne sont pas déclarées terminées par la seule présence des fichiers ou par des tests substitués. RapportScan détaillé à jour ; preuve accès privée native-access-contracts.log.

Dernières précisions propriétaire : accès/limites DÉJÀ NATIFS, réutiliser Profiles/Exceptions/Gateway et droitsScan/Delegation du Catalog, ne pas les réinventer. Arrivée enregistrée et sortie confirmée : détail école/parent + notification application + WhatsApp automatique ; contrôles financiers créés/configurés par admin : rouge refuse, jaune rappelle de compléter/tolérance, bleu en ordre pour ce contrôle. Rouge ne prouve pas une absence. Raccorder notifications/WhatsApp EXISTANTS (NotificationFeed/Notify/Whatsapp), après persistance métier, destinataire parental validé, consentement/session opérationnelle et anti-doublon. Un contrôle de frais n'est pas un reçu de paiement ; événement dédié à tracer, pas de double moteur ni copie aveugle de la policyUUID du bridge. Aucun envoi externe réel ou activation de session pendant les tests.

Suite cycles après8b8e158 : RED réel deux tokens/même arrivée→deux sorties. Correction native sans registre parallèle :039additif borne passages/checkpoints et lie preuve à année/arrivée ; verrou commun enfant, une sortie par cycle, réentrée réelle possible, preuve ancienne inutilisée refusée. Anciennes lignes non attribuées jamais devinées ; événement non attribué plus récent invalide l'ancien cycle. Checkpoints désactivés/étrangers/autre année/mauvais sens refusés.160unités/contrats+8lint et39gates PostgreSQL réel jetable PASS (cycles-scope-pg-final), dont vrais moteurs QR/entrée/remise, concurrence, migration039 répétée et ancien record préservé. Aucun HTTP/caméra/image/production. Suivant : parent principal/REQUESTED_BY réel, délégation et listes/configuration des checkpoints, compatibilité lecteurs natifs, vraie barrière Finances, UI/qualification. Migration039 NON installée en production ; pas de rollback automatique/contraction. Palmarès différé.

Suite après89961ed : plafond3+3 corrigé dans les fonctions natives. RED réel :4externes/slot3 dupliqué et4familiaux avec deux requêtes concurrentes. VERT : transaction sérialisée par enfant, inscription école/année verrouillée, au plus3familiaux et3accréditations/slotsdistincts ; propriétaire réactivant relu sous verrou, pas de réécriture après COMMIT.159unités/contrats+3lint PASS ;18contrôles PostgreSQL LOCAL jetable PASS (limits-pg-final-r2), dont concurrence réelle et refus école/année/photo sans réservation. Pas de HTTP/caméra/production ni garantie SQL contre d'autres écrivains. Prochaines tâches : deux preuves de remise distinctes/même enfant, enrôlement parent/REQUESTED_BY et checkpoint année/école, puis délégation/finances/UI/qualification. Palmarès toujours différé.

Ordre propriétaire confirmé après ajout palmarès : **finir Scan d'abord**, puis matrice palmarès tous profils/visibilité parent administrée et cinq classes les plus actives (devoirs/TP/interros réellement donnés, enseignants associés ; période à préciser). Brouillons palmarès conservés, non inclus dans la livraison Scan.

Sous-lot sécurité après658616c : booleans PostgreSQL stricts, famille non principale autorisée, photo/état/propriété de réactivation ; preuve de remise liée à l'opérateur, transaction et verrou avant lecture/confirmation, refus si insertion preuve échoue.39contrats gestion +116unités natives =155PASS,5lint ;12contrôles PostgreSQL réel LOCAL sur base jetable et vrai PHP, dont deux confirmations concurrentes d'une même preuve = une sortie et un rejeu. Cleanup nonce vérifié. Ce n'est pas du HTTP, de la caméra ou de la production. Restent notamment trois+trois/concurrence de preuves distinctes, enrôlement parent/REQUESTED_BY, checkpoint école/année, délégation, vraie barrière financière, UI et qualification séparée.

GO propriétaire après clôture dashboard. Branche dédiée feat/presence-scan-qr-20261023 depuis9d5cfad, agent pi seul écrivain. Lecture seule production : dépendances PHP du scanner absentes/config bridge absente ; tables natives entry_exit présentes. Sources retrouvées dans commit canonique8f81b8f sur main,16fichiers récupérés sans merge global ni migration. Premier correctif TDD arrivée≠présence pédagogique :3RED puis vert ;103assertions unitaires PHP/5lint PASS. Pas de preuve PostgreSQL/HTTP/caméra ni nouvelle livraison. Continuer la revue sécurité/3+3/booleans/scopes/concurrence, délégation opérateur et vraie politique financière bloquante avant UI et qualification. [État exact et critères](../../qualification/SCHOOL_PRESENCE_SCAN_QR_20261023.md). Ne pas réintroduire une deuxième implémentation ni modifier les sources du menu/dashboard livré.

## Clôture — dashboard LIVRÉ, base du lot Scan

Production qualifiée livrée après GO : recette4065f9406a3a525f6bd64478b5207dd732c2d498 / deployed/school-dashboard-composition-20261023, runtimeb10d8d3, image8770153a, digest9a84afb7. Bascule8,95s, backup privé valide649096octets/0600,73sessions/10cache/config/services préservés, prédécesseur arrêté conservé. Contrôle distinct PASS :3sources/15sources protégées/notation/thèmes,5services/22autres conteneurs identiques,aucune session non-expirée manquante. Aucune migration/restauration/login réel/envoi externe. Aucun changement runtime depuis la qualification exact-r2 ; docs de clôture seuls ensuite. Prochaine action : vérifier le chemin Scan réellement installé avant raccordement des commandes et de la politique financière explicitement demandée, en protégeant cette livraison et les familles.

## Historique — dashboard qualifié, avant bascule

Le 23/10, le propriétaire demande de finir/livrer le dashboard puis d'attaquer Scan séparément. Exact-r2 PASS : image8770153a, sourceb10d8d3, harnais84f5268, digest9a84afb7 ; trois fichiers/2761 inchangés,186PHP/37PG,126DOM,25groupes natifs dont deux écoles et compositions1280/390/768,10récupérations et course/rollback. R1 rouge au test historique du lien configuration désormais dans Actions ; seul harnais corrigé, même image réutilisée sur fixture fraîche. Snapshot, absence de comptes QA et nettoyage nonce PASS, production inchangée. Recette ops/releases/school-dashboard-composition-20261023 en préparation : backup frais, préservation sessions/cache/config/services/prédécesseurs puis contrôle distinct. Ne pas modifier Scan/permissions/familles dans cette livraison. Détail du lot et preuves : [rapport](../../qualification/SCHOOL_DASHBOARD_COMPOSITION_20261022.md).

## Historique — composition locale après validation du menu

GO de réalisation reçu. Le propriétaire a rejeté le diaporama malgré le compteur fonctionnel et fourni les dispositions ordinateur et téléphone. Conserver toutes les informations, pas les 13 écrans paginés. Ajustements validés : quatre KPI et modules 4 × 2 sur téléphone, anniversaires en partie haute, informations détaillées en bas, effets lumineux/ombres/animations réduisibles sur les deux formats, deux commandes en haut du téléphone (Notifications et Actions regroupées). Sources menu/thème inchangées, éléments natifs déplacés/rétablis sans clones ni nouveaux droits.

Candidat local à trois sources produit, agent pi seul écrivain, branche fix/school-dashboard-stability-20261022, base716545c. 20 contrats du vrai renderer avec substituts et 126 contrôles navigateur à huit largeurs PASS avant les derniers ajustements visuels ; captures privées du renderer en clair/nuit et panneau Actions. Suite HTTP adaptée mais NON rejouée, aucun nouveau déploiement ni compte réel. Prochaine action : finir les gates locaux, présenter la composition, puis qualification authentifiée et livraison seulement dans le périmètre autorisé. Voir [suivi du nouveau lot](../../qualification/SCHOOL_DASHBOARD_COMPOSITION_20261022.md). Les exigences historiques ci-dessous de conserver le pager sont remplacées ; la livraison technique/menu reste acquise, pas l'acceptation du dashboard.

## Historique — Application scolaire LIVRÉE, contrôle distinct PASS

Runtimea69e745, recette/VPSf1dcb3b sur deployed/school-dashboard-stability-20261022, imageb271dcc5, digest257f7b79. Qualification finale ci-dessous confirmée, cinq sources exactes/2759 inchangées. Bascule9,21s,74sessions vérifiées conservées/cache10/configurations Docker inchangées, prédécesseur arrêté préservé. Backup649030octets0600 listé/hash336fb8a2, cinq sources sauvegardées, aucune migration/restauration. Vérification distincte PASS : sources/Git/auth8/notation, services5+historique20, aucune session non expirée manquante, routes HTTPS200/302/403 attendues. Aucun compte réel ou envoi testé. Rapport SCHOOL_DASHBOARD_STABILITY_20261022.md à jour ; lot clôturé, pas de nouveau déploiement implicite.

## Application scolaire — qualification et préparation antérieures (livraison effectuée ci-dessus)

Final exact-r7 : imageb271dcc5, runtimea69e745, digest257f7b79, cinq runtimefiles (quatre UI/cache + garde de source ArrivalRead). 122 DOM,166PHP/37PG,25groupes natifs authentifiés/10récupérations ; treize vues réelles et mêmes menus1280/390/768 avec deux écoles fictives ; liens GET200, historique, dossiers, création/reconnexion. Deux rollbacks, deux sessions concurrentes, un gagnant/un epoch. Snapshot/cleanup PASS et production inchangée ; captures réelles revues. Harnais distant5ddff1d, navigateur propre au nonce et seuls artefacts QA partagés, aucun provider/install modifié. Source/auth/notation préservés. Recette ops/releases/school-dashboard-stability-20261022 : GO propriétaire confirmé, sauvegarde récente et bascule préservant sessions/cache/config/containers, puis contrôle distinct. **Depuis livré et contrôlé, voir clôture ci-dessus**. Détail des tentatives rouges et limites dans le rapport de qualification.

## Application scolaire — étapes locales antérieures (remplacées par la qualification ci-dessus)

Propriétaire confirme son menu personnalisé par deux captures privées, maintien des treize vues et de tous contenus, mêmes destinations à droits identiques sur téléphone/ordinateur ; GO de mise en production après analyse/correction/test/stabilisation. Branche fix/school-dashboard-stability-20261022, base14365d0. [Suivi courant](../../qualification/SCHOOL_DASHBOARD_STABILITY_20261022.md). Quatre runtime files : JS photo, JS SchoolSafeCodex, CSS photo, PHP photo (cache). Réutiliser menu/en-tête/dock natifs ; état de vue URL, dossiers complets, filtres/ancres, démontage réversible. Tests DOM fictif vrais assets : 39/50 avant, 89/0 puis extension98/24, final122/0 à huit largeurs ; contrats frontière84/0 et syntaxe PASS. **HTTP/PG fictif, image exacte, revue visuelle et déploiement restent à faire** ; ne pas annoncer tous boutons métier certifiés. Harnais natif complété opt-in SCHOOL_NAV_HTTP=1, recette QA school-dashboard-image-qa.py sur base immuable1e8259e4. Aucune production modifiée. Ne pas remplacer les treize vues par une synthèse unique ; ancienne proposition ci-dessous non retenue. Pas d’auth/migration/envoi externe réel, notation séparée, Watch honnêtement non raccordé.

## Application scolaire — analyse initiale (historique, avant choix visuel)

Mandat courant : comprendre les tableaux de bord scolaires avant stabilisation, pas redéployer ProDéli. [Analyse et plan ciblé](../../qualification/SCHOOL_DASHBOARD_NAVIGATION_ANALYSIS_20261022.md). Quatre hashes d’assets production correspondent à Git ; DOM fictif avec vrais JS/CSS à 1280/768/390 : compteur dynamique 1/13 et retour systématique Accueil au reload, menu caché après carte de module, divergence de seuils 700/900 et clic extérieur qui referme le menu. Le bouton Menu desktop 1280 fonctionne dans le diagnostic : ne pas le déclarer universellement cassé. Cartographie admin/onglets/détails/enseignant/parent/élève/synthèses natives effectuée, pas une certification des données métiers de tous profils. **Aucun code produit, compte réel ou déploiement modifié dans ce lot.** Premier correctif recommandé : état menu partagé puis navigation persistante des rubriques ; validation fictive par profil avant toute livraison distincte. La notation reste séparée.

## Frontière ProDéli / écoles et téléphone — LIVRÉ, clôturé PASS

Clôture effective : recette `d87947b`, runtime `fb0774d`, image `1e8259e4…`, branche VPS `deployed/prodeli-school-boundary-20261022`, backup PG privé valide 648754 octets/0600, bascule 8,86 s et contrôle indépendant PASS. Douze sources canoniques, huit moteurs inchangés, 128 sessions copiées avant démarrage (aucune non expirée manquante après GC), dix entrées Chromium, configuration et 20 autres conteneurs conservés. HTTPS public/protection anonyme PASS. Aucun compte réel manipulé pour essai, migration, restauration ou envoi externe. **Prochain lot : analyser séparément le tableau de bord/menus de l’administrateur scolaire et le nombre 13 signalés, sans mélanger la notation.** Ne pas rejouer les recettes livrées. Les paragraphes suivants conservent l’historique des qualifications avant bascule.

Actualisation historique : `exact-r18` PASS sur l’image immuable `1e8259e4…` / runtime `fb0774d`. 156 contrats / 57 PG natifs / 37 PG récupération ; 21 parcours natifs r17 et 24 vues responsive r10 conservés avec provenance stricte du même ID d’image et harnais inchangés ; 10 groupes récupération et concurrence/rollback repris sur nouvelles fixtures r18. Snapshot/absence de résidu/cleanup et production inchangée PASS. Sauvegarde privée récente vérifiée, bascule puis contrôle indépendant restent obligatoires ; GO toujours acquis. Aucun élargissement scolaire/notation. Le relevé réseau ci-dessous est historique.

GO explicite acquis, mais qualification finale alors incomplète. Image finale `1e8259e4…`, source runtime `fb0774d`, douze fichiers (dont lien Configurer mon école et écriture tenant-scoped, styles centraux anti-superposition). 156 contrats + 57 PG natifs + 37 PG récupération et 24 vues desktop/mobile PASS ; aucun parcours natif complet de 21 groupes encore PASS sur cette image. Les tentatives rouges et interruptions SSH sont documentées, pas rebaptisées réussies. Dernier push d’outils IPv4 bloqué aussi par GitHub:443 ; nettoyage du nonce QA r12 non confirmé. Production inchangée `a4a3cbf8…`, dernier contrôle running/healthy, aucun staging/bascule/backup récent de ce lot exécuté. Stabiliser le transport, retirer les seuls objets QA appartenant au nonce, puis finir les gates avant sauvegarde/livraison. Pas d’élargissement aux treize vues scolaires ni à la notation. [Statut et reprise](../../qualification/PRODELI_SCHOOL_BOUNDARY_RELEASE_20261022.md).

## Historique — frontière ProDéli / écoles et activation qualifiée localement

Nouvelle consigne propriétaire : ProDéli ne peut plus ouvrir un espace/profil scolaire, même via URL/POST ; l’administrateur scolaire termine son accès personnel avant d’entrer dans sa propre école. Dix runtime ciblés, sans notation : frontière serveur, liens/contextes globaux retirés, état d’activation fiable, ancien accès initial explicite possible pour un administrateur mono-école jamais connecté. Aucun compte réel modifié ; identité gérée et comptes déjà utilisés/activés exclus de cette préparation. 114 contrats PHP avec substituts + 57 PG natifs/rollback + 37 PG récupération/SMS + 20 groupes navigateur fictifs PASS avec nettoyage ; préférences/SMS/WhatsApp centraux conservés. [Rapport](../../qualification/PRODELI_SCHOOL_BOUNDARY_20261022.md). Prochaine étape : recette d’image canonique dédiée à la nouvelle politique, préservation/sauvegarde et livraison autorisée ; ne pas réutiliser les anciens critères workspace d’ouverture globale. La production reste sur la présentation `a4a3cbf8…` ci-dessous. Aucune autorisation de bascule implicite par la publication de ce correctif.

## Présentation SchoolSafe — DÉPLOYÉE, 22/10/2026

Après validation de l’aperçu et GO répété/confirmation, les deux lots graphiques sont livrés : image `a4a3cbf8…`, source runtime `9c9b338`, recette `8a969d5`. Notation exclue par branche de release issue du workspace actif. 17 runtime / 2 747 autres fichiers identiques ; 150 contrats PHP + 37 PG, 14 groupes natifs, 17 workspace, dix récupération-SMS, course/rollbacks PASS. Sauvegarde 0600 vérifiée ; 169 sessions/dix entrées cache, config/services conservés ; bascule 9,21 s ; contrôle indépendant PASS. [Rapport](../../qualification/PRODELI_PRESENTATION_RELEASE_20261022.md). Prochaine action : validation visuelle connectée par le propriétaire ; ni envoi réel, premier accès humain ou conformité PDF universelle déduits. La notation demeure non livrée.

## Accès / thème SchoolSafe — local qualifié, production autorisée, 22/10/2026

Le propriétaire valide l’aperçu et demande la production des deux lots graphiques, sans embarquer la notation. Neuf runtime de ce deuxième lot : connexion, premier accès et fiche autonome harmonisés, thème effectif unique et alternatives non sélectionnables, anciens fichiers/préférences conservés. 25 contrats thème, 17 préservation, 124 contrats PHP totaux + 37 PG, 14 groupes natifs et 17 workspace PASS localement avec cleanup ; téléchargement réel en mémoire et PDF expurgé vérifiés. [Rapport](../../qualification/PRODELI_ACCESS_THEME_20261022.md). Prochaine action : source de release issue du workspace actif, qualification exacte, sauvegarde/bascule préservée/vérification séparée. Production pas encore exécutée dans ce statut.

## Premier lot graphique SchoolSafe — qualifié localement, NON DÉPLOYÉ, 22/10/2026

GO après audit pour récupération, formulaires de configuration, en-têtes et espacement mobile central/wizard. Huit runtime, handlers/formulaires/droits/langue native conservés ; marque et contrôles récupération, zones natives agrandies, préambules mobiles réduits, retour ProDéli sur seconde ligne sans cacher la marque. 16 groupes navigateur/PG fictif avec nettoyage, 14 préservation, 83 contrats PHP substitués, 37 récupération/SMS PG et 7 JS PASS. Captures bureau/mobile relues ; conflit de spécificité d’en-tête détecté visuellement puis corrigé avec nouveaux contrôles de géométrie. [Rapport et limites](../../qualification/PRODELI_DESIGN_PRESENTATION_20261022.md). Pas d’image immuable/de production ; premier accès, fiche isolée, PDF et suspension de sélecteurs alternatifs restent hors de ce premier lot. Prochaine action : revue propriétaire et périmètre explicite de suite/livraison, sans embarquer implicitement la notation déjà publiée mais non déployée.

## Correction ProDéli — DÉPLOYÉE le 22/10/2026 après GO explicite

Image `59fb6b25…`, source applicative `a3fbffb`, recette/source VPS `8fc3546`, mêmes 16 blobs runtime. Arrivée native globale sur ProDéli, séparation scolaire, retour visible, réglages/console/confirmation d’école SchoolSafe, scope principal lisible et limite de connexion cohérente. 123 contrats + 37 PG, 14 groupes workspace/13 native/dix récupération-SMS et course/rollbacks PASS sur image exacte. Sauvegarde et contrôle séparé PASS ; 91 sessions/cache Chromium/configuration/services préservés, aucune migration/envoi réel. Session propriétaire : arrivée `/index.php` -> ProDéli, mobile/ancres/six destinations vérifiés. École/admin fictifs autorisés conservés, Default School restaurée ; premier accès humain et acceptation propriétaire restent distincts. [Rapport et limites](../../qualification/PRODELI_WORKSPACE_RELEASE_20261022.md). Les prochaines actions historiques ci-dessous ne remplacent pas ce résultat ni le haut du handoff.

## Récupération/SMS ProDéli — DÉPLOYÉ le 22/10/2026

**Livraison autorisée et terminée techniquement** : source/recette `68de604de0973bf9a22b2873cf791899f822dbe1`, image canonique Git `01c194f4…` (remplace l'image worktree `b7c276be…` après normalisation des fins de ligne et requalification complète). Sauvegarde privée vérifiée, préflight colonnes/contraintes PASS, aucune migration. 118 sessions et cache Chromium préservés, paramètres/configuration/autres services inchangés, ancien conteneur conservé arrêté. Contrôle séparé PASS : healthy, cinq hashes = Git publié, HTTPS public 200 et routes protégées 302/403. Aucun compte réel manipulé pour essai, aucun envoi externe ni merge main/production. **Reste la validation connectée du propriétaire**, puis réception réelle seulement avec destinataire expressément autorisé. [Rapport de livraison](../../qualification/PRODELI_RECOVERY_SMS_RELEASE_20261022.md).

### Historique de qualification avant autorisation/bascule (dépassé par la livraison ci-dessus)

**QUALIFIÉ SUR IMAGE EXACTE, NON DÉPLOYÉ/NON PUBLIÉ** : candidat `b7c276be…`, cinq fichiers runtime et 2 755 autres identiques ; 37 contrôles PG (concurrence OTP), dix groupes récupération/SMS, course HTTP native (un succès/époque 1, deux pannes de révocation avec rollback), 92 contrats et dix groupes natifs de non-régression PASS. Snapshot fictif inchangé, QA temporaire retirée et services de production préservés. [Rapport et limites](../../qualification/PRODELI_RECOVERY_SMS_20261022.md), [préflight et retour arrière compatible](../../../ops/releases/prodeli-recovery-sms-20261022/README.md). Prochaine action : autorisation spécifique de publication/déploiement, commit associé au manifeste, sauvegarde/préflight/bascule et recette finale. Aucun push/merge/déploiement ni envoi externe. Tableau de bord et parcours de création/accès initial conservés, ne pas refaire les lots livrés.

## Lots 2–3 ProDéli — raccord natif et premier accès livrés

**DÉPLOYÉ** source `3670dad176a4fe6932e69e87a481a5f205131543`, image `36a9e2fb…`, recette de préservation `0ab0690`. Raccord explicite sans plugin actif, profil confirmé/une école, transaction avec suivi/audit, remise unique/URL officielle, temporaire 24 h, changement personnel ciblé, renouvellement nonce/génération. 47 contrôles PostgreSQL fictif et dix groupes navigateur rejoués sur l'image exacte en QA VPS éphémère isolée, retirée ensuite ; 92 contrats, 27 régressions et restauration fictive de 118 tables PASS. 2 750 autres fichiers identiques. Sauvegarde privée de production validée, non restaurée pour un essai ; migrations additives 018/038, 120 sessions et cache Chromium préservés, bascule 15,31 s, contrôle indépendant PASS. [Rapport et rollback conditionnel](../../qualification/PRODELI_NATIVE_ACCESS_RELEASE_20261021.md). Acceptation du propriétaire reçue pour les lots 2–3 (« je valide »), sans nouvelle preuve de recette manuelle détaillée ; récupération et deux défauts SMS constituent le lot suivant, pas une clôture de tout ProDéli. Keycloak en pause, configuration communication et autres services inchangés.

## Historique — lot 2 ProDéli, procédure native qualifiée localement, raccord ouvert

`SchoolSafeCreateDirector()` : compte/rattachement/audit transactionnels, refus du remplacement implicite du principal, échappement Rosario corrigé et concurrence vérifiée. 29 contrôles sur PostgreSQL 17.11 fictif réel, 76 contrats de régression PASS. [Preuves et limites](../../qualification/PRODELI_NATIVE_DIRECTOR_TRANSACTION.md). **NON DÉPLOYÉ** ; contrôleur ProDéli/préparation/confirmation, droits HTTP/rejeu, premier accès et migration production à qualifier. Keycloak reste en pause ; aucune communication ni donnée réelle modifiée. Prochaine action : raccord natif explicite sans dépendance aux hooks du plugin désactivé ; le helper possède sa transaction.

## Tableau de bord central — complément navigation livré

Répertoire des établissements autorisés complet au lieu de huit lignes, recherche, ouverture par POST protégé puis `Side.php` natif, retour du portail scolaire vers ProDéli pour le global, comptes centraux directement accessibles sans nouveau menu. 20 contrats + huit groupes HTTP/PG fictif + 19 groupes de régression PASS ; cinq fichiers runtime, 14 composants protégés inchangés. [Rapport](../../qualification/PRODELI_NAVIGATION_RELEASE_20261021.md). **DÉPLOYÉ** source `3eb2e9e35984de9c40e22c571b212e727b13a2bb`, image `c8d10345…`. 76 contrats sur image exacte, 2 753 autres fichiers identiques, 163 sessions conservées ; contrôle indépendant PASS, aucun compte/école réel manipulé ni message externe. Validation connectée propriétaire attendue. Ce complément ne clôture pas création/rattachement/premier accès/récupération. Keycloak reste en pause.

## Priorité du propriétaire — finir les réglages Communication

Organisation Studio/messagerie Rosario, destinataires et répartition des notifications examinés et conservés ; Keycloak reste en pause. Finition de deux pages existantes SMS/WhatsApp et CSS dédiée, sans nouveau menu/fournisseur ni modification des handlers. 28 contrats, huit groupes HTTP/PG fictif, cinq régressions dashboard PASS. **DÉPLOYÉ** source `90f6238cfee03f81af19a0c3ff4f902f9c134c3e`, image `3daf00a1…` ; 56 contrats image exacte, 2 755 autres fichiers identiques, 169 sessions conservées, contrôle indépendant PASS. Configuration communication inchangée, pas d'envoi externe ni migration. Validation connectée propriétaire attendue ; [rapport et limites](../../qualification/PRODELI_COMMUNICATION_RELEASE_20261021.md). Ce sous-lot ne clôture pas les parcours de comptes ci-dessous.

## Priorité courante — achever ProDéli, mandat validé

[Plan exécutable et résultats](../../qualification/PRODELI_COMPLETION.md). Un seul tableau de bord SchoolSafe sans nouveau menu, réemploi Rosario ; administrateur principal, accès initial, récupération et communication existante conservée (SMS quatre réseaux, courriel, WhatsApp). Lot 1 **livré en production** : entrée globale canonique et commandes de dashboard sans menu, source `38856445143050895b6502c51be18285e9309042`, image `9177067d…`. 28 contrats image exacte, cinq cas d'entrée exécutés avec substituts, cinq groupes UI/HTTP/PG et six régressions PASS ; 2 754 autres fichiers identiques, 78 sessions conservées, autres services inchangés et vérification indépendante PASS. [Rapport](../../qualification/PRODELI_DASHBOARD_RELEASE_20261021.md). Création native/rattachement/premier accès/récupération **non terminés**, ne pas les présenter livrés avec cet accueil. Puis lots fonctionnels de création/rattachement et récupération ; ne pas confondre ce périmètre avec la livraison précédente ci-dessous. Handoff courant prioritaire.

## 21 octobre 2026 — Présentation ProDéli livrée, validation humaine attendue

Mandat direct de production reçu. Quatre fichiers isolés sans nouvelles mutations natives : populations scolaire/centrale, écoles/états/première connexion, suivi et navigation. Source `6e741ba8a289866dc443dc61bf4108a3ecb29672`, image `b45be4ea…`. 16 contrats + six groupes UI/HTTP/PG fictif ; image exacte vérifiée, 2 753 autres fichiers identiques. Production saine, 90 fichiers de session préservés, autres services inchangés. [Rapport](../../qualification/PRODELI_PRESENTATION_RELEASE_20261021.md). Prochaine action : validation connectée du propriétaire ; le grand lot local, concurrence/audit, fournisseur, logo et remise/récupération restent séparés. Lire le handoff courant, ne pas reprendre les anciennes consignes ci-dessous.

## Lot prioritaire du 28 septembre 2026 — Control → SchoolSafe

Statut : implémentation et qualification locales terminées, publication en PR pour revue et CI GitHub. Base SchoolSafe 575ddb1, branche feat/control-admin-onboarding-v1. Auth/v7 additive, 139 scénarios PostgreSQL réels et sept étapes sans code maître. [Contrat](../../CONTROL_ADMIN_ONBOARDING_V1.md). Prochaine action : vérifier la CI du SHA final et revoir les PR ; aucun merge, VPS ou déploiement dans ce mandat. Les anciens ordres ci-dessous sont historiques.

# SchoolSafe — intégration des fonctionnalités, Rôles et accès en premier

Date : 16 septembre 2026. Inventaire initial : `main`, `3ca73fa`.
**PLAN ACTIF COMMUN À TOUS LES AGENTS**, A1 `7b2896a` puis A2 livré le 16/09.
Le propriétaire autorise la poursuite étape par étape ; ce document organise cette
exécution. Les choix techniques proposés restent distincts des règles produit validées.

## Reprise en cinq minutes

**ACTIVE — 19/09 : base de développement validée, publication autorisée uniquement
sur `develop/codex-base` dans `medygoo/schoolsafe-stable`.** Conserver les 772
fichiers et 14 exclusions ; pas de main, déploiement ni VPS. Après vérification
du SHA distant et de l'existence de la branche, arrêter. SQL et Docker restent
à qualifier dans des lots séparés ; le handoff précise les défauts ouverts.
Les anciens arrêts avant publication ci-dessous sont historiques.

**ACTIVE — 19/09, préparation Stable : manifeste validé, copie locale préparée
pour revue ; aucun push ni déploiement.** Voir
[la sélection finale et ses preuves](../../migration/2026-09-19-stable-preparation/README.md).
JASPE/cartes intégraux, six portraits sans preuve d'usage exclus de la copie,
Docker/AGENTS corrigés, registre SQL exhaustif avec blocages explicites.
Build Docker réel non exécuté ; installation globale non validée. Les alertes
statiques restent inchangées. Prochaine tâche : revue de la copie et des réserves,
puis lots additifs d'installation séparés ; Control attend sa source existante.
Les anciens ordres ci-dessous sont historiques et n'autorisent aucun push.

**HISTORIQUE — arbitrage désormais validé :**

**Arbitrage final du 19/09 : proposition livrée, ATTENTE DE VALIDATION.**
737 fichiers SCHOOLSAFE, 0 CONTROL, 16 ARCHIVE, 8 EXCLURE ; six documents
de migration séparés. Les adaptateurs Control restent côté SchoolSafe.
JASPE/cartes/ACCESS_LAW conservés ; scan textuel sans motif de secret détecté,
provenance des illustrations de connexion encore à confirmer. Ne copier,
fusionner, migrer, committer, pousser ou déployer aucun élément à cette étape.

**Mission du 19/09 — base stable, prioritaire pour cette reprise :** préparer
le [manifeste exact des fichiers](../../migration/2026-09-19-base-stable/README.md)
depuis `main` / `841d3ef`, préserver cartes et JASPE avec leurs dépendances.
Travail documentaire uniquement : aucune suppression, migration, fusion,
publication ni déploiement. Les prochains lots applicatifs restent inchangés.
La publication conditionnelle proposée ci-dessous est suspendue par ce mandat.
**Livré : manifeste préparatoire**, 761 fichiers classés, 753 proposés à copie
identique ; dépendances cartes/JASPE et annexe du worktree documentées.
Intégrité 12 manifestes/43 unités et assets vérifiée ; aucune recette fonctionnelle
nouvelle. Prochaine étape : arbitrer les lignes À REVOIR avant une migration
distinctement autorisée. Aucun fichier supprimé, code changé, commit ou push.

**Revue finale du 19/09 :** complément A5.1 revu sans défaut bloquant identifié ;
commit local autorisé. **Push suspendu** : absence de déploiement automatique
GitHub/Coolify non vérifiable avec les accès disponibles. Distant `main`
effectivement lu : `62df2b16ceca2e609c1789eba44afbe6fe278e19`, inchangé.
Bolt doit d'abord obtenir cette preuve de configuration, puis recontrôler le
distant avant publication sans force push. Les deux alertes statiques restent
actives et à traiter séparément. Aucun déploiement ni validation globale.

**Historique — reprise du 19/09, remplacée par la revue ci-dessus :** A5.1 ciblé terminé localement, sans commit/push/déploiement.
Complément après revue : DENY school/none avec cible renseignée corrigé ;
39 tests frontend, 36 scénarios SQL A5.1, 47 A5.0 et huit parcours réels
rôle/exception × portée × cible PASS. Revue finale du complément à faire.
Voir la preuve et le bloc courant du handoff. Le mandat interdit de poursuivre
automatiquement l'éditeur d'exceptions ou les travaux visuels. Les ordres Git
historiques ci-dessous sont suspendus pour cette mission.

**Historique — interruption produit du 16/09, remplacée pour cette reprise :** le propriétaire demande d’abord le
bandeau JASPE compact avec bureau occasionnel, cartable et logo réel de l’école.
Travail préparatoire uniquement, non intégré. Suivre le bloc courant du handoff
et `docs/design/jaspe-cartable-v1/REPRISE.md`, puis revenir à **A5.1** ci-dessous.

1. Lire `AGENTS.md`, `docs/PROJECT_CONTEXT.md`, `docs/DECISIONS.md`, puis tout le
   handoff. Seul le bloc en tête de `docs/CURRENT_HANDOFF.md` décrit la reprise
   active ; les anciens « prochaine action » appartiennent à l'historique.
2. Exécuter `git status --short`, `git branch --show-current`, `git fetch origin`,
   `git rev-parse HEAD` et `git rev-parse origin/main`. Si la branche locale est
   seulement en retard et sans changement à préserver : `git pull --ff-only`.
   En cas de divergence ou de modifications non attribuées, identifier leur
   propriétaire et préparer l'intégration ; ne pas écraser ni forcer un push.
3. Lire le tableau de suivi ci-dessous, puis la fiche détaillée de la première
   tâche incomplète. Vérifier ses prérequis dans le code, pas uniquement ici.
4. Inscrire dans le bloc courant du handoff : identifiant de tâche, agent, branche,
   SHA de départ, objectif vérifiable et fichiers réservés. Un seul propriétaire
   écrit le lot. Les noms d'agents ne donnent aucun droit exclusif permanent.
5. Réaliser le lot, exécuter ses contrôles, relire le diff, actualiser plan/handoff,
   committer, pousser et comparer le SHA distant réel. Continuer à la tâche suivante
   si le mandat le prévoit ; laisser une reprise exacte à chaque interruption.

Le travail reste séquentiel sur `main` par défaut. Si un travail simultané est
explicitement coordonné, employer des branches distinctes (préfixe `codex/` pour
Codex), des périmètres sans conflit et une intégration revue. `app/app.js`,
`app/index.html`, le catalogue et les migrations n'ont qu'un rédacteur à la fois.
Ce plan ne demande ni outil d'agent particulier ni création automatique de tâches.

## Tableau de suivi — à mettre à jour à chaque lot

Statuts : **À FAIRE**, **EN COURS**, **CODE VÉRIFIÉ** (préciser les substituts),
**PARCOURS VALIDÉ** (frontend + serveur + PostgreSQL de test réels), **BLOQUÉ**
(preuve et condition de reprise). La production reste une validation séparée.
Un blocage SQL n'interdit pas le travail indépendant, mais interdit de déclarer le
parcours réel terminé. Aucune estimation de pourcentage ne remplace ces preuves.

| ID | Livrable | Prérequis | Statut / preuve |
| --- | --- | --- | --- |
| A1 | Session native et droits du compte connecté | Existant | CODE VÉRIFIÉ · `7b2896a` · API/PG substitués |
| A2.0 | Contrat de lecture IAM et préparation de la preuve SQL | A1 | PARCOURS VALIDÉ · contrat et runner PostgreSQL 17.11 réels |
| A2.1 | Projections et API natives des profils/rôles de l'école | A2.0 | PARCOURS VALIDÉ · SQL sous schoolsafe_api, 2 écoles, assemblage natif |
| A2.2 | Liste réelle et détail d'accès dans la console existante | A2.1 | PARCOURS VALIDÉ · navigateur/cookie/API/PG réels, bureau/mobile, révocation |
| A3.0 | Contrat de modification et limites de délégation | A2.2 | PARCOURS VALIDÉ · contrat et migrations additives |
| A3.1 | Attribution/révocation de rôles existants, persistantes | A3.0 | PARCOURS VALIDÉ · SQL/API/UI réels, concurrence, audit et JASPE |
| A4 | Création de postes et composition des permissions | A3.1 | PARCOURS VALIDÉ · création/copie/composition/attribution, DENY, audit, SQL/API/UI réels |
| A5 | Périmètres, conditions et exceptions individuelles | A4 | EN COURS · A5.0 temporel validé ; correctif A5.1 ciblé validé localement (ligne suivante) ; éditeur complet hors lot, restant à planifier |
| A5.1 ciblé | Raccordement et cohérence des refus ciblés | A5.0 | PARCOURS VALIDÉ LOCAL · 19/09, complément résiduel inclus : 36 scénarios SQL, 39 tests frontend, huit combinaisons HTTP/UI réelles, A5.0 47/47 ; [preuve et limites](../specs/2026-09-19-a51-denial-contract.md) ; revue finale achevée, commit local autorisé, push suspendu faute de garantie contre un déploiement automatique ; deux contrôles statiques préexistants hors lot |
| A6 | Journal d'accès et révocation effective des droits | A3.1, A5 | À FAIRE |
| A7 | Recette complète Rôles et accès, sans régression | A2 à A6 | À FAIRE |
| B à K | Autres fonctions et raccordements, voir séquence finale | A7 puis dépendances | À FAIRE |

Responsable du dernier lot : Codex, A5.1 ciblé sur `main`, base `62df2b1` ;
correctif local terminé, défaut résiduel traité, dernière revue achevée.
Prochaine étape pour Bolt : vérifier les déclencheurs GitHub/Coolify avant
tout push, puis traiter les contrôles statiques préexistants dans un autre lot.
Historique remplacé : revue finale du complément à faire et publication non
autorisée ; le dernier mandat autorise le commit et conditionne le push à une
preuve encore manquante. Historique : première validation à
28 scénarios SQL, puis revue ayant identifié le cas school/none avec cible renseignée.

Historique remplacé pour la reprise : Codex, A5.0 sur `main`, base applicative `a5037ce` ; lot clôturé.
Ancienne consigne (obsolète) :
A5.1 est la prochaine tâche à réserver dans le handoff. La source du
SHA d'un lot est Git ; ne pas essayer d'inscrire un commit dans son propre contenu.

## Mandat et état

Le propriétaire demande de compléter les interfaces, les backends et les liens
avec l'écosystème des 16 rubriques ci-dessous. Il précise ensuite : **commencer
par Rôles et accès, corriger et rendre fonctionnel, conserver l'existant, avancer
étape par étape**. Cette priorité remplace le démarrage immédiat par les appareils.

Le présent suivi complète la roadmap de livraison du 13/09. Les autres modules
ne sont pas déclarés terminés par la présence d'un écran ou d'un fichier serveur.
Le miroir concerne le code et la documentation versionnés, pas les bases métier,
les secrets, ni une mise en production automatique.

## Première séquence : Rôles et accès

### A1 — contexte natif et consultation des droits : livré, preuves avec substituts

- Défaut reproduit avant correction : `getCurrentUser()` renvoyait `demo-school-1`
  pour une session native valide sans bearer. La navigation, les documents,
  plusieurs modules et JASPE pouvaient ainsi utiliser un contexte démonstratif.
- Session native reconnue par son marqueur `native`, et non par la présence d'un
  token JavaScript. Le cookie HttpOnly reste l'autorité de session côté serveur.
- Console native : ouverture/actualisation → `GET /native/session/bootstrap` →
  identité/école/rôles/permissions/portées/refus réels du compte → catalogue local
  pour les libellés → lecture filtrable. Aucune nouvelle route ni nouvelle table.
- Le rôle affiché « admin » ne donne aucun droit implicite. La console requiert
  `roles.manage` avec portée `school`, et un refus explicite l'emporte.
- Les différentes cibles d'une même permission restent visibles. « Attribuée »
  décrit le paquet de session ; l'autorisation d'une action précise reste soumise
  aux conditions et aux contrôles serveur. Les UUID sont affichés faute de
  projection sûre des libellés de cibles dans ce lot.
- Une erreur ne réaffiche pas d'anciens droits. Refus/expiration → reconnexion ;
  changement d'école/profil inattendu → rejet ; réponse tardive après fermeture
  → ignorée. Le cache du catalogue peut être retenté après un échec de chargement.
- Ancien éditeur conservé en démonstration. Aucun de ses brouillons ne devient
  une attribution réelle. Le bouton de brouillon refuse toute session réelle.

Sources principales : `app/app.js`, `app/modules/administration/access-console.js`,
`app/modules/core/access.js`, `server/src/sessionnative/routes.ts`,
`server/src/sessionnative/service.ts`, `database/projections/v1/02_session_bootstrap.sql`.

Preuves : `app/qa-native-access-console.cjs` (échec initial puis réussite),
contrôles Access Law/JASPE, 23 tests serveur ciblés, contrats permissions/isolation,
régressions paiement/campagnes et dashboard JASPE. API navigateur et dépendances
PostgreSQL substituées : **aucune validation sur une base réelle annoncée**.

### A2 — consulter les profils de l'école et leurs attributions : livré

Contrat exact, corrections, protocole rejouable et limites :
[annuaire natif des accès](../specs/2026-09-16-access-directory-contract.md).
Les trois routes de lecture sont assemblées et les vues Mon compte/Utilisateurs/
Rôles sont raccordées. Preuve sur PostgreSQL 17.11 temporaire, distincte de la
production. Correction du rôle désactivé encore autorisant, des gardes SQL
60/64 et du CORS 4176 ; aucun droit attribué ou supprimé par l'interface.

Le résumé initial est conservé comme trace du travail prévu. Son exécution est découpée en A2.0,
A2.1 et A2.2 dans les fiches ci-dessous ; ne pas considérer ce résumé comme un
second plan à mener en parallèle.

1. Relire la console et l'Administration existantes : `staffSamples` reste une
   fixture, et `SchoolSafeSchoolAPI` appelle encore `/school/staff` et
   `/school/roles`. Ces routes legacy ne sont pas assemblées par `buildNativeApp`.
2. Réutiliser `iam.profiles`, `iam.roles`, `iam.profile_roles`, les grants et leurs
   portées. Distinguer profil d'accès et futur dossier RH ; ne créer aucun doublon
   de personne ou second moteur de permissions.
3. Préparer une projection native de profils/rôles/grants lisible uniquement sous
   `roles.manage` dans l'école résolue par le serveur. Vérifier les champs utiles,
   la pagination et les cibles ; ne transmettre aucun secret d'authentification.
4. Brancher la liste de personnes existante sur cette projection ; conserver les
   états chargement, erreur, vide et refus. Ne pas remplacer une réponse vide par
   les noms de démonstration. Faire apparaître le lien rôle → droit → périmètre.
5. Prouver : deux écoles, utilisateur sans droit, DENY, profil d'une autre école,
   changement de session pendant chargement. Valider aussi les migrations sur une
   base de test isolée avant de prétendre que le parcours SQL fonctionne.

### A3 — attribution et révocation persistantes : résumé

Le découpage détaillé A3.0/A3.1 puis A4–A7 précise cette suite sans supprimer le mandat.

- Relire les règles canoniques et les fonctions IAM avant de définir le contrat
  de mutation. Réutiliser la transaction et l'audit existants ; ne pas écrire
  directement dans les tables depuis le navigateur ou rejouer un brouillon offline.
- Encadrer les rôles attribuables et les cibles de portée. Les permissions Control
  ne sont pas accordables depuis la console d'une école. Aucun paramètre client
  ne remplace le contexte scolaire résolu par la session.
- Définir et tester la protection contre la perte du dernier administrateur, les
  modifications concurrentes, l'expiration/révocation et les tentatives d'élévation.
  Les détails non décrits dans les règles existantes sont à expliciter dans le lot.
- Enregistrer atomiquement, journaliser acteur/cible/changement, puis relire les
  droits. Reconnexion et changement d'école ne doivent pas restaurer un ancien droit.
- Ensuite seulement : restrictions individuelles/conditions avancées et inspection
  des décisions d'accès pour les autres modules, sans moteur parallèle.

## Cartographie vérifiée du menu

État observé dans le code au 16/09, pas un pourcentage de disponibilité en production.
Les liens écosystème indiquent la cible de raccordement, pas une intégration livrée.

| Rubrique | Existant et raccordement restant | Liens à préserver / construire |
| --- | --- | --- |
| Pilotage | UI + client `/pilotage/*` ; services legacy non assemblés en natif | Projections des modules, alertes, Control selon autorité |
| École | Client natif élèves pour liste/lecture/brouillon ; structure/familles encore partiellement démo ou legacy | SchoolSafe ID, Guardian, cartes/Pass |
| Personnel | UI RH démonstrative ; comptes IAM existants ; dossiers RH/présences à persister | SchoolSafe ID, StaffID, Device Hub pour les événements |
| Pédagogie | Backend `/native/pedagogy/*` assemblé ; client encore `/pedagogy/*` et portail démo | Classes/élèves, enseignants, Parent, documents |
| Sécurité et contrôle | UI scanner/portail ; backend sécurité legacy non assemblé en natif | Pass, Guardian, photos/validation humaine, événements Device Hub |
| Finance | Backend `/native/finance/*` assemblé ; client encore `/finance/*` | Élève, caisse, reçu, statut limité Parent/Pédagogie, Comptabilité |
| Comptabilité | Synthèses/trésorerie de démonstration ; persistance métier à compléter | Finance, justificatifs, rapprochements ; pas de soldes inventés |
| Stock / Inventaire | Catalogue/mouvements/achats démonstratifs ; backend à construire | Achats, Comptabilité, équipements Lab selon périmètre produit |
| Communication | Brouillons locaux ou de session ; services notifications/email existants non raccordés à ce parcours natif | Parents/personnel, annonces, alertes et documents |
| Administration | Écrans existants ; comptes/rôles appellent le client École legacy | SchoolSafe ID, Rôles et accès, paramètres ; Control reste distinct |
| Contrôle et rapports | Entrée de menu encore « prochaine étape » | Projections autorisées des modules, exports, audit |
| Centre de documents | Registre d'aperçus et contrôles frontend existants ; sorties officielles à raccorder par domaine | Tous les modules ; impression de cartes via Control |
| Paramètres | Entrée système encore « prochaine étape » ; préférences et réglages dispersés existants | École, années/classes, comptes, intégrations |
| Rôles et accès | A1/A2 livrés : compte et annuaire natif ; modifications persistantes restent A3–A6 | Tous les modules, SchoolSafe ID ; JASPE limité aux mêmes droits |
| Audit et journaux | Entrée système encore « prochaine étape » ; tables/triggers/services existants | Actions sensibles, changements de droits, corrections traçables |
| Intégrations | Entrée encore « prochaine étape » ; connecteurs hétérogènes présents | Device Hub, notifications, stockage, Control ; secrets côté serveur |

Preuves de raccordement : `server/src/native-app.ts` est l'assemblage natif actif ;
`server/src/app.ts` n'enregistre les routes legacy que si leurs dépendances sont
fournies. Les clients API sont sous `app/modules/`. Les entrées système et la
navigation sont dans `app/app.js` ; les cartes écosystème dans `app/index.html`.

Attention : les cartes Sécurité, Cartes élèves et Guardian dirigent actuellement
toutes vers le module Sécurité. Leur badge « Actif » ne prouve pas un parcours
complet. StaffID, Watch et Lab restent marqués « Bientôt ». La description Lab
dans l'interface (« expérimentations pédagogiques ») diffère de la référence du
propriétaire (« équipements ») : conserver et préciser le périmètre au lot Lab.

## Ordre après les accès et conditions de clôture

Ordre de travail recommandé : École/identités/classes/tuteurs → Personnel de base
→ Sécurité/Pass/Guardian et présences → Finance/Comptabilité → Pédagogie →
Communication/documents → Stock/Inventaire. Pilotage et rapports sont raccordés
progressivement aux sources réellement prêtes. Paramètres, audit et intégrations
accompagnent chaque lot ; les libellés du menu ne sont pas réordonnés ici.

Chaque lot est fermé uniquement après un parcours frontend → session/permission
serveur → stockage → relecture, ses tests critiques, le handoff, le commit et le
push avec égalité vérifiée du SHA local/distant. Une API ou base substituée est
signalée ; elle ne suffit pas à déclarer une mise en production prête.

La validation structurelle Device Hub demandée au §56 reste distincte et en
attente ; voir `docs/superpowers/specs/2026-09-16-schoolsafe-device-hub-audit-design.md`.
Les incohérences SQL d'admission et le rejeu/restauration P5 restent ouverts.
Watch, la refonte de JASPE et le déploiement ne sont pas des dépendances d'A1.
Ce lot ne change aucune décision d'exploitation ni configuration VPS.

## Fiches d'exécution — chantier Rôles et accès

**Clôture A2 le 16/09 :** les fiches A2.0–A2.2 ci-dessous conservent les critères
initiaux ; leur réalisation et leurs choix exacts sont consignés dans le contrat
lié ci-dessus. Les fichiers/URLs proposés dans ces fiches existent désormais.
Reprendre A3.0, sans refaire le travail A2.

### A2.0 — verrouiller le contrat de lecture et la préparation SQL

**Résultat attendu :** l'agent suivant sait exactement quelles données lire et
comment prouver le parcours sans dépendre de fixtures ni d'une table parallèle.

**Lire / vérifier :**

- `database/baseline/v1/05_iam.sql` : profils, rôles, attributions, grants,
  conditions, exceptions et portées. Les portées d'un grant de rôle s'appliquent
  au rôle : ne pas les modifier pour restreindre une seule personne.
- `database/baseline/v1/08_internal_functions.sql`,
  `database/baseline/v1/09_api_rpc.sql`, `database/baseline/v1/10_triggers.sql`,
  `database/baseline/v1/11_rls_acl.sql`, `database/access/v1/01_role_templates.sql`.
- `database/projections/v1/02_session_bootstrap.sql`, `shared/permissions.json`,
  `server/src/db/context.ts`, `server/src/db/access.ts`,
  `server/src/authnative/middleware.ts` pour la résolution de la session côté serveur.
- `server/src/school/service.ts` et `app/modules/administration/administration-demo.js`
  pour comprendre l'ancien contrat, sans réactiver globalement les routes Supabase.

**Travail :**

- [x] Décrire ici le contrat retenu : liste paginée des profils de l'école,
  détail des attributions d'un profil, rôles disponibles et catalogue délégable.
  Nom proposé pour le backend : `accessnative`, conforme aux services natifs existants.
- [x] Prévoir les routes de lecture `/native/access/profiles`,
  `/native/access/profiles/:profileId` et `/native/access/roles` ; confirmer les
  noms après inventaire des routes, sans créer de doublon. Aucune route livrée par ce plan.
- [x] Limiter les sorties à l'identifiant du profil, nom d'affichage, état du compte,
  rôles, grants, portées et exceptions utiles. Ne pas joindre contacts, secrets,
  paiements ou dossiers d'enfant pour remplir une liste d'accès.
- [x] Conserver l'acteur authentifié dans le contexte serveur. Le profil consulté
  est une cible, jamais un acteur usurpé via un changement de contexte de session.
  Un relevé d'attributions n'est pas une simulation complète des accès effectifs.
- [x] Fixer filtres, tri stable, pagination bornée, champs de réponse et erreurs :
  401 sans session, 403 sans droit, 404 pour une cible inexistante ou hors école.
- [x] Identifier un PostgreSQL de **test isolé**, sa version, les extensions et
  l'ordre d'application réel des manifestes. Ne jamais afficher les credentials.
  À défaut d'instance, documenter exactement ce qui manque et préparer les tests
  indépendants ; ne pas transformer leur réussite en preuve SQL.
- [x] Inventorier les SQL hors manifeste : notamment
  `database/projections/v1/02_student_list.sql`, absent du manifeste actuel des
  projections. Le contrôle des hashes ne détecte pas ce fichier non déclaré.
  Distinguer les anomalies bloquant IAM de celles à corriger au futur lot École.

**Limite d'exploitation :** les scripts historiques
`database/access/v1/scripts/run-ephemeral.sh` et
`database/baseline/v1/scripts/run-from-zero-test.sh` utilisent Docker. Leur présence
ne vaut pas autorisation d'en ajouter. Préparer un chemin PostgreSQL direct pour
le test, ou réutiliser une cible de test déjà autorisée. Conserver les scripts.

**Fin de tâche :** contrat de lecture précis et protocole SQL reproductible décrits
dans cette fiche/handoff ; blocages identifiés. A2.1 peut être développé avec des
substituts, mais ne reçoit pas le statut PARCOURS VALIDÉ sans rejeu PostgreSQL.

### A2.1 — construire les projections et routes de lecture natives

**Fichiers existants à raccorder :** `server/src/app.ts`, `server/src/native-app.ts`,
`server/src/db/access.ts`, `server/src/db/context.ts`. Nouveaux fichiers proposés :
`server/src/accessnative/service.ts`, `server/src/accessnative/routes.ts`,
`server/tests/accessnative.test.ts`. Choisir l'unité SQL versionnée après A2.0,
puis mettre à jour son manifeste et le contrôleur de versions si nécessaire.

- [x] Écrire les tests de refus avant la lecture : session absente/invalide,
  `roles.manage` refusé même avec un rôle nommé admin, portée insuffisante.
- [x] Résoudre user/profile/school depuis `request.authSession`, valider les
  entrées avec les conventions existantes, utiliser `withAuthorizedContext`.
- [x] Appeler une RPC `api.*` qui contrôle aussi l'accès et filtre explicitement
  l'école. Aucun `SELECT` IAM arbitraire privilégié depuis le navigateur.
- [x] Borner également les totaux, jointures, rôles, grants, cibles et exceptions
  à l'école. Tester une recherche/pagination qui ne révèle aucun profil voisin.
- [x] Assemblage réel dans `buildNativeApp` ; tester une requête HTTP sur cet
  assemblage, pas seulement une méthode service appelée directement.
- [x] Tester succès/vide/erreur, cible de l'autre école, paramètre `school_id`
  falsifié, rollback en cas d'erreur et absence de données d'authentification.
- [x] Rejouer SQL sur la cible de test, avec les véritables rôles DB et deux
  écoles synthétiques ; conserver une preuve sans données privées.

**Fin de tâche :** les routes listent seulement les profils/rôles autorisés,
avec réponse stable et preuve explicite du niveau réellement testé.

### A2.2 — raccorder les personnes et leurs attributions dans la console

**Fichiers :** `app/modules/administration/access-console.js`, `app/app.js`,
`app/index.html`, `app/styles/modules/native-access.css`,
`app/qa-native-access-console.cjs`. Ajouter un client natif dédié seulement si
nécessaire ; suivre `app/modules/authnative/auth-native.js` pour l'origine API,
le cookie et les erreurs. Conserver l'Administration existante et ses points d'entrée.

- [x] Garder « Mon compte » et ajouter la liste réelle paginée avec recherche.
- [x] Sélectionner une personne → lire ses rôles/grants/refus/périmètres. Le texte
  distingue attributions stockées et décision effective pour une action donnée.
- [x] Traduire les cibles en libellés uniquement via une projection autorisée ;
  garder un identifiant explicite lorsqu'un libellé n'est pas encore disponible.
- [x] Afficher chargement, liste vide, erreur avec réessai et accès refusé ; ne
  jamais remplacer une réponse vide/échouée par `staffSamples`.
- [x] Invalider les réponses tardives sur fermeture, changement de sélection,
  de compte ou d'école. Une sélection rapide A → B ne doit pas réafficher A.
- [x] Tester bureau/mobile, clavier, clair/sombre, recherche, pagination,
  expiration et perte de `roles.manage` pendant l'affichage.

**Fin de tâche :** un administrateur autorisé consulte une personne réelle de
son école depuis l'UI, sans fuite ni mutation. La démonstration reste disponible
uniquement dans son contexte explicite.

### A3.0 — préciser les modifications autorisées avant de les coder

**Produit déjà validé le 13/09 :** l'administrateur principal peut créer des
postes et composer leurs permissions/portées dans l'enveloppe Control/Access Law.
Les quatre expériences adultes ne limitent pas le nombre de postes internes.

- [x] Relire les règles actives, les modèles de rôle et la séparation d'autorité
  Control. Décrire quels rôles/permissions sont attribuables et par qui, sans
  inventer un droit de délégation à partir d'une case frontend.
- [x] Définir les invariants du dernier administrateur autorisé, l'auto-retrait,
  les comptes suspendus, les attributions futures/expirées et les rôles inactifs.
- [x] Définir contrôle de concurrence et précondition de version : une écriture
  depuis un écran ancien doit échouer proprement, pas écraser un changement récent.
- [x] Choisir une protection transactionnelle commune à l'école pour l'invariant
  du dernier administrateur ; verrouiller seulement la personne modifiée ne suffit
  pas si deux retraits concurrents visent deux personnes différentes.
- [x] Décrire confirmation humaine **dans l'application**, résumé avant/après,
  motif, audit et effet sur la session cible. Cela ne signifie pas demander au
  propriétaire une nouvelle permission pour chaque modification de code autorisée.
- [x] Si une règle produit reste indécidable après lecture des sources, préparer
  les choix concrets et demander seulement cet arbitrage ; continuer les tâches
  indépendantes. Les choix déjà validés ne sont pas soumis à une deuxième approbation.

**Fin de tâche :** contrat des mutations et cas de refus inscrits dans le plan.
Les choix techniques sont documentés comme tels ; les nouveaux arbitrages produit
ne sont marqués validés qu'après une réponse explicite du propriétaire.

### A3.1 — attribuer ou retirer un rôle existant

**Fichiers :** mêmes couches que A2, RPC IAM versionnée, triggers d'audit existants,
tests serveur/SQL et console. Réutiliser `iam.profile_roles` et ses dates/états.

- [x] Tests de refus : autre école, rôle Control/rôle inactif, absence de droit,
  tentative d'élévation hors enveloppe, requête périmée et retrait du dernier admin.
- [x] Transaction unique : autorisation courante → contrôle des invariants →
  attribution/désactivation → audit → résultat relisible ; rollback intégral sinon.
- [x] Préserver l'historique par les mécanismes d'état/révocation ; ne pas copier
  l'ancien schéma Supabase de suppression complète puis réinsertion hors transaction.
- [x] UI : modifications explicites, confirmation avant/après, envoi unique,
  erreur conservant le formulaire, succès uniquement après réponse serveur.
- [x] Après mutation : relire les attributions ; vérifier une nouvelle requête
  de la personne cible. Aucun ancien droit ne doit survivre dans l'autorité serveur.
- [x] Tester double soumission, deux changements concurrents, erreur d'audit,
  reconnexion et changement d'école. Ne pas placer ces mutations dans la file offline.

**Fin de tâche :** attribuer puis retirer un rôle modifie réellement la base de
test, les accès et l'audit ; le parcours survit à un rechargement.

### A4 — créer des postes et composer les permissions

**Réutiliser :** `iam.roles`, `iam.role_permission_grants`, catalogue canonique,
modèles `iam.role_templates` et console existante. Ne pas éditer les modèles
souverains pour personnaliser un poste d'école.

- [x] Créer un poste à partir d'un modèle ou d'une composition vide, puis modifier
  son nom et ses permissions autorisées. Identité du rôle stable ; libellé libre
  ne créant ni endpoint, ni permission, ni code exécutable.
- [x] Montrer le lien entre permissions, rubriques et services de l'écosystème.
  Distinguer droit de l'utilisateur, autorité Control et activation commerciale
  d'un service. SchoolSafe ID reste le socle permanent.
- [x] Avant modification d'un rôle partagé, montrer les profils affectés et
  confirmer l'impact ; ne pas changer tous ses membres par une action présentée
  comme une restriction d'une seule personne.
- [x] Autoriser uniquement des codes canoniques et délégables ; valider côté serveur
  chaque code, portée, date et cible. Tester payload forgé et permission Control.
- [x] Auditer création/modification/désactivation ; préserver les références et
  l'historique. Vérifier deux rôles cumulés et le maintien de la priorité des DENY.

**Fin de tâche :** un poste personnalisé est créé, composé, attribué et relu,
avec effets prouvés sur les actions autorisées, sans rôle codé en dur dans l'UI.

Livré le 16/09 : [contrat et preuves A4](../specs/2026-09-16-custom-roles-contract.md).
Les nouvelles portées simples sont explicites ; les cibles/dates/conditions
existantes sont conservées et leur modification attend A5. Les modèles canoniques
restent protégés. Upgrade A3 sans changement des données, 90 tests serveur,
38 contrats statiques, QA A1/A2/A4 et parcours réel PostgreSQL/API/UI PASS.

### A5 — périmètres et exceptions individuelles

**Réutiliser :** `iam.grant_scopes`, `iam.permission_conditions`,
`iam.profile_permission_exceptions`, `iam.exception_scopes` et l'évaluateur IAM.

A5.0 livré le 16/09 : migration additive `projections/v1/05_session_validity.sql`,
ancienne unité 02 intacte. Dates/états rôle/grant/portée/exception et rattachements
enseignants/enfants/portails corrigés. 47 scénarios réels, 44 contrats statiques,
installation de 25 unités et upgrade sans changement de données PASS.
**Mise à jour 19/09 :** raccordement et projection A5.1 corrigés et vérifiés
localement ; voir [la preuve dédiée](../specs/2026-09-19-a51-denial-contract.md).
L'édition complète demeure hors mandat. A5 complet n'est pas clôturé.

**Historique — consigne remplacée pour la projection, conservée pour l'édition : A5.1 reste à faire** : projection exacte des DENY ciblés/conditions puis édition
des restrictions/conditions/exceptions. Ne pas cocher A5 grâce au seul correctif temporel.

- [ ] Supporter les portées prévues par le catalogue avec cibles multiples
  (école, soi, enfants rattachés, classes, matières, portail, appareils gérés).
  Ne jamais déduire le périmètre réel de la seule portée par défaut du catalogue.
- [ ] Restreindre une personne via le mécanisme individuel approprié, sans changer
  le grant partagé de son rôle. Afficher la provenance rôle/exception du droit.
- [ ] Exiger motif et dates cohérentes pour une exception ; traiter expiration,
  révocation, cible supprimée/inactive et refus explicite dans le même moteur.
- [ ] N'exposer que des conditions déjà supportées par le serveur. Une condition
  inconnue n'est jamais ignorée silencieusement ou exécutée comme du code libre.
- [ ] Tester : enseignant hors classe/matière, parent sur enfant non rattaché,
  gardien hors portail, plusieurs portées, refus prioritaire et exception expirée.

**Fin de tâche :** restrictions et exceptions donnent la même décision dans les
parcours serveur, la projection de session et les affichages de la console.

### A6 — audit et révocation effective

- [ ] Vérifier les événements de `audit.capture_access_change()` et `audit.events` :
  acteur, école, cible, avant/après utiles, motif, date et corrélation. Ne pas
  dupliquer des événements si le trigger les écrit déjà.
- [ ] Ajouter une lecture bornée au journal des accès avec permission canonique
  adaptée. Si aucun code n'existe, traiter explicitement son ajout au catalogue,
  au seed, aux modèles et aux tests ; ne pas inventer un littéral isolé.
- [ ] Relier la console à « Audit et journaux » sans ouvrir les journaux d'autres
  écoles ou les données sensibles sans rapport avec les attributions.
- [ ] Vérifier les sessions déjà ouvertes, le cache, la déconnexion/reconnexion
  et plusieurs onglets après révocation. Les écritures serveur doivent revérifier
  l'autorisation courante, indépendamment d'un menu ancien encore visible.
- [ ] Protéger aussi les lectures et les documents téléchargés ; ne pas se limiter
  à masquer des boutons. JASPE conserve le même plafond d'autorisation.

**Fin de tâche :** une attribution et son retrait sont traçables et la permission
retirée échoue réellement sur les routes concernées, même depuis un ancien écran.

### A7 — recette du module et transfert vers les autres fonctionnalités

- [ ] Deux écoles synthétiques, profils admin limité/enseignant/gardien/parent,
  poste personnalisé, plusieurs rôles, cible autorisée et cible hors périmètre.
- [ ] Parcours complet : consulter → créer un poste → composer → attribuer →
  restreindre → vérifier l'action cible → révoquer → vérifier le refus → lire l'audit.
- [ ] Tester ce parcours avec l'API **et PostgreSQL réels de test**, puis avec
  session expirée, refus, panne, écriture concurrente et rechargement.
- [ ] Rejouer A1 et les protections déjà livrées. Tester les actions métier sur
  les routes natives déjà raccordées ; ne pas déclarer les 15 autres rubriques
  opérationnelles simplement parce que les permissions fonctionnent.
- [ ] Vérifier desktop/mobile/clavier/clair/sombre et les données montrées par
  JASPE/documents. Maintenir un état indisponible honnête pour les modules non raccordés.
- [ ] Mettre à jour les preuves, limites, SHA, cartographie et prochain lot.

**Fin de chantier accès :** toutes les conditions A2–A6 ont une preuve réelle de
test ; aucun point critique ouvert sur fuite inter-écoles, élévation ou perte des
droits serveur. La disponibilité en production exige encore le lot d'exploitation.

## Commandes de vérification réutilisables

Depuis la racine, Node.js ≥22 et dépendances verrouillées disponibles. Exécuter
les commandes proportionnées au lot ; vérifier chaque code de sortie séparément.
Ne pas relancer toute la batterie pour une modification documentaire seule.

```text
node --test scripts/permission-contract.test.mjs scripts/cross-school-static.test.mjs
node scripts/check-migration-versions.mjs
npm run typecheck
npm run test --workspace server -- tests/sessionnative.test.ts tests/auth-session.test.ts tests/authnative-profiles.test.ts tests/db-context.test.ts tests/native-access-contract.test.ts
node app/qa-native-access-console.cjs
node app/qa-safe-assistant-access.cjs
node app/qa-responsive-visual-system.cjs
git diff --check
```

Ajouter les nouveaux tests `accessnative` dès A2.1 ; les commandes ci-dessus ne
couvrent pas automatiquement le code à venir. Le navigateur utilise par défaut
`http://127.0.0.1:4176/` (variable `SCHOOLSAFE_URL`), une prévisualisation déjà
démarrée et l'API substituée dans le test A1. Ce test n'est pas une preuve PostgreSQL.
Les manifestes prouvent les hashes des unités déclarées, pas leur exécution ni
l'exhaustivité des fichiers SQL. Le protocole de rejeu réel sera fixé en A2.0.

## Séquence B–K après les accès

Ces lots gardent la vision complète ; les contrats précis seront définis lors
de leur prise, à partir de l'existant et des décisions du propriétaire.

| Lot | Rubriques concernées | Parcours minimum frontend/backend/écosystème à prouver |
| --- | --- | --- |
| B | École, Paramètres, Administration | École active → année/classes → élève → tuteur ; identité unique, données persistantes, droits et relecture ; corriger les incompatibilités SQL d'admission |
| C | Personnel | Compte/profil réutilisé → dossier personnel → affectation ; StaffID de base sans refaire l'identité ; présences branchées sur les événements autorisés |
| D | Sécurité et contrôle, Intégrations | Pass révocable → scan → événement → décision ; Guardian avec photo et validation humaine ; Device Hub après son accord structurel distinct |
| E | Finance, Comptabilité | Frais → versement constaté → reçu → annulation/clôture → rapprochement ; cohérence des devises, montants, audit et absence de double écriture |
| F | Pédagogie | Classe/enseignant → devoir/notes → publication → vue du parent ; périmètres exacts et documents cohérents |
| G | Communication, Centre de documents | Brouillon → validation explicite → diffusion autorisée → état d'envoi ; document produit depuis la source métier, droits conservés à l'export |
| H | Stock / Inventaire | Article → entrée/sortie → état du stock → justificatif ; liaison achats/comptabilité, périmètre Lab à préciser |
| I | Pilotage, Contrôle et rapports | Agrégats des sources prêtes → filtre autorisé → rapport ; aucun indicateur fictif ni seconde comptabilité ; raccordement progressif dès B |
| J | Audit et journaux, Intégrations, Administration | Consolider les journaux et états techniques de B–I, erreurs/reprises et frontière Control ; secrets exclus du frontend |
| K | Exploitation / recette finale | Rejeu des parcours d'une école, isolation, sauvegarde **restaurée**, release reproductible et retour arrière ; arbitrer le désaccord d'exploitation avant configuration VPS |

Paramètres, audit, documents et permissions sont traités avec chaque module ; J
consolide ces travaux et ne reporte pas les contrôles de sécurité à la fin.
Watch et les extensions avancées ne sont pas un prérequis pour terminer le cœur.

## Modèle de passation obligatoire

Copier ce format dans le bloc courant du handoff, sans historique de conversation :

```text
TÂCHE / STATUT : identifiant du tableau, état réel
RESPONSABLE / BRANCHE : agent, branche, périmètre réservé
BASE : SHA de départ et résultat du fetch
OBJECTIF : résultat observable de ce lot
EXISTANT VÉRIFIÉ : chemins, symboles et écarts constatés
FAIT / RESTANT : réalisation concrète et conditions non satisfaites
FICHIERS : fichiers réellement touchés
PREUVES : commandes, résultat, niveau de substitution, preuve SQL/UI éventuelle
RISQUES / DÉCISIONS : faits ouverts, arbitrages validés ou à préciser
GIT : commit du code, push, comparaison local/distant ; jamais son propre SHA inventé
PROCHAINE ACTION : une tâche exacte, son premier fichier et le résultat à obtenir
```

Clôturer le lot par un commit limité à ses fichiers. Préserver `.claude/` et les
deux PNG de référence non suivis, sauf demande explicite de les intégrer.
Après push : comparer `git rev-parse HEAD` à `git ls-remote origin refs/heads/main`
sur la branche séquentielle ; utiliser la branche réellement concernée sinon.
Si un push échoue, laisser un état explicite « local seulement » avec la cause.
Le prochain agent reprend le premier identifiant incomplet, sans refaire A1 ni
prendre un ancien rapport de tests comme une nouvelle exécution.


## Mission directe VPS ? 20 septembre 2026

La mission utilisateur courante remplace les anciens mandats Coolify/Easypanel :
Docker Compose + Caddy, deux bases/reseaux, source GitHub production. Corrections
locales additives sur codex/schoolsafe-installation-v2, jamais dans le checkout
VPS ; ni merge ni deploiement SchoolSafe autorise. Voir docs/INSTALLATION_V2.md
et SCHOOLSAFE_DIRECT_VPS_REPORT.md. Les sections anterieures restent historiques.

## 29 septembre 2026 — licence perpétuelle

Lot ciblé en qualification sur codex/perpetual-license. Contrat Control/SchoolSafe explicite et migration additive. Production inchangée ; provisionnement et vérification métier bloqués jusqu’à livraison coordonnée et configuration de signature.
