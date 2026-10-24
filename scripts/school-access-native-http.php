<?php
// Inject only into a nonce-owned, read-only exact-image fixture by the QA driver.
if(PHP_SAPI!=='cli'||getenv('SCHOOLSAFE_SCAN_HTTP_FIXTURE')!=='schoolcef-synthetic-image-20261023'||getenv('SCHOOLSAFE_DB_HOST')!=='schoolcef-harmonization-pg'||getenv('POSTGRES_DB')!=='schoolcef_fixture')exit(2);
$pdo=new PDO('pgsql:host=schoolcef-harmonization-pg;dbname=schoolcef_fixture',getenv('POSTGRES_USER'),getenv('POSTGRES_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
require '/var/www/html/functions/Password.php';
$prefix='qa.access.'.bin2hex(random_bytes(8));$password=bin2hex(random_bytes(24));$cookies=[];$ids=[];$baseProfile=null;$teacherProfile=null;$moduleRow=null;$results=[];$failure=null;$cookie=null;
$q=fn($x)=>$pdo->quote($x);
function gate($ok,$label){global$results;if(!$ok)throw new RuntimeException($label);$results[]=$label;}
function req($url,$fields=null){global$cookie;
 if(!str_starts_with($url,'/')||str_starts_with($url,'//'))throw new RuntimeException('Fixture loopback only');
 $c=curl_init('http://127.0.0.1'.$url);curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_TIMEOUT=>20,CURLOPT_COOKIEFILE=>$cookie,CURLOPT_COOKIEJAR=>$cookie]);
 if($fields!==null){curl_setopt($c,CURLOPT_POST,true);curl_setopt($c,CURLOPT_POSTFIELDS,http_build_query($fields));}
 $body=curl_exec($c);$status=curl_getinfo($c,CURLINFO_HTTP_CODE);curl_close($c);if(!is_string($body))throw new RuntimeException('Fixture HTTP failure');return['body'=>$body,'status'=>$status,'json'=>json_decode($body,true)];
}
function document($body){$d=new DOMDocument();@$d->loadHTML($body);return new DOMXPath($d);}
function fields($body){$xp=document($body);$out=[];foreach($xp->query('//form[@data-native-access-form]//input[@type="hidden"]')as$node)$out[$node->getAttribute('name')]=$node->getAttribute('value');return$out;}
function profileFields($body,$pid){$xp=document($body);$form=$xp->query('//form[input[@name="profil_id" and @value="'.$pid.'"]]')->item(0);$out=[];if($form)foreach($xp->query('.//input[@type="hidden"]',$form)as$n)$out[$n->getAttribute('name')]=$n->getAttribute('value');return$out;}
function loginAs($name){global$cookie,$cookies,$password;$cookie=tempnam('/tmp','access-qa-');$cookies[]=$cookie;$r=req('/index.php');$form=['USERNAME'=>$name,'PASSWORD'=>$password];foreach(document($r['body'])->query('//form[@id="loginform"]//input[@name]')as$n){$k=$n->getAttribute('name');if(!isset($form[$k]))$form[$k]=$n->getAttribute('value');}req('/index.php',$form);return$cookie;}
function caps(){return req('/scanner-relay.php?operation=capabilities');}
try{
 $school=(int)$pdo->query('SELECT id FROM schools WHERE syear=2026 ORDER BY id LIMIT 1')->fetchColumn();gate($school>0,'synthetic school exists');
 $moduleRow=$pdo->query("SELECT * FROM config WHERE school_id=0 AND title='MODULES'")->fetch(PDO::FETCH_ASSOC);$modules=unserialize($moduleRow['config_value'],['allowed_classes'=>false]);$modules['SchoolSafe']=true;
 $pdo->exec('UPDATE config SET config_value='.$q(serialize($modules))." WHERE school_id=0 AND title='MODULES'");
 $baseProfile=(int)$pdo->query("INSERT INTO user_profiles(title,profile) VALUES(".$q($prefix.'.base').",'admin') RETURNING id")->fetchColumn();
 $teacherProfile=(int)$pdo->query("INSERT INTO user_profiles(title,profile) VALUES(".$q($prefix.'.teacher').",'teacher') RETURNING id")->fetchColumn();
 // The central account needs the real reader prerequisite for ProDéli pages.
 // Keep its profile separate: never enlarge the school base profile to make QA pass.
 $centralProfile=(int)$pdo->query("INSERT INTO user_profiles(title,profile) VALUES(".$q($prefix.'.central').",'admin') RETURNING id")->fetchColumn();
 $pdo->exec('INSERT INTO profile_exceptions(profile_id,modname,can_use,can_edit) VALUES('.$centralProfile.", 'Users/User.php','Y','')");
 gate((int)$pdo->query('SELECT count(*) FROM profile_exceptions WHERE profile_id='.$centralProfile." AND modname='Users/User.php' AND can_use='Y'")->fetchColumn()===1,'synthetic central actor explicitly has the native ProDéli reader prerequisite');
 foreach([$baseProfile,$teacherProfile]as$pid)foreach(['misc/Portal.php','Students/Student.php']as$key)$pdo->exec('INSERT INTO profile_exceptions(profile_id,modname,can_use,can_edit) VALUES('.$pid.','.$q($key).",'Y','Y')");
 $first=(int)$pdo->query('SELECT coalesce(max(staff_id),0)+100 FROM staff')->fetchColumn();
 for($i=0;$i<6;$i++){$id=$first+$i;$ids[]=$id;$role=$i<2?'admin':'teacher';$scope=$i===0?'NULL':$q(','.$school.',');
  $pdo->exec('INSERT INTO staff(staff_id,syear,username,password,first_name,last_name,profile,profile_id,schools,current_school_id,last_login) VALUES('.$id.',2026,'.$q($prefix.'.'.$i).','.$q(encrypt_password($password)).",'Synthetic','Native access',".$q($role).','.($i===0?$centralProfile:($i===1?$baseProfile:$teacherProfile)).','.$scope.','.$school.',CURRENT_TIMESTAMP)');}
 $globalCookie=loginAs($prefix.'.0');
 $r=req('/schoolsafe-administrators.php?home=1');$homeLink='/admin-console.php?access_school='.$school.'#school-native-access';
 gate($r['status']===200&&document($r['body'])->query('//a[@href="'.$homeLink.'"]')->length===1,'authenticated ProDéli home exposes the school access button');
 $r=req('/schoolsafe-administrators.php');$accountLink='/admin-console.php?access_school='.$school.'&access_staff='.$ids[1].'#school-native-access';
 $accountLinks=document($r['body'])->query('//tr[@id="sskc-account-'.$ids[1].'"]//a[@href="'.$accountLink.'"]');
 gate($r['status']===200&&$accountLinks->length===1,'authenticated account row exposes the exact school and account access button');
 $r=req($accountLinks->item(0)->getAttribute('href'));$discoveredFields=fields($r['body']);
 gate($r['status']===200&&($discoveredFields['access_staff']??'')===(string)$ids[1]&&($discoveredFields['access_school']??'')===(string)$school,'visible account button opens the native form with the correct school and recipient');
 loginAs($prefix.'.1');gate(req('/scanner-native.php')['status']===403,'school administrator without native grant is refused before the fixture bootstrap');$cookie=$globalCookie;
 $r=req('/admin-console.php?access_school='.$school.'&access_staff='.$ids[1]);
 gate($r['status']===200&&count(fields($r['body']))>0,'global native console exposes school access metadata without entering school');$globalFields=fields($r['body']);
 gate(req('/ecoles/'.$school.'/profils')['status']===403&&req('/scanner-native.php')['status']===403,'ProDéli cannot open school hub or scanner before bootstrap');
 $all=[];$catalog=require'/var/www/html/modules/SchoolSafe/Catalog.php';foreach($catalog as$key=>$def)if(in_array('admin',$def['profiles'],true))$all[$key]=['use'=>'Y','edit'=>'Y'];
 $all['Users/User.php']=['use'=>'Y','edit'=>'Y'];$all['Student_Billing/StudentFees.php']=['use'=>'Y','edit'=>'Y'];$all['Food_Service/Accounts.php']=['use'=>'Y','edit'=>'Y'];
 $post=$globalFields+['access_confirm'=>'Y','access_rights'=>$all];$bad=$post;$bad['console_token']='invalid-synthetic';
 req('/admin-console.php',$bad);gate((int)$pdo->query('SELECT profile_id FROM staff WHERE staff_id='.$ids[1])->fetchColumn()===$baseProfile,'invalid central CSRF cannot bootstrap');
 $r=req('/admin-console.php',$post);gate((int)$pdo->query('SELECT profile_id FROM staff WHERE staff_id='.$ids[1])->fetchColumn()!==$baseProfile,'confirmed native central POST bootstraps existing school administrator');
 gate((int)$pdo->query('SELECT count(*) FROM staff WHERE staff_id IN('.implode(',',$ids).')')->fetchColumn()===6,'bootstrap creates no account');
 gate(req('/scanner-native.php')['status']===403&&req('/ecoles/'.$school.'/profils')['status']===403,'ProDéli still has no school membership after bootstrap');
 $directorCookie=loginAs($prefix.'.1');$hub='/ecoles/'.$school.'/profils';
 $scanner=req('/scanner-native.php');gate($scanner['status']===200,'confirmed fixture bootstrap opens the school administrator scanner page');
 gate(str_contains($scanner['body'],'Présence &amp; Scan QR')&&str_contains($scanner['body'],'scanner-theme.js?v=1')&&str_contains($scanner['body'],'scanner-native.css?v=scan-theme-1')&&str_contains($scanner['body'],'id="camera"')&&str_contains($scanner['body'],'disabled'),'authenticated scanner serves approved layout, theme bootstrap and inert camera until capability load');
 $scannerCss=req('/schoolsafe-card-bridge/scanner-native.css?v=scan-theme-1');gate($scannerCss['status']===200&&str_contains($scannerCss['body'],'data-ss-background="night"')&&str_contains($scannerCss['body'],'data-ss-background="mint"')&&!str_contains($scannerCss['body'],'background:#030c25'),'authenticated scanner stylesheet follows dashboard palettes rather than a fixed dark blue canvas');
 $scannerTheme=req('/schoolsafe-card-bridge/scanner-theme.js?v=1');gate($scannerTheme['status']===200&&str_contains($scannerTheme['body'],"localStorage.getItem('schoolsafe-background')")&&str_contains($scannerTheme['body'],"window.addEventListener('storage'")&&str_contains($scannerTheme['body'],"includes(saved)?saved:'white'"),'authenticated scanner theme script reuses dashboard preference with safe fallback');
 $scans=[];foreach(['ScanArrival','ScanPickup','ScanFees','ScanCanteen','ScanVerify']as$key)$scans['SchoolSafe/'.$key.'.php']=['use'=>'Y','edit'=>'Y'];
 $operatorCookies=[];
 for($i=2;$i<=4;$i++){
  // Log in BEFORE assignment to prove live rights in an already open session.
  $operatorCookies[$i]=loginAs($prefix.'.'.$i);$cookie=$directorCookie;
  $r=req($hub.'?access_staff='.$ids[$i]);$f=fields($r['body']);gate($r['status']===200&&count($f)>0,'school native hub selects existing recipient '.$i);
  if($i===2){$b=$f+['access_rights'=>$scans];req($hub,$b);gate((int)$pdo->query('SELECT profile_id FROM staff WHERE staff_id='.$ids[$i])->fetchColumn()===$teacherProfile,'missing human confirmation cannot assign');}
  $r=req($hub,$f+['access_confirm'=>'Y','access_rights'=>$scans]);gate((int)$pdo->query('SELECT profile_id FROM staff WHERE staff_id='.$ids[$i])->fetchColumn()!==$teacherProfile,'native school POST assigns full Scan set to recipient '.$i);
  $cookie=$operatorCookies[$i];$r=caps();$data=$r['json']['data']??$r['json'];
  gate($r['status']===200,'existing recipient session reaches scanner capabilities '.$i);
  foreach(['ScanArrival','ScanPickup','ScanFees','ScanCanteen','ScanVerify']as$key){$pid=(int)$pdo->query('SELECT profile_id FROM staff WHERE staff_id='.$ids[$i])->fetchColumn();gate((int)$pdo->query('SELECT count(*) FROM profile_exceptions WHERE profile_id='.$pid.' AND modname='.$q('SchoolSafe/'.$key.'.php')." AND can_use='Y'")->fetchColumn()===1,'persistent independent '.$key.' for recipient '.$i);}
 }
 $cookie=$directorCookie;$r=req($hub.'?access_staff='.$ids[2]);$stale=fields($r['body']);$r=req($hub,$stale+['access_confirm'=>'Y']);
 gate((int)$pdo->query('SELECT count(*) FROM profile_exceptions e JOIN staff s ON s.profile_id=e.profile_id WHERE s.staff_id='.$ids[2]." AND e.modname LIKE 'SchoolSafe/Scan%'")->fetchColumn()===0,'native school POST revokes only chosen recipient');
 $cookie=$operatorCookies[2];gate(caps()['status']===403,'already open recipient session loses Scan immediately after revocation');
 $cookie=$operatorCookies[3];gate(caps()['status']===200,'other recipient keeps access after individual revocation');
 $cookie=$directorCookie;req($hub,$stale+['access_confirm'=>'Y','access_rights'=>$scans]);
 gate((int)$pdo->query('SELECT count(*) FROM profile_exceptions e JOIN staff s ON s.profile_id=e.profile_id WHERE s.staff_id='.$ids[2]." AND e.modname LIKE 'SchoolSafe/Scan%'")->fetchColumn()===0,'stale HTTP form cannot restore revoked rights');
 // Exercise the actual existing shared-profile controller, not just its GET or helpers.
 $cookie=$directorCookie;$r=req($hub.'?access_staff='.$ids[5]);$csrf=fields($r['body'])['token']??'';
 $create=['token'=>$csrf,'ecole_profil_action'=>'creer','titre'=>$prefix.'.shared','base'=>'teacher','droit'=>['SchoolSafe/ScanVerify.php'=>'Y'],'edit'=>['SchoolSafe/ScanVerify.php'=>'Y']];
 req($hub,$create);gate((int)$pdo->query('SELECT count(*) FROM user_profiles WHERE title='.$q($prefix.'.shared'))->fetchColumn()===0,'shared native creation also requires human impact confirmation');
 req($hub,$create+['profile_confirm'=>'Y']);$shared=(int)$pdo->query('SELECT id FROM user_profiles WHERE title='.$q($prefix.'.shared'))->fetchColumn();
 gate($shared>0,'actual shared profile controller creates school-owned native profile');
 gate((int)$pdo->query('SELECT school_id FROM user_profiles WHERE id='.$shared)->fetchColumn()===$school,'shared profile remains owned by selected school');
 $r=req($hub);$pf=profileFields($r['body'],$shared);gate(count($pf)>0,'shared editor renders current fingerprint and confirmation fields');
 req($hub,$pf+['profile_confirm'=>'Y','droit'=>['SchoolSafe/ScanVerify.php'=>'Y']]);
 gate((int)$pdo->query('SELECT count(*) FROM profile_exceptions WHERE profile_id='.$shared." AND modname='SchoolSafe/ScanVerify.php' AND can_use='Y' AND coalesce(can_edit,'')=''")->fetchColumn()===1,'actual shared rights POST can reduce edit to use-only');
 req($hub,$pf+['profile_confirm'=>'Y','droit'=>['SchoolSafe/ScanVerify.php'=>'Y'],'edit'=>['SchoolSafe/ScanVerify.php'=>'Y']]);
 gate((int)$pdo->query('SELECT count(*) FROM profile_exceptions WHERE profile_id='.$shared." AND can_edit='Y'")->fetchColumn()===0,'stale shared editor cannot restore edit');
 // Synthetic fixture preparation only: two existing fixture accounts hold this native profile.
 $pdo->exec('UPDATE staff SET profile_id='.$shared.' WHERE staff_id IN('.$ids[4].','.$ids[5].')');
 $r=req($hub);$pf=profileFields($r['body'],$shared);$del=$pf;$del['ecole_profil_action']='supprimer';$del['profile_confirm']='Y';req($hub,$del);
 gate((int)$pdo->query('SELECT count(*) FROM staff WHERE profile_id='.$shared)->fetchColumn()===2&&(int)$pdo->query('SELECT count(*) FROM user_profiles WHERE id='.$shared)->fetchColumn()===1,'shared deletion refuses assigned profile without converting holders to Custom');
 $r=req($hub);$pf=profileFields($r['body'],$shared);req($hub,$pf+['profile_confirm'=>'Y']);
 gate((int)$pdo->query('SELECT count(*) FROM profile_exceptions WHERE profile_id='.$shared)->fetchColumn()===0,'actual shared rights revocation affects both explicitly named holders');
 $cookie=$operatorCookies[4];gate(caps()['status']===403,'already open shared holder session loses revoked Scan rights');$cookie=$directorCookie;
 $pdo->exec('UPDATE staff SET profile_id='.$teacherProfile.' WHERE staff_id IN('.$ids[4].','.$ids[5].')');
 $r=req($hub);$del=profileFields($r['body'],$shared);$del['ecole_profil_action']='supprimer';$del['profile_confirm']='Y';req($hub,$del);
 gate((int)$pdo->query('SELECT count(*) FROM user_profiles WHERE id='.$shared)->fetchColumn()===0,'actual native deletion succeeds only after no fixture holder remains');
 gate((int)$pdo->query('SELECT count(*) FROM schoolsafe_account_audit WHERE person_key='.$q($prefix.'.1')." AND action IN ('profile_creer','profile_droits','profile_supprimer')")->fetchColumn()===4,'shared create edits and delete each commit mandatory native audit, refusals do not');
 gate((int)$pdo->query('SELECT count(*) FROM profile_exceptions WHERE profile_id='.$baseProfile)->fetchColumn()===2,'shared native base profile unchanged');
 gate((int)$pdo->query('SELECT count(*) FROM schoolsafe_account_audit WHERE person_key LIKE '.$q($prefix.'%')." AND action IN ('access_bootstrap','access_assign')")->fetchColumn()===5,'native mutations have one atomic audit each, preparations and refusals do not');
}catch(Throwable$e){$failure=$e->getMessage();}
finally{
 try{
  // Only rows bearing this run's random synthetic identity; never baseline users.
  if($ids){$in=implode(',',$ids);$pdo->exec('DELETE FROM staff_exceptions WHERE user_id IN('.$in.')');$pdo->exec('DELETE FROM schoolsafe_account_audit WHERE person_key LIKE '.$q($prefix.'%'));$pdo->exec('DELETE FROM schoolsafe_account_state WHERE person_key LIKE '.$q($prefix.'%'));$pdo->exec('DELETE FROM staff WHERE staff_id IN('.$in.')');}
  // Generated titles must belong to our exact target IDs, not merely this school.
  $owned=$pdo->query('SELECT id,title FROM user_profiles')->fetchAll(PDO::FETCH_ASSOC);$ours=[];
  foreach($owned as$p){if(str_starts_with($p['title'],$prefix))$ours[]=(int)$p['id'];foreach($ids as$id)if(str_starts_with($p['title'],'Accès école '.$school.' / compte '.$id.' / '))$ours[]=(int)$p['id'];}
  if($ours){$in=implode(',',array_unique($ours));$pdo->exec('DELETE FROM profile_exceptions WHERE profile_id IN('.$in.')');$pdo->exec('DELETE FROM user_profiles WHERE id IN('.$in.')');}
  if($moduleRow){$pdo->exec("DELETE FROM config WHERE school_id=0 AND title='MODULES'");$keys=array_keys($moduleRow);$values=array_map(fn($v)=>$v===null?'NULL':$q($v),array_values($moduleRow));$pdo->exec('INSERT INTO config('.implode(',',$keys).') VALUES('.implode(',',$values).')');}
  foreach($cookies as$c)if(is_file($c))unlink($c);
 }catch(Throwable$cleanup){$failure='Synthetic access cleanup failed';}
}
echo json_encode(['status'=>$failure?'FAIL':'PASS','checks'=>count($results),'results'=>$results,'error'=>$failure],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."\n";exit($failure?1:0);
