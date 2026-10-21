<?php
/** Execute the exact entry script with fake native handlers, never live DB. */
declare(strict_types=1);
$root=getenv('SCHOOLSAFE_CONTRACT_PHP_ROOT')?:'/work/php';
$dir=sys_get_temp_dir().'/prodeli-entry-'.bin2hex(random_bytes(8));mkdir($dir,0700);
copy($root.'/plateforme-prodeli.php',$dir.'/plateforme-prodeli.php');
file_put_contents($dir.'/Warehouse.php','<?php function SchoolSafeGlobalAdmin(){return (bool)getenv("TEST_GLOBAL");}');
file_put_contents($dir.'/admin-console.php','<?php echo "NATIVE_HANDLER";');
$cases=[['GET',true,[],false],['GET',false,[],true],['POST',true,[],true],['GET',true,['console_action'=>'state'],true],['POST',false,[],true]];
$failed=0;
try{foreach($cases as $i=>[$method,$global,$query,$expected]){
    $code='$_SERVER["REQUEST_METHOD"]='.var_export($method,true).';$_GET='.var_export($query,true).';putenv("TEST_GLOBAL='.($global?'1':'0').'");require '.var_export($dir.'/plateforme-prodeli.php',true).';';
    $command=escapeshellarg(PHP_BINARY).' -r '.escapeshellarg($code);
    $lines=[];$exit=0;exec($command,$lines,$exit);$called=in_array('NATIVE_HANDLER',$lines,true);
    $ok=$exit===0&&$called===$expected;if(!$ok)$failed++;
    echo ($ok?'PASS ':'FAIL ').'entry case '.$i.' method='.$method.' global='.(int)$global.' legacy='.(int)$expected."\n";
}}finally{foreach(glob($dir.'/*') as $f)unlink($f);rmdir($dir);}
echo json_encode(['passed'=>count($cases)-$failed,'failed'=>$failed])."\n";exit($failed?1:0);
