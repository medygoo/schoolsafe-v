# Passage à l’année suivante

**Nom SchoolSafe.** Passage à l’année suivante.

**Grande branche.** École.

**Groupe.** Utilitaires.

**Sous-branche.** Année. Ce n’est pas un paramètre, ni la fiche de l’école. Ici, on prépare l’année qui vient.

**Où la trouver.** École, groupe Utilities, lien Rollover, juste après Configuration.

**À quoi elle sert.** Cette page prépare l’année suivante à partir de l’année en cours. Cochez ce qu’il faut recopier, puis confirmez. Les lignes grisées existent déjà l’année suivante : les recopier efface ce qui s’y trouve.

**Qui peut l’utiliser.** L’administrateur peut ouvrir la page et confirmer. L’enseignant, le parent et l’élève ne voient pas ce lien. Une adresse directe ne reste pas sur la page.

**Ce qu’on peut faire.** Choisir ce qui est recopié : école, utilisateurs, créneaux, périodes, calendriers, barèmes, codes de présence, cours, inscriptions, élèves, commentaires, configuration, activités, comptes de restauration et formulaire de signalement. L’école est recopiée d’office s’il n’en existe pas encore pour l’année suivante. Confirmer ou annuler. Confirmer peut effacer des données déjà présentes l’année suivante.

**Dépendances.** L’année en cours doit être l’année par défaut. Les cours demandent aussi les utilisateurs, les créneaux, les périodes, les calendriers et les barèmes. Les élèves demandent les codes d’inscription. Les commentaires de bulletin demandent les cours.

**Fonction Rosario correspondante.** `School_Setup/Rollover.php` (Rollover). Les données sont recopiées vers l’année suivante dans les tables cochées.
