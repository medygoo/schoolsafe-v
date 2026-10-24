<?php
// Matrix privacy and ranking contract on an isolated synthetic data source.
$root=getenv('SCHOOLSAFE_RUNTIME_ROOT') ?: dirname(__DIR__).'/app/modules/codex-ui-v1/extensions-test-runtime/ProgramFunctions';
$queries=[];$profile='admin';$rights=true;$enabled='Y';$checks=0;$empty=false;
function User($key){global $profile;return $key==='PROFILE'?$profile:($key==='STAFF_ID'?1:null);}
function UserMP(){return 19;}function Config($key){global $enabled;return $enabled;}function AllowUse($key){global $rights;return $rights;}
function DBGet($sql){global $queries,$empty;$queries[]=$sql;
 if($empty)return [];
 if(str_contains($sql,'FROM schedule sc')&&str_contains($sql,'JOIN course_periods cp'))return [['STUDENT_ID'=>1,'COURSE_PERIOD_ID'=>71,'TITLE'=>'Classe <A>'],['STUDENT_ID'=>1,'COURSE_PERIOD_ID'=>72,'TITLE'=>'Classe B']];
 if(str_contains($sql,'FROM student_mp_stats')||str_contains($sql,'FROM (WITH ranked')){
  if(str_contains($sql,'course_period_id=71'))return [['STUDENT_ID'=>1,'POSITION'=>1]];
  if(str_contains($sql,'course_period_id=72'))return [['STUDENT_ID'=>1,'POSITION'=>2]];
  return [['STUDENT_ID'=>1,'POSITION'=>1,'FIRST_NAME'=>'Élève','MIDDLE_NAME'=>'','LAST_NAME'=>'Fictif','NAME_SUFFIX'=>'','PERIOD_TITLE'=>'Période synthétique','MARKING_PERIOD_ID'=>19,'SCORE'=>16,'SCALE'=>20]];
 }
 return [];
}
function ParseMLField($value){return $value;}
function check($ok,$why){global $checks;$checks++;if(!$ok)throw new RuntimeException($why);}
require_once $root.'/SchoolSafeDashboardNative.fnc.php';require_once $root.'/SchoolSafeSchoolLife.fnc.php';
$html=SchoolSafePalmaresMatrixVisual(3,2026);
check(str_contains($html,'Élève Fictif'),'Native student name in admin matrix');
check(str_contains($html,'Rang école')&&str_contains($html,'Rang en classe'),'Both rank scopes labelled');
check(str_contains($html,'Classe &lt;A&gt;')&&!str_contains($html,'Classe <A>'),'Class name escaped');
check(str_contains($html,'<details')&&str_contains($html,'<summary'),'Keyboard-operable detail');
check(str_contains(implode(' ',$queries),'sc.school_id=3')&&str_contains(implode(' ',$queries),'cp.school_id=3'),'Class mapping stays in school');
check(str_contains(implode(' ',$queries),'mp.marking_period_id=19'),'Native weighted rank uses selected period');
$queries=[];$profile='parent';check(SchoolSafePalmaresMatrixVisual(3,2026)===''&&$queries===[],'Parent never sees school-wide matrix');
$profile='admin';$rights=false;check(SchoolSafePalmaresMatrixVisual(3,2026)===''&&$queries===[],'No grade-list permission, no query');
$rights=true;$enabled='N';check(SchoolSafePalmaresMatrixVisual(3,2026)===''&&$queries===[],'Inactive palmares, no query');
$enabled='Y';$empty=true;check(str_contains(SchoolSafePalmaresMatrixVisual(3,2026),'Aucune moyenne native'),'No fabricated students when grades absent');
echo json_encode(['status'=>'PASS','checks'=>$checks],JSON_UNESCAPED_UNICODE)."\n";
