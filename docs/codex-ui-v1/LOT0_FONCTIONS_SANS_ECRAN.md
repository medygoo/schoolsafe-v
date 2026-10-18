# Fonctions existantes sans consommateur frontend direct

Recherche dans app JS/HTML : aucune reference aux quatre chemins board/teacher, board/school, on-site et scan-control/audit. Les declarations HTTP et les RPC existent. Un ecran generique de presence/historique existe deja, mais il ne consomme pas ces contrats. Le diagnostic est donc une absence de surface directement raccordee, pas l'absence de tout ecran de presence.

| Fonction / preuve SQL | API / preuve routes | Droit serveur | Organisation proposee |
| --- | --- | --- | --- |
| api.scan_control_teacher_board, database/scancontrol/v1/01_scan_control.sql:522 | GET /native/scan-control/board/teacher, routes.ts:133 | Affectations du profil + iam.can_access school.student.read par eleve | Enseignant > Pedagogie > Ma journee > synthese de presence |
| api.scan_control_school_board, meme SQL:577 | GET /native/scan-control/board/school, routes.ts:137 | pilotage.dashboard.read | Direction/admin > Pilotage > Tableau de bord > presence/ecole |
| api.scan_control_on_site_student, meme SQL:457 | GET /native/scan-control/on-site?student_id=..., routes.ts:141 | school.student.read, eleve et classe | Ecole > Eleves > Fiche > presence actuelle |
| api.scan_control_on_site_class, meme SQL:481 | GET /native/scan-control/on-site?class_id=..., routes.ts:141 | school.student.read, classe | Pedagogie > Mes classes > presence actuelle |
| api.scan_control_on_site_school, meme SQL:502 | GET /native/scan-control/on-site, routes.ts:141 | pilotage.dashboard.read | Pilotage > Indicateurs > presence actuelle |
| api.scan_control_audit_read, meme SQL:408 | GET /native/scan-control/audit?student_id=..., routes.ts:152 | security.events.read, eleve | Securite > Historique > Trace des controles |

Les preuves routes.ts renvoient a server/src/scancontrol/routes.ts. Les appels de service sont dans server/src/scancontrol/service.ts:181-201. Sources officielles : app.students, app.student_enrollments, app.teacher_assignments, app.attendance_records, app.security_events et audit.events, toujours dans l'ecole courante.

Etat des six fonctions : declaration SQL et facade existantes; consommation UI directe absente detectee; execution non verifiee ici. Test propose : lecture authentifiee autorisee puis refus sur autre enfant/classe/ecole; rapprochement des compteurs avec les enregistrements reels; aucune mutation necessaire.

Les 48 noms sans reference serveur directe figurent dans LOT0_API_RPC.json. Ils peuvent etre des fonctions internes, des dependances SQL ou des versions historiques. Ne pas les compter comme 48 fonctions sans ecran. Les bulletins BACKEND_GAP sont egalement exclus des six fonctions existantes : l'absence d'un moteur est un autre diagnostic.
