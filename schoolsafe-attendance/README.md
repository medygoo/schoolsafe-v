# Native arrival projection: prepared first slice

Not installed or wired. No migration, real event, account or grant has been applied.

## Files

- `001-arrival-ledger.pg.sql`: explicit PostgreSQL migration for the native Rosario database. No permissions are provisioned. The schema name intentionally must not already exist; installation failures must be reviewed rather than silently ignored.
- `ArrivalProjection.php`: pure event/mapping validation and parameterized transactional projection through an injected query adapter.
- `arrival-projection-20261012-unit.php`: CLI-only mocked tests.

## Contract

The caller authenticates a committed source event and resolves one trusted mapping before calling `SchoolSafeArrivalProject`. This helper is not an authentication endpoint.

Event fields: `sourceInstance`, `eventId`, `schoolId`, `studentId`, `occurredAt`, `eventType=entry`, `decision=allowed`, `source=scanner_entry`. Source IDs are UUIDs. The timestamp is UTC ISO 8601 with Z or +00:00, optionally microseconds. No client date or timezone is accepted.

Trusted mapping fields: `sourceInstance`, `targetSchoolId`, `targetStudentId`, `nativeSchoolId`, `nativeStudentId`, `nativeYear`, `schoolTimezone`. Native IDs/year are positive decimal strings. A configured IANA school timezone is mandatory; it cannot be guessed from the host or country.

The query adapter must use one dedicated PostgreSQL connection, associative fetches, exception-on-error behavior and bound named parameters. It must return row arrays for SELECT/RETURNING, and an empty array for transaction statements. There must be no outer transaction. This adapter and its authorization are not implemented here.

An enrollment must match native student, school, year and the event's school date. Zero or multiple matches fail before insert. Matching enrollment/student rows are share-locked through commit. The ledger preserves the enrollment ID as provenance, not a new enrollment or permission.

Uniqueness is source instance/event UUID. PostgreSQL ON CONFLICT handles concurrent duplicates. A duplicate is accepted only if the existing identity, instant, timezone, school date and evidence status match. Existing events are never updated. The helper revalidates enrollment on retries; changed/ambiguous enrollment is a blocked retry, not permission to overwrite historical evidence. A separate future correction/reconciliation workflow must preserve that history.

The ledger contains arrival evidence only. It neither creates course/day presence or absence nor alters teacher/admin corrections. Multiple legitimate source events on one school day remain distinct; dashboard arrival counts should count distinct native students, not events.

## Remaining integration

1. Review/install migration separately and provide the native query adapter. The current delivery does neither.
2. Add the committed-event outbox and retrying worker. Do not depend on successful browser response delivery. Acknowledge only after native commit; retry the same event UUID after an uncertain commit.
3. Add an authenticated service receiver with signature verification, replay handling compatible with event idempotency, server-resolved instance/school/student/year mapping and school timezone. No browser-provided mapping or synthetic user session.
4. Surface arrival evidence in native attendance views: teacher scope from current course assignments (including secondary teachers), valid schedule/enrollment; admin scope from the current native school/year. Show arrival separately from corrected attendance.
5. Test against PostgreSQL after installation is authorized: concurrent duplicate workers, rollback, commit/ack uncertainty, identity conflicts, enrollment changes, midnight/DST and teacher/admin visibility. No live SQL projection tests or migration application were performed in this slice.

No Supabase dependency. No permission module changes. Parent notification delivery remains a separate connector.
