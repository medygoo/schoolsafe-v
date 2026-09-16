# JASPE assise — séquences 2D v1

8 planches générées avec `image_gen.imagegen`, 4 images chacune :

| Action | Fichiers (clair / sombre) | Mouvement |
|---|---|---|
| Parole | `parole-*.png` | Lèvres et main gauche qui accompagne la réponse |
| Réflexion | `reflexion-*.png` | Main de la joue à la tempe et aux cheveux |
| Sourire | `sourire-*.png` | Sourire progressif et clignement |
| Lecture | `lecture-*.png` | Regard au livre, page tournée, sourire pendant la lecture |

Chaque PNG mesure 1536×1024, grille 2×2 sans gouttière, images 768×512.
Ordre : haut gauche, haut droite, bas gauche, bas droite. Images opaques avec
fond intégré ; aucune alpha ni séparation personnage/bureau annoncée.
Les originaux v12 restent intacts. Les prompts et les hashes sont dans
`prompts.json` et `manifest.json`. Les premières versions de lecture sont
remplacées par les éditions souriantes à la demande du propriétaire ; l'historique
de génération est conservé dans les prompts.

Le lecteur `app/modules/jaspe2d/seated-companion.js` affiche ces cadres par CSS,
sans modifier les PNG. Les séquences ont des durées par image, une transition
de 65 ms et une façade avant du bureau fixe. Les ressources sont chargées au
besoin ; une erreur de chargement conserve la pose précédente sans boucle réseau.
Les animations s'arrêtent hors écran, en onglet caché, à la perte de permission,
pendant la sortie entière et en mode mouvements réduits.

Le lecteur de poses n'a aucun accès métier. `dashboard-companion.js` traduit
les états autorisés de SafeAssistant en actions fermées. Les événements de
synthèse vocale commandent la séquence de parole, sans synchronisation phonétique.
La lecture décorative apparaît occasionnellement au repos ; elle ne signifie pas
que JASPE accède à un livre ou à des données de l'utilisateur.

Aperçu interactif local : `/jaspe-assise-preview.html` sur le serveur de `app/`.
Contrôles : `node app/qa-jaspe-seated.cjs` et `node app/qa-jaspe-dashboard.cjs`.
Les tests de voix utilisent un adaptateur simulé, pas le microphone réel.
