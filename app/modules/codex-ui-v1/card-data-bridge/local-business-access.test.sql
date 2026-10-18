\set ON_ERROR_STOP on
BEGIN;
DO $$
DECLARE r record;
BEGIN
 SELECT * INTO r FROM card_bridge_local.resolve_identity(
  '04286836-0c33-4744-aec2-dfab27c15f22','8f1c3850-6568-4a26-8312-ed582c95891b',
  '46141a61-9270-4164-bce4-655ac96a5dd1','codex-addons-local:1');
 IF r.user_id IS NULL THEN RAISE EXCEPTION 'ADMIN_MAPPING_FAILED'; END IF;
 PERFORM api.set_request_context(r.user_id,r.profile_id,r.school_id,gen_random_uuid());
 PERFORM iam.require_access('cards.request.print',NULL,r.student_id);
 IF EXISTS (SELECT 1 FROM card_bridge_local.resolve_identity(
  r.profile_id,r.school_id,r.student_id,'codex-addons-local:3')) THEN
  RAISE EXCEPTION 'WRONG_SUBJECT_ACCEPTED';
 END IF;
 SELECT * INTO r FROM card_bridge_local.resolve_identity(
  '9889f9f4-ba6b-4a0b-8103-b80fcb1da11c','8f1c3850-6568-4a26-8312-ed582c95891b',
  '46141a61-9270-4164-bce4-655ac96a5dd1','codex-addons-local:3');
 PERFORM api.set_request_context(r.user_id,r.profile_id,r.school_id,gen_random_uuid());
 BEGIN
  PERFORM iam.require_access('cards.request.print',NULL,r.student_id);
  RAISE EXCEPTION 'PARENT_PRINT_ACCEPTED';
 EXCEPTION WHEN insufficient_privilege THEN NULL;
 END;
 IF has_table_privilege(current_user,(SELECT c.oid FROM pg_class c JOIN pg_namespace n ON n.oid=c.relnamespace WHERE n.nspname='app' AND c.relname='students'),'UPDATE')
 OR has_table_privilege(current_user,(SELECT c.oid FROM pg_class c JOIN pg_namespace n ON n.oid=c.relnamespace WHERE n.nspname='iam' AND c.relname='users'),'SELECT')
 OR has_function_privilege(current_user,'api.student_card_issue(uuid,text,text,text)','EXECUTE') THEN
  RAISE EXCEPTION 'EXCESSIVE_BRIDGE_PERMISSION';
 END IF;
 RAISE NOTICE 'PASS actual bridge role: admin allowed, parent denied, identity mismatch denied, no broad rights';
END $$;
ROLLBACK;
