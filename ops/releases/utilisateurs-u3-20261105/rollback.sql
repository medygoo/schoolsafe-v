-- Retour arrière du lot U3 (5 novembre 2026) : remet le profil 10, ses 242 droits
-- et le suffixe de Medy Lomela, depuis les sauvegardes créées par fix.sql.
\set ON_ERROR_STOP on
BEGIN;

INSERT INTO user_profiles SELECT * FROM schoolsafe_bak_u3_user_profiles
  WHERE NOT EXISTS (SELECT 1 FROM user_profiles WHERE id=10);
INSERT INTO profile_exceptions SELECT * FROM schoolsafe_bak_u3_profile_exceptions b
  WHERE NOT EXISTS (SELECT 1 FROM profile_exceptions a
    WHERE a.profile_id=b.profile_id AND a.modname=b.modname);
UPDATE staff s SET name_suffix=b.name_suffix FROM schoolsafe_bak_u3_staff_suffix b
  WHERE s.staff_id=b.staff_id AND s.syear=b.syear;

DO $$
DECLARE n int;
BEGIN
  SELECT count(*) INTO n FROM profile_exceptions WHERE profile_id=10;
  IF n<>242 THEN RAISE EXCEPTION 'droits restaurés attendus 242, obtenus %', n; END IF;
END $$;

DROP TABLE schoolsafe_bak_u3_user_profiles;
DROP TABLE schoolsafe_bak_u3_profile_exceptions;
DROP TABLE schoolsafe_bak_u3_staff_suffix;

COMMIT;
