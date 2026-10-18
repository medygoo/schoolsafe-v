BEGIN;
DO $$ BEGIN
 IF current_database()<>'schoolsafe_cards_isolated' THEN RAISE EXCEPTION 'ISOLATED_ONLY'; END IF;
END $$;
CREATE SCHEMA IF NOT EXISTS card_bridge_family;
REVOKE ALL ON SCHEMA card_bridge_family FROM PUBLIC;
GRANT USAGE ON SCHEMA card_bridge_family TO schoolsafe_card_bridge;

CREATE OR REPLACE FUNCTION card_bridge_family.parent_activate(p_student uuid,p_guardian uuid,p_reason text)
RETURNS jsonb LANGUAGE plpgsql SECURITY DEFINER SET search_path=pg_catalog AS $$
DECLARE g app.student_guardians%rowtype; a app.pickup_authorizations%rowtype;
 principal uuid; actor uuid:=iam.current_profile_id(); school uuid:=iam.current_school_id(); previous_actor uuid; previous_event text; slot integer;
BEGIN
 IF nullif(btrim(p_reason),'') IS NULL THEN RAISE check_violation USING MESSAGE='Reason required'; END IF;
 PERFORM 1 FROM app.students WHERE id=p_student AND school_id=school FOR UPDATE;
 principal:=api.pickup_parent_actor(p_student);
 IF principal IS NULL THEN RAISE insufficient_privilege USING MESSAGE='Principal parent required'; END IF;
 SELECT * INTO g FROM app.student_guardians WHERE id=p_guardian AND student_id=p_student AND school_id=school FOR UPDATE;
 IF NOT FOUND OR NOT g.is_active THEN RAISE insufficient_privilege USING MESSAGE='Active person required'; END IF;
 IF api.pickup_photo_accepted(g.id) IS DISTINCT FROM true THEN RAISE check_violation USING MESSAGE='PHOTO_NOT_VALIDATED'; END IF;
 IF g.guardian_type IN ('pere','mere','tuteur') THEN
   IF g.is_authorized_pickup OR g.pickup_hold IS NOT NULL THEN RAISE check_violation USING MESSAGE='Not a parent suspension'; END IF;
   SELECT actor_profile_id,event_type INTO previous_actor,previous_event FROM audit.events WHERE school_id=school AND entity_id=g.id
   AND event_type LIKE 'pickup.family.%' ORDER BY occurred_at DESC,created_at DESC,id DESC LIMIT 1;
   IF previous_event IS DISTINCT FROM 'pickup.family.suspended' OR previous_actor IS DISTINCT FROM actor OR NOT EXISTS(SELECT 1 FROM audit.events WHERE school_id=school AND entity_id=g.id
    AND event_type='pickup.family.suspended' AND actor_profile_id=actor) THEN
    RAISE insufficient_privilege USING MESSAGE='Only own suspension can be restored'; END IF;
   UPDATE app.student_guardians SET is_authorized_pickup=true,updated_at=now() WHERE id=g.id;
 ELSE
   SELECT * INTO a FROM app.pickup_authorizations WHERE guardian_id=g.id AND student_id=p_student AND school_id=school
    AND status='suspended' ORDER BY created_at DESC LIMIT 1 FOR UPDATE;
   IF NOT FOUND OR a.requested_by IS DISTINCT FROM principal THEN RAISE insufficient_privilege USING MESSAGE='Own authorization required'; END IF;
   SELECT actor_profile_id,event_type INTO previous_actor,previous_event FROM audit.events WHERE school_id=school AND entity_id=a.id
    AND event_type LIKE 'pickup.authorization.%' ORDER BY occurred_at DESC,created_at DESC,id DESC LIMIT 1;
   IF previous_event IS DISTINCT FROM 'pickup.authorization.suspended' OR previous_actor IS DISTINCT FROM actor OR NOT EXISTS(SELECT 1 FROM audit.events WHERE school_id=school AND entity_id=a.id
    AND event_type='pickup.authorization.suspended' AND actor_profile_id=actor) THEN
    RAISE insufficient_privilege USING MESSAGE='Only own suspension can be restored'; END IF;
   IF NOT a.photo_verified OR (a.starts_on IS NOT NULL AND a.starts_on>current_date) OR (a.ends_on IS NOT NULL AND a.ends_on<current_date) THEN
    RAISE check_violation USING MESSAGE='Authorization not admissible'; END IF;
   SELECT n INTO slot FROM generate_series(1,3) n WHERE NOT EXISTS(SELECT 1 FROM app.pickup_authorizations
    WHERE school_id=school AND student_id=p_student AND status='active' AND slot_no=n) ORDER BY n LIMIT 1;
   IF slot IS NULL THEN RAISE check_violation USING MESSAGE='PICKUP_LIMIT_REACHED'; END IF;
   UPDATE app.pickup_authorizations SET status='active',slot_no=slot,updated_at=now() WHERE id=a.id;
 END IF;
 PERFORM audit.write_event('pickup.parent.activated','student_guardian',g.id,jsonb_build_object('reason',p_reason,'authorization_id',a.id));
 RETURN jsonb_build_object('guardian_id',g.id,'status','active');
END $$;

CREATE OR REPLACE FUNCTION card_bridge_family.school_retire(p_student uuid,p_guardian uuid,p_reason text,p_consent text)
RETURNS jsonb LANGUAGE plpgsql SECURITY DEFINER SET search_path=pg_catalog AS $$
DECLARE g app.student_guardians%rowtype; school uuid:=iam.current_school_id();
BEGIN
 PERFORM iam.require_access('school.guardian.manage',null,p_student,null);
 IF nullif(btrim(p_reason),'') IS NULL OR nullif(btrim(p_consent),'') IS NULL THEN RAISE check_violation USING MESSAGE='School request and consent required'; END IF;
 PERFORM 1 FROM app.students WHERE id=p_student AND school_id=school FOR UPDATE;
 SELECT * INTO g FROM app.student_guardians WHERE id=p_guardian AND student_id=p_student AND school_id=school FOR UPDATE;
 IF NOT FOUND OR NOT g.is_active THEN RAISE check_violation USING MESSAGE='Active person required'; END IF;
 IF g.is_primary THEN RAISE check_violation USING MESSAGE='Principal transfer requires a separate school procedure'; END IF;
 UPDATE app.pickup_authorizations SET status='revoked',updated_at=now() WHERE guardian_id=g.id AND school_id=school AND status<>'revoked';
 PERFORM api.student_guardian_deactivate(p_student,g.id);
 PERFORM audit.write_event('pickup.school.retired','student_guardian',g.id,jsonb_build_object('student_id',p_student,'reason',p_reason,'consent',p_consent));
 RETURN jsonb_build_object('guardian_id',g.id,'status','revoked');
END $$;

CREATE OR REPLACE FUNCTION card_bridge_family.school_replace(p_student uuid,p_guardian uuid,p_reason text,p_payload jsonb)
RETURNS jsonb LANGUAGE plpgsql SECURITY DEFINER SET search_path=pg_catalog AS $$
DECLARE old_type text; replacement jsonb; new_guardian uuid; school uuid:=iam.current_school_id();
BEGIN
 PERFORM iam.require_access('school.guardian.manage',null,p_student,null);
 PERFORM 1 FROM app.students WHERE id=p_student AND school_id=school FOR UPDATE;
 SELECT guardian_type INTO old_type FROM app.student_guardians WHERE id=p_guardian AND student_id=p_student AND school_id=school;
 PERFORM card_bridge_family.school_retire(p_student,p_guardian,p_reason,p_payload->>'consent');
 IF old_type IN ('pere','mere','tuteur') THEN
  replacement:=api.student_guardian_add(p_student,p_payload || jsonb_build_object('guardian_type',old_type,'is_primary',false,'is_authorized_pickup',false));
  new_guardian:=(replacement->>'id')::uuid;
  PERFORM api.scan_control_portrait_set(new_guardian,p_payload->>'photo_path');
 ELSE
  replacement:=api.pickup_external_register(p_student,p_payload);
  new_guardian:=(replacement->>'guardian_id')::uuid;
 END IF;
 PERFORM audit.write_event('pickup.school.replaced','student_guardian',p_guardian,jsonb_build_object('replacement_guardian_id',new_guardian,'reason',p_reason));
 RETURN replacement || jsonb_build_object('guardian_id',new_guardian,'status','pending','replaced_guardian_id',p_guardian);
END $$;
CREATE OR REPLACE FUNCTION card_bridge_family.school_family_approve(p_student uuid,p_guardian uuid,p_reason text,p_consent text)
RETURNS jsonb LANGUAGE plpgsql SECURITY DEFINER SET search_path=pg_catalog AS $$
DECLARE g app.student_guardians%rowtype; school uuid:=iam.current_school_id();
BEGIN
 PERFORM iam.require_access('school.guardian.manage',null,p_student,null);
 IF nullif(btrim(p_reason),'') IS NULL OR nullif(btrim(p_consent),'') IS NULL THEN RAISE check_violation USING MESSAGE='School request and consent required'; END IF;
 PERFORM 1 FROM app.students WHERE id=p_student AND school_id=school FOR UPDATE;
 SELECT * INTO g FROM app.student_guardians WHERE id=p_guardian AND student_id=p_student AND school_id=school FOR UPDATE;
 IF NOT FOUND OR NOT g.is_active OR g.guardian_type NOT IN ('pere','mere','tuteur') THEN RAISE check_violation USING MESSAGE='Active family person required'; END IF;
 IF api.pickup_photo_accepted(g.id) IS DISTINCT FROM true THEN RAISE check_violation USING MESSAGE='PHOTO_NOT_VALIDATED'; END IF;
 UPDATE app.student_guardians SET is_authorized_pickup=true,pickup_hold=null,updated_at=now() WHERE id=g.id;
 PERFORM audit.write_event('pickup.family.school_approved','student_guardian',g.id,jsonb_build_object('reason',p_reason,'consent',p_consent));
 RETURN jsonb_build_object('guardian_id',g.id,'status','active');
END $$;
REVOKE ALL ON ALL FUNCTIONS IN SCHEMA card_bridge_family FROM PUBLIC;
GRANT EXECUTE ON FUNCTION card_bridge_family.parent_activate(uuid,uuid,text),card_bridge_family.school_retire(uuid,uuid,text,text),card_bridge_family.school_replace(uuid,uuid,text,jsonb) TO schoolsafe_card_bridge;
GRANT EXECUTE ON FUNCTION card_bridge_family.school_family_approve(uuid,uuid,text,text) TO schoolsafe_card_bridge;
COMMIT;
