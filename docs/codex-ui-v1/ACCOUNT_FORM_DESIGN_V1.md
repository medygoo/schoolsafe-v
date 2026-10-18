# Formulaire de compte : design V1

Presentation blanche et bleue inspiree de la reference SchoolSafe.
Sections Identite, Acces Rosario, Coordonnees ; accents vert et ambre
discrets. Labels visibles, focus clavier et champs de 46 px minimum.
UI/UX Pro Max consulte pour les labels de formulaire (domaine ux).

account-form.html est une preview sans formulaire de soumission, sans
stockage et sans API. Identifiant et mot de passe y sont desactives.
Le lien final ouvre le formulaire natif et ses permissions, pas une
creation de compte. Aucun resultat metier n'est simule.

account-form.css est scope par .ss-account et reutilisable. Integration
ulterieure : appliquer les classes aux champs rendus par Rosario sans
changer leurs name, valeurs, action, token, handlers ou AllowEdit.
Les profils, ecoles, titres, suffixes et droits doivent provenir de
Rosario ; les choix statiques de cette preview ne sont pas une source
de droits. Photo et champs personnalises restent a reprendre du rendu
natif avant de remplacer visuellement un formulaire complet.

Aucun fichier Rosario partage n'est modifie. Le design n'est pas encore
applique au formulaire operationnel. Studio Card intact.
