-- Caller owns transaction. Prepared only; no installation or grants.
DO $guard$
DECLARE helper text; confirmation text;
BEGIN
 IF current_database() <> 'schoolsafe_cards_isolated' THEN RAISE EXCEPTION 'ISOLATED_ONLY'; END IF;
 helper := pg_get_functiondef('api.pickup_photo_accepted(uuid)'::regprocedure);
 confirmation := pg_get_functiondef('api.pickup_confirm(uuid,uuid,uuid,text,text,text)'::regprocedure);
 IF strpos(helper, 'return v_decision = ''accepted'';') > 0 THEN
   helper := replace(helper, 'return v_decision = ''accepted'';', 'return coalesce(v_decision = ''accepted'', false);');
 ELSIF strpos(helper, 'return coalesce(v_decision = ''accepted'', false);') = 0 THEN
   RAISE EXCEPTION 'PHOTO_HELPER_REVIEW_REQUIRED';
 END IF;
 IF strpos(confirmation, 'if not api.pickup_photo_accepted(p_guardian_id) then') > 0 THEN
   confirmation := replace(confirmation, 'if not api.pickup_photo_accepted(p_guardian_id) then',
     'if api.pickup_photo_accepted(p_guardian_id) IS DISTINCT FROM true then');
 ELSIF strpos(confirmation, 'if api.pickup_photo_accepted(p_guardian_id) IS DISTINCT FROM true then') = 0 THEN
   RAISE EXCEPTION 'PICKUP_CONFIRM_REVIEW_REQUIRED';
 END IF;
 EXECUTE helper;
 EXECUTE confirmation;
END $guard$;
