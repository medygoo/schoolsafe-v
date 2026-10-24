# Tableau de bord — logo relevé et recherche raccourcie (24 octobre 2026)

## Livraison

- Production : checkout `a1c03ff2977d406781786b2abae9de93be3aae9b`, image **exacte** `sha256:0f002b209ec308325b1171ad6a921a396903aef2a091ab675b4a1f8126c107b0`, basée sur l'image portrait `sha256:ed97d7d18745e715bf8075ac066967e11444d79d657b9d08e3bbb6f632c549a6`.
- Modification de **deux fichiers applicatifs** : `SchoolSafePhotoDashboard.fnc.php` et `schoolsafe-photo-dashboard.css`. La sélection du vrai logo par école et ses replis natifs ne changent pas ; sa figure quitte le bas du héros pour rejoindre la barre au-dessus du contenu, **avant** la recherche. Recherche : maximum 270 px sur grand écran, 156 px à 360 px et 171 px à 390 px dans la fixture ; logo 88/72 px, image non étirée, animations désactivées si demandé.
- Aucun changement de schéma, de comptes, de droits, de session, de configuration ni des deux fichiers média réels. Les trois montages persistants (configuration, logos, portraits) ont été conservés. Ancien conteneur préservé à l'arrêt. Aucun rollback automatique ni restauration de dump.

## Qualification et vérification

- Image exacte : 178 assertions de rendu, 37 de récupération, 2 fichiers changés, 2 803 fichiers applicatifs inchangés, réseau interne sans port publié, conteneur de test en lecture seule.
- HTTP authentifié sur école et comptes **synthétiques** : 62 assertions dont la position réelle dans le HTML rendu ; fixture préservée puis nettoyée. Contrats navigateur DOM synthétique/CSS réel : 8 cas (360/390/768/1280 px en clair/sombre), sans débordement. Repli de logo antérieur : 12 cas. Syntaxe PHP et `git diff --check` OK.
- Avant bascule : dump base privé (688 532 octets, mode 0600) et archives média locales existantes contrôlées. Après bascule : santé OK, page d'accès public 200, accès anonyme protégés, réglages et services voisins inchangés, 108 fichiers de session transférés, empreintes du logo et du portrait réel identiques. Vérification indépendante des trois montages, des empreintes média, de l'image et de la CSS : PASS.

## Rectificatif après inspection de la photo du propriétaire

La première disposition ci-dessus était **trop petite et du mauvais côté sur téléphone**. La zone rouge de la photo demande une grande image **à droite**, couvrant la recherche et les sélecteurs ; la recherche reste raccourcie **à gauche**. Correction distincte ensuite livrée : checkout `243fe43576a3aaa70c2972a67699405df1935dd6`, image `sha256:2ad1a6fcac1675be4c7fef301831892c464a55f493f98988ad316aabd1c758d1`. Logo 172 px de haut occupant la colonne droite à 360/390 px ; recherche 156/171 px dans les fixtures. Capture synthétique privée confrontée à la zone rouge ; huit scénarios CSS, 62 HTTP authentifiés synthétiques, 178 contrats image et 37 de récupération. Vérification séparée image saine/trois montages/empreintes médias inchangées et CSS publique de la nouvelle version : PASS. Une session réelle sur le téléphone du titulaire reste à vérifier.

## À ne pas confondre avec une preuve de session réelle

Cette livraison ne prouve ni l'affichage dans la session du titulaire, ni une sauvegarde hors site. Les anniversaires et les Top 10 disposent de lecteurs natifs déjà présents dans le code mais n'ont pas été redéployés ou acceptés ici comme étapes distinctes ; **le Top 5 des classes selon les vrais TP n'est pas livré**. Il faut préciser quelle catégorie native représente les « travaux pratiques » et quelle période/agrégation retenir avant d'établir ce classement ; ne pas produire des scores ou élèves fictifs.
