-- Profils : parents sans profil + profil partagé « Administrateur scolaire » complet.
-- Une seule transaction ; toute divergence avec l'état attendu annule tout.
\set ON_ERROR_STOP on
BEGIN;
DO $$
DECLARE n int;
BEGIN
  -- 8 parents de l'école 3 sans profil : aucun droit effectif -> profil natif Parent (3).
  UPDATE staff SET profile_id=3
   WHERE staff_id IN (13,14,15,16,17,18,26,27) AND syear=2026 AND profile='parent' AND profile_id IS NULL;
  GET DIAGNOSTICS n = ROW_COUNT; IF n<>8 THEN RAISE EXCEPTION 'parents attendus 8, obtenus %', n; END IF;

  -- Format Rosario des écoles : ',3,' (',3' n'est pas reconnu par LIKE '%,3,%').
  UPDATE staff SET schools=',3,' WHERE staff_id IN (26,27) AND syear=2026 AND schools=',3';
  GET DIAGNOSTICS n = ROW_COUNT; IF n<>2 THEN RAISE EXCEPTION 'format ecoles attendu 2, obtenu %', n; END IF;

  -- Le profil partagé 7 reçoit les 34 droits SchoolSafe que seul le clone 10 portait.
  INSERT INTO profile_exceptions(profile_id,modname,can_use,can_edit)
  SELECT 7, b.modname, b.can_use, b.can_edit FROM profile_exceptions b
   WHERE b.profile_id=10 AND NOT EXISTS (SELECT 1 FROM profile_exceptions a WHERE a.profile_id=7 AND a.modname=b.modname);
  GET DIAGNOSTICS n = ROW_COUNT; IF n<>34 THEN RAISE EXCEPTION 'droits attendus 34, obtenus %', n; END IF;

  SELECT count(*) INTO n FROM (
    SELECT modname,can_use,can_edit FROM profile_exceptions WHERE profile_id=7 AND (can_use='Y' OR can_edit='Y')
    EXCEPT SELECT modname,can_use,can_edit FROM profile_exceptions WHERE profile_id=10 AND (can_use='Y' OR can_edit='Y')) x;
  IF n<>0 THEN RAISE EXCEPTION 'profil 7 a % droits absents du 10', n; END IF;
  SELECT count(*) INTO n FROM (
    SELECT modname,can_use,can_edit FROM profile_exceptions WHERE profile_id=10 AND (can_use='Y' OR can_edit='Y')
    EXCEPT SELECT modname,can_use,can_edit FROM profile_exceptions WHERE profile_id=7 AND (can_use='Y' OR can_edit='Y')) x;
  IF n<>0 THEN RAISE EXCEPTION 'profil 10 a % droits absents du 7', n; END IF;

  -- Le directeur principal de l'école 3 revient sur le profil partagé (droits identiques).
  UPDATE staff SET profile_id=7 WHERE staff_id=6 AND syear=2026 AND profile_id=10;
  GET DIAGNOSTICS n = ROW_COUNT; IF n<>1 THEN RAISE EXCEPTION 'directeur attendu 1, obtenu %', n; END IF;

  -- Le clone est conservé (aucune suppression), marqué obsolète.
  UPDATE user_profiles SET title='Obsolète — '||title WHERE id=10 AND title NOT LIKE 'Obsolète%';
  SELECT count(*) INTO n FROM staff WHERE profile_id=10; IF n<>0 THEN RAISE EXCEPTION 'profil 10 encore utilise'; END IF;
END $$;
COMMIT;
SELECT coalesce(profile_id::text,'null') pid, profile, count(*) FROM staff WHERE syear=2026 GROUP BY 1,2 ORDER BY 1;
SELECT p.id, p.title, count(*) FILTER (WHERE e.can_use='Y') droits FROM user_profiles p
  LEFT JOIN profile_exceptions e ON e.profile_id=p.id WHERE p.id IN (7,10) GROUP BY 1,2;
