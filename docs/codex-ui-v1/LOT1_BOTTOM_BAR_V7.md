# Barre mobile : V7

Reference ECC design-system et modele codex-ui-v1 : telephone <=700px, trois colonnes Tableau de bord / JASPE / Menu. La barre existe deja ; alignement renforce par une seule rangee explicite, cases minmax(0,1fr), contraintes de largeur et icone Menu avec stroke explicite. Le bouton Menu natif isole en haut est masque sur tous les formats ; tablette/ordinateur gardent le menu lateral visible.

Controle navigateur a 1127px : bouton haut display:none, menu lateral display:flex, barre mobile display:none comme attendu hors telephone. Trois enfants de la barre dans le bon ordre. Panneau adjacent visible. Capture : lot1-sans-menu-double-v7.png.

La demande de viewport 390x844 reste sans effet, largeur reelle 1127px. Rendu des trois boutons sur telephone non valide visuellement. Override reinitialise. Lot 1 non clos. JASPE reste desactive, sans faux raccordement. Une alerte native CSP est visible sur le portail ; son origine n'a pas ete investiguee dans cette campagne.

Seul fichier de theme modifie : mobile-navigation.css, copie runtime identique. Aucun formulaire soumis ni changement du moteur, de la base, des comptes, des droits, du Studio Cartes ou du VPS.
