# Navigation SchoolSafe : campagne V5

Cadre ECC : design-system et browser-qa, campagne locale en lecture seule.

Correction du theme : logo natif conserve et reduit a 36px, nom SchoolSafe visible, en-tete fixe dans la colonne principale. Les selecteurs natifs sont conserves. La liste des branches dispose de son propre defilement ; le panneau adjacent garde son defilement et sa fermeture.

Verification navigateur a 720px : hauteur de l'en-tete reduite de 423px observes a environ 322px ; liste principale hauteur 437px, contenu 550px, overflow-y:auto. Acces a Controle et rapports : scrollTop de la liste environ 113px, position de l'en-tete inchangee (top 39px), nom SchoolSafe visible. Sous-panneau a x=204.

Navigation par le lien Niveaux scolaires : ecran natif GradeLevels.php charge, data-ss-page coherent, une branche ouverte et un aria-expanded=true. Aucun formulaire soumis. Syntaxe JavaScript validee.

Modifications : scripts.js (nom de marque idempotent), mobile-navigation.css (identite compacte et defilement de la liste), copies dans le runtime isole. Preuve : lot1-navigation-compacte-v5.png.

Lot 1 non clos : validation reelle sous 700px et grand ecran encore absente, autres roles et impressions non verifies, JASPE non raccorde, fidelite complete au modele restant a examiner. Ces tests ne sont pas remplaces par les controles a 720px. Aucun changement Rosario PHP, base, droits, comptes, Studio Cartes ou VPS.
