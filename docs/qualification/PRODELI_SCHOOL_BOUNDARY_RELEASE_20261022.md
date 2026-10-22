# Finalisation ProDéli et frontière scolaire — livraison du 22/10/2026

## Autorisation et statut

**GO explicite de production reçu**, puis confirmé : terminer/stabiliser/sécuriser ProDéli avant le point suivant. Le propriétaire ajoute le contrôle des écrans ProDéli sur téléphone et la correction du bouton scolaire « Configurer mon école », qui ne doit jamais ouvrir ProDéli. Le remaniement des treize vues/menus du tableau de bord scolaire demeure un sujet séparé.

**À ce stade de ce rapport : qualification de l’image finale en préparation ; pas de bascule exécutée.** La production reste sur `a4a3cbf8…` / recette `8a969d5`. Ce statut sera remplacé par les preuves effectives de livraison, pas par une simple réussite locale.

## Périmètre final — douze fichiers runtime

Les dix fichiers du [correctif local initial](PRODELI_SCHOOL_BOUNDARY_20261022.md), plus :

- `ProgramFunctions/SchoolSafePhotoDashboard.fnc.php` : uniquement le href du bouton de configuration rendu absolu, `/Modules.php?modname=School_Setup/Configuration.php`, afin de ne pas dépendre d’une URL scolaire imbriquée. Aucune réorganisation des vues du tableau de bord.
- `modules/School_Setup/Configuration.php` : contexte scolaire valide obligatoire ; sauvegarde des réglages généraux dans `config` avec l’identifiant de l’école courante, jamais dans une valeur héritée `SCHOOL_ID=0`. Le fonctionnement `ProgramConfig` déjà scolaire et les traitements scolaires natifs sont conservés.

`Warehouse.php` ajoute aussi des règles de présentation émises **uniquement pour les comptes globaux** : dock JASPE/Watch/Menu scolaire et outils scolaires supprimés visuellement de ProDéli ; en-tête natif résiduel en flux normal, langue et déconnexion lisibles/cliquables conservées, flèche de sous-menu scolaire sans destination retirée. Les assets CSS historiques ne sont pas modifiés ; les comptes scolaires ne reçoivent pas ces règles et gardent leur navigation. Aucun changement de traitement JASPE/Watch ou de préférence historique.

La garde centrale est maintenant bidirectionnelle : ProDéli ne peut entrer dans les modules scolaires ; les comptes scolaires ne peuvent entrer dans les contrôleurs de plateforme ni forcer les onglets globaux d’installation de modules/plugins. Les requêtes anonymes conservent les contrôleurs d’authentification existants. Les refus scolaires offrent un retour vers leur propre portail, pas vers ProDéli.

Le défaut de portée est reproduit par nouveaux contrats sur l’ancien runtime : 47 réussites / 25 échecs, puis 84/0 après correction et ajout des refus d’onglets. Le href relatif est constaté dans la source ; le chemin exact emprunté par le navigateur du propriétaire n’a pas été enregistré. La correction est donc prouvée par le clic réel et la destination scolaire des fixtures, pas par l’inspection d’un mot de passe réel.

Aucune notation déplacée, migration de production, restauration, conversion d’identité gérée, changement de fournisseur, envoi externe ou manipulation de compte réel pour essai.

## Qualification attendue de l’image finale

- 156 contrats PHP : 84 frontière/éligibilité/activation + 16 comptes + 16 intentions + 12 logique dashboard + 28 communications.
- 57 contrôles PostgreSQL natifs/conflits/rollback et 37 récupération/SMS avec transport substitué.
- 21 groupes natifs/HTTP : temporaire, expiration/renouvellement, choix personnel, reconnexion, legacy inutilisé, configuration propre à l’école et refus de ProDéli. Le test de configuration provoque un POST sans CSRF, puis un POST autorisé : l’override scolaire est créé, le faux défaut plateforme reste intact. Les six étapes du wizard sont traversées/capturées à 390 px.
- Récupération/SMS et concurrence réelle de reset : préservation des chemins critiques, sans envoi réel.
- Huit écrans centraux à 1280/390/360 px : accueil, administrateurs, wizard, demandes, console, SMS, WhatsApp et préférences. Titre réellement visible (H2 de `.ss-page-heading` pour préférences, le titre natif original étant volontairement caché par le thème), absence de débordement global, captures relues et nettoyage. Le défilement horizontal des tableaux contenus est distinct d’un débordement de toute la page.
- Diff exact de douze fichiers, 2 752 autres fichiers identiques (contenu, mode, propriétaire/groupe), conteneur web QA en lecture seule, réseau interne sans port publié, snapshots fictifs étendus inchangés, production non modifiée pendant les tests.

## Essais antérieurs et corrections du harnais

- `exact-r1` : 114 contrats, 57 PG natifs et 37 PG récupération PASS, 19 groupes natifs avant une assertion de texte d’aide lue avant sa visibilité. Un contrôle ciblé retrouve le bon texte et l’absence de bouton temporaire sur les comptes terminés ; la vérification attend maintenant la visibilité. Cet essai reste FAIL, pas un succès de 20 groupes.
- `exact-r2` : nouvelle image d’essai (ID différent, même digest de payload), contrats PASS. Le contrôle responsive s’arrête parce qu’il exige H1 sur les préférences natives, qui utilisent `table.header h2`. Le sélecteur est corrigé, sans modification de l’application pour masquer ce défaut de test. Les premières captures prises avant visibilité ne constituent pas des preuves visuelles valides. Ressources éphémères nettoyées.
- `exact-r3` : image finale à douze fichiers, 156 contrats + 57/37 PG PASS ; arrêt responsive sur le H2 natif des préférences, volontairement caché après clonage par le thème. Le véritable titre visible est `.ss-page-heading h2` (constaté dans le JS/CSS du thème). Correction du sélecteur, pas de modification du thème. L’image de produit n’a pas changé pour cette correction du harnais ; une réutilisation de son ID immuable avec nouvelles fixtures, contrôles de labels/hashes complets et exécution finale est autorisée par la recette.
- `exact-r4` : 21 groupes natifs PASS et 24 pages responsive PASS, tous avec contenu visible et zéro débordement global. Récupération : neuf groupes PASS, puis l’ancien helper de connexion attend encore 200 sur le portail scolaire pour le faux compte global, maintenant correctement refusé 403. Le helper vise désormais l’accueil ProDéli pour le global et exige explicitement son refus du portail scolaire. La protection n’est pas assouplie. Les résultats natifs/mobile peuvent être conservés avec provenance et hashes sur le même ID immuable, scripts identiques ; récupération/concurrence/snapshot finaux doivent passer sur de nouvelles fixtures. L’essai r4 complet reste FAIL.
- `exact-r5` : image `84321036…` entièrement qualifiée avec les preuves natives/mobile r4 sur le même ID, 10 groupes récupération, concurrence (un gagnant/deux sessions, rollback false/exception), snapshot final et nettoyage PASS. **Avant livraison**, relecture humaine des captures : dock scolaire superposé aux pages centrales et parent `#footer` fixé sur les préférences. Correction globale-only dans WarehouseHeaderJS (pas de disparition de navigation scolaire) ; le harnais contrôle maintenant `#footer` **et** `#footerwrap`, le dock/overlay et la flèche scolaire. Le diagnostic local constate dock absent au centre, présent à l’école, parents non fixes et sortie visible >=44 px. Une nouvelle image et une qualification complète sont obligatoires : r5 n’est pas utilisé pour certifier ce nouveau runtime.
- Nouvel ajout scolaire : 21 groupes locaux PASS, après adaptation du harnais au jeton CSRF natif dans le `<head>` (pas un input caché ; le balisage peut aussi dupliquer le meta dans le corps). Aucun contournement du contrôle CSRF.

Ces preuves antérieures ne remplacent pas une exécution finale sur l’ID d’image livré. Les sources Git canoniques sont utilisées, pas les fins de ligne du worktree Windows.

## Livraison et clôture — à renseigner après qualification

Recette : `ops/releases/prodeli-school-boundary-20261022/`. Sauvegarde PostgreSQL privée 0600/validée et sources antérieures avant bascule ; sessions et cache d’impression copiés après arrêt ; configuration, paramètres Docker, services voisins et prédécesseurs arrêtés conservés. Aucun rollback automatique. Contrôle final de recette puis contrôle indépendant HTTPS, sources et conservation.

Preuves privées sous `school-boundary-release-20261022/`. Aucune donnée de connexion réelle ne doit entrer dans ce rapport. Après clôture, reprendre uniquement l’analyse du tableau de bord/menus scolaire signalé par le propriétaire ; ne pas présenter les contrôles ProDéli comme une certification de tous les modules ou téléphones.
