# ProDéli — état de compte et révocation, diagnostic ciblé

**Diagnostic terminé ; correction locale après GO, non déployée.** Après qualification HTTP/image du correctif des établissements (distinct et toujours NON déployé), CONTINUE a autorisé l'examen ciblé suivant. Pi seul rédacteur ; base Git `8e7490fe4b304059b7cf4f19d8e5c077bde752c8` égale à origin.

Dans `ProgramFunctions/SchoolSafeAccountState.fnc.php`, `SchoolSafeAccountSet()` et `SchoolSafeAccountRevoke()` lisent les lignes puis écrivent l'état/époque et l'audit via `DBQuery` autocommit, sans transaction. `DBQuery` termine la requête sur erreur SQL ; le `catch(Throwable)` n'annule donc pas les écritures déjà committées. `SchoolSafeAccountSet()` utilise également `date(...,strtotime(...))` sans date civile stricte. Les protections du contrôleur `admin-console.php` (session, POST, CSRF, portée, restrictions) n'ont pas été retirées ou testées comme absentes.

Reproduction **PostgreSQL17.11 local jetable**, migration014 canonique, vraie connexion PHP et `DBQuery`, acteur synthétique, trigger d'échec d'audit synthétique dans trois bases tmpfs distinctes ; réseau interne sans port publié, nettoyage des trois conteneurs et du réseau contrôlé. `scripts/prodeli-account-state-{probe.php,audit.py}`. Preuve privée `C:/Users/PC/SchoolSafeProductionUpdate-20261021-evidence/school-scan-20261023/prodeli-account-state-red-r1/result.json`.

| Cas | Avant | Après panne d'audit / date invalide | Résultat |
| --- | --- | --- | --- |
| `SchoolSafeAccountSet` suspend | active, epoch5, audit0 | **suspended, epoch6, audit0** | invariant état+audit atomiques violé |
| `SchoolSafeAccountRevoke` | active, epoch5, audit0 | **active, epoch6, audit0** | révocation sans journal |
| `SchoolSafeAccountSet` + date `2026-99-99` | active, epoch5, audit0 | **suspended, epoch6, UNTIL1970, audit0** | date invalide acceptée ; la panne d'audit est injectée dans ce cas aussi |

Le code de retour du processus PHP est0 même après l'écran d'erreur natif (`die()`); la preuve repose sur le résultat DB, pas sur ce code de retour. Ces cas **ne prouvent pas** qu'un compte réel a été affecté ni que le contrôleur HTTP en production échoue. Éviter de confondre ce diagnostic avec l'étape précédente sur l'état de l'école, déjà corrigée localement/qualifiée mais non déployée.

## Correction locale après GO

Produit : seulement `SchoolSafeAccountState.fnc.php`. `Set` et `Revoke` possèdent chacun une transaction (refus transaction appelante), verrou advisory sur personne+école et lecture `FOR UPDATE` de l'état/époque actuel avant modification. Écriture d'état/époque et audit forment une seule transaction ; une erreur SQL retourne false et annule les écritures. Une date `AAAA-MM-JJ` invalide est refusée avant transaction. Les contrôles du contrôleur restent inchangés et aucune session n'est renouvelée par le test.

`prodeli-account-state-green-r1/result.json` : **4 scénarios PostgreSQL17.11 local jetable FIX_VERIFIED**. Avec audit en panne, `Set` et `Revoke` renvoient false et conservent active/epoch5/audit0 ; date invalide refuse sans écrire ; témoin `Revoke` valide true/active/epoch6/audit1. Trois bases en échec et une positive supprimées, réseau temporaire nettoyé. **84 frontières +16 présentation +62 droits natifs PASS** ; PHP lint et diffcheck PASS. Ne pas confondre le résultat du helper avec une confirmation HTTP ou production.

**Suite après GO distinct :** HTTP authentifié via vrai contrôleur + qualification image exacte du **candidat combinant état école et état compte** ; preuves de concurrence et autorité avant recette/déploiement. Toujours aucune migration production, vrai compte, session ou provider manipulés. Ne pas prétendre ProDéli entièrement stabilisé par ces deux corrections.
