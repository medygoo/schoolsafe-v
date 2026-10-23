## 2026-10-23 — Dashboard en production d’abord, puis Scan distinct

Le propriétaire autorise explicitement de terminer et mettre en production le tableau de bord composé, puis de poursuivre le lot Scan. Qualification exacte, sauvegarde récente, sessions/cache/configuration et conteneurs antérieurs préservés ; aucun élargissement à Scan pendant cette livraison.

Précisions validées pour le lot Scan suivant : bouton accessible visuellement à tous les profils, exécution soumise à l’accès accordé par l’administrateur scolaire ; commandes distinctes par type de scan ; contrôle des frais activé depuis Finances avec montant limite choisi par l’administrateur ; rouge refuse l’entrée, jaune représente la tolérance, bleu autorise. Ne pas créer ces décisions depuis une animation, ni changer des droits par simple visibilité. Vérifier la politique financière déjà présente et ses seuils avant raccordement ; pas de nouvelle implémentation Scan dans le candidat dashboard.

## Clarification propriétaire — tableau de bord composé et disposition mobile validés

Le menu est accepté ; le dashboard précédent ne l’est pas. Le compteur fonctionne mais les informations des 13 vues doivent être réunies dans la composition des références ordinateur/téléphone, sans personnage féminin ajouté. Cette précision et le GO remplacent l'interdiction historique de supprimer le découpage paginé : conserver l'information, pas le diaporama. Téléphone : quatre KPI, grille d'accès 4 × 2 selon droits, alertes, informations détaillées en bas. Anniversaires en haut sur les deux formats. Effets discrets d'animation/ombre/couleur lumineuse, réduction des animations respectée. Deux commandes en haut du téléphone : Notifications reste dehors, Actions regroupe les contrôles existants. Ne pas modifier les sources du menu validé ni élargir les accès ; réutiliser les vrais éléments et href. [Candidat local et niveaux de preuve](qualification/SCHOOL_DASHBOARD_COMPOSITION_20261022.md), non déployé et non accepté visuellement par simple réussite de tests.

## Historique — 2026-10-22, stabilisation scolaire et conservation du pager (remplacé pour sa présentation)

Le propriétaire fournit ses captures et confirme : réemployer son menu personnalisé existant (clair, branches colorées, panneau adjacent, commandes rondes, dock mobile), conserver les treize vues et leurs contenus, mêmes menus à droits identiques téléphone/ordinateur, boutons reliés aux fonctions existantes. Ordre obligatoire : analyse → correction → tests → stabilisation → mise en production explicitement autorisée après preuves. Pas de nouveau design ni suppression du découpage, pas de simulation de fonction non raccordée ou d’élargissement des permissions. Les captures identifient le visuel, pas une autorisation de restaurer d’anciens liens centraux. [Lot en cours et niveaux de preuve](qualification/SCHOOL_DASHBOARD_STABILITY_20261022.md). ProDéli livré et notation séparée restent inchangés.

## Clôture — frontière ProDéli / écoles et téléphone livrés

GO exécuté après qualification exacte r18 : runtime `fb0774d`, image `1e8259e4…`, recette déployée `d87947b` sur `deployed/prodeli-school-boundary-20261022`. 156 contrats / 57 PG natifs / 37 PG récupération ; 21 parcours natifs, 24 vues responsive, dix groupes récupération et concurrence/rollback PASS, avec provenance de réutilisation uniquement sur le même ID immuable. Sauvegarde PG privée valide 648754 octets/0600 et douze sources antérieures ; bascule 8,86 s, 128 sessions copiées avant démarrage, cache/configuration/20 autres conteneurs conservés. Vérification indépendante PASS, aucune session non expirée manquante malgré la collecte PHP normale de fichiers expirés. Aucun test de compte réel, migration, restauration ou envoi externe. **Clôture de ce lot : le tableau de bord/menus de l’administrateur scolaire reste une analyse distincte ; notation non livrée.** [Rapport final](qualification/PRODELI_SCHOOL_BOUNDARY_RELEASE_20261022.md).

## 2026-10-22 — Ajouts au GO : téléphone ProDéli et configuration propre à l’école

Le propriétaire exige la stabilité des écrans ProDéli sur téléphone puis ajoute le bouton scolaire « Configurer mon école » qui ne doit jamais ouvrir ProDéli. Frontière bidirectionnelle : contrôleurs de plateforme refusés aux comptes scolaires, onglets d’installation globale de modules/plugins refusés, lien scolaire rendu absolu. Le module scolaire ne doit pas modifier un défaut hérité `config.SCHOOL_ID=0` : ses sauvegardes créent/mettent à jour l’override de l’école courante uniquement ; ProgramConfig déjà scolaire reste inchangé. Douze runtime, pas de remaniement des treize vues/menus scolaires ni de notation. Contrats étendus à 156, parcours locaux 21 PASS (CSRF, clic réel, portée des écritures). Qualification finale et livraison restent en cours, non déduites de ces tests locaux. [Rapport](qualification/PRODELI_SCHOOL_BOUNDARY_RELEASE_20261022.md).

## 2026-10-22 — GO de finalisation ProDéli en production ; tableau de bord scolaire séparé

Le propriétaire confirme que l’accès fonctionne désormais et demande de terminer la stabilisation/sécurisation ProDéli en production avant le point suivant. Après précision, le bug du tableau de bord et des menus concerne les profils d’administrateur scolaire, pas ProDéli : ne pas le modifier dans cette livraison. GO de préparation/qualification/bascule du correctif ciblé acquis ; ne pas redemander l’autorisation déjà donnée. Maintenir les contrôles d’image canonique, sauvegarde récente, sessions/cache/configuration/services et vérification indépendante, sans notation, migration ni compte réel manipulé pour essai. [Recette](../ops/releases/prodeli-school-boundary-20261022/README.md).

## 2026-10-22 — ProDéli ne constitue pas une appartenance aux écoles clientes

**Demande explicite du propriétaire après la livraison graphique :** supprimer l’entrée des gestionnaires ProDéli dans les espaces/profils scolaires et vérifier les accès temporaire/personnel. Nouvelle frontière serveur centrale, suppression des entrées scolaires et du changement de contexte global ; gestion des métadonnées/provisionnement conservée. Activation personnelle distinguée de `LAST_LOGIN`. Ancien administrateur d’une seule école jamais connecté : préparation initiale explicite, atomique et confirmée possible, sans conversion d’identité gérée ni réinitialisation de compte déjà activé/utilisé. Keycloak reste en pause. Correctif local qualifié : 114 contrats substitués, 57 PG natifs/rollback, 37 PG récupération-SMS, 20 groupes navigateur avec nettoyage. **Pas de nouvelle livraison de production ni réparation automatique de compte réel.** Les qualifications historiques permettant l’entrée scolaire globale ne valent pas pour cette nouvelle règle. [Périmètre, preuves et limites](qualification/PRODELI_SCHOOL_BOUNDARY_20261022.md).

## 2026-10-22 — Présentation SchoolSafe livrée après confirmation de production

Image `a4a3cbf8…`, source runtime `9c9b338`, recette/VPS `8a969d5` ; 17 runtime, 2 747 autres fichiers identiques, notation explicitement exclue. 150 contrats PHP + 37 PG, 14 groupes natifs/17 workspace/dix récupération-SMS et course/rollbacks PASS sur la même image immuable ; conservation des preuves par ID d’image. Sauvegarde privée valide, 169 sessions/dix entrées cache Chromium/configuration/services conservés, ancien conteneur arrêté conservé. Contrôle séparé public/hashes/runtime PASS ; aucune migration, émission externe ni manipulation de compte réel pour essai. Validation visuelle connectée laissée au propriétaire conformément à sa demande. [Livraison et limites](qualification/PRODELI_PRESENTATION_RELEASE_20261022.md).

## 2026-10-22 — Une présentation SchoolSafe ; livraison graphique autorisée

Le propriétaire valide l’aperçu local et autorise explicitement sa mise en production. Référence : écrans connectés SchoolSafe ; alternatives non sélectionnables mais fichiers/préférences historiques conservés, fondations FlatSIS internes maintenues. Inclut les deux lots graphiques (récupération/configuration/en-têtes puis connexion/premier accès/fiche isolée/thème). Exclut le déplacement de la base de notation encore non livré. Qualification d’image exacte, sauvegarde et préservation sessions/cache/configuration requises avant bascule ; aucune mutation de compte réel, émission externe, restauration ou rollback automatique autorisés. [Qualification locale et limites](qualification/PRODELI_ACCESS_THEME_20261022.md).

## 2026-10-22 — Correction ProDéli livrée après GO explicite

Le propriétaire demande de finir ProDéli avant les autres fonctionnalités et autorise explicitement son déploiement en production. Correction livrée : image `59fb6b25…`, source applicative `a3fbffb`, recette `8fc3546`, 16 sources ciblées sans changement des droits ni des traitements métier. 123 contrats + 37 PG, quatre suites exactes PASS, sauvegarde privée valide et contrôle séparé PASS ; 91 sessions, cache Chromium, configuration et autres services préservés. Session propriétaire connectée : arrivée `/index.php` -> Tableau de bord ProDéli, navigation et mobile contrôlés. Aucune migration ni envoi réel, Keycloak en pause. L’école/admin fictifs précédemment autorisés sont conservés, sélection Default School rétablie. Aucun mot de passe propriétaire lu ni acceptation finale déduite des tests. [Rapport](qualification/PRODELI_WORKSPACE_RELEASE_20261022.md).

## 2026-10-22 — Récupération/SMS livrés après GO

Source/recette livrée `68de604`, image canonique Git `01c194f4…` ; le candidat worktree antérieur a été remplacé après détection de normalisation des fins de ligne et requalification complète. Sauvegarde/préflight PASS, 118 sessions/cache Chromium préservés, aucun changement des lots déjà acceptés, configuration et autres services inchangés. Aucune migration ni envoi réel. Ancien conteneur conservé arrêté ; pas de fallback automatique vers une image vulnérable. Contrôle séparé PASS ; validation connectée du propriétaire et réception réelle expressément autorisée restent distinctes. [Rapport](qualification/PRODELI_RECOVERY_SMS_RELEASE_20261022.md).

## 2026-10-22 — Publication et livraison ciblées récupération/SMS (historique d'autorisation)

**AUTORISÉ par le propriétaire (« go » après demande explicite de publication et déploiement)** : publier le lot récupération/SMS sur `work/prodeli-completion-20261021`, puis livrer l'image qualifiée `b7c276be…` après sauvegarde et préflight. Cinq fichiers runtime seulement ; ne pas refaire le tableau de bord, la création d'école, le rattachement ou le premier accès. Aucun merge main implicite, SMS réel, activation de ligne ou test de compte réel. Préserver sessions, cache d'impression, configuration et autres services ; aucun fallback automatique vers les failles corrigées ni restauration de base. [Rapport](qualification/PRODELI_RECOVERY_SMS_20261022.md).

## 2026-10-21 — Finition Communication : conserver l'organisation existante

**VALIDÉ par le propriétaire :** le système de messages/notifications est déjà organisé ; examiner et conserver ses règles, destinataires et répartition des canaux. Terminer le lot de réglages SMS/WhatsApp dans le tableau de bord ProDéli existant, sans reconstruire le Studio, Brevo, les notifications ou les fournisseurs. Keycloak reste en pause. GO d'exécution et de livraison ciblée acquis. [Rapport et limites](qualification/PRODELI_COMMUNICATION_RELEASE_20261021.md).

## 2026-10-21 — Achèvement ProDéli validé

**VALIDÉ :** tableau de bord unique sans nouveau menu, base/traitements Rosario et présentation SchoolSafe/logo original ; terminer école → administrateur principal → premier accès → récupération. Préserver toutes les fonctions de communication existantes, courriel, quatre réseaux SMS et WhatsApp ; ne pas les mettre en pause ni reconstruire leurs fournisseurs. Pas d'envoi externe d'essai sans destinataire autorisé. Exécution progressive avec preuves et livraison ciblée ; « terminé » exige un parcours complet, pas une interface seule. [Suivi courant](qualification/PRODELI_COMPLETION.md).

## 2026-10-21 — Mise à jour directe de production, présentation ProDéli isolée

**VALIDÉE par le propriétaire :** travailler directement en production puis appliquer les dernières corrections, après abandon de la poursuite de la préproduction. Le produit reste SchoolSafe avec son logo original ; ProDéli est son espace central existant, pas une application parallèle. Livrer par lots ciblés avec sauvegarde et retour arrière, sans importer des mutations non qualifiées.

**APPLIQUÉ :** sous-lot de quatre fichiers de présentation, source `6e741ba8a289866dc443dc61bf4108a3ecb29672`, image `b45be4ea…`. Gestion métier, comptes et schéma non modifiés ; contrôles techniques PASS et validation connectée du propriétaire attendue. Les autres travaux locaux restent distincts. [Rapport de livraison](qualification/PRODELI_PRESENTATION_RELEASE_20261021.md).

## 2026-10-18 — Un seul dépôt GitHub officiel : `medygoo/schoolsafe-v`

Décision du propriétaire. Le dépôt actif `medygoo/schoolsafePRODELIFIN` est renommé `medygoo/schoolsafe-v` ; GitHub redirige l'ancienne URL et l'historique est intact (branche `main`, commit `d8bbcde` au moment du renommage). L'ancien `medygoo/schoolsafe-v` du 18 septembre devient `schoolsafe-v-legacy-20260918` et est archivé. Douze dépôts doublons sont archivés — lecture seule, historique conservé, réversible : `schoolsafe-stable`, `SchoolSafe-`, `Prodeli-`, `schoolsafemm`, `SchoolSafe1site`, `the-wise-school`, `schoolsafe-bolt-work`, `fiche`, `LOMELA_DENIS_FIN`, `medygoo-schoolsafe-V2`, `schoolsafev2-fin`, `Bolt`. Restent actifs volontairement : `Rosario-Schoolsafe` (base RosarioSIS 12.9.4), `schoolsafe-control-` (couche Control) et deux dépôts sans rapport produit. Toute mention de `schoolsafe-stable` ou `develop/codex-base` dans les mandats antérieurs est historique : ce dépôt est archivé, il ne doit plus servir de base de publication.

## 2026-10-10 — Preuve serveur avant une remise

Une remise ordinaire consomme une preuve de préparation à usage unique, liée à l'école, à l'élève, à la carte scannée, au gardien, au portail et à la personne choisie. Elle expire en deux minutes. `api.pickup_confirm` la vérifie avant de contrôler à nouveau la carte active, la photographie approuvée et le droit de retrait. Une preuve absente, réutilisée, expirée, d'une autre école ou d'un autre portail est refusée et journalisée. La case de contrôle humain affichée dans l'interface ne remplace pas cette preuve. Aucune exception administrative silencieuse n'est ajoutée.

## 2026-10-10 — Plafond de révocation des délégations de frais

Complément de `SCHOOLSAFE_SCANNER_FEES_ACCESS_CONTROL_V1`. Un titulaire de `SCAN_FEES_DELEGATE` dont les classes, la campagne ou l'échéance sont limités ne peut ni révoquer ni réécrire une délégation `SCAN_FEES` qui sort de ce plafond. Si son propre plafond est déjà celui de l'école, il conserve ce plafond. Le statut d'une carte inactive en mode frais n'est renvoyé qu'après cette autorisation.

## 2026-10-10 — Contrôle des frais au scanner

Décision verrouillée `SCHOOLSAFE_SCANNER_FEES_ACCESS_CONTROL_V1`.

- L'activation du mode pour l'établissement exige `finance.control.manage`. Elle est distincte de l'usage du scanner et de la gestion des délégations.
- L'usage appartient à un titulaire de `finance.control.scan` à l'échelle de l'école, à la portée `assigned_fee_classes` déjà prévue, ou à une délégation `SCAN_FEES` limitée à l'école et, lorsqu'elles sont renseignées, aux classes ou à la campagne indiquées.
- La gestion des délégations exige `finance.control.manage`, ou la capacité distincte `SCAN_FEES_DELEGATE`. Cette capacité peut déléguer `SCAN_FEES`. Elle ne peut ni activer le module ni s'accorder `SCAN_FEES_DELEGATE`.
- La lecture est consultative. Le solde est calculé depuis les frais enregistrés. Un dossier absent reste « aucune donnée financière ». Le statut envoyé par le navigateur n'est pas une décision.
- L'impayé scolaire ne bloque ni l'entrée ni la récupération de l'enfant.

## 2026-09-28 — Admission du principal par Control

Décision du propriétaire : Control pré-enregistre l'administrateur principal et conserve seul son mot de passe sécurisé. SchoolSafe vérifie son accès par API bootstrap serveur, réutilise son auth native et son onboarding à sept étapes, sans code maître ni auto-inscription inconnue. La liaison locale auth/v7 est atomique avec la création ; le bind Control est idempotent et récupérable à la prochaine connexion. Les sessions liées sont refusées après suspension/révocation. Les comptes locaux créés par l'école sont préservés. Voir [le contrat](CONTROL_ADMIN_ONBOARDING_V1.md). Ce lot autorise les PR uniquement, sans merge ni déploiement.

# Décisions validées SchoolSafe

Ce journal conserve les décisions durables. Il ne doit contenir ni secrets ni transcriptions brutes de conversations.

| Date | Décision | Statut |
|---|---|---|
| 2026-09-12 | Supprimer définitivement les traces et dépendances 3D du projet, tout en conservant JASPE 2,5D. | Validée et appliquée au commit `43982cb` |
| 2026-09-12 | Conserver SchoolSafe Control comme couche centrale protégée pour gérer les écoles inscrites, les licences, la supervision et l'impression. | Validée |
| 2026-09-12 | Travailler localement et sur GitHub en miroir, puis intervenir sur le VPS seulement lorsque les lots GitHub sont terminés. | Validée |
| 2026-09-12 | Ne pas utiliser Docker pour la cible finale ; prévoir un déploiement direct sur VPS. | **OBSOLÈTE — remplacée le 2026-09-14** par Docker + Coolify (voir décision du 14/09 ci-dessous). Conservée pour l'historique : elle reflétait l'architecture « 1 école = 1 VPS » alors en vigueur. |
| 2026-09-12 | Limiter les tests, tout en maintenant des tests obligatoires pour les points critiques : sécurité, permissions, argent, enfants, isolation entre écoles et migrations. | Validée |
| 2026-09-13 | Améliorer JASPE en consolidant le moteur 2,5D existant plutôt qu'en reconstruisant le personnage. | Validée |
| 2026-09-13 | Le moteur physique JASPE doit couvrir le visage et le corps ; la voix et la synchronisation labiale sont reportées au raccordement Cloudflare. | Validée |
| 2026-09-13 | GitHub et les documents versionnés du dépôt deviennent la mémoire commune entre les deux comptes ChatGPT. | Validée |
| 2026-09-13 | Retenir l'approche 1 pour le physique JASPE : contrôleur unique progressif, v12 principal et WebP de secours. | Validée |
| 2026-09-13 | Supprimer définitivement l'ancien écran visuel `guardian`, ouvrir directement la connexion depuis le splash et conserver les références métier `school.guardian` des tuteurs d'élèves. | Validée |
| 2026-09-13 | Adopter les références SchoolSafe mobile et bureau comme direction visuelle officielle, corrigée pour la lisibilité, l'accessibilité et la performance ; conserver HTML/CSS/JavaScript, le logo existant et JASPE 2,5D, sans React, Ant Design ni 3D. | Validée |
| 2026-09-13 | Retenir quatre expériences adultes visibles — Direction, Enseignant, Gardien et Parent — et conserver les métiers internes comme postes combinables plutôt que comme profils applicatifs séparés. | Validée |
| 2026-09-13 | Autoriser l'administrateur principal d'une école à créer librement des postes et à composer leurs permissions, portées et limites dans l'enveloppe souveraine imposée par SchoolSafe Control et l'Access Law. | Validée |
| 2026-09-13 | Construire menus, tableaux de bord, services d'écosystème et capacités JASPE depuis un même contrat de session calculé par l'Access Law. | Validée |
| 2026-09-13 | JASPE peut exécuter les actions ordinaires autorisées ; les notes, paiements, permissions, suppressions, opérations sensibles de sécurité et données protégées d'enfant exigent une confirmation humaine. | Validée |
| 2026-09-13 | Afficher aux utilisateurs uniquement les services d'écosystème activés et autorisés ; montrer à la Direction les services activables et réserver le catalogue complet à SchoolSafe Control. | Validée |
| 2026-09-14 | Adopter **Docker + Coolify** comme architecture d'exploitation du VPS central. Docker organise les services techniques (Web, API, Control, PostgreSQL, workers) ; **il n'existe pas de conteneur par école** — l'isolation multi-écoles repose sur `school_id` + PostgreSQL + ACCESS_LAW. La formulation « sans Docker » du 12/09 est obsolète (voir statut historique ci-dessus). | Validée |
| 2026-09-14 | **Déploiement final = Git → Coolify → Docker → VPS.** Règles : jamais de copie manuelle sur le VPS ; tout part du dépôt Git ; image reproductible ; staging et production séparés ; secrets dans Coolify/environnement (jamais dans Git) ; `/health` exposé pour les healthchecks ; volumes persistants pour PostgreSQL et les données ; sauvegarde avant migration ; tag/SHA précis par release pour le retour en arrière ; gros fichiers des écoles dans R2 par `school_id`. Source opérationnelle : `ops/deployment/README.md`. | Validée |
| 2026-09-14 | **Un VPS central héberge SchoolSafe Control et SchoolSafe pour plusieurs écoles**, systèmes logiquement séparés. « 1 école = 1 VPS » est incorrect. Formulation officielle : **1 plateforme SchoolSafe = plusieurs écoles isolées, chacune avec un `school_id` unique et stable**. La séparation Control/SchoolSafe porte sur les privilèges (credentials, rôles, droits, migrations distincts ; impossibilité pour SchoolSafe de modifier les données souveraines de Control) ; elle ne dépend pas d'un nom physique de base. | Validée |
| 2026-09-14 | Deux identifiants d'école : **`school_id` UUID** = identité technique canonique immuable (FK, RLS, ACCESS_LAW, API, licences signées) ; **`school_code`** type `SCH-000001` = identifiant public lisible (Control, écrans, contrats, support, documents). Ne jamais remplacer les UUID existants par des codes publics. | Validée |
| 2026-09-14 | L'**identité d'authentification est globale à la plateforme** ; l'accès aux écoles se fait par memberships/rattachements. Un seul membership actif → contexte résolu automatiquement ; plusieurs memberships → sélection d'un contexte tenant-aware par l'application. **Le navigateur n'impose jamais de `school_id`** : le serveur résout et valide le tenant actif à partir de l'identité authentifiée + memberships valides + contexte de session + ACCESS_LAW. Téléphone normalisé unique quand il sert d'identifiant de connexion. Une fiche contact n'est pas automatiquement une identité de connexion. | Validée |
| 2026-09-14 | L'**OTP ne doit pas bloquer le cœur** : abstraction `OTP_PROVIDER` à créer plus tard (Africell ou autre SMS), aucun fournisseur verrouillé, aucun fournisseur codé en dur. | Validée |
| 2026-09-14 | **PostgreSQL du VPS = base de production souveraine.** Supabase : inventorier → classifier → migrer → tester → retirer uniquement ce qui est devenu réellement inutile ; jamais de suppression massive. | Validée |
| 2026-09-14 | Construire d'abord un **Control minimal** (registre écoles, school_id/school_code, statut essai/actif/suspendu, services autorisés, émission/révocation de licence signée, audit minimum). Console SaaS complète, facturation automatisée et provisioning avancé attendent plusieurs écoles clientes. | Validée |
| 2026-09-14 | **VISION LARGE, LIVRAISON ÉTROITE** : aucune nouvelle grande fonctionnalité tant que le parcours quotidien de la première école (Le Sage) n'est pas stable, sécurisé, sauvegardable et restaurable. Les éléments non prioritaires sont classés en roadmap V2/V3, jamais supprimés de la vision. | Validée |
| 2026-09-14 | **Priorités validées** : P1 Phase A ACCESS_LAW · P2 toute fuite inter-écoles = bloquante · P3 enforcement backend de la licence · P4 cœur quotidien Le Sage **+ personnes autorisées + photo obligatoire à la sortie + validation humaine du gardien** · P5 sauvegarde/restauration testées avant la première école dépendante · P6 Control avancé, JASPE, Watch, **Guardian avancé** (délégations avancées, politiques), Lab, StaffID avancé. La gestion de base des personnes autorisées n'est PAS reportée ; le produit Guardian complet peut l'être. | Validée |
| 2026-09-14 | **Sauvegarde automatique + rétention définie + restauration documentée et réellement exécutée** (preuve/journal du test) avant la première école réelle. Une sauvegarde jamais restaurée n'est pas une garantie. | Validée |
| 2026-09-14 | Le **dépôt versionné constitue la source de vérité opérationnelle** : procédures, configurations non secrètes, scripts d'exploitation et runbooks y sont conservés (dossier `ops/`) ; aucun secret ne doit être commité. | Validée |
| 2026-09-14 | **License Contract V2** validé (spec G0) : CORE `id` non licenciable ; services `pass/watch/staffid/guardian/lab` + catalogue extensible par Control ; grâce globale ; quotas reportés ; héritage V1 = `["pass"]` (car `id` est fourni par le CORE) ; sunset déclenché par critères de readiness puis fenêtre de compatibilité, jamais par une date arbitraire. Implémentation d'enforcement après la Phase A. | Validée |
| 2026-09-14 | **Règle permanente de continuité** : toutes les instructions, ordres, décisions, plans et handoffs se conservent. Une nouvelle instruction s'ajoute à l'historique avec statut (`VALIDÉE`, `ACTIVE`, `REMPLACÉE`, `OBSOLÈTE`, `REPORTÉE`, `ABANDONNÉE`, `À VALIDER`), date et raison. Ne jamais supprimer l'historique ; ne jamais laisser deux règles contradictoires actives. Chaque lot laisse une trace complète (prévu/fait/modifié/tests/résultats/décisions/fichiers/SHA/reste à faire/risques) permettant la reprise immédiate par tout agent. | Validée |

## Décision produit du 16 septembre 2026 — JASPE au tableau de bord

- **VALIDÉE, instruction directe du propriétaire le 16/09** : reprendre les références
  bureau/mobile avec le personnage JASPE déjà conçu et son moteur v12 2D/2,5D.
  JASPE peut discuter par écrit ou audio sur place. Le cadrage en buste initial
  est **REMPLACÉ** par la décision de pose assise ci-dessous.
- **VALIDÉE** : un appui prolongé sur le bouton JASPE fait sortir le personnage
  en corps entier, animé et adapté à l'écran, avec une boîte de dialogue flottante
  aux couleurs pâles. L'application reste utilisable autour. Clic et clavier
  permettent également l'ouverture ; fermeture par bouton ou Échap.
- **REMPLACE l'historique de chat du lot du 15/09** : aucun journal de conversation
  accumulé ou persisté ; seule la réponse courante est affichée, partagée entre
  bandeau et boîte flottante, et effacée au changement de contexte utilisateur.
- Les réponses et les actions restent soumises à Access Law et au routeur existant.
  L'audio navigateur ne constitue pas un raccordement GLM/Cloudflare ni une
  synchronisation labiale ; ces capacités ne sont pas livrées par ce lot.

## Complément validé le 16 septembre — poses assises par action

- **VALIDÉE, demande directe du propriétaire** : dans le bandeau, JASPE est
  réellement assise derrière un bureau pour éviter la coupure du personnage
  debout. Conserver son identité v12, sa tenue et le corps entier flottant existant.
- **VALIDÉE** : créer des planches regroupées par action pour maintenir le cadrage
  et la cohérence des gestes : parole avec la main, réflexion/main aux cheveux,
  sourire/clignement, lecture. Ajouter un sourire pendant la lecture.
- Décliner chaque action en clair et sombre. Le lot initial utilise quatre images
  par planche, affichées en séquence 2D. Les fonds sont intégrés aux images :
  aucun détourage alpha ni calques séparés n'est annoncé comme livré.
- La lecture vocale pilote le début/la fin de la séquence de parole ; il ne s'agit
  pas d'une synchronisation phonétique. Permissions et réponse courante sans
  historique restent régies par le contrat du lot précédent.
- **VALIDÉE, complément du propriétaire le 16/09** : ajouter une séquence de
  parole avec les deux mains, en clair et sombre, en conservant celle à une main.
  Les explications (`TalkHandsOpen` / `TalkPassionately`) utilisent cette nouvelle
  séquence. Le propriétaire demande ensuite la clôture du chantier courant.

## Complément du 16 septembre — reprise du parcours vocal

- **VALIDÉE, reprise « GO » du propriétaire** : vérifier et corriger écouter,
  répondre, interrompre et reprendre, dans le bandeau et en corps entier. Ajouter
  un contrôle direct de lecture/arrêt de la réponse courante ; aucun historique.
- Le changement de compte/contexte doit couper la voix et le micro, même si le
  compte suivant a lui aussi accès à JASPE. Aucun démarrage automatique du micro.
- **CONCRÉTISÉE par le lot suivant, initialement à faire pendant ce lot** : JASPE peut se lever dans le
  bandeau, cadrée sur le haut du corps et adaptée au fond. Proposition présentée :
  bureau masquant le bas du corps, séquence assise/appui/debout/retour et déclinaisons
  clair/sombre. Aucune nouvelle image ni transition livrée par le lot vocal.

## Complément du 16 septembre — transition assise/debout

- **VALIDÉE ET APPLIQUÉE**, suite du plan autorisée par le propriétaire : dans le
  bandeau, JASPE se lève derrière son bureau pour expliquer avec les deux mains,
  puis se rassoit. Le bureau masque le bas du corps ; garder l'identité et la tenue.
- Deux familles de planches clair/sombre complètent les actions assises : montée
  en quatre étapes (lues à l'envers pour la descente) et parole debout en quatre
  poses. Le thème choisit les images. Le corps entier flottant reste distinct.
- Les explications `TalkHandsOpen` / `TalkPassionately` passent de la parole assise
  à la séquence debout dans le bandeau ; salutations, écoute et lecture restent
  assises. Cette règle complète et remplace le seul choix de pose des explications
  du lot « deux mains ». Les contraintes de permissions et de réponse sans
  historique ne changent pas.
- Fin/arrêt de voix ou nouvelle action : retour assis. Perte d'accès : arrêt
  immédiat. Mouvements réduits : pose fixe. Assets indisponibles : repli sur
  la parole assise. Pas de synchronisation phonétique ajoutée dans ce lot.

## Mission du 16 septembre — Device Hub, identité et présences

- **VALIDÉE, instruction directe du propriétaire** : préparer l'intégration
  native des terminaux autour de SchoolSafe comme source de vérité. Une identité
  de personne, plusieurs moyens d'identification et plusieurs appareils possibles.
- **VALIDÉE** : Device Hub indépendant des règles de présence, adaptateurs par
  marque, première cible Hikvision DS-K1T808MFWX ; ne pas inventer ses commandes
  ni supposer la lecture QR. QR téléphone indépendant du terminal.
- **VALIDÉE** : personnel et élèves créés dans SchoolSafe ; mappings techniques,
  isolation par école, credentials révocables, événements idempotents, corrections
  auditées, reprise après panne ; gabarits biométriques conservés sur équipement.
- **VALIDÉE** : biométrie élèves configurable, enfant lié aux droits parent/tuteur,
  remise physique soumise à la photo de la personne autorisée et au gardien.
  Watch reste hors de ce lot. Les UUID scolaires existants sont conservés ; les
  codes lisibles des exemples ne remplacent pas les clés techniques.
- **ACTIVE — ARRÊT AVANT CODAGE STRUCTUREL** : le §56 exige un audit/projet en
  24 points puis validation. Le dossier
  `docs/superpowers/specs/2026-09-16-schoolsafe-device-hub-audit-design.md`
  est livré comme **PROPOSITION À VALIDER**, pas comme architecture déjà acceptée.
- Ce cadrage reprend la priorité demandée par le propriétaire ; il ne déclare
  pas les parcours Le Sage/P5 terminés et ne retire aucune condition de sécurité
  ou de sauvegarde avant exploitation réelle.

## Priorité du 16 septembre — fonctionnalités et Rôles et accès

- **VALIDÉE, instruction directe du propriétaire** : traiter les 16 rubriques du
  menu avec leurs interfaces, backends et liens écosystème, en réutilisant l'existant.
- **VALIDÉE, priorité explicite** : commencer par **Rôles et accès**, corriger et
  rendre fonctionnel sans supprimer, puis progresser étape par étape. Maintenir
  le miroir local/GitHub après chaque lot vérifié.
- A1 livré : contexte natif corrigé et consultation des droits du compte connecté
  par le bootstrap serveur existant. L'ancien éditeur reste démonstratif ; aucune
  attribution persistante n'est annoncée. A2/A3 restent à réaliser dans
  `docs/superpowers/plans/2026-09-16-schoolsafe-functional-integration.md`.
- Cette priorité ne valide pas implicitement l'architecture Device Hub en attente.

## Continuité du 16 septembre — plan exécutable par tout agent

- **VALIDÉE, demande directe du propriétaire** : formaliser un plan de travail
  que tout agent peut suivre pour poursuivre les fonctionnalités, Rôles et accès
  en premier. Le document existant
  `docs/superpowers/plans/2026-09-16-schoolsafe-functional-integration.md`
  devient le plan actif détaillé, référencé dans `AGENTS.md` et le contexte.
- Le plan comporte des identifiants de tâches stables, prérequis, fichiers,
  résultats attendus, contrôles, états et format de passation. Le handoff conserve
  le responsable, la branche et la prochaine action concrète.
- **REMPLACÉE pour ce chantier fonctionnel** : l'ancienne répartition par nom
  d'assistant conservée dans l'historique. Tout agent peut reprendre un lot ;
  un seul rédacteur possède les fichiers du lot à un instant donné. Aucun travail
  concurrent implicite, aucun effacement de l'historique.
- Le niveau de preuve est explicite : tests avec substituts, parcours SQL/UI
  réel de test, puis production. La rédaction du plan ne valide ni les tâches
  futures, ni leurs choix produit encore ouverts, ni l'architecture Device Hub.

## Réalisation du 16 septembre — A2, annuaire des accès

- **APPLIQUÉE dans le mandat existant** : consultation de profils/rôles/attributions
  de l'école par trois projections `api.access_*` et routes natives protégées par
  `roles.manage`. Tables IAM et moteur Access Law réutilisés, sans doublon ni
  mutation d'accès depuis les brouillons de démonstration.
- Une consultation d'un tiers conserve l'acteur connecté ; les attributions
  stockées et leur validité ne sont pas présentées comme une simulation complète
  des permissions effectives. Frontend, serveur et PostgreSQL testés ensemble.
- Le rôle inactif ne participe plus aux ALLOW/DENY du moteur canonique. Les
  gardes d'installation sont alignées sur les 64 permissions déjà validées.
- Preuve de test uniquement, pas de déploiement ni nouvelle décision produit.
  Contrat : `docs/superpowers/specs/2026-09-16-access-directory-contract.md`.

## Loi générale validée le 16 septembre — JASPE et autorité du profil

- **VALIDÉE EXPLICITEMENT PAR LE PROPRIÉTAIRE** : JASPE agit au nom du profil
  connecté et ne possède jamais plus de droits que lui. Cette loi s'applique à
  toute l'application et à tout outil/connecteur, pas seulement à la pédagogie.
- L'administrateur humain attribue les permissions et limites. Le serveur commun
  contrôle chaque lecture/action de l'interface, des API et de JASPE : acteur,
  école, permission, ressource, portée, conditions et refus explicites. Aucun rôle
  implicite, prompt, voix, site ou connecteur ne remplace cette autorisation.
- Une révocation s'applique à la demande suivante, même dans une session ou un
  dialogue déjà ouvert. Les tâches différées doivent revérifier les droits à leur
  exécution ; elles ne conservent pas une délégation devenue invalide.
- JASPE peut exécuter les tâches autorisées dans cette enveloppe. Les confirmations
  humaines des opérations sensibles, déjà validées, restent obligatoires.
- Exemples non exhaustifs : un parent peut consulter les frais/devoirs accessibles
  à ses enfants, pas les salaires des enseignants, leurs brouillons ou cahiers
  internes ; un enseignant reste limité à ses attributions et ressources autorisées.
- Cette validation porte sur une loi à faire respecter et tester transversalement.
  Elle ne signifie pas que tous les outils JASPE ou modules sont déjà raccordés.
  L'accord Device Hub reste distinct : le propriétaire demande ici son résumé,
  sans valider encore son architecture structurelle.

## Règle de remise d'enfant précisée et validée le 16 septembre

- **VALIDÉE, confirmation explicite du propriétaire** : conserver le parcours QR
  scanné par le gardien. La fiche de l'enfant présente les trois personnes
  autorisées ; l'adulte présent est vérifié parmi celles-ci, avec comparaison
  physique à la photo enregistrée et confirmation humaine avant remise.
- **VALIDÉE pour le secours en l'absence du gardien** : c'est l'empreinte de
  **l'enfant** qui sert à l'identifier. Un **remplaçant habilité** contrôle l'adulte
  parmi les trois personnes autorisées et confirme la remise. Il utilise son
  propre profil autorisé et le périmètre du portail ; aucun compte gardien partagé.
- Le secours rejoint le même contrôle de remise que le QR. Il ne contourne ni
  l'autorisation active de l'adulte, ni la photo, ni la confirmation, ni l'audit.
  La reconnaissance de l'adulte par l'enfant peut aider la vérification mais
  n'accorde pas à elle seule une autorisation de sortie. Sans adulte autorisé
  vérifié et validateur habilité, aucune remise n'est validée par le système.
- Conserver la trace de l'enfant, de la personne récupérante, du validateur,
  de la méthode d'identification, du portail et de l'heure. L'empreinte reste sur
  l'appareil ; activation biométrique facultative et autorisations prévues maintenues.
- Décision produit à implémenter dans Guardian/Device Hub ; aucun raccordement
  matériel ou parcours natif de remise n'est déclaré livré par cette validation.

## Présentation JASPE et logo de l’école — validée le 16 septembre

- Le bureau devient occasionnel : scènes de lecture/explication, puis retour à
  JASPE debout avec son cartable. Conserver son visage, sa coiffure et son uniforme
  v12 ; animation 2D/2,5D, aucune nouvelle 3D.
- Sur téléphone, réduire sa place et réserver à côté un espace stable au logo
  de l’école courante, chargé depuis l’identité enregistrée à la création.
  Sans logo, afficher le nom de l’école ; ne pas inventer de logo partenaire.
- La saisie reste sous le bandeau, sans historique. Voix, sortie complète et
  permissions existantes sont conservées. Le logo reste fixe pendant les gestes.
- Précision ultérieure du propriétaire : les nouvelles images déforment le
  visage ; ne pas les intégrer. La maquette mobile réenvoyée montre le placement.
  Conserver l’identité de notre JASPE existante et corriger la fidélité des poses.
- Cadrage final précisé : **vue jusqu’aux hanches, bras et mains visibles pour
  expliquer**, placée sur le côté. Ce cadrage concerne le bandeau ; la sortie
  flottante complète reste disponible.

## Décisions restant à préciser — inchangées

### 19 septembre 2026 — préparation de dépôts séparés

- **VALIDÉE, instruction du propriétaire** : le manifeste arbitré devient la
  base de `medygoo/schoolsafe-stable`. Préparer une copie locale sans push ni
  déploiement ; préserver JASPE, cartes, ACCESS_LAW, auth, tests et historiques.
- **CONFIRMÉE** : Docker + Coolify remplace la règle VPS direct d'AGENTS ; garder
  cette règle antérieure comme historique. Ne pas modifier les fournisseurs IA.
- **VALIDÉE** : exclure les fichiers privés déjà classés et n'inclure les six
  portraits qu'avec usage légitime et approprié établi. Faute de preuve à ce stade,
  les laisser dans la source et les omettre de la copie ; aucune suppression locale.
- **VALIDÉE** : récupérer l'ancien Control depuis sa source existante vers
  `medygoo/schoolsafe-control-2` dans une mission distincte. Ne pas le reconstruire
  depuis SchoolSafe ; les adaptateurs/contrats restent dans SchoolSafe.
- Les défauts métier DeviceHub/famille/cartes sont conservés hors de ce mandat.
  L'inventaire d'installation peut signaler un blocage sans autoriser son
  contournement ni une réécriture historique.

### Historique des décisions encore ouvertes avant cette mission

- Technologie exacte de synthèse vocale Cloudflare lorsque le lot voix commencera.
- Procédure finale de service VPS, de proxy inverse et de sauvegarde avant le déploiement de production.


## Mission directe VPS ? 20 septembre 2026

La mission utilisateur courante remplace les anciens mandats Coolify/Easypanel :
Docker Compose + Caddy, deux bases/reseaux, source GitHub production. Corrections
locales additives sur codex/schoolsafe-installation-v2, jamais dans le checkout
VPS ; ni merge ni deploiement SchoolSafe autorise. Voir docs/INSTALLATION_V2.md
et SCHOOLSAFE_DIRECT_VPS_REPORT.md. Les sections anterieures restent historiques.

## 29 septembre 2026 — durée indéterminée par école

Le propriétaire demande une licence sans expiration pour son école. Représentation explicite : expires_at=null et perpetual=true dans le jeton signé ; aucune date artificielle, aucune activation globale. Les licences demeurent suspendables/révocables après synchronisation. Une migration additive préserve les données et FORCE RLS. La clé de signature et la confiance correspondante restent à provisionner, sans modifier les clés SSH.

## 18 octobre 2026 — cycle de vie par école cliente (suspendre / désactiver)

Décision : l'accès d'une école cliente entière se pilote depuis le tableau de bord du propriétaire
(PRODELI), sans jamais supprimer de données. Trois états seulement, portés par l'école et non par la
personne : `active`, `suspended` (accès coupé, date de fin facultative pour un retour automatique),
`inactive` (établissement fermé, réactivation manuelle). Une personne affectée à plusieurs écoles
perd l'école coupée et garde les autres ; le propriétaire de la plateforme conserve l'accès pour
administrer. Seul un administrateur global peut couper une école : un directeur ne le peut pas. Une
école coupée ne modifie aucune table native (élèves, notes, paiements, historiques intacts) et
l'absence de la migration 016 laisse la fonctionnalité inerte : la règle est additive et jamais
bloquante. Motif : éviter la suppression d'un compte ou d'une école pour couper un accès, et donner
au propriétaire un levier réversible sur un établissement en défaut de paiement ou hors service.

Extension (même jour) : cet état ferme aussi les **sessions élèves** (connexion et requêtes en cours)
et les familles sont informées sur le portail — école nommée et motif lisible — sans perdre l'accès aux
autres écoles ; quand toutes les écoles d'un parent sont coupées, sa connexion est refusée avec le même
motif. Portée vérifiée sur l'environnement final : image `Dockerfile.vps` (PHP 8.3, configuration de
production) et PostgreSQL 17.11 chargé avec `rosariosis.sql`, migrations 014–016 additives et idempotentes.


## 18 octobre 2026 — écran de connexion : les deux écrans bleus, authentification native conservée

Décision : l'accueil (bouton « Commencer ») et la connexion par nom d'utilisateur et mot de passe
deviennent les deux écrans de la connexion Rosario, repris tels quels du prototype validé (copie
octet pour octet de ses feuilles de style sous `assets/login/blue/`). Le formulaire reste celui de
Rosario — POST vers `index.php`, champs `USERNAME` et `PASSWORD`, `redirect_to`, hook
`index.php|login_form_link`, création de compte, `PasswordReset.php`, choix de langue, mentions
légales — et les messages natifs (erreur, note) sont rendus dans le panneau de l'écran. Aucune
authentification parallèle, aucune session, aucun droit, aucune donnée déplacée ; l'ancien écran
reste sur disque et se remet en service en une ligne dans `index.php`. Motif : une seule
application SchoolSafe/Rosario, avec l'identité visuelle demandée et une authentification native
stable.
