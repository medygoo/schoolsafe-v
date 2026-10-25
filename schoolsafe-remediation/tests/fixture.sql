-- Minimal native contracts, synthetic identities only; run in a disposable database.
CREATE TABLE staff(staff_id integer PRIMARY KEY,first_name text,last_name text,profile text,profile_id integer,syear numeric(4,0),schools text);
CREATE TABLE user_profiles(id integer PRIMARY KEY,title text);
CREATE TABLE profile_exceptions(profile_id integer,modname text,can_use text);
CREATE TABLE students(student_id integer PRIMARY KEY,first_name text,last_name text);
CREATE TABLE courses(course_id integer PRIMARY KEY,school_id integer,syear numeric(4,0),title text);
CREATE TABLE course_periods(course_period_id integer PRIMARY KEY,course_id integer REFERENCES courses,school_id integer,syear numeric(4,0),teacher_id integer REFERENCES staff,marking_period_id integer);
CREATE TABLE gradebook_assignment_types(assignment_type_id serial PRIMARY KEY,staff_id integer REFERENCES staff,course_id integer REFERENCES courses,title text);
CREATE TABLE gradebook_assignments(assignment_id serial PRIMARY KEY,staff_id integer REFERENCES staff,marking_period_id integer,course_id integer REFERENCES courses,course_period_id integer REFERENCES course_periods,assignment_type_id integer,title text,points integer,created_at timestamp DEFAULT current_timestamp);
CREATE TABLE gradebook_grades(student_id integer REFERENCES students,course_period_id integer REFERENCES course_periods,assignment_id integer,points numeric(6,2),updated_at timestamp,PRIMARY KEY(student_id,assignment_id,course_period_id));
CREATE TABLE billing_fees(id serial PRIMARY KEY,student_id integer REFERENCES students,school_id integer,syear numeric(4,0),title text,amount numeric(14,2),assigned_date date,created_by text);
CREATE TABLE billing_payments(id serial PRIMARY KEY,student_id integer REFERENCES students,school_id integer,syear numeric(4,0),amount numeric(14,2),payment_date date,refunded_payment_id integer,comments text,created_by text);
INSERT INTO staff VALUES(1,'Synthetic','Teacher','teacher',NULL,2026,',1,'),(2,'Synthetic','Director','admin',1,2026,',1,');
INSERT INTO staff VALUES(3,'Synthetic','Parent','parent',NULL,2026,',1,');
INSERT INTO user_profiles VALUES(1,'Directeur pédagogique');
INSERT INTO profile_exceptions VALUES(1,'Grades/StudentGrades.php','Y');
INSERT INTO students VALUES(1,'Synthetic','Student'),(2,'Other','Student');
INSERT INTO courses VALUES(1,1,2026,'Synthetic mathematics'),(2,1,2026,'Synthetic language'),(3,2,2026,'Other school');
INSERT INTO course_periods VALUES(1,1,1,2026,1,11),(2,1,1,2026,1,12),(3,2,1,2026,1,11),(4,3,2,2026,1,11);
CREATE TABLE test_assertions(label text);
CREATE FUNCTION test_assert(ok boolean,label text) RETURNS void LANGUAGE plpgsql AS $$
BEGIN
 IF ok IS DISTINCT FROM true THEN RAISE EXCEPTION 'FAIL: %',label; END IF;
 INSERT INTO test_assertions VALUES(label);
 RAISE NOTICE 'PASS: %',label;
END $$;
