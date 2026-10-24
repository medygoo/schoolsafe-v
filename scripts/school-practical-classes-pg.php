<?php
// Run ONLY on an isolated nonce-owned image QA fixture, against PostgreSQL temp tables.
if (PHP_SAPI!=='cli' || getenv('SCHOOLSAFE_PRACTICALS_FIXTURE')!=='synthetic-only'
    || getenv('SCHOOLSAFE_DB_HOST')!=='schoolcef-harmonization-pg'
    || getenv('POSTGRES_DB')!=='schoolcef_fixture') exit(2);
$pdo=new PDO('pgsql:host=schoolcef-harmonization-pg;dbname=schoolcef_fixture',getenv('POSTGRES_USER'),getenv('POSTGRES_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$pdo->beginTransaction();$checks=0;$profile='admin';
function User($key){global $profile;return $profile;}function AllowUse($key){return $key==='Grades/Assignments.php';}function UserMP(){return 19;}
function ParseMLField($title){return $title;}
function DBGet($sql){global $pdo;$rows=$pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);return array_map(static fn($row)=>array_change_key_case($row,CASE_UPPER),$rows);}
function gate($ok,$name){global $checks;if(!$ok)throw new RuntimeException($name);$checks++;}
try{
 foreach([
 'courses(course_id integer,school_id integer,syear integer)',
 'staff(staff_id integer,syear integer,first_name text,last_name text)',
 'school_marking_periods(marking_period_id integer,school_id integer,syear integer)',
 'gradebook_assignment_types(assignment_type_id integer,course_id integer,staff_id integer,title text)',
 'course_periods(course_period_id integer,course_id integer,school_id integer,syear integer,teacher_id integer,secondary_teacher_id integer,title text)',
 'schedule(student_id integer,course_period_id integer,school_id integer,syear integer,start_date date,end_date date)',
 'student_enrollment(student_id integer,school_id integer,syear integer,start_date date,end_date date)',
 'gradebook_assignments(assignment_id integer,course_id integer,course_period_id integer,staff_id integer,assignment_type_id integer,marking_period_id integer,assigned_date date,created_at timestamp)'
 ] as $table)$pdo->exec('CREATE TEMP TABLE '.$table.' ON COMMIT DROP');
 $pdo->exec("INSERT INTO courses VALUES(11,3,2026),(12,4,2026),(13,3,2025)");
 $pdo->exec("INSERT INTO staff VALUES(31,2026,'Mme','Fictive'),(32,2026,'M.','Fictif')");
 $pdo->exec("INSERT INTO school_marking_periods VALUES(19,3,2026),(20,4,2026),(21,3,2025)");
 $pdo->exec("INSERT INTO gradebook_assignment_types VALUES(1,11,31,'TP'),(2,11,32,'Travaux pratiques'),(3,11,31,'Devoirs'),(4,12,31,'TP'),(5,13,31,'TP')");
 $pdo->exec("INSERT INTO course_periods VALUES(71,11,3,2026,31,32,'Classe A'),(72,11,3,2026,31,NULL,'Classe B'),(73,12,4,2026,31,NULL,'Autre école'),(74,13,3,2025,31,NULL,'Autre année')");
 $pdo->exec("INSERT INTO schedule VALUES(101,71,3,2026,CURRENT_DATE-30,NULL),(102,71,3,2026,CURRENT_DATE-30,NULL),(103,72,3,2026,CURRENT_DATE-30,NULL),(104,73,4,2026,CURRENT_DATE-30,NULL)");
 $pdo->exec("INSERT INTO student_enrollment VALUES(101,3,2026,CURRENT_DATE-30,NULL),(102,3,2026,CURRENT_DATE-30,NULL),(103,3,2026,CURRENT_DATE-30,NULL),(104,4,2026,CURRENT_DATE-30,NULL)");
 $pdo->exec("INSERT INTO gradebook_assignments VALUES
 (1,11,NULL,31,1,19,CURRENT_DATE-1,CURRENT_TIMESTAMP-INTERVAL '1 day'),
 (2,11,71,32,2,19,CURRENT_DATE-1,CURRENT_TIMESTAMP-INTERVAL '1 day'),
 (3,11,71,31,1,19,CURRENT_DATE-1,CURRENT_TIMESTAMP-INTERVAL '1 day'),
 (4,11,71,31,3,19,CURRENT_DATE-1,CURRENT_TIMESTAMP-INTERVAL '1 day'),
 (5,11,71,31,1,19,CURRENT_DATE+1,CURRENT_TIMESTAMP-INTERVAL '1 day'),
 (6,11,71,31,1,19,NULL,CURRENT_TIMESTAMP-INTERVAL '1 day'),
 (7,11,72,31,1,19,CURRENT_DATE-1,CURRENT_TIMESTAMP-INTERVAL '1 day'),
 (8,12,73,31,4,20,CURRENT_DATE-1,CURRENT_TIMESTAMP-INTERVAL '1 day'),
 (9,13,74,31,5,21,CURRENT_DATE-1,CURRENT_TIMESTAMP-INTERVAL '1 day')");
 $root=getenv('SCHOOLSAFE_RUNTIME_ROOT')?:'/var/www/html/ProgramFunctions';require_once $root.'/SchoolSafePracticalClasses.fnc.php';
 $html=SchoolSafePracticalClassesVisual(3,2026);
 gate(str_contains($html,'Classe A')&&str_contains($html,'4 TP'),'Shared, secondary teacher and undated assignment counted once, not per pupil');
 gate(str_contains($html,'Classe B')&&str_contains($html,'2 TP'),'Shared assignment counted once in the second affected class');
 gate(str_contains($html,'Mme Fictive')&&str_contains($html,'M. Fictif'),'Real assignment owners grouped in class');
 gate(!str_contains($html,'Autre école')&&!str_contains($html,'Autre année'),'School/year boundaries');
 gate(!str_contains($html,'5 TP')&&!str_contains($html,'Devoirs'),'Future and non-TP excluded');
 $profile='parent';gate(SchoolSafePracticalClassesVisual(3,2026)==='','Parent denied');
 echo json_encode(['status'=>'PASS','checks'=>$checks],JSON_UNESCAPED_UNICODE)."\n";
} finally {$pdo->rollBack();}
