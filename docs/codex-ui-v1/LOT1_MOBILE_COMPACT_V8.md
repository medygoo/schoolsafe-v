# LOT 1 - Menu mobile compact V8

Date : 2026-10-12. Travail local exclusivement, theme SchoolSafeCodex isole.

## Corrections
- Suppression des chevrons des branches, tous formats.
- Tiroir principal limite a 310 px, avec 24 px de reserve sur les petits ecrans.
- Lignes de 44 px minimum, texte 14 px, icones 20 px.
- Logo 36 x 36 px : suppression du conflit qui lui donnait la largeur du tiroir.
- En-tete fixe dans le tiroir, liste defilante independante, parametres en bas.
- Correction du niveau de superposition qui masquait le logo sous la barre native.
- Masquage explicite des sous-menus dans le premier niveau mobile.
- Bouton Parametres exclu du gestionnaire de fermeture par clic exterieur.
- Version du fichier CSS actualisee pour eviter son ancienne copie en cache.

## Preuves observees
Navigateur local, largeur reelle 481 px, hauteur 807 px.
- 12 branches : hauteur mesuree 44 px, pseudo-element fleche absent.
- Logo : 36 x 36 px, position 12/12 px, visible.
- Liste principale : largeur 310 px ; sous-menu masque.
- Bouton Parametres : hauteur 44 px, place au-dessus de la barre basse.
- Ecole : panneau contextuel independant plein ecran, largeur mesuree 481 px.
- Retour au premier niveau et activation clavier observes.
- Niveaux scolaires : page Rosario ouverte et menu ferme automatiquement.
- Parametres : ouverture clavier et clic final observes ; fermeture par Echap observee.
- Les essais de clic ont ete intermittents pendant les changements de panneau :
  verification tactile sur appareil reel et repetition des clics encore requises.

Captures : lot1-mobile-compact-v8.png et lot1-mobile-context-v8.png.

## Perimetre des fichiers
Source : app/styles/codex-ui-v1/native-theme/SchoolSafeCodex/
Copies servies : app/modules/codex-ui-v1/native-theme-runtime/assets/themes/SchoolSafeCodex/
Fichiers modifies : mobile-navigation.css, stylesheet.css, scripts.js.
Dependance : theme parent SchoolSafe existant ; archive non autonome.

## Limites
LOT 1 reste ouvert. Pas de validation complete a 320/390 px, sur tablette ou
ordinateur pour cette revision, ni des autres roles ou de l'impression.
JASPE reste desactive dans cette variante native tant que son raccordement manque.
Avertissement CSP natif observe sur le portail, non diagnostique dans ce lot.
Aucune sauvegarde metier, modification du moteur Rosario, du Studio Cartes,
de comptes, de droits ou du VPS pendant cette correction.
