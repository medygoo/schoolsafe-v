# Isolated arrival transfer worker

Activated locally at 2026-10-12T18:54:26Z. Container `codex-arrival-worker` is running and real empty-source ticks succeed. This proves readiness, not a real scan-to-ledger result. No endpoint or synthetic/business arrival was created.

## Active private configuration

Under `/var/lib/schoolsafe-attendance`, outside the web root:

- `http-config.json`: freshly generated minimal configuration for dedicated source/mapping credentials, not a copy of privileged bridge credentials or signing secrets.
- `worker.json`: `environment=local-isolated-test-only`, `sourceInstance=codex-addons-local`, explicit UTC `activatedAt` (`YYYY-MM-DDTHH:MM:SSZ`), `native` PostgreSQL connection object, `schoolTimezones` keyed by native school ID, optional integer `batchSize` (1..500, default 100), `ticks` (1..100, default 1), `pollSeconds` (1..60, default 15).
- Each connection object has `host`, optional integer `port`, `database`, `user`, `password`. Target database schoolsafe_cards_isolated is on codex-card-target-db-v17; mapping schoolsafe_card_bridge_test and native rosario_addons_test are on codex-addons-db. Configs are provisioned in Docker volume `codex-arrival-worker-private`, outside the webroot.

Verified private directory 0700 and configs/checkpoint 0600 owned by 33:33. Credentials were generated in memory and passed through container stdin, never command arguments or environment variables. The worker persists event IDs/timestamps/source fields, not names or QR payloads. Keep the checkpoint and lock on the persistent volume. No application permission module or user grants were changed.

The installed native ledger is reused. Container command is `php /worker/arrival-worker-supervise.php`, invoking the bounded CLI every 15 seconds. Configured batchSize=100, ticks=1, activation=2026-10-12T18:54:26Z; school 1 timezone Africa/Kinshasa was verified from native config. Code is a read-only bind, reloaded per CLI invocation.

## Deployment and technical privileges

Existing image `codex-ref-v1-php:local` provides PHP 8.3/PDO PostgreSQL. Restart policy is `unless-stopped`; user 33:33, read-only rootfs, all capabilities dropped, no-new-privileges. Only the internal `codex-card-bridge-isolated` network, no published port. Limits: 128 MiB, 0.5 CPU, 32 PIDs; rotated logs 1 MiB x 3. Docker/host must be running for scheduling.

- `schoolsafe_arrival_source_reader`: non-elevated LOGIN, CONNECT target DB, USAGE schoolsafe_arrival_source, SELECT only its security-barrier arrivals view. Filtered to the reviewed target school, entry/allowed/scanner_entry and activation. No raw security_events SELECT/write.
- `schoolsafe_arrival_mapping_reader`: non-elevated LOGIN, CONNECT mapping DB, USAGE schoolsafe_arrival_mapping, SELECT only the security-barrier identities view. Only reviewed source instance/native school 1 and required school/student mapping fields; no raw mapping table SELECT/write.
- `schoolsafe_arrival_native_writer`: non-elevated LOGIN, CONNECT native DB, USAGE schoolsafe_attendance; SELECT filtered transfer_enrollments and ledger, column-limited ledger INSERT, EXECUTE lock_enrollment_at(integer,integer,numeric,date). No native students/enrollment UPDATE; no ledger UPDATE/DELETE.

The locking function is SECURITY DEFINER owned by the existing native database owner, with pg_catalog search_path, fully qualified public table names, and native DB/school 1 guards. It only reads/share-locks enrollment and student rows. PUBLIC execution is revoked; the worker needs no broad UPDATE to perform FOR SHARE. Ledger RLS SELECT/INSERT policies scope the technical role to the reviewed source/school. Existing owners retain normal owner behavior; unrelated PUBLIC privileges are not globally changed.

Applied provisioning SQL: local-worker-target-access.pg.sql, local-worker-mapping-access.pg.sql, local-worker-native-access.pg.sql. Source passwords are placeholders. The provisioning script refuses existing roles/volume/container rather than replacing credentials.

## Processing guarantees and limits

Dedicated target/mapping PDO sessions are read-only. Native PDO uses the existing transaction helper. All sessions have connection/statement/lock timeouts. The CLI rejects request-mode execution, command-line overrides, nonisolated database configuration and concurrent local workers.

Only committed rows from restricted `schoolsafe_arrival_source.arrivals` are selected. The view filters security_events; the worker cannot access the raw table. This is dedicated database trust, not an HTTP export. Activation changes are refused after checkpoint creation; source-view boundary changes need reviewed provisioning.

Each tick retries at most batchSize pending events and reads at most batchSize more. The keyset cursor advances through committed events, while every unsuccessful projection stays in a persistent retry queue. Failed items rotate for fairness. Mapping/config/SQL failures are not discarded or marked delivered. Native commit must return inserted/duplicate before removing a retry. Exceptions never print secret-bearing messages.

The checkpoint is atomically replaced after a tick. A crash/uncertain checkpoint restarts from the old state; committed ledger events can return duplicate. An independent reconciliation cursor advances one bounded batch every fifth tick without stopping forward ingestion. Old checkpoints receive compatible defaults. Duplicate comparison includes enrollment_id. Main reran 12 worker and 16 projection mocked tests successfully after these ECC fixes. Preserve source history; deletion before collection cannot be repaired by this worker.

Repeated sweeps reread old events and therefore cost grows with retained history. The ledger deduplicates them. An indefinitely unbounded history or retry queue needs future operational compaction after reconciliation; never prune undelivered events. Runs/ticks/queries are bounded, not a guarantee of immediate delivery for large backlogs. No target outbox/retention guarantee is implemented yet.

Mappings reverse the reviewed school/student maps, then select exactly one native enrollment at the school-local event date to obtain year. The projection helper revalidates and locks that enrollment before commit. No class/course presence, absence, teacher/admin correction or notification is written. Class-scoped dashboard integration remains separate.

Validation before the independent P2 work: 15 projection + 9 worker mocked tests pass; PHP syntax valid. Actual readiness with worker credentials confirms 0 source arrivals, 2 reviewed student mappings, 0 ledger rows, no elevated roles, denied raw source/mapping reads, no native UPDATE/ledger UPDATE-DELETE, and private file modes. The native lock function executed and was rolled back without an event or insertion. Repeated actual ticks report projected=duplicates=retrying=pending=0.

Evidence: ACTIVATION-EVIDENCE-20261012.md. Run `docker exec codex-arrival-worker php /worker/arrival-worker-readiness.php` for secret-free readiness checks and use bounded docker logs --tail. Remaining: authorized genuine arrival, concurrent SQL/idempotency/crash verification, teacher/admin displays and source retention/outbox. No VPS action or attendance_period/day changes.
