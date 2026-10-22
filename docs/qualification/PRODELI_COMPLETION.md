# ProDéli — achèvement du parcours existant

## Mandat validé

Le propriétaire valide l'achèvement complet : tableau de bord unique ProDéli sans nouveau menu, base et traitements Rosario conservés, présentation SchoolSafe/logo original ; école → administrateur principal → premier accès → récupération ; communication existante conservée, notamment quatre réseaux SMS, courriel et WhatsApp. Pas de reconstruction de communication ni de retrait de fonctions. Les essais de réception externe exigent des destinataires autorisés. Le mandat de production est acquis, mais une modification n'est livrée qu'après qualification de son candidat et préparation du retour arrière.

## État et limites de départ

Production acquise : présentation quatre fichiers, source `6e741ba8a289866dc443dc61bf4108a3ecb29672`, image `b45be4ea…`. Le reste du worktree d'harmonisation n'est pas livré. Ce chantier part de la branche documentaire `cbc69769c1c99c80ff464db4b399a108786f4cdf`, nouvelle branche `work/prodeli-completion-20261021`.

Inspection précédente en lecture seule : configuration Keycloak inaccessible dans le PHP, plugin global désactivé, zéro liaison d'identité. Le formulaire ProDéli appelle néanmoins le fournisseur pour préparer/créer un administrateur. Création native et récupération natives existent : réutiliser leurs contrôles, pas de conversion silencieuse d'un compte lié. Table `schoolsafe_school_leadership` absente à l'inspection ; qualification/migration additive nécessaire avant annoncer le rattachement principal fiable.

Communication : pages SMS/WhatsApp et routeurs déjà présents, à préserver. Quatre lignes SMS préparées mais désactivées et sans credentials serveur complets à l'observation. Cela ne signifie pas supprimer/mettre en pause le système développé ni l'activer automatiquement. Raccord OTP appelle un envoi de texte vide avant génération ; test SMS d'une ligne passe par routage automatique : deux défauts à reproduire/tester avant correction ciblée.

## Suivi vérifiable

| Lot | État | Condition de clôture |
|---|---|---|
| 1. Point d'entrée et tableau de bord sans menu central | **DÉPLOYÉ** source `3885644`, image `9177067d…` | Contrôles techniques PASS ; validation connectée propriétaire attendue |
| 2. Création native et rattachement principal | **DÉPLOYÉ** source `3670dad`, image `36a9e2fb…` ; contrôles techniques et indépendants PASS, acceptation du propriétaire reçue. [Preuves](PRODELI_NATIVE_ACCESS_RELEASE_20261021.md) | Transaction/reprise, unicité, une école, profil, doublons et échecs, sans fallback d'une identité gérée |
| 3. Premier accès | **DÉPLOYÉ** source `3670dad`, image `36a9e2fb…` : remise unique, temporaire 24 h, choix personnel obligatoire et renouvellement initial contrôlé ; acceptation du propriétaire reçue. [Preuves](PRODELI_NATIVE_ACCESS_RELEASE_20261021.md) | Remise confidentielle, URL canonique, secret à usage limité, choix du mot de passe et arrivée dans la bonne école |
| 4. Récupération | **DÉPLOYÉ le 22/10**, source/recette `68de604`, image canonique Git `01c194f4…` : 37 PG, 92 contrats, dix groupes récupération/SMS et course native PASS ; 118 sessions/cache préservés, contrôle séparé PASS, validation propriétaire attendue ; [livraison et limites](PRODELI_RECOVERY_SMS_RELEASE_20261022.md) | Courriel/code selon canal disponible, expiration, usage unique, rate-limit, révocation et messages génériques |
| 5. Communication conservée et raccordée | Réglages **DÉPLOYÉS** source `90f6238`, image `3daf00a1…` ; [rapport](PRODELI_COMMUNICATION_RELEASE_20261021.md). Organisation d'envoi inchangée ; test de ligne confirmée et confidentialité du code OTP corrigés, source `68de604`, image `01c194f4…` ; réception externe non testée | SMS/courriel/WhatsApp sans perte de configuration/fonction, essais contrôlés, aucun faux statut de livraison |
| 6. Recette globale et livraison | Lots 2–3 livrés : 47 PG + dix groupes navigateur sur image exacte, migrations 018/038, 120 sessions, contrôle indépendant PASS ; récupération/SMS livrés `68de604` sans nouvelle migration, 118 sessions et cache préservés ; validation humaine de ce lot et validation globale restent ouvertes | PostgreSQL/HTTP réels fictifs, rôles et écoles, pannes/reprises, bureau/mobile, image exacte et validation humaine |

## Lot 1 — réalisé localement

- `plateforme-prodeli.php` : seul le GET sans paramètres d'un acteur global redirige vers l'accueil existant `/schoolsafe-administrators.php?home=1`. POST et liens anciens paramétrés continuent vers `admin-console.php` ; console scolaire conservée. Chargement préalable `Warehouse.php` en `require_once`.
- `Wizard.php` : retire le menu central de l'en-tête ; conserve marque originale, déconnexion, lancement JASPE et retour au tableau de bord depuis les sous-écrans. Ajoute un bloc de commandes de communication réservé au global vers les pages SMS/WhatsApp existantes et la configuration scolaire courriel. La destination courriel reste soumise aux droits existants : pas un nouveau formulaire SMTP ni un accès automatique aux secrets.
- Console native accessible dans un détail « Opérations administratives existantes » : pas de suppression de commandes ni de registre concurrent.
- `central-ui.css` : disposition simple et focus clavier des commandes, réutilisant la palette existante ; version CSS 14 pour invalidation du cache. Le JS existant continue à fonctionner sans dépendre de l'ancien menu.
- Aucun changement de moteur de compte, fournisseur, base, OTP ou communication dans ce sous-lot. Les pages annexes SMS/WhatsApp conservent encore leur présentation : leur harmonisation n'est pas annoncée terminée.

## Preuves locales

- `scripts/prodeli-dashboard-contracts.php` : **5 PASS/7 FAIL avant → 12 PASS après**, renderer PHP réel, DB et permissions substituées ; certaines assertions sur les routes sont statiques, pas une preuve d'exécution des POST historiques.
- 16 contrats de présentation des comptes PASS ; deux lints PHP et `git diff --check` PASS.
- Cinq groupes navigateur PHP/PostgreSQL fictif PASS : redirection de l'entrée globale, absence de menu, logo, liens, retour, largeur 1280/390 ; GET des pages SMS, WhatsApp et console native 200. Aucune action d'envoi ni mutation métier.
- Six groupes de régression présentation des administrateurs PASS (trois comptes synthétiques nettoyés), incluant refus 403 de vue centrale pour acteur scolaire.
- QA dans le web distinct `schoolsafe-prodeli-presentation-qa`, relay loopback 8096 ; réseau/base fictifs existants. Aperçu 8094 conservé. Base runtime en cache + copie source, **pas une image release exacte**.

Preuves hors Git : `C:/Users/PC/SchoolSafeProductionUpdate-20261021-evidence/dashboard-completion/` et scripts QA voisins. Aucun secret dans les documents ni messages réels envoyés.

## Livraison du lot 1 — mise à jour

[Rapport de livraison](PRODELI_DASHBOARD_RELEASE_20261021.md). Source `38856445143050895b6502c51be18285e9309042`, image `9177067d…`, 28 contrats sur image exacte, 2 754 autres fichiers identiques ; cinq cas d'entrée exécutés avec handlers substitués. 78 sessions transférées, production vérifiée indépendamment et autres services inchangés. Aucune migration ni action réelle de compte/message. Les lots 2 à 6 demeurent ouverts.

## Priorité complémentaire du propriétaire — Communication

Terminer d'abord le sous-lot de réglages SMS/WhatsApp, après examen du Studio natif, des fonctions métier, de Notify et du dispatcher. Le propriétaire confirme que la répartition des messages/notifications existe : elle n'est ni réécrite ni redistribuée. Keycloak reste en pause. Trois fichiers applicatifs (deux pages et CSS dédiée), handlers et huit composants protégés identiques ; 28 contrats + huit groupes HTTP/PG fictif + cinq régressions dashboard PASS ; image exacte 56 contrats PASS, 2 755 autres fichiers identiques. Source `90f6238`, image `3daf00a1…` déployées, 169 sessions conservées, contrôle indépendant PASS. [Rapport](PRODELI_COMMUNICATION_RELEASE_20261021.md). Pas de test externe ni d'activation de ligne implicite.

## Complément tableaux de bord — navigation centrale

Répertoire scolaire auparavant tronqué à huit lignes, sans ouverture depuis les lignes : complété avec recherche, POST CSRF/périmètre vers le changement d'école Rosario natif et retour central depuis le portail pour le global. Accès direct aux comptes centraux, sans nouveau menu ; Keycloak et communication inchangés. 20 contrats, huit groupes HTTP/PG fictif et 19 groupes de régression PASS ; [rapport et limites](PRODELI_NAVIGATION_RELEASE_20261021.md). **DÉPLOYÉ** source `3eb2e9e35984de9c40e22c571b212e727b13a2bb`, image `c8d10345…` ; 76 contrats image exacte PASS, 2 753 autres fichiers identiques, 163 sessions préservées, contrôle indépendant PASS. Création/rattachement/premier accès/récupération toujours distincts et ouverts.

## Prochaine action exacte — mise à jour du 22/10/2026

Récupération/SMS **qualifiés sur image exacte `b7c276be…`** : cinq sources runtime, 2 755 autres fichiers identiques, 37 contrats PG + 92 renderer, dix groupes récupération/SMS et dix groupes natifs PASS. Course du lien natif reproduite puis corrigée : un seul succès et révocation transactionnelle, deux pannes de révocation annulent les écritures. QA interne fictive retirée, snapshot inchangé et production préservée. [Rapport](PRODELI_RECOVERY_SMS_20261022.md) et [préflight/retour arrière compatible](../../ops/releases/prodeli-recovery-sms-20261022/README.md). Restent autorisation spécifique de publication/déploiement, commit associé au manifeste, préflight/sauvegarde/bascule et recette après livraison. Aucun push/merge/déploiement effectué, aucun envoi externe sans destinataire autorisé. Ne pas refaire les lots tableau de bord/création/rattachement/premier accès.

## Historique — prochaine action avant qualification récupération/SMS

**Lots 2–3 livrés et vérifiés**, [rapport courant](PRODELI_NATIVE_ACCESS_RELEASE_20261021.md) : source `3670dad176a4fe6932e69e87a481a5f205131543`, image `36a9e2fb…`, recette `0ab0690`, 47 contrôles PG et dix groupes navigateur sur image exacte, 92 contrats, migrations 018/038, sauvegarde privée et 120 sessions préservées, contrôle indépendant PASS. **Acceptation du propriétaire reçue : « je valide ».** Aucun scénario manuel supplémentaire n'est déclaré ; les essais de création réelle restent limités à une école et un destinataire expressément autorisés.

Lot suivant distinct : récupération après première connexion et deux défauts SMS identifiés (`EMPTY_TEXT` avant émission OTP ; test de ligne utilisant le routage automatique). Reproduire sur données fictives et qualifier droits, anti-abus, expiration/usage unique avant toute livraison. Keycloak reste en pause ; ni reconstruction de communication ni envoi externe sans destinataire autorisé. Ne pas relivrer le helper seul ni effacer l'onboarding pour contourner le garde de rollback.

Historique de la prochaine action précédente : commencer le lot 2 avec la procédure native existante : préparation, POST final avec plugin désactivé, transaction compte/rattachement/audit/reprise et tests de première connexion. La table leadership absente exige une migration additive qualifiée avec sauvegarde/restauration ; ne pas changer globalement la politique de première connexion pour faire passer un test. Ne pas utiliser le garde `prodeli-presentation-scope.py` comme gate de ce nouveau lot : il limite volontairement la livraison précédente à quatre fichiers et refuserait les nouveaux changements. Mettre le plan/handoff à jour à chaque lot. Ne pas annoncer la création native, la récupération ou l'ensemble ProDéli terminés après les seuls tests d'accueil.
