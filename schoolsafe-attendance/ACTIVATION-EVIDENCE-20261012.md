# Local activation evidence

Activation: 2026-10-12T18:54:26Z. Isolated Docker only. No passwords, signing keys, cookies, personal names or event payloads are recorded here.

Observed bounded logs contain repeated successful actual ticks:

```json
{"projected":0,"duplicates":0,"retrying":0,"pending":0}
```

Actual worker readiness command: `docker exec codex-arrival-worker php /worker/arrival-worker-readiness.php`:

```json
{"nonElevatedRoles":true,"rawSourceDenied":true,"rawMappingDenied":true,"nativeUpdateDenied":true,"ledgerUpdateDeleteDenied":true,"lockFunctionExecutedThenRolledBack":true,"sourceArrivalCount":0,"reviewedStudentMappings":2,"nativeLedgerCount":0,"privateFiles0600Owner33":true}
```

Readiness initially failed because its privilege assertion resolved inaccessible schemas by qualified relation name. Catalog OIDs fixed the assertion; no worker grant was broadened.

Deployment inspection: running, user 33:33, restart unless-stopped, read-only rootfs, ports {}, log rotation max-size=1m/max-file=3. Network codex-card-bridge-isolated is internal. Existing PHP 8.3 image reused. Code bind is read-only; private volume configs/checkpoint are 0600, directory 0700.

Applied: three dedicated non-elevated LOGIN roles; filtered source/mapping views; native filtered enrollment view and definer read/lock function; ledger INSERT/SELECT and scoped RLS. No UPDATE/DELETE on native business tables, no user permission grants or VPS action.

24 mocked projection/worker tests passed after access adapters changed. Empty ticks prove config/connections/filtered reads/checkpoint readiness only. No actual or synthetic scan event or ledger insertion was created. The function was exercised inside a rolled-back read transaction.

Main verification after ECC review: independent bounded reconciliation and enrollment_id duplicate comparison implemented. 12 worker and 16 projection mocked tests rerun successfully, plus 83 connector contract tests. Supervisor children reload corrected code each invocation. Remaining: genuine arrival end-to-end, real insert/idempotency/crash tests under worker credentials, native teacher/admin displays with authorized accounts and source-event retention.
