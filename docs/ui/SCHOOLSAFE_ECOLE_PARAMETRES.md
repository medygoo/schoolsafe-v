# Paramètres de l’école

**Nom SchoolSafe.** Paramètres de l’école.

**Grande branche.** École.

**Groupe.** Setup.

**Sous-branche.** Paramètres. Ce n’est pas la fiche de l’école. Ici, on règle le fonctionnement.

**Où la trouver.** École, groupe Setup, lien Configuration, juste après School Fields.

**À quoi elle sert.** Cette page règle le fonctionnement général de l’école. Certains réglages touchent plusieurs parties de l’application. Les réglages de sécurité et d’accès se modifient avec prudence.

**Qui peut la modifier.** L’administrateur peut ouvrir la page et enregistrer. L’enseignant, le parent et l’élève ne voient pas ce lien. Une adresse directe ne reste pas sur la page.

**Sections.** Application (nom, thème, inscription publique, sécurité, affichage des noms). École (année, rotation, logo, monnaie, recherche d’un élève). Élèves (adresses, contacts, commentaires). Notes (barème, saisie, statistiques). Présence (durée de la journée, délais de modification). Restauration (seuils du solde). Modules et plugins, pour activer ou retirer des compléments.

**Réglages sensibles.** L’inscription publique, la limite des essais de connexion, la force du mot de passe et l’obligation de le changer au premier accès. Le thème forcé change l’apparence de toute l’application. Les modules et les plugins peuvent ajouter ou retirer des parties du logiciel.

**Fonctions impactées.** L’affichage de toute l’application, le portail si l’inscription publique est ouverte, la fiche des élèves, les notes, la présence et la restauration. Les pages déjà visitées dans École ne sont pas modifiées par cette présentation.

**Fonction Rosario correspondante.** `School_Setup/Configuration.php` (Configuration). Les valeurs sont dans `config`, `program_config`, et, pour le nombre de jours de rotation, dans `schools`.

**Essai fermé.** Le seul réglage modifié pendant la vérification était la recherche d’un élève. Valeur de départ : Popup window. Elle a été relue, puis remise exactement à Popup window. Les autres réglages de cet onglet n’ont pas été changés.
