\set ON_ERROR_STOP on
BEGIN;
DO $$ BEGIN
 IF current_database() <> 'schoolsafe_cards_isolated' THEN
  RAISE EXCEPTION 'LOCAL_ISOLATED_DATABASE_REQUIRED';
 END IF;
END $$;
GRANT EXECUTE ON FUNCTION api.school_card_identity(), api.school_card_motto(),
 api.class_card_visual_list(), api.student_card_guardian_phones(uuid),
 api.student_card_read(uuid) TO schoolsafe_card_bridge;
COMMIT;
