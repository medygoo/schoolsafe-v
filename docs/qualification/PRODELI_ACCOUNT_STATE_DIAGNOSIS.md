# ProDéli — état de compte et révocation, diagnostic ciblé

**Diagnostic terminé ; non corrigé, non déployé.** Après qualification HTTP/image du correctif des établissements (distinct et toujours NON déployé), CONTINUE a autorisé l'examen ciblé suivant. Pi seul rédacteur ; base Git `8e7490fe4b304059b7cf4f19d8e5c077bde752c8` égale à origin.

Dans `ProgramFunctions/SchoolSafeAccountState.fnc.php`, `SchoolSafeAccountSet()` et `SchoolSafeAccountRevoke()` lisent les lignes puis écrivent l'état/époque et l'audit via `DBQuery` autocommit, sans transaction. `DBQuery` termine la requête sur erreur SQL ; le `catch(Throwable)` n'annule donc pas les écritures déjà committées. `SchoolSafeAccountSet()` utilise également `date(...,strtotime(...))` sans date civile stricte. Les protections du contrôleur `admin-console.php` (session, POST, CSRF, portée, restrictions) n'ont pas été retirées ou testées comme absentes.

Reproduction **PostgreSQL17.11 local jetable**, migration014 canonique, vraie connexion PHP et `DBQuery`, acteur synthétique, trigger d'échec d'audit synthétique dans trois bases tmpfs distinctes ; réseau interne sans port publié, nettoyage des trois conteneurs et du réseau contrôlé. `scripts/prodeli-account-state-{probe.php,audit.py}`. Preuve privée `C:/Users/PC/SchoolSafeProductionUpdate-20261021-evidence/school-scan-20261023/prodeli-account-state-red-r1/result.json`.

| Cas | Avant | Après panne d'audit / date invalide | Résultat |
| --- | --- | --- | --- |
| `SchoolSafeAccountSet` suspend | active, epoch5, audit0 | **suspended, epoch6, audit0** | invariant état+audit atomiques violé |
| `SchoolSafeAccountRevoke` | active, epoch5, audit0 | **active, epoch6, audit0** | révocation sans journal |
| `SchoolSafeAccountSet` + date `2026-99-99` | active, epoch5, audit0 | **suspended, epoch6, UNTIL1970, audit0** | date invalide acceptée ; la panne d'audit est injectée dans ce cas aussi |

Le code de retour du processus PHP est0 même après l'écran d'erreur natif (`die()`); la preuve repose sur le résultat DB, pas sur ce code de retour. Ces cas **ne prouvent pas** qu'un compte réel a été affecté ni que le contrôleur HTTP en production échoue. Éviter de confondre ce diagnostic avec l'étape précédente sur l'état de l'école, déjà corrigée localement/qualifiée mais non déployée.

**Prochaine étape seulement après GO :** correction ciblée dans le propriétaire natif `SchoolSafeAccountState.fnc.php`, transactions/contrôle de concurrence pour état/epoch+audit et date civile stricte, tests RED→GREEN sur PostgreSQL jetable avec cas de suspension/révocation; conserver sessions existantes et scope par école. Puis HTTP/image exacte et livraison séparément autorisée. Aucune migration, vrai compte, session, provider ou production manipulés ici.
