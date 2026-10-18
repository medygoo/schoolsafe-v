BEGIN;
DO $guard$
BEGIN
 IF current_database()<>'rosario_addons_test' THEN RAISE EXCEPTION 'REMEDIATION_ISOLATED_DATABASE_REQUIRED'; END IF;
END $guard$;
SET LOCAL lock_timeout='5s';
SET LOCAL statement_timeout='30s';
-- Abort rather than deleting/reassigning any pre-existing duplicate recipient snapshots.
CREATE UNIQUE INDEX schoolsafe_remediation_parent_snapshot_unique
ON schoolsafe_remediation_notifications(event_id) WHERE audience='principal_parent';
CREATE FUNCTION schoolsafe_remediation_parent_snapshot_guard() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE event_xid text;
BEGIN
 IF TG_OP='DELETE' THEN
  IF OLD.audience='principal_parent' THEN RAISE EXCEPTION 'REMEDIATION_PARENT_SNAPSHOT_IMMUTABLE'; END IF;
  RETURN OLD;
 END IF;
 IF TG_OP='UPDATE' THEN
  IF (OLD.audience='principal_parent' OR NEW.audience='principal_parent')
   AND ROW(OLD.event_id,OLD.audience,OLD.recipient_id) IS DISTINCT FROM ROW(NEW.event_id,NEW.audience,NEW.recipient_id)
  THEN RAISE EXCEPTION 'REMEDIATION_PARENT_SNAPSHOT_IMMUTABLE'; END IF;
 ELSE
  IF NEW.audience='principal_parent' AND NEW.recipient_id IS NOT NULL THEN
   SELECT xmin::text INTO event_xid FROM schoolsafe_remediation_events WHERE id=NEW.event_id;
   IF event_xid IS DISTINCT FROM pg_current_xact_id()::text
   THEN RAISE EXCEPTION 'REMEDIATION_HISTORICAL_PARENT_PROOF_REQUIRED'; END IF;
  END IF;
 END IF;
 RETURN NEW;
END $$;
CREATE TRIGGER schoolsafe_remediation_parent_snapshot BEFORE INSERT OR UPDATE OR DELETE ON schoolsafe_remediation_notifications
FOR EACH ROW EXECUTE FUNCTION schoolsafe_remediation_parent_snapshot_guard();
COMMIT;
