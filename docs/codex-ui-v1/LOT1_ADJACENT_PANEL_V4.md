# Lot 1 : panneau adjacent, campagne V4

Cadre applique : ECC design-system, audit du modele existant. Reference : unified-interface.js (renderContext, close-context), codex-ui-v1.css (primary/context) et branch-presentation.css (entete et accents).

Corrections dans le theme isole uniquement :
- En-tete reel du sous-panneau visible aussi sur tablette/ordinateur, titre et fermeture 44px. Ancien titre CSS masque pour eviter les doublons.
- Fermeture desktop : panneau ferme, aria-expanded=false, focus rendu a la branche ; contenu retrouve sa largeur.
- Indicateurs de branche orientes vers le panneau lateral plutot que vers un accordeon.
- setMobilePanel ne masque plus le menu desktop lorsqu'appele hors du mode telephone.

Preuves navigateur a 720px : menu principal 204px, panneau 220px commencant a x=204, contenu a x=424. Fermeture : contenu a x=204, aucun panneau ouvert, aucun aria-expanded=true. Fermeture/reouverture Ecole : position de la branche stable a y=381.90625. Liste des href identique avant/apres les interactions. Syntaxe JavaScript validee avec node --check.

Le premier changement Personnel vers Ecole a entraine un defilement du menu lors du retour de focus ; ne pas confondre ce defilement avec un deplacement des branches par insertion du sous-menu. Le test sur la meme branche confirme l'absence de deplacement lors de l'ouverture.

Les sous-menus restent descendants de leur module dans le DOM pour conserver les attentes de openMenu dans warehouse.js ; leur position fixe les rend visuellement independants. Aucun remplacement du moteur ni de warehouse.js.

Reserve : toute la conformite du lot 1 n'est pas encore acquise. Telephone <=700px, grand ecran, autres roles, impressions, fidelite complete des en-tetes et gestion des zones de defilement restent a valider/completer. JASPE reste non raccorde. Aucun formulaire soumis ni modification de base, compte, droit, Studio Cartes ou VPS.

Fichiers modifies : SchoolSafeCodex/scripts.js, mobile-navigation.css et copies correspondantes dans native-theme-runtime. Capture : lot1-panneau-adjacent-v4.png.
