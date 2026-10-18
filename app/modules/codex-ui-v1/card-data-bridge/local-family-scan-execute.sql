-- Owner-approved technical execution only. No human role or business data change.
DO $$
DECLARE item record;
BEGIN
  IF current_database() <> 'schoolsafe_cards_isolated' THEN
    RAISE EXCEPTION 'ISOLATED_DATABASE_REQUIRED';
  END IF;
  FOR item IN
    SELECT p.oid::regprocedure AS signature
    FROM pg_proc p JOIN pg_namespace n ON n.oid=p.pronamespace
    WHERE n.nspname='api' AND p.proname = ANY (ARRAY[
      'pickup_parent_authorization_list', 'pickup_school_authorization_list',
      'pickup_parent_suspend', 'pickup_parent_family_suspend',
      'pickup_parent_reactivation_request', 'pickup_parent_family_reactivation_request',
      'scan_control_my_capabilities', 'scan_control_location_list',
      'scan_control_location_check', 'scan_control_card_by_number',
      'scan_control_presence', 'scan_control_note', 'scan_control_presence_mark',
      'scanner_pickup_prepare', 'scanner_pickup_confirm', 'scanner_fee_consult'
    ])
  LOOP
    EXECUTE format('GRANT EXECUTE ON FUNCTION %s TO schoolsafe_card_bridge', item.signature);
  END LOOP;
END $$;
