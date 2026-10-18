# Lot 1 : retour au modele mobile SchoolSafe

Etat au 12 octobre 2026 : lot 1 toujours ouvert. Le lot 2 est suspendu pour finir la navigation. Ce document remplace les seuils responsive indiques dans les bilans precedents.

## Disposition implementee

Sources du modele : codex-ui-v1.css lignes 130-143 et unified-interface.js (navigation mobile, selection de branche, fermeture du panneau, gestion du focus).

- Telephone <=700px : barre basse fixe de 72px avec zone de securite, Tableau de bord, JASPE et Menu.
- Menu principal : panneau lateral de 310px maximum ; aucune sous-fonction depliee dans la liste principale.
- Choix de branche : panneau blanc distinct pleine largeur au-dessus de la barre basse, titre de branche, fermeture et sous-fonctions natives.
- Fermeture du sous-menu : retour au menu principal. Echap : fermeture et focus sur Menu. Tabulation contenue dans la navigation ouverte. Contenu et barre native rendus inert pendant l'ouverture.
- Choix de fonction : navigation Rosario conservee ; fermeture du panneau apres preparation AJAX du contenu.
- Tablette >=701px : menu principal fixe, panneau contextuel independant, contenu decale. Largeurs 204/220px jusqu'a 1100px ; 236/248px au-dessus.
- Aucun lien de formulaire, nom de champ, traitement PHP ou permission modifie.

JASPE est visible mais desactive et signale comme non raccorde via son titre. Aucun moteur ou faux dialogue ne lui est substitue. Sa presentation interactive complete reste une reserve.

## Verification de cette campagne

- node --check scripts.js : succes.
- Chargement du theme actualise dans le navigateur local : constate.
- 12 branches cliquees a 703px : une classe ss-open et un aria-expanded=true a chaque ouverture.
- Deux conflits herites corriges : position du menu natif (bottom inline) et marge du contenu annulee par le theme parent.
- Mesures a 703px : menu principal 204px, contenu commencant a 424px lorsque le panneau de 220px est ouvert. Trois zones distinctes visibles. Ecran etroit, le contenu restant est compact ; le calendrier conserve son defilement horizontal.
- Capture : lot1-tablette-navigation-v3.png.

## Non verifie, a ne pas declarer termine

La demande de viewport 390x844 n'a pas ete appliquee : innerWidth reste 703px. Par consequent, la barre basse, les transitions telephone, le focus mobile, les safe areas et l'absence de chevauchement sur un vrai viewport <=700px sont codes mais pas validates en navigateur dans cette campagne. Une reduction du panneau de preview a ete demandee au proprietaire. Tests desktop large, autres roles et impressions restent egalement en attente.

## Fichiers du theme touches

- scripts.js : navigation mobile, controles de fermeture, focus, inert et changements de breakpoint.
- redesign.css : seuil 701px et correction des conflits de disposition tablette.
- mobile-navigation.css : nouvelle feuille responsive.
- stylesheet.css : import de cette feuille.
- Copies identiques dans native-theme-runtime/assets/themes/SchoolSafeCodex pour la preview.

Le theme parent SchoolSafe reste une dependance. Rosario, sa base, ses comptes et permissions, Studio Cartes, scanner, recuperation, JASPE et VPS restent inchanges dans cette campagne. Aucun enregistrement metier soumis.
