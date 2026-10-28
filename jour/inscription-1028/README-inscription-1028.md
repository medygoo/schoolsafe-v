# Inscription — l'enrichissement de la création d'élève (28/10 soir)

## Ce qui a été ajouté DANS les pages existantes (la loi: harmoniser)
1. **General_Info.inc.php** (la branche student_id=new):
   - les 4 champs d'adresse (n°+avenue, quartier, ville, téléphone) — ils alimentent
     la carte de l'élève (le verso) et la fiche (address + students_join_address)
   - **le bandeau visible** si des champs obligatoires manquent à la soumission
     (le compteur + le défilement vers le champ + le contour rouge) — la fin du
     blocage silencieux HTML5 qui faisait « rien ne fonctionne » sur les téléphones
   - novalidate: la validation serveur RosarioSIS prend le relais avec des
     messages VISIBLES
2. **Student.php** (après le hook create_student):
   - l'adresse → address + students_join_address (résidence+courrier+principale)
   - l'ID universel 6 chiffres → schoolsafe_person_ids (le prochain 3xxxxx libre)
   - le bandeau « 🎓 ID universel de l'élève : NNNNNN » à la création
   - la photo: le champ NATIF RosarioSIS (name=photo) gère l'upload + le
     redimensionnement 150×150 + le rangement (ImageUpload natif) — la cause du
     bug photo: le DOSSIER assets/StudentPhotos/{année}/ n'existait pas

## Les corrections de données (l'école le sage)
- **Le calendrier scolaire manquait** (il bloquait TOUTE création par l'interface):
  attendance_calendars créé (id 2, défaut Y) + les inscriptions recalées
- **Les 12 champs personnalisés en français**: Sexe, Ethnie (ÉCRITURE LIBRE — les
  choix multiples supprimés), Nom usuel, N° acte de naissance (le champ de la
  carte), Date de naissance (REQUISE), **Langue parlée (Français/Anglais —
  l'espagnol et le reste supprimés)**, Médecin, Téléphone du médecin, Hôpital
  préféré, Commentaires santé, A une note du médecin, Commentaires de la note
- **Les permissions**: le set complet des clés SchoolSafe (les 2 formats:
  chemin complet pour AllowUse + clé catalogue pour NativePermission) accordé
  aux profils 1/7/10

## La vérification
- Le robot (390px mobile + machine): la création complète avec photo+adresse →
  l'élève créé, l'ID affiché en grand, la photo redimensionnée rangée ✓
- Les élèves de test: 17, 19, 20 (+ 300004-300007) — supprimables
