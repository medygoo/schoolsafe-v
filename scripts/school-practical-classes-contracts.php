<?php
// Synthetic class rows only; production classes/people are never printed.
$root=getenv('SCHOOLSAFE_RUNTIME_ROOT') ?: dirname(__DIR__).'/app/modules/codex-ui-v1/extensions-test-runtime/ProgramFunctions';
$queries=[];$profile='admin';$rights=true;$period=19;$configured=true;$rows=true;$checks=0;
function User($key){global $profile;return $key==='PROFILE'?$profile:1;}function AllowUse($key){global $rights;return $rights;}function UserMP(){global $period;return $period;}
function DBGet($sql){global $queries,$configured,$rows;$queries[]=$sql;
 if(str_contains($sql,'AS tp_count'))return $configured?[['TP_COUNT'=>1]]:[['TP_COUNT'=>0]];
 return $rows?[['COURSE_PERIOD_ID'=>71,'TITLE'=>'Classe <A>','TEACHERS'=>'Mme Fictive','TOTAL'=>4,'POSITION'=>1],['COURSE_PERIOD_ID'=>72,'TITLE'=>'Classe B','TEACHERS'=>'M. Fictif','TOTAL'=>4,'POSITION'=>1]]:[];
}
function ParseMLField($s){return $s;}
function check($ok,$message){global $checks;$checks++;if(!$ok)throw new RuntimeException($message);}
require_once $root.'/SchoolSafePracticalClasses.fnc.php';
$html=SchoolSafePracticalClassesVisual(3,2026);
check(str_contains($html,'Classe &lt;A&gt;')&&!str_contains($html,'Classe <A>'),'Class title escaped');
check(str_contains($html,'Mme Fictive')&&str_contains($html,'M. Fictif'),'Only native teacher identities displayed');
check(substr_count($html,'data-rank="1"')===2,'True ties are visible');
check(str_contains($html,'4 TP')&&!str_contains($html,'4 élèves'),'Counts are assignments, never pupils');
$sql=implode(' ', $queries);
check(str_contains($sql,'lower(trim(t.title)) IN')&&str_contains($sql,"'travaux pratiques'")&&str_contains($sql,"'tp'"),'Only explicit native TP categories');
check(str_contains($sql,'mp.school_id=3')&&str_contains($sql,'mp.syear=2026')&&str_contains($sql,'a.marking_period_id=19'),'School/year/period guarded');
check(str_contains($sql,'COUNT(DISTINCT a.assignment_id)')&&str_contains($sql,'a.course_period_id IS NULL'),'Shared assignment counted once per affected class');
check(str_contains($sql,'a.assigned_date<=')&&str_contains($sql,'a.created_at::date<='),'Future assignments excluded, undated included only if created');
$queries=[];$profile='parent';check(SchoolSafePracticalClassesVisual(3,2026)===''&&$queries===[],'Parents have no school-wide classes');
$profile='admin';$rights=false;check(SchoolSafePracticalClassesVisual(3,2026)===''&&$queries===[],'Native permission missing, no query');
$rights=true;$period=0;check(SchoolSafePracticalClassesVisual(3,2026)===''&&$queries===[],'No selected period, no aggregation');
$period=19;$configured=false;check(str_contains(SchoolSafePracticalClassesVisual(3,2026),'Aucun TP configuré'),'Unconfigured category is honest');
$configured=true;$rows=false;check(str_contains(SchoolSafePracticalClassesVisual(3,2026),'Aucun TP donné'),'No assignments is honest');
echo json_encode(['status'=>'PASS','checks'=>$checks],JSON_UNESCAPED_UNICODE)."\n";
