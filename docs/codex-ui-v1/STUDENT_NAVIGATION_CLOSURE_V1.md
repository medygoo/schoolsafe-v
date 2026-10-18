# Parcours eleves et previews

La vue generale distingue les acces natifs Eleves Rosario et les quatre
previews de design. Les previews n'utilisent pas de donnees reelles et
ne remplacent aucun formulaire natif. Leurs liens sont regroupes dans
une section explicitement nommee, visible pour le profil admin.

Six liens natifs eleves : consulter, inscrire, parents associes, rapport
avance, repartition, impression. Les liens sont autorises uniquement si
la cle complete figure dans le menu filtre et satisfait AllowUse.

Preuves : Students/Menu.php. L'inscription a une cle avec include et
student_id=new distincte de Student.php ; aucune permission d'ajout
n'est deduite de la consultation. Les roles non-admin gardent uniquement
les programmes que leur menu natif permet.

Il s'agit de navigation native, pas du raccordement des formulaires de
preview. Les six raccourcis ne sont pas six nouvelles destinations du
catalogue. Aucun enregistrement, export ou impression lance ici.

La suite des tests et adaptations ne doit pas modifier Studio Card,
scanner, recuperation, moteurs financiers, JASPE ou Rosario.
