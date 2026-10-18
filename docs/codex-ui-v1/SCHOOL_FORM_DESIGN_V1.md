# Informations de l'ecole : presentation V1

School_Setup/Schools.php examine en lecture seule, champs natifs :
TITLE, SHORT_NAME, ADDRESS, CITY, STATE, ZIPCODE, PHONE, PRINCIPAL,
WWW_ADDRESS, SCHOOL_NUMBER, REPORTING_GP_SCALE. Limites de longueur
reprisent depuis les TextInput natifs. Echelle 1 a 10000, sans valeur
choisie arbitrairement. Ce champ affecte le metier : desactive en preview.

Trois sections : Etablissement, Coordonnees, References scolaires.
Le logo est un emplacement de presentation, pas un nouveau gestionnaire
de fichiers. Les champs personnalises school_fields ne sont pas simules :
ils devront etre presentes depuis leur configuration native (type,
options, obligation, ordre et traduction).

La preview n'a ni formulaire de soumission, stockage ou API. Le lien
final ouvre le programme natif. Aucune ecole creee ou modifiee, aucun
parametre d'authentification ou de securite touche. Ne pas confondre
Schools.php avec Configuration.php ni avec la creation multi-ecoles
dans Appsmith, qui reste une etape distincte.

Lors du raccordement : conserver values[...] natifs, AllowEdit, valeurs
serveur, ecole de session, champs caches et validation. Le style reutilise
account-form.css valide et les labels visibles recommandes par UI/UX
Pro Max. Ce n'est pas encore le formulaire operationnel re-style.
