# Journal des connexions

**Nom SchoolSafe.** Journal des connexions.

**Grande branche.** École.

**Groupe.** Sécurité.

**Sous-branche.** Journal. On consulte les connexions. On ne raconte pas l’activité page par page.

**Où la trouver.** École, groupe Security, lien Access Log, juste après Rollover.

**À quoi elle sert.** Cette page montre les tentatives de connexion : qui s’est connecté, à quelle date, avec quel résultat, et depuis quel navigateur. Elle ne liste pas chaque page visitée.

**Qui peut la consulter.** L’administrateur peut ouvrir le journal. L’enseignant, le parent et l’élève ne voient pas ce lien. Une adresse directe ne reste pas sur la page.

**Informations visibles.** Date, nom d’utilisateur, profil, résultat (réussite, échec ou accès bloqué), adresse réseau et navigateur.

**Filtres disponibles.** Une période, du jour de début au jour de fin. Une recherche dans la liste. Un tri en cliquant sur une colonne. Un export de la liste. Les pages n’apparaissent que si la liste est assez longue. Un bouton peut effacer tout le journal ; les lignes ne se corrigent pas une par une. Les connexions de plus d’un an peuvent disparaître à l’ouverture.

**Fonction Rosario correspondante.** `School_Setup/AccessLog.php` (Access Log). Les lignes sont dans `access_log`. Elles sont ajoutées à la connexion, pas à chaque page ouverte.
