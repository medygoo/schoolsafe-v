# Inscrire un élève

**Grande branche.** Élèves.

**Groupe parent.** Aucun. Le lien vient juste après Student Info.

**Sous-branche.** Inscription. Cette page crée l’élève et son inscription. Elle ne reprend pas Dossier, qui sert à retrouver une fiche déjà existante.

**Nom SchoolSafe.** Inscrire un élève.

**Fonction Rosario.** `Students/Student.php&include=General_Info&student_id=new` (Add a Student).

**Où la trouver.** Élèves, lien Add a Student, deuxième élément du menu.

**À quoi elle sert.** Cette page sert à inscrire un nouvel élève. Indiquez son identité, son niveau et sa date d’entrée. Les autres renseignements sont facultatifs. Après l’enregistrement, sa fiche s’ouvre.

**Qui peut ajouter un élève.** L’administrateur voit le lien, ouvre la page et peut enregistrer. L’enseignant, le parent et l’élève ne voient pas le lien. Une adresse directe ne leur ouvre pas ce formulaire.

**Champs principaux.** Identité : photo, prénom, deuxième prénom, nom, suffixe, identifiant, nom d’utilisateur, mot de passe. Informations complémentaires : genre, origine, nom usuel, numéro de sécurité sociale, date de naissance, langue. Scolarité : niveau, calendrier, passage ou redoublement, date d’entrée, code d’entrée, école.

**Champs obligatoires.** Le prénom, le nom, le niveau, le calendrier et le choix de passage ou de redoublement. La date d’entrée est proposée ; Rosario n’accepte pas qu’elle reste vide pour un nouvel élève.

**Ce qui est créé après validation.** La fiche de l’élève, son inscription pour l’année, et le compte de restauration associé. Aucun compte de connexion n’est créé si le nom d’utilisateur et le mot de passe restent vides. Aucune famille ni adresse n’est créée sur cette page.

**Où retrouver l’élève ensuite.** La fiche s’ouvre aussitôt. On peut aussi le rechercher dans Fiche de l’élève.
