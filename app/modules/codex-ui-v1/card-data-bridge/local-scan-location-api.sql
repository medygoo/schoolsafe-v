-- Prepared only. Caller owns transaction; intentionally NO GRANT.
DO $$ BEGIN
 IF current_database()<>'schoolsafe_cards_isolated' THEN RAISE EXCEPTION 'ISOLATED_ONLY'; END IF;
 IF to_regclass('app.locations') IS NULL THEN RAISE EXCEPTION 'LOCATION_SCHEMA_REQUIRED'; END IF;
END $$;
CREATE OR REPLACE FUNCTION api.scan_control_location_manage(p_input jsonb)
RETURNS jsonb LANGUAGE plpgsql SECURITY DEFINER SET search_path=pg_catalog AS $$
DECLARE school uuid:=iam.current_school_id(); location app.locations%rowtype; result jsonb;
BEGIN
 IF school IS NULL OR iam.context_is_valid() IS DISTINCT FROM true THEN
  RAISE insufficient_privilege USING MESSAGE='Context required';
 END IF;
 PERFORM iam.require_access('roles.manage',null,null,null);
 IF p_input->>'action'='list' AND p_input - 'action'='{}'::jsonb THEN
  SELECT coalesce(jsonb_agg(jsonb_build_object('id',id,'code',code,'label',label,'kind',kind,'is_active',is_active) ORDER BY code),'[]'::jsonb)
  INTO result FROM app.locations WHERE school_id=school;
  RETURN jsonb_build_object('locations',result);
 END IF;
 IF p_input->>'action' IS DISTINCT FROM 'save'
 OR p_input - ARRAY['action','id','code','label','kind','is_active'] <> '{}'::jsonb
 OR jsonb_typeof(p_input->'code') IS DISTINCT FROM 'string'
 OR jsonb_typeof(p_input->'label') IS DISTINCT FROM 'string'
 OR length(btrim(p_input->>'code')) NOT BETWEEN 1 AND 100
 OR length(btrim(p_input->>'label')) NOT BETWEEN 1 AND 200
 OR (p_input->>'kind') IS NULL OR (p_input->>'kind') NOT IN ('gate','door','classroom','office','control_point')
 OR jsonb_typeof(p_input->'is_active') IS DISTINCT FROM 'boolean' THEN
  RAISE check_violation USING MESSAGE='Invalid location input';
 END IF;
 IF p_input ? 'id' THEN
  UPDATE app.locations SET code=btrim(p_input->>'code'),label=btrim(p_input->>'label'),
   kind=p_input->>'kind',is_active=(p_input->>'is_active')::boolean,updated_at=now()
  WHERE id=(p_input->>'id')::uuid AND school_id=school RETURNING * INTO location;
  IF NOT FOUND THEN RAISE insufficient_privilege USING MESSAGE='Location unavailable'; END IF;
 ELSE
  INSERT INTO app.locations(school_id,code,label,kind,is_active)
  VALUES(school,btrim(p_input->>'code'),btrim(p_input->>'label'),p_input->>'kind',(p_input->>'is_active')::boolean)
  RETURNING * INTO location;
 END IF;
 RETURN jsonb_build_object('id',location.id);
END $$;
REVOKE ALL ON FUNCTION api.scan_control_location_manage(jsonb) FROM PUBLIC;
