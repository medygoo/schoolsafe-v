# Logo aligné avec la recherche — livraison isolée

Deux fichiers applicatifs seulement : rendu du tableau de bord et feuille de style. Image exacte qualifiée sur base portrait `sha256:ed97d7d18745e715bf8075ac066967e11444d79d657b9d08e3bbb6f632c549a6`. Aucune migration, aucune modification des droits ou des médias. La recette vérifie et conserve les **trois montages** (configuration, logos et portraits), crée un dump privé de la base et garde l'ancien conteneur arrêté. Elle ne restaure jamais ce dump automatiquement.

Les deux identités réelles sont contrôlées par empreinte sans exposer les fichiers. L'exécution exige les preuves de l'image, du HTTP authentifié sur données fictives (62 assertions), et du navigateur synthétique (8 combinaisons). La validation dans la session réelle du titulaire n'est pas remplacée par ces tests.

Séquence opérateur : réunir `release.json`, les manifestes protégés, les preuves et les six modules figés dans le répertoire de livraison root ; `python3 deploy.py prepare` ; inspecter les résultats et les sauvegardes ; `python3 deploy.py deploy` ; `python3 deploy.py status` et contrôle indépendant. STOP à la première divergence, sans rollback automatique d'écritures nouvelles.
