# Cartes d'élève — ajout de l'adresse (27/10 soir)

L'adresse de l'ÉLÈVE apparaît au verso des cartes (badge + carte horizontale),
dans une ligne conditionnelle « 📍 Adresse de l'élève » — aucune adresse
enregistrée = aucun changement visuel (comportement existant préservé).

Chaîne : RosarioCardRead (studentAddress, résidence principale via
students_join_address) → NativeCardRegistryRead (record.student_address)
→ studio-original-adapter (student.address) → restored-studio-data
(students[].addr) → cards-core.js verso (2 rendus conditionnels).

La liberté admin (toute l'école / par classe : famille, variante couleur,
format, palette, héritage) EXISTE DÉJÀ dans l'atelier Modèles du studio
validé (studio-models.js) — rien à construire, à documenter auprès des écoles.

Corrigé au passage : les photos d'élèves doivent être en JPEG sous
FileUploads/StudentPhotos/{syear}/{id}.jpg (le gabarit glob '*.jpg' dans le
sous-dossier année) — les PNG à la racine ne s'affichaient jamais.

Backups : *.orig-1027 dans le conteneur. École le sage (3) : 3 élèves test
(300001-300003), 6 parents, adresses et photos fictives pour le test complet.
