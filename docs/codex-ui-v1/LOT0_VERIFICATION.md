# Verification executee pendant LOT 0

Date : 2026-10-11. Workspace exclusivement SchoolSafeCodex/SCHOOLSAFE_CODEX_LOCAL_SNAPSHOT_V1/workspace.

| Controle | Resultat | Ce que cela prouve |
| --- | --- | --- |
| Empreintes TREE.sha256 | 1017 fichiers controles, 0 absent, 0 modifie | Fichiers de l'instantane conserves; nouveaux fichiers non inclus dans TREE |
| node app/qa-rosario-visual-bindings.cjs | PASS | Assertions statiques des liaisons declarees |
| node app/qa-navigation-two-level.cjs | PASS | Contrat statique menu principal/panneau secondaire |
| node app/qa-rosario-visual-wiring.cjs | Exit 0, 34 cibles / 3 boutons / 4 modales / 3 viewports | Assertions sur sources et media queries; aucun navigateur lance |
| lot0-audit.cjs | Extraction JS/HTML/roles/routes/RPC | Registres reproductibles avec references fichier:ligne |

Les mentions de viewports dans le troisieme controle sont des assertions de CSS. Elles ne prouvent ni absence de debordement ni fonctionnement responsive reel. Les essais navigateur historiques du handoff ne sont pas reexecutes ici. Aucun nouveau test visuel n'est annonce.

Non executes : API authentifiees, PostgreSQL, camera physique, multi-profils navigateur, integration Cursor, tests responsive des composants Codex. Aucune installation de dependances ni ouverture des dossiers Cursor.

Limites : detection statique par motifs, pas analyse complete de l'AST ni execution des generateurs; indices de gestionnaire possiblement homonymes; roles demo differents des droits calcules en session; functions api.* avec versions historiques; chargement indirect de modules non prouve par loadedByIndex.
