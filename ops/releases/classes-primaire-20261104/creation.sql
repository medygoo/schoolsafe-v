-- Ecole 3 (04/11, accord du proprietaire « Oui, crée-les ») : classes de 1ere et 3eme Primaire sur le modele
-- de la Classe maternelle (journee entiere, lundi-vendredi, Medy Lomela, bareme RDC), types Devoir /
-- Interrogation / Examen relies au rattrapage, puis Josue (1ere) et Esperance (3eme) places.
BEGIN;
DO $$
DECLARE v_subject integer; v_course integer; v_cp integer; v_level integer; v_short text; v_title text; v_student integer; v_type integer; r record;
BEGIN
  IF EXISTS (SELECT 1 FROM courses WHERE school_id=3 AND syear=2026 AND short_name IN ('P1','P3')) THEN RAISE EXCEPTION 'CLASSES_DEJA_CREEES'; END IF;
  INSERT INTO course_subjects(syear,school_id,title,sort_order) VALUES(2026,3,'|fr_FR.utf8:Primaire',2) RETURNING subject_id INTO v_subject;
  FOR v_level, v_short, v_title, v_student IN SELECT * FROM (VALUES (20,'P1','1ère Primaire',4),(22,'P3','3ème Primaire',5)) x LOOP
    INSERT INTO courses(syear,subject_id,school_id,grade_level,title,short_name)
    VALUES(2026,v_subject,3,v_level,'|fr_FR.utf8:Classe de '||v_title||'|en_US.utf8:Classe de '||v_title,v_short) RETURNING course_id INTO v_course;
    INSERT INTO course_periods(syear,school_id,course_id,title,short_name,mp,marking_period_id,teacher_id,total_seats,filled_seats,gender_restriction,calendar_id,grade_scale_id,credits)
    SELECT syear,school_id,v_course,title,short_name,mp,marking_period_id,teacher_id,total_seats,0,gender_restriction,calendar_id,grade_scale_id,credits
    FROM course_periods WHERE course_period_id=1 RETURNING course_period_id INTO v_cp;
    UPDATE course_periods SET parent_id=v_cp WHERE course_period_id=v_cp;
    INSERT INTO course_period_school_periods(course_period_id,period_id,days) SELECT v_cp,period_id,days FROM course_period_school_periods WHERE course_period_id=1;
    FOR r IN SELECT t.*,m.kind FROM gradebook_assignment_types t LEFT JOIN schoolsafe_remediation_types m USING(assignment_type_id) WHERE t.course_id=1 ORDER BY t.sort_order LOOP
      INSERT INTO gradebook_assignment_types(staff_id,course_id,title,final_grade_percent,sort_order,color,created_mp)
      VALUES(r.staff_id,v_course,r.title,r.final_grade_percent,r.sort_order,r.color,r.created_mp) RETURNING assignment_type_id INTO v_type;
      IF r.kind IS NOT NULL THEN INSERT INTO schoolsafe_remediation_types(assignment_type_id,kind,configured_by) VALUES(v_type,r.kind,6); END IF;
    END LOOP;
    INSERT INTO schedule(syear,school_id,student_id,start_date,course_id,course_period_id,mp,marking_period_id)
    SELECT 2026,3,v_student,CURRENT_DATE,v_course,v_cp,'FY',15 WHERE NOT EXISTS (SELECT 1 FROM schedule WHERE student_id=v_student AND syear=2026 AND course_period_id=v_cp);
    UPDATE course_periods SET filled_seats=1 WHERE course_period_id=v_cp;
    RAISE NOTICE 'classe % : cours %, periode %, eleve %', v_short, v_course, v_cp, v_student;
  END LOOP;
END $$;
COMMIT;
