# Composants Codex conserves pour revue

Aucun changement des quatre fichiers visuels pendant LOT 0.

| Composant | Compatible | Correction apres validation |
| --- | --- | --- |
| button(), codex-ui-v1.js:29 | Texte echappe, icones Lucide, attributs parametrables | Contrat action stable, permission, callbacks, desactivation explicite; pas de gestionnaire actuel |
| badge(), metricCard(), lignes de tableau | Presentation reutilisable de statuts et compteurs | Sources officielles; marquage demo pour chaque valeur; acces aux donnees selon portee |
| subnav(), ligne 59 | Panneau de sous-fonctions independant | Filtrage reel, branche active, aria-current/pressed, contrat data-codex-action stable |
| dashboard(), ligne 71 | Structure de tableau de bord configurable | Menu principal fixe absent; donnees/fonctions du vrai role; retour et etats loading/error/empty/denied |
| jaspePanel(), ligne 85 | Representation 2D et icones existantes | Chemin image relatif incorrect en preview; Ecrit/Audio/Fermer sans action; reutiliser panneau et controleur existants |
| mount(), ligne 96 | Montage local HTML et Lucide | Verification du cycle de vie et des gestionnaires apres rerendu |
| preview.js | Trois exemples de role conserves | Ajouter direction/gardien/finance seulement apres autorisation; remplacer affirmations Reel/Raccordee/Reelle; afficher demo sur toutes les donnees |
| codex-ui-v1.css | Prefixe sscx, themes light/dark compatibles avec app.js:2762 | Rayon 16px, sections en panneaux; variables :root globales; cadrage mobile et focus a valider |

Comptage des boutons sans action dans les configurations : admin 2 actions + 6 sous-fonctions + 3 JASPE = 11; enseignant 1 + 5 + 3 = 9; parent 0 + 5 + 3 = 8. Total 28 instances configurees, et non 28 boutons visibles simultanement. Les trois boutons de selection du role possedent un gestionnaire dans preview.js:94; ils sont exclus.

Risque de presentation trompeuse : preview.js:20 appelle la licence Active/Reel; ligne 51 appelle une liste Raccordee; ligne 75 appelle la remise Reelle. Ces valeurs sont des constantes. Le badge global Prototype visuel ne transforme pas ces affirmations en preuves. Elles restent conservees sur instruction du proprietaire et ne doivent pas etre presentees comme operationnelles.

L'asset par defaut ./assets/jaspe2d/... est resolu depuis app/modules/codex-ui-v1/preview.html et non app/index.html : le fichier attendu n'existe pas a cette adresse. Aucun moteur JASPE ne doit etre remplace pour corriger ce chemin.
