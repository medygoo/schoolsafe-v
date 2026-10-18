# Ecole : presentation interactive native SchoolSafe V2

Date : 2026-10-11. Environnement exclusif de test : Rosario local, port 18082.

## Correction realisee

Les neuf modules ordinaires emploient leurs vrais formulaires Rosario avec une couche de presentation SchoolSafe blanche et bleue. Les anciens brouillons HTML restent conserves et identifies comme previsualisations ; leurs champs ne sont pas raccordes. La solution V2 reutilise les champs natifs plutot que de reconstruire leurs controles metier.

Le tableau de bord isole dirige les douze destinations School_Setup connues vers school-live.php. Son catalogue est partage avec le shell. Les verifications Menu.php et AllowUse restent natives. Les routes des autres branches ne sont pas remplacees.

L'adaptateur JavaScript ajoute uniquement une classe et une feuille de style dans les neuf documents natifs de meme origine autorises. Aucun remplacement de formulaire, champ, valeur, nom, methode, action, jeton, validation, evenement metier, cookie, requete SQL ou API. Le style est reapplique apres chargement complet et reste present durant les mises a jour AJAX natives.

La navigation de branche est exterieure au formulaire. Le menu global natif est masque visuellement dans ces neuf documents uniquement ; les selecteurs natifs ecole/annee/periode sont conserves, ainsi que les actions natives aide, impression et deconnexion. Le shell ne synchronise pas son titre avec chaque navigation interne de l'iframe : la navigation interne demeure native.

Les trois destinations sensibles restent des ecrans d'avertissement avec lien natif explicite : copie, changement d'annee, journal. Leur formulaire natif se charge seulement apres action explicite ; pas d'harmonisation de leur document interne dans cette version. Le journal peut purger automatiquement les entrees anciennes a son ouverture. Ne pas le charger pour un simple test visuel.

## Preuves

- PHP lint : school-live.php, school-catalog.php, index.php valides.
- node --check : adaptateur JavaScript valide.
- test-school-native-presentation.cjs : 16 cas passes (neuf modules, idempotence, exclusion origine externe/connexion/autre branche/module sensible/route invalide, absence de frame et acces refuse).
- Version CSS finale : neuf modules x trois largeurs observees 390/768/1280 = 27 controles. Zero debordement horizontal du document externe ou natif, selecteur d'ecole visible partout. Ce n'est pas un audit exhaustif de tous les tableaux et dialogues.
- Sauvegarde native apres harmonisation : niveau Kindergarten, KG -> TST ; persistance apres rechargement ; TST -> KG, restauration relue. Essai reversible explicitement autorise. Aucune suppression.
- Calendrier : ouverture du formulaire natif Modifier / Recreer le calendrier Main, sans soumission ; presentation reste appliquee et formulaire present. Recreer peut affecter les jours de classe : aucune execution.
- Tableau de bord : les douze destinations Ecole et deux raccourcis Annonces/Parametres observes pointant vers le shell correct.
- Recherche de niveaux : essai de saisie/Entree realise, mais aucun filtrage confirme ; ne pas declarer ce parcours valide.

## Etat exact et conditions de validation complete

### Complement annonces et sondages

Le proprietaire a explicitement autorise la creation locale d'une annonce et d'un sondage TEST CODEX, sans destinataire et sans piece jointe, avec suppression soumise a confirmation separee.

- Annonce ID 1 : TEST CODEX - ANNONCE. Texte relu apres rechargement : Essai local de raccordement SchoolSafe. Ne pas publier.
- Sondage ID 1 : TEST CODEX - SONDAGE. Question relue : Essai local du formulaire. Choix A et Choix B, type options a choix simple.
- Pour les deux : selection multiple PUBLISHED_PROFILES vide verifiee avant sauvegarde et apres rechargement. Les conditions du portail natif exigent l'appartenance du profil (modules/misc/Portal.php, notamment lignes 214, 245, 488, 516, 762, 789, 950, 976). L'absence de publication est donc fondee sur ces conditions sources et la selection sauvegardee ; aucun essai de connexion des autres profils effectue.
- Aucun fichier envoye, aucune suppression, aucun vote enregistre.
- Les deux donnees de test sont volontairement conservees dans la base locale. Ne pas transferer ces fixtures en production.
- Ajout dynamique de question : deux champs distincts new1/new2 observes dans le brouillon, sans sauvegarde de ce brouillon.
- Ajustement CSS uniquement : editeur et fenetres natives Colorbox, zone de texte responsive et commande de fermeture plus grande.
- Cinq dialogues (contenu annonce, piece jointe, publication annonce, questions sondage, publication sondage) x trois largeurs 390/768/1280 : quinze ouvertures. Largeur scroll egale a largeur client dans chaque mesure, bouton Fermer visible. Attente de fermeture complete indispensable entre deux ouvertures ; une premiere boucle sans cette attente n'a pas abouti et n'est pas comptee comme succes.
- Preuves : school-dialog-checks.json, school-note-saved.jpg, school-poll-saved.jpg.

Ce complement valide creation et relecture de ces deux fixtures, pas la modification, suppression, publication ou participation par les autres profils. Il ne leve pas les autres conditions ci-dessous.

La couverture des destinations est presente. La validation complete de la branche n'est pas acquise.

1. Profils enseignant/parent/eleve/personnalises : seules les regles sources sont examinees ; sessions reelles manquantes pour les essais. Ne pas simuler une session ni accorder des droits pour obtenir un resultat.
2. Isolation entre ecoles : une seule ecole observee. Il faut une seconde ecole de test ou une fixture approuvee.
3. Calendriers, annonces, sondages et champs : formulaires disponibles, mais creation/suppression/persistance completes non testees. Les champs modifient le schema natif ; pas de mutation dans ce lot.
4. Copie/report/journal : executer uniquement avec sauvegarde et autorisation des operations sensibles. Jamais par un simple GO de navigation.
5. Modules/plugins : pas d'activation/desactivation ni configuration sensible testee. La decouverte native peut renommer des repertoires ; elle n'est pas une simple lecture sans effet.
6. Design : les formulaires sont natifs harmonises, et non une reproduction identique de tous les anciens brouillons. Les brouillons restent intacts. Les tableaux remplis, dialogues, impression et tous les parcours clavier demandent encore une verification visuelle detaillee.

Aucun deploiement, commit, integration Cursor, changement Rosario source, Studio Card, scanner, recuperation, finance, authentification ou VPS.

## Manifeste du changement

- Nouveau : school-catalog.php (catalogue des 12 destinations).
- Nouveau : school-native-presentation.js (adaptation visuelle de meme origine).
- Nouveau : school-native-presentation.css (theme des formulaires natifs).
- Nouveau : test-school-native-presentation.cjs (tests isoles).
- Modifie : school-live.php (catalogue partage et script de presentation).
- Modifie : index.php (destinations Ecole vers le shell ; lien fonctionnel retire de la rubrique Previsualisations).
- Nouveau : ce rapport.

Dependances visuelles conservees : account-form.css, school-workspace.css, school-live.css, schoolsafe-logo.png. Les neuf modules metier et Warehouse.php/Menu.php doivent etre ceux de Rosario local valide. Aucun endpoint de presentation ne doit contourner Modules.php.

Preuves exportees : school-styled-responsive.json, school-native-styled.jpg. Le paquet exporte contient les fichiers de presentation et dependances ; l'index fourni est celui du shell isole, pas un remplacement de l'index principal Rosario.
