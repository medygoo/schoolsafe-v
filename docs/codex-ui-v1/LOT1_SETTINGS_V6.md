# Profil et parametres : V6

Regroupement visuel local selon le modele SchoolSafe et le cadre ECC design-system/browser-qa.

Bouton fixe en bas de la colonne principale. Panneau adjacent : titre, compte connecte, langues et selecteurs ecole/annee/periode. Resume du contexte visible sous la marque, cliquable pour ouvrir les parametres.

Le formulaire reste dans #menu-top pour respecter le remplacement AJAX natif. Aucune duplication ni recreation des selecteurs : school, syear, mp gardent leur classe onchange-ajax-post-form et leur action Side.php?sidefunc=update. prepareSettings est reapplique lors de ajaxPrepare du menu ou de son en-tete.

Tests en navigateur a 720px : en-tete 128px contre environ 322px avant ; bouton bas 48px ; ouverture et fermeture constatees, aria-expanded coherent, focus rendu au bouton ; un seul formulaire. Valeurs inchangees : ecole 1, annee 2026, periode 5. Aucun changement de selecteur ni soumission effectue. Persistance d'un changement de contexte non testee dans cette campagne. Syntaxe JavaScript validee.

Apparence blanc/bleu non ajoutee ici : aucun changement de theme natif ni preference sauvegardee. Le panneau affiche seulement les controles existants verifies. Lot 1 reste ouvert : responsive telephone et grand ecran, autres roles, impressions et fidelite complete restent a valider. JASPE demeure non raccorde.

Fichiers : scripts.js, stylesheet.css, nouveau profile-settings.css, copies runtime correspondantes. Preuve : lot1-profil-parametres-v6.png. Aucun moteur Rosario, base, compte, droit, Studio Cartes ou VPS modifie.
