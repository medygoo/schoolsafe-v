-- Utilisateurs, lot U3 (5 novembre 2026) — données des comptes.
-- docs/qualification/ANALYSE_UTILISATEURS_20261105.md
-- Une seule transaction ; toute divergence avec l'état attendu annule tout.
-- Retour arrière : rollback.sql (sauvegardes schoolsafe_bak_u3_* créées ici).
\set ON_ERROR_STOP on
BEGIN;

-- Sauvegardes complètes avant toute modification.
CREATE TABLE schoolsafe_bak_u3_user_profiles AS
  SELECT * FROM user_profiles WHERE id=10;
CREATE TABLE schoolsafe_bak_u3_profile_exceptions AS
  SELECT * FROM profile_exceptions WHERE profile_id=10;
CREATE TABLE schoolsafe_bak_u3_staff_suffix AS
  SELECT staff_id, syear, name_suffix FROM staff WHERE staff_id=12 AND syear=2026;

DO $$
DECLARE n int;
BEGIN
  -- 1. Profil « Obsolète — Accès école 3 / compte 6 » (n° 10) : aucun compte ne
  --    l'utilise ; ses droits ont été recopiés sur le profil 7 le 03/11.
  SELECT count(*) INTO n FROM user_profiles WHERE id=10 AND title LIKE 'Obsolète%';
  IF n<>1 THEN RAISE EXCEPTION 'profil 10 obsolète attendu 1, trouvé %', n; END IF;

  SELECT count(*) INTO n FROM staff WHERE profile_id=10;
  IF n<>0 THEN RAISE EXCEPTION 'profil 10 encore utilisé par % compte(s)', n; END IF;

  DELETE FROM profile_exceptions WHERE profile_id=10;
  GET DIAGNOSTICS n = ROW_COUNT; IF n<>242 THEN RAISE EXCEPTION 'droits du profil 10 attendus 242, obtenus %', n; END IF;

  DELETE FROM user_profiles WHERE id=10;
  GET DIAGNOSTICS n = ROW_COUNT; IF n<>1 THEN RAISE EXCEPTION 'profil 10 supprimé attendu 1, obtenu %', n; END IF;

  -- 2. Suffixe « Jr » de Medy Lomela, saisi par erreur (pas d'usage en RDC).
  UPDATE staff SET name_suffix=NULL WHERE staff_id=12 AND syear=2026 AND name_suffix='Jr';
  GET DIAGNOSTICS n = ROW_COUNT; IF n<>1 THEN RAISE EXCEPTION 'suffixe de Medy attendu 1, obtenu %', n; END IF;

  -- Contrôles finaux.
  SELECT count(*) INTO n FROM schoolsafe_bak_u3_profile_exceptions;
  IF n<>242 THEN RAISE EXCEPTION 'sauvegarde des droits incomplète : %', n; END IF;
END $$;

COMMIT;
