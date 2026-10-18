BEGIN;
DO $$ BEGIN IF current_database()<>'schoolsafe_cards_isolated' THEN RAISE EXCEPTION 'ISOLATED_ONLY'; END IF; END $$;
CREATE SCHEMA IF NOT EXISTS card_bridge_fees;
REVOKE ALL ON SCHEMA card_bridge_fees FROM PUBLIC;
GRANT USAGE ON SCHEMA card_bridge_fees TO schoolsafe_card_bridge;
CREATE TABLE IF NOT EXISTS card_bridge_fees.models(
 id uuid PRIMARY KEY DEFAULT gen_random_uuid(), school_id uuid NOT NULL REFERENCES app.schools(id),
 native_year integer NOT NULL, label text NOT NULL CHECK(length(label) BETWEEN 1 AND 100),
 threshold numeric(14,2) NOT NULL CHECK(threshold>0), tolerance integer NOT NULL CHECK(tolerance BETWEEN 0 AND 100),
 active boolean NOT NULL DEFAULT false, created_by uuid NOT NULL REFERENCES iam.profiles(id),created_at timestamptz NOT NULL DEFAULT now());
CREATE UNIQUE INDEX IF NOT EXISTS fee_one_active ON card_bridge_fees.models(school_id,native_year) WHERE active;
ALTER TABLE card_bridge_fees.models ENABLE ROW LEVEL SECURITY;
REVOKE ALL ON card_bridge_fees.models FROM PUBLIC,schoolsafe_card_bridge;
CREATE OR REPLACE FUNCTION card_bridge_fees.manage(p_year integer,p_action text,p_input jsonb)
RETURNS jsonb LANGUAGE plpgsql SECURITY DEFINER SET search_path=pg_catalog AS $$
DECLARE school uuid:=iam.current_school_id(); model uuid;
BEGIN
 PERFORM iam.require_access('roles.manage',null,null,null);
 PERFORM 1 FROM app.schools WHERE id=school FOR UPDATE;
 IF p_action='save' THEN
  INSERT INTO card_bridge_fees.models(school_id,native_year,label,threshold,tolerance,created_by)
  VALUES(school,p_year,p_input->>'label',(p_input->>'threshold')::numeric,(p_input->>'tolerance')::integer,iam.current_profile_id()) RETURNING id INTO model;
 ELSIF p_action='activate' THEN
  model:=(p_input->>'id')::uuid;
  IF NOT EXISTS(SELECT 1 FROM card_bridge_fees.models WHERE id=model AND school_id=school AND native_year=p_year) THEN RAISE insufficient_privilege USING MESSAGE='Policy not accessible'; END IF;
  UPDATE card_bridge_fees.models SET active=false WHERE school_id=school AND native_year=p_year;
  UPDATE card_bridge_fees.models SET active=true WHERE id=model;
 ELSIF p_action='stop' THEN
  UPDATE card_bridge_fees.models SET active=false WHERE school_id=school AND native_year=p_year;
 ELSIF p_action<>'list' THEN RAISE check_violation USING MESSAGE='Invalid action'; END IF;
 IF p_action<>'list' THEN PERFORM audit.write_event('bridge.fees.policy.'||p_action,'fee_policy',model,jsonb_build_object('native_year',p_year)); END IF;
 RETURN (SELECT coalesce(jsonb_agg(to_jsonb(m) ORDER BY m.created_at DESC),'[]'::jsonb) FROM card_bridge_fees.models m WHERE m.school_id=school AND m.native_year=p_year);
END $$;
CREATE OR REPLACE FUNCTION card_bridge_fees.active_model(p_year integer)
RETURNS jsonb LANGUAGE plpgsql SECURITY DEFINER SET search_path=pg_catalog AS $$
BEGIN
 PERFORM api.scan_control_require('SCAN_FEES');
 RETURN (SELECT to_jsonb(m) FROM card_bridge_fees.models m WHERE m.school_id=iam.current_school_id() AND m.native_year=p_year AND m.active);
END $$;
REVOKE ALL ON ALL FUNCTIONS IN SCHEMA card_bridge_fees FROM PUBLIC;
CREATE OR REPLACE FUNCTION card_bridge_fees.scan_scope(p_class uuid)
RETURNS boolean LANGUAGE sql STABLE SECURITY DEFINER SET search_path=pg_catalog AS $$
 SELECT iam.context_is_valid() AND EXISTS(SELECT 1 FROM app.scan_control_grants WHERE school_id=iam.current_school_id() AND profile_id=iam.current_profile_id()
 AND capability='SCAN_FEES' AND revoked_at IS NULL AND (expires_at IS NULL OR expires_at>now()) AND campaign_id IS NULL
 AND (class_ids IS NULL OR cardinality(class_ids)=0 OR p_class=ANY(class_ids)));
$$;
REVOKE ALL ON FUNCTION card_bridge_fees.scan_scope(uuid) FROM PUBLIC;
GRANT EXECUTE ON FUNCTION card_bridge_fees.scan_scope(uuid) TO schoolsafe_card_bridge;
CREATE OR REPLACE FUNCTION card_bridge_fees.record_result(p_student uuid,p_payload jsonb)
RETURNS void LANGUAGE plpgsql SECURITY DEFINER SET search_path=pg_catalog AS $$
DECLARE class uuid;
BEGIN
 SELECT class_id INTO class FROM app.students WHERE id=p_student AND school_id=iam.current_school_id();
 IF NOT FOUND OR NOT card_bridge_fees.scan_scope(class) THEN RAISE insufficient_privilege USING MESSAGE='Fee scope denied'; END IF;
 IF p_payload->>'color' NOT IN ('blue','yellow','red') OR p_payload->>'source' IS DISTINCT FROM 'rosario_billing' THEN RAISE check_violation USING MESSAGE='Invalid fee result'; END IF;
 PERFORM audit.write_event('bridge.fees.result','student',p_student,p_payload);
END $$;
REVOKE ALL ON FUNCTION card_bridge_fees.record_result(uuid,jsonb) FROM PUBLIC;
GRANT EXECUTE ON FUNCTION card_bridge_fees.record_result(uuid,jsonb) TO schoolsafe_card_bridge;
GRANT EXECUTE ON FUNCTION card_bridge_fees.manage(integer,text,jsonb),card_bridge_fees.active_model(integer) TO schoolsafe_card_bridge;
COMMIT;
