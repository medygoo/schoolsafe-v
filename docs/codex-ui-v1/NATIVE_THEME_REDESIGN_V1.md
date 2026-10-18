# Theme natif SchoolSafeCodex : premiere variante

Statut : ACTIVE sur le compte Admin A Administrator dans la copie locale isolee ; chargement constate en navigateur. Validation complete non terminee.

## Architecture

La variante SchoolSafeCodex herite du theme SchoolSafe deja present dans le Rosario local. Les ecrans utilises sont les vrais modules Rosario, sans school-live.php, iframe, endpoint alternatif ou duplication de formulaire. Les anciens visuels et adaptateurs sont conserves mais ne sont pas des dependances du theme.

Le theme existant et FlatSIS restent des dependances obligatoires. Les imports ui-system du theme parent et ses polices Google externes restent egalement actifs. La variante utilise une police systeme, mais ne supprime PAS la requete de police externe du parent.

## Contenu

- stylesheet.css : import du theme parent puis de la surcharge.
- redesign.css : controles de 44px, tableaux, focus visible, accents par branche, contraintes de fenetres et petits ecrans.
- stylesheet_wkhtmltopdf.css : presentation PDF distincte.
- scripts.js : script SchoolSafe adapte pour les titres, aria-controls et fermeture Echap.
- logo.png, spinning.gif, btn/, modules/, icons/ : copies inchangees des ressources existantes.

Les accents completent les textes et icones, sans definir de permission ou fonction metier. Compatibilite apres navigation AJAX a verifier.

## Lot 1 : progression

Panneau secondaire blanc code pour >=1200px, liens natifs conserves. Ouverture mobile et fermeture Echap constatees ; titre de branche et aria-controls presents. Syntaxe JavaScript validee avec node --check. Le viewport demande n'a pas ete applique a la page : tests desktop/tablette non conclusifs. Lot non termine. Aucun test de sauvegarde metier effectue. Preuve : lot1-navigation-mobile.png.

## Installation locale ulterieure

1. Conserver une sauvegarde et la valeur de theme/preferences actuelle.
2. Ajouter SchoolSafeCodex sous assets/themes/ dans une copie locale writable de Rosario ; ne pas ecraser SchoolSafe.
3. Conserver assets/themes/SchoolSafe, assets/themes/FlatSIS et ui-system/ a la racine Rosario.
4. Selectionner SchoolSafeCodex via les preferences d'un compte local de test, sans le forcer pour tous les comptes.
5. Ouvrir directement Modules.php, pas schoolsafe-view/school-live.php.
6. Verifier la branche Ecole, puis les autres branches et impressions.
7. Retour arriere : rechoisir le theme anterieur via les preferences natives.

Le conteneur Rosario original reste inchange. L'activation a ete effectuee dans le nouvel environnement isole decrit ci-dessous, uniquement dans les preferences visuelles du compte de test.

## Limites et validation requise

- Panneau independant ajoute pour >=1200px ; validation desktop necessaire. Navigation repliable conservee en dessous.
- Les tableaux de bord enrichis, statistiques par classe et palmares ne sont pas crees par un theme CSS.
- Les fonctionnalites SchoolSafe hors Rosario ne sont pas raccordees par ce theme.
- Styles responsive prepares, mais tests desktop/tablette/mobile non executes.
- Aucun resultat de sauvegarde, PDF, permission ou interaction ne peut etre declare valide sur cette variante avant activation et tests.
- Ne pas modifier ou activer de parametrage de securite, comptes, migrations ou fonctions sensibles pendant les essais visuels.
- Studio Cartes et interfaces externes : aucun fichier modifie ; verifier l'absence d'effet du theme sur tout module de cartes rendu a l'interieur de Rosario.

## References examinees

- Rosario local : assets/themes/README.md ; assets/themes/SchoolSafe/README.md ; stylesheet.css ; scripts.js ; stylesheet_wkhtmltopdf.css.
- Warehouse.php : chargement de la feuille CSS du theme choisi et du scripts.js optionnel.
- Documentation officielle : https://gitlab.com/francoisjacquet/rosariosis/tree/mobile/assets/themes

Cette livraison amorce le redesign natif ; elle ne constitue pas une cloture de la branche Ecole ni une autorisation de deploiement.

## Environnement natif isole disponible

Un environnement supplementaire a ete prepare apres cette premiere livraison :

- URL : http://127.0.0.1:18582/index.php
- Conteneur web : codex-native-theme, publication uniquement sur 127.0.0.1.
- Code : app/modules/codex-ui-v1/native-theme-runtime/, copie du Rosario local sans son dossier .git ; aucun fichier source original modifie.
- Base : rosario_theme_test, restauree depuis un dump frais de rosario_test dans le serveur PostgreSQL local existant. Aucun changement de configuration ou de donnee de la base source.
- Configuration locale : seul le nom de base a ete change dans la copie ; secret de connexion fourni via la variable existante, pas inscrit dans les rapports ou le paquet de theme.
- SchoolSafeCodex ajoute au repertoire de themes de cette copie.
- Page de connexion native ouverte et constatee dans le navigateur. Une premiere erreur de connexion due a l'absence de variable de mot de passe a ete corrigee en recreant uniquement le nouveau conteneur avec la variable locale existante.

Apres connexion de l'utilisateur, SchoolSafeCodex a ete selectionne et enregistre via Utilisateurs > Mes preferences > Options d'affichage. Apres rechargement, la selection persiste et la feuille assets/themes/SchoolSafeCodex/stylesheet.css est chargee. La fiche native School_Setup/Schools.php affiche les donnees existantes de Default School sans iframe.

Le contraste du texte des boutons de sauvegarde a ete corrige dans la surcharge du theme et sa copie d'execution ; l'import redesign.css?v=2 renouvelle le cache. Aucune sauvegarde de donnees scolaires, modification de mot de passe ou de droits n'a ete effectuee pendant cette activation. Seule la preference visuelle du compte dans rosario_theme_test a ete enregistree.

Preuve visuelle : theme-natif-ecole-active.png dans les livrables. Ne pas forcer le theme pour tous les comptes pendant les premiers essais.

Ne pas inclure native-theme-runtime/ dans le paquet public : il contient une copie locale de l'application et potentiellement des fichiers non publics. Ne pas diffuser le dump de base. Les tests responsive complets, sauvegardes metier, navigation AJAX et PDF restent a faire.
