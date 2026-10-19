# Plan simple — organisation écoles + utilisateurs (100 % natif Rosario)

Principe : aucun nouvel écran de données. On organise les écrans natifs
Rosario déjà intégrés, on modernise leur présentation, et les informations
des 6 étapes atterrissent dans les tables natives.

## PRODELI = 3 portes natives, rien d'inventé

1. **Création** : `/schoolsafe-administrators.php?wizard=1` — parcours en
   6 étapes (présentation/orchestration). L'écriture finale passe par le
   contrôleur natif `School_Setup/CopySchool.php` (école) et la fiche native
   `Users/User.php` (compte). Aucune personne ni finance copiée.
2. **Gestion** : `admin-console.php` — états, suspension par école,
   révocation de sessions, direction, filtre par école. Les trois cartes de
   création sont déjà réduites à une seule carte vers le parcours guidé.
3. **Quotidien natif** : `Users/Search.inc.php` + `User.php` (comptes),
   `School_Setup/Schools.php` (fiche école = hub), `misc/Portal.php`
   (tableau de bord), `index.php` (connexion native USERNAME/PASSWORD).

## Où vont les infos des 6 étapes (natif, vérifié dans le code)

| Étape | Champs | Destination native |
|---|---|---|
| 1 École | TITLE, SHORT_NAME, ADDRESS, CITY, PHONE, WWW... | table `schools` (même INSERT que CopySchool) |
| 2 Documents | e-mail officiel + devise (≤180) | table `config` : SCHOOLSAFE_SCHOOL_EMAIL / SCHOOLSAFE_SCHOOL_SLOGAN |
| 3 Responsable | prénom, nom, identifiant, e-mail, profil admin | table `staff` (même INSERT que User.php, PROFILE_ID admin délégable, 1 école) + mot de passe temporaire, 1ère connexion forcée |
| 4 Images | logo + portrait cadrés JPEG | fichiers image natifs (photo utilisateur / identité école) |
| 5 Configuration | modèle + base de notation | copie school_gradelevels, school_periods, school_marking_periods (PARENT_ID remappé) ; calendrier du modèle vérifié AVANT copie |
| 6 Vérification | récapitulatif, anti-doublon, reprise d'école orpheline | confirmation → POST natif CopySchool, puis fiche confidentielle affichée une seule fois |

## 4 petites étapes, dans l'ordre, un test avant chaque publication

1. **Tester le parcours guidé** : nouveau `app/qa-school-wizard.cjs`
   (étapes 1→5, refus calendrier cassé, 0 école créée). Zéro touche au code.
2. **Une seule validation du calendrier** : `CopySchool.php` appelle la
   fonction existante `SchoolSafeSchoolCalendarStatuses()` au lieu de sa
   logique locale. Additif, même refus, un seul code.
3. **Fiche école = hub** : `School_Setup/Schools.php` affiche en tête
   direction (migration 018), état de l'école (016), e-mail/devise (config)
   avec liens vers les écrans natifs. Présentation seule.
4. **Vocabulaire** : mêmes mots partout (propriétaire / directeur /
   école / compte), menus existants inchangés dans leur structure.

## Règle de stabilité

Aucun nouvel écran, aucune suppression de logique, contrôleurs serveur
inchangés. Couverture existante : 13 (écoles) + 37 (comptes) + 18 (pont)
+ rôles personnalisés. On ne déploie une étape que verte.
