# Correspondance des destinations SchoolSafe et Rosario PHP

## Livraison

- ROSARIO_SCREEN_BINDINGS_V1.json : les 211 destinations du catalogue, avec leur etat individuel.
- app/modules/codex-ui-v1/rosario-view/screen-routes.json : liste de 22 destinations vers des routes presentes.
- app/modules/codex-ui-v1/rosario-view/screen-bindings.json : matrice generee utilisee par la vue locale.
- build-rosario-screen-map.cjs : generation et verification des identifiants et fichiers PHP ; refuse une route absente ou invalide.

## Resultats et limites

22 correspondances de navigation, 1 Studio Card preserve, 188 sans correspondance PHP confirmee dans cette liste initiale. Plusieurs destinations partagent une route : 22 ne signifie pas 22 ecrans natifs distincts.

Ces nombres ne constituent pas un inventaire de fonctions manquantes. La liste de 22 n'est pas exhaustive des correspondances possibles. Les autres destinations restent a examiner, notamment dans les moteurs SchoolSafe Node existants, et ne doivent pas etre remplacees par une fonction PHP approximative.

Les correspondances ouvrent l'ecran natif correspondant. Pour une destination composite comme Devoirs et corrections, Assignments couvre l'ecran des devoirs, pas une garantie de tout le workflow de correction. Les champs et operations doivent encore etre controles.

La vue locale genere les liens seulement si le module est actif, la route presente dans le menu du profil et AllowUse renvoie true. Les routes non permises ne sont pas affichees. La protection serveur de Modules.php demeure l'autorite, pas cette liste JSON.

Observation navigateur administrateur : 20 liens affiches sur les 22 correspondances ; les routes teacher/student non presentes dans ce menu sont masquees. Le lien Personnel a ete clique et ouvre Users/User.php, avec formulaire Trouver un utilisateur. Aucun formulaire soumis ni dossier modifie.

Tests : generateur termine avec succes, existence des 22 routes, lint PHP reussi, chargement de la vue et filtrage du menu administrateur observe. Autres profils, parcours de sauvegarde et tests responsive non valides par ce controle.

## Presentation conservee

Le preview 5187 reste une demonstration : ses callbacks et fixtures n'ont pas ete remplaces. Les 22 liens nouveaux sont dans la vue de test sous Rosario, pas encore dans tous les boutons du preview. Les composants existants restent conserves.

Un visuel manquant ne doit etre declare qu'apres examen du composant ET de la fonction metier. Le detail filles/garcons, les presences enseignants et l'emplacement du palmares existent deja visuellement ; leurs donnees restent a raccorder. Un tableau de resultats top 10 reel reste a completer apres validation des criteres.

## Priorites

1. Completer les correspondances des 188 destinations non confirmees, sans declarer leurs moteurs absents.
2. Pour chaque route confirmee, adapter les champs et boutons du composant existant au contrat natif, au lieu de seulement ouvrir Rosario.
3. Confirmer le perimetre de chaque role et les variantes de routes, puis tester dans les sessions propres.
4. Preserver totalement Studio Card, scanners et moteurs financiers ; une route ressemblante ne suffit pas a etablir leur equivalence.
5. Seulement ensuite dresser une liste validee des nouveaux visuels a creer.

FULL_APPLICATION_BINDING=NOT_COMPLETE

PREVIEW_LIVE_CALLBACKS=NOT_CONNECTED

PRODUCTION_READY=NO
