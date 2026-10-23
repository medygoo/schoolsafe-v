# Stabilisation scolaire — menu existant et treize vues

## Mandat courant

Le propriétaire confirme la présentation de ses deux captures privées (ordinateur et téléphone), le maintien des treize vues et de leurs contenus, les mêmes destinations autorisées à compte/droits identiques sur chaque format, et l’ordre **analyse → correction → tests → stabilisation → production**. Ne pas reconstruire le menu, remplacer l’accueil par une page unique ou restaurer les anciens accès centraux présents dans des captures historiques. Les originaux personnels ne sont pas publiés.

Branche `fix/school-dashboard-stability-20261022`, départ `14365d0f86eb7d95baf9d5503398142177b3acb3` égal à son distant. Un seul agent écrivain. Base de livraison inchangée : image `sha256:1e8259e4af681d8d905c7a23d80cca860fe64bc44dd795adfcdab0d14c43981f`, sources runtime comparées à `2f8b9f10ed3026ff6f5a3ced4452058d8222881f`.

## État — QUALIFICATION FINALE PASS, livraison en préparation

- Analyse et mécanismes : [rapport précédent](SCHOOL_DASHBOARD_NAVIGATION_ANALYSIS_20261022.md), complété par les captures fournies. Son option de remplacer le découpage n’est pas retenue : garder les vues existantes.
- Tests navigateur DOM fictif avec vrais assets : **122 PASS / 0 FAIL** après corrections.
- Frontière centrale/scolaire : **84 contrats PASS**, autorisation et données substituées, pas une nouvelle certification HTTP.
- Syntaxe des JS modifiés/harnais et AST Python, `git diff --check` : PASS.
- Premières images exactes r1/r2 : 156 contrats PHP + 37 PG PASS, 2760 fichiers inchangés, parcours réels des treize vues/menus/historique aux largeurs1280/390/768 PASS avant échec d’une destination de détail. Diagnostic r2 : `details_tab=presence` référence la table optionnelle `entry_exit_student_records` absente du fixture ; `DBGet()` arrête la page avant le `catch` du rendu indisponible. Aucun problème de production équivalent n’est supposé sans preuve. Snapshots de vérification et nettoyage r1/r2 PASS ; production inchangée.
- Correctif de disponibilité étroit, cinquième fichier runtime : 7/3 avant, **10/0 après** dans les contrats SELECT-only. Ajouter la source native seulement si son helper de disponibilité existant la confirme ; sans bridge ni registre natif, lever `ARRIVAL_READ_UNAVAILABLE` avant SQL métier, laisser le renderer existant afficher son indisponibilité, jamais zéro inventé. Même périmètre école/année/inscription/date, autorisation enseignant et agrégats conservés.
- Final exact-r7, navigateur côté VPS, source runtime `a69e745d5b4bd36efc60dad4317583a0af037a4a`, harnais `5ddff1df97b48c61f7afb8e066cceaedf84a6275` : **PASS**. Image `sha256:b271dcc5c4e069623b278d327af6f71013b5fcc88c3bc90cee53c7f3bffe12e7`, digest `257f7b7913586eecd1b789429e17e300f931babecf496f8c981b475496cdf07d`, **166 PHP / 37 PG**, **25 groupes natifs / 10 récupérations**, deux rollbacks, deux sessions, un gagnant et un epoch. Vues réelles et menu terminé comparés à1280/390/768 ; deux écoles/comptes fictifs, création et reconnexion, dossiers et destinations GET. Captures authentifiées1280/390 ouvertes/revues ; menu desktop étiqueté et chrome mobile existants. Version/cache JS thème modifiée (mtime différent de la base vérifié), URLs photo rafraîchies.
- Vérification finale : **snapshot fixture inchangé**, aucun compte/schéma d’essai restant, production inchangée, cleanup nonce PASS. Navigateur isolé supprimé, bibliothèques existantes non installées, aucun fournisseur/configuration Docker modifié. Les fichiers de téléchargement ont été effectivement lus et contrôlés, pas dispensés du test.
- Essais rouges conservés : r3 interruption SSH après parcours UI PASS, nettoyage repris et PASS ; r4 multiplexage Windows non utilisable (zéro groupe) et cleanup PASS ; r5 comparaison du SSR transitoire avant métadonnées, attente de fin d’organisation corrigée ; r6 transport PG/SSH interrompu, clone supprimé ; r7 première sous-exécution sans dépendance, puis artefacts Chrome séparés du client, résolus par dépendances transitives et partage du seul répertoire temporaire QA. Snapshot vérifié entre reprises ; ces erreurs de harnais ne sont pas annoncées comme fautes de production.
- Livraison et contrôle distinct : **À FAIRE**, recette `ops/releases/school-dashboard-stability-20261022/`. Aucun compte réel testé, aucune opération financière ou envoi externe réel. Les parcours ci-dessus ne prétendent pas certifier chaque opération métier possible. Ne pas annoncer « tous les boutons fonctionnels » ou « production stabilisée » sur ces seuls contrôles DOM.

## Corrections candidates — cinq fichiers runtime ciblés

1. `assets/js/schoolsafe-photo-dashboard.js` : les cartes délèguent au contrôleur de menu existant ; le clic déclencheur ne referme plus le panneau ; vrai href de repli et gestes Ctrl/Meta conservés. La pagination garde tous les blocs, notamment le corps des dossiers auparavant parqué ; vue nommée dans `dashboard_view`, historique navigateur, rechargement, ancres et formulaires GET du portail conservent le bon écran. Démontage réversible et retrait des gestionnaires, y compris lors d’un remontage du même DOM.
2. `assets/themes/SchoolSafeCodex/scripts.js` : point d’entrée `schoolSafeNavigation.openBranch`, réutilisant les branches, liens, seuil 700, focus et états existants. Aucune destination ou permission ajoutée.
3. `assets/css/schoolsafe-photo-dashboard.css` : retrait des remplacements du menu et de l’en-tête natifs par le tableau de bord ; contenu paginé positionné après le vrai menu principal/contexte et entre en-tête/dock. Couleurs, icônes, thèmes historiques et commandes natives restent leurs propriétaires d’origine. Les anciennes règles `ss-school-shell` restent inactives, plutôt qu’un nettoyage transversal hors lot.
4. `ProgramFunctions/SchoolSafePhotoDashboard.fnc.php` : nouvelles URLs de cache CSS/JS `school-stability-20261022`, sans modification des calculs, requêtes, droits ou moteurs métier dans ce premier candidat.

5. `schoolsafe-attendance/ArrivalRead.php` : garde des deux sources optionnelles décrite ci-dessus, déclenchée par une destination réellement en échec. Contrats `scripts/school-arrival-availability-contracts.php` : sources absentes/native/bridge/union, aucun SQL de table absente, indisponible distinct de zéro, périmètre et refus d’enseignant révoqué.

Aucun changement du moteur Rosario `warehouse.js`, de l’authentification, de la récupération, de l’onboarding, des permissions, des transports ou du schéma. La notation reste un lot séparé. Watch ouvre son état existant honnête « aucune montre raccordée » ; ce n’est pas une intégration de montre ni une fonction simulée.

## Régression prouvée avant correction

`app/qa-school-dashboard-navigation.cjs`, Playwright existant, aucune dépendance installée, service worker interdit, toute requête externe ou non-GET bloquée. DOM scolaire explicite fictif, vrais JS/CSS, vraies fonctions natives de menu extraites sans modification ; aucun login ou DB dans cette suite.

- Première campagne : **39 PASS / 50 FAIL**. Reproduit dossiers cachés, menu de carte invisible/refermé, remplacement des outils du compte et état de vue perdu.
- Premier candidat : **89 PASS / 0 FAIL**, huit largeurs : 360/390/700/701/768/900/901/1280.
- Extension de régression : **98 PASS / 24 FAIL**. Reproduit retour incorrect après filtre, fragment de dossiers non restauré et écouteur obsolète après démontage/remontage.
- Corrections correspondantes : **122 PASS / 0 FAIL**. Nombre exact de treize vues dans le jeu complet explicite, contenu et une seule vue active à chaque sélection, mêmes liens natifs entre formats, palette, Watch honnête, précédent/suivant, historique/rechargement, dossiers, filtres GET, ancres, démontage, état invalide et jeu restreint sans gains de droits.

Preuves privées : `school-dashboard-stability-20261022/{before,after-r1,edge-before,after-r2}/result.json` et captures. Les captures DOM sont des diagnostics structurels, **pas une reproduction authentifiée ni une acceptation visuelle de production**. Couverture de code globale à 80 % non mesurée/revendiquée.

## Qualification suivante

`scripts/school-dashboard-image-qa.py` réemploie la chaîne éprouvée d’image immuable, snapshot de production en lecture seule, dump du seul fixture local étiqueté, réseau QA interne et nettoyage nonce-owned. Comparaison complète avec la base livrée : seules les cinq sources déclarées peuvent différer. Ce script ne déploie pas.

`app/qa-school-dashboard-http.cjs`, opt-in `SCHOOL_NAV_HTTP=1` depuis le harnais natif déjà gardé par label/réseau/loopback : après activation des seuls comptes fictifs, vérifier les treize vues réellement rendues, leurs corps, les menus, l’historique et les destinations GET autorisées. Le harnais natif reste responsable de ses propres fixtures et de leur suppression. Pas de soumission métier à partir de ce complément. Les opérations financières, envois, copies d’école et comptes réels ne sont jamais testés en production.

Après tous les contrôles : sauvegarde récente vérifiée, provenance exacte, préservation des sessions/cache/configuration/autres conteneurs, bascule ciblée et contrôle indépendant. Aucun retour arrière ou restauration automatique.
