-- Recette base de la migration 047 (spec v1.1 § 10). Tout est annulé en fin de script.
-- Usage : psql -X -v ON_ERROR_STOP=1 -v student=<id> -v school=<id> -v staff=<id> -f 047-pickup-family-guardrails.test.sql
BEGIN;
CREATE TEMP TABLE r047 (cas text, attendu text, obtenu text) ON COMMIT DROP;

CREATE OR REPLACE FUNCTION pg_temp.expect_fail(cas text, stmt text) RETURNS void AS $$
BEGIN
    EXECUTE stmt;
    INSERT INTO r047 VALUES (cas, 'refus', 'accepte');
EXCEPTION WHEN OTHERS THEN
    INSERT INTO r047 VALUES (cas, 'refus', 'refus: ' || left(SQLERRM, 60));
END $$ LANGUAGE plpgsql;

CREATE OR REPLACE FUNCTION pg_temp.expect_ok(cas text, stmt text) RETURNS void AS $$
BEGIN
    EXECUTE stmt;
    INSERT INTO r047 VALUES (cas, 'accepte', 'accepte');
EXCEPTION WHEN OTHERS THEN
    INSERT INTO r047 VALUES (cas, 'accepte', 'refus: ' || left(SQLERRM, 60));
END $$ LANGUAGE plpgsql;

SELECT set_config('t.student', :'student', true), set_config('t.school', :'school', true), set_config('t.staff', :'staff', true);

DO $$
DECLARE st int := current_setting('t.student')::int; sc int := current_setting('t.school')::int;
        sf int := current_setting('t.staff')::int; g1 int; e int; a int; ids int[] := '{}';
BEGIN
    INSERT INTO schoolsafe_pickup_guardians(school_id, student_id, guardian_type, full_name, is_primary, photo_path, photo_accepted)
         VALUES (sc, st, 'pere', 'T047 Pere', true, 't047/pere.jpg', true) RETURNING id INTO g1;
    PERFORM set_config('t.g1', g1::text, true);
    PERFORM pg_temp.expect_ok('01 mere en plus du pere',
        format($s$INSERT INTO schoolsafe_pickup_guardians(school_id, student_id, guardian_type, full_name) VALUES (%s,%s,'mere','T047 Mere')$s$, sc, st));
    PERFORM pg_temp.expect_fail('02 deuxieme pere actif',
        format($s$INSERT INTO schoolsafe_pickup_guardians(school_id, student_id, guardian_type, full_name) VALUES (%s,%s,'pere','T047 Pere bis')$s$, sc, st));
    PERFORM pg_temp.expect_fail('03 deuxieme principal',
        format($s$INSERT INTO schoolsafe_pickup_guardians(school_id, student_id, guardian_type, full_name, is_primary) VALUES (%s,%s,'tuteur','T047 Tuteur',true)$s$, sc, st));
    PERFORM pg_temp.expect_fail('10 accredite avec compte',
        format($s$INSERT INTO schoolsafe_pickup_guardians(school_id, student_id, guardian_type, full_name, staff_id) VALUES (%s,%s,'externe','T047 Ext compte',%s)$s$, sc, st, sf));
    PERFORM pg_temp.expect_fail('R2 responsable recuperation sans photo',
        format($s$INSERT INTO schoolsafe_pickup_guardians(school_id, student_id, guardian_type, full_name, is_authorized_pickup) VALUES (%s,%s,'tuteur','T047 Tuteur sans photo',true)$s$, sc, st));

    FOR i IN 1..4 LOOP
        INSERT INTO schoolsafe_pickup_guardians(school_id, student_id, guardian_type, full_name, photo_path, photo_accepted)
             VALUES (sc, st, 'externe', 'T047 Ext ' || i, 't047/ext' || i || '.jpg', true) RETURNING id INTO e;
        INSERT INTO schoolsafe_pickup_authorizations(guardian_id, student_id, school_id, status, photo_verified, requested_by)
             VALUES (e, st, sc, 'pending', i <> 4, sf) RETURNING id INTO a;
        ids := ids || a;
    END LOOP;
    PERFORM set_config('t.a', array_to_string(ids, ','), true);

    PERFORM pg_temp.expect_fail('06 activation sans photo verifiee',
        format($s$UPDATE schoolsafe_pickup_authorizations SET status='active', slot_no=1 WHERE id=%s$s$, ids[4]));
    PERFORM pg_temp.expect_ok('04a accredite 1 place 1', format($s$UPDATE schoolsafe_pickup_authorizations SET status='active', slot_no=1 WHERE id=%s$s$, ids[1]));
    PERFORM pg_temp.expect_ok('04b accredite 2 place 2', format($s$UPDATE schoolsafe_pickup_authorizations SET status='active', slot_no=2 WHERE id=%s$s$, ids[2]));
    PERFORM pg_temp.expect_ok('04c accredite 3 place 3', format($s$UPDATE schoolsafe_pickup_authorizations SET status='active', slot_no=3 WHERE id=%s$s$, ids[3]));
    UPDATE schoolsafe_pickup_authorizations SET photo_verified=true WHERE id=ids[4];
    PERFORM pg_temp.expect_fail('05 place deja occupee', format($s$UPDATE schoolsafe_pickup_authorizations SET status='active', slot_no=1 WHERE id=%s$s$, ids[4]));
    PERFORM pg_temp.expect_fail('04d quatrieme actif sans place', format($s$UPDATE schoolsafe_pickup_authorizations SET status='active', slot_no=NULL WHERE id=%s$s$, ids[4]));
END $$;

-- 07 : photo modifiée -> personne et accréditation repassent en attente.
UPDATE schoolsafe_pickup_guardians SET photo_path='t047/ext1-nouvelle.jpg'
 WHERE id=(SELECT guardian_id FROM schoolsafe_pickup_authorizations WHERE id=split_part(current_setting('t.a'),',',1)::int);
INSERT INTO r047 SELECT '07 photo modifiee -> en attente', 'pending/false',
       a.status || '/' || g.photo_accepted FROM schoolsafe_pickup_authorizations a JOIN schoolsafe_pickup_guardians g ON g.id=a.guardian_id
 WHERE a.id=split_part(current_setting('t.a'),',',1)::int;
DO $$ BEGIN
    PERFORM pg_temp.expect_ok('04e place liberee reprise par le 4e',
        format($s$UPDATE schoolsafe_pickup_authorizations SET status='active', slot_no=1 WHERE id=%s$s$, split_part(current_setting('t.a'),',',4)));
END $$;
INSERT INTO r047 SELECT '04f actifs pour l eleve', '3', count(*)::text FROM schoolsafe_pickup_authorizations
 WHERE student_id=current_setting('t.student')::int AND status='active';

-- R3 responsable : nom modifié -> récupération retirée jusqu'à revalidation.
UPDATE schoolsafe_pickup_guardians SET photo_accepted=true WHERE id=current_setting('t.g1')::int;
UPDATE schoolsafe_pickup_guardians SET is_authorized_pickup=true WHERE id=current_setting('t.g1')::int;
UPDATE schoolsafe_pickup_guardians SET full_name='T047 Pere renomme' WHERE id=current_setting('t.g1')::int;
INSERT INTO r047 SELECT 'R3 responsable renomme', 'false/false', is_authorized_pickup || '/' || photo_accepted
  FROM schoolsafe_pickup_guardians WHERE id=current_setting('t.g1')::int;

-- 12 : restrictions ; 13 : fratries (une pièce = une personne).
SELECT pg_temp.expect_ok('12a restriction justice sur responsable',
    format($s$INSERT INTO schoolsafe_pickup_restrictions(school_id, student_id, guardian_id, reason, source) VALUES (%s,%s,%s,'Ordonnance T047','justice')$s$,
           current_setting('t.school'), current_setting('t.student'), current_setting('t.g1')));
SELECT pg_temp.expect_fail('12b restriction sans cible',
    format($s$INSERT INTO schoolsafe_pickup_restrictions(school_id, student_id, reason) VALUES (%s,%s,'T047')$s$, current_setting('t.school'), current_setting('t.student')));
SELECT pg_temp.expect_ok('13a personne avec piece',
    format($s$INSERT INTO schoolsafe_pickup_persons(school_id, full_name, id_doc_type, id_doc_number) VALUES (%s,'T047 Oncle','CNI','T047-0001')$s$, current_setting('t.school')));
SELECT pg_temp.expect_fail('13b meme piece enregistree deux fois',
    format($s$INSERT INTO schoolsafe_pickup_persons(school_id, full_name, id_doc_type, id_doc_number) VALUES (%s,'T047 Oncle bis','CNI','T047-0001')$s$, current_setting('t.school')));

SELECT CASE WHEN obtenu = attendu OR (attendu = 'refus' AND obtenu LIKE 'refus%') THEN 'OK' ELSE 'ECHEC' END AS resultat, cas, obtenu FROM r047 ORDER BY cas;
SELECT count(*) FILTER (WHERE NOT (obtenu = attendu OR (attendu = 'refus' AND obtenu LIKE 'refus%'))) AS echecs, count(*) AS cas FROM r047;
ROLLBACK;
