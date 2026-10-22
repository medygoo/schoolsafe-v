'use strict';
// Real native HTTP race, via two independent in-container curl clients.
// The ordinary loopback QA relay serializes requests and cannot prove a race.
const assert=require('node:assert/strict'),crypto=require('node:crypto'),fs=require('node:fs'),path=require('node:path');
const {execFileSync,spawn}=require('node:child_process'),readline=require('node:readline');
const web=process.env.PRODELI_QA_WEB||'schoolsafe-prodeli-presentation-qa';
const db=process.env.PRODELI_QA_DB||'schoolcef-harmonization-pg',network=process.env.PRODELI_QA_NETWORK||'schoolcef-harmonization-local';
const label=process.env.PRODELI_QA_LABEL||'harmonization-20261021';
const out=process.env.PRODELI_RECOVERY_EVIDENCE;assert.ok(out,'Private evidence directory required');fs.mkdirSync(out,{recursive:true});
const shellQuote=s=>"'"+s.replaceAll("'","'\\''")+"'";
const command=args=>process.env.PRODELI_QA_SSH?['ssh',['-o','BatchMode=yes',process.env.PRODELI_QA_SSH,'docker '+args.map(shellQuote).join(' ')]]:['docker',args];
const docker=(args,opts={})=>{const [exe,argv]=command(args);return execFileSync(exe,argv,{encoding:'utf8',timeout:30000,...opts}).trim();};
for(const name of [web,db])assert.equal(docker(['inspect','--format','{{index .Config.Labels "schoolcef.qualification"}}',name]),label);
assert.equal(docker(['network','inspect',network,'--format','{{.Internal}}']),'true');
const sql=q=>docker(['exec','-i',db,'psql','-X','-v','ON_ERROR_STOP=1','-U','schoolcef_fixture','-d','schoolcef_fixture','-qAt'],{input:q});
const quote=s=>"'"+String(s).replaceAll("'","''")+"'";
const user='qa.reset.'+crypto.randomBytes(6).toString('hex'),passwords=[0,1].map(()=>crypto.randomBytes(20).toString('hex'));
const clients=[],results=[];let staffId,locker;
function worker(code,input){
 const [exe,argv]=command(['exec','-i',web,'php','-r',code]);
 const p=spawn(exe,argv,{stdio:['pipe','pipe','pipe']});
 const lines=readline.createInterface({input:p.stdout});let readyResolve,doneResolve,doneReject,error='';
 const ready=new Promise(resolve=>readyResolve=resolve),done=new Promise((resolve,reject)=>{doneResolve=resolve;doneReject=reject;});
 // Mark rejection handled even if setup fails before the consumer awaits it.
 done.catch(()=>{});
 lines.on('line',line=>{if(line==='READY')readyResolve();else results.push({worker:p.pid,result:line});});
 p.stderr.on('data',chunk=>error+=chunk);p.on('error',doneReject);
 p.on('exit',code=>{lines.close();code===0?doneResolve():doneReject(new Error('Synthetic worker failed, exit '+code));});
 const timer=setTimeout(()=>{p.kill();doneReject(new Error('Synthetic worker timed out'));},45000);done.finally(()=>clearTimeout(timer)).catch(()=>{});
 if(input)p.stdin.write(JSON.stringify(input)+'\n');
 return {p,ready:Promise.race([ready,done.then(()=>{throw new Error('Worker ended before barrier');})]),done,release(){p.stdin.end('GO\n');}};
}
(async()=>{try{
 const source=Number(sql('SELECT id FROM schools WHERE syear=2026 ORDER BY id LIMIT 1'));
 const hash=docker(['exec','-i',web,'php','-r',"require '/var/www/html/functions/Password.php';echo encrypt_password(stream_get_contents(STDIN));"],{input:crypto.randomBytes(20).toString('hex')});
 staffId=Number(sql(`INSERT INTO staff(syear,first_name,last_name,username,password,email,profile,profile_id,schools,current_school_id,last_login) VALUES(2026,'Race','Fixture',${quote(user)},${quote(hash)},${quote(user+'@example.invalid')},'teacher',2,${quote(','+source+',')},${source},CURRENT_TIMESTAMP) RETURNING staff_id`));
 sql(`INSERT INTO staff(syear,first_name,last_name,username,password,email,profile,profile_id,schools,current_school_id,last_login) SELECT 2025,first_name,last_name,username,password,email,profile,profile_id,schools,current_school_id,last_login FROM staff WHERE staff_id=${staffId} AND syear=2026`);
 const mintCode=`$_SERVER['SERVER_NAME']='127.0.0.1';$_SERVER['SERVER_PORT']='80';$_SERVER['SCRIPT_NAME']='/PasswordReset.php';chdir('/var/www/html');require 'Warehouse.php';require_once 'ProgramFunctions/SchoolSafeRecovery.fnc.php';echo json_encode(SchoolSafeRecoveryIssueReset(${staffId},'staff',${JSON.stringify(user+'@example.invalid')},static function(){return true;}));`;
 const mint=JSON.parse(docker(['exec','-i',web,'php','-r',mintCode]));assert.ok(mint.sent);const h=new URL(mint.link).searchParams.get('h');assert.ok(h);
 const snapshot=()=>sql(`SELECT json_agg(s ORDER BY syear) FROM (SELECT syear,password,last_login FROM staff WHERE username=${quote(user)}) s`);
 const before=snapshot();
 const current=JSON.parse(sql(`SELECT json_build_object('id',staff_id,'password',password,'deadline',last_login::text) FROM staff WHERE staff_id=${staffId}`));
 for(const failure of ['false','exception']){
  const code=String.raw`chdir('/var/www/html');require 'Warehouse.php';require_once 'ProgramFunctions/SchoolSafeRecovery.fnc.php';$in=json_decode(stream_get_contents(STDIN),true);$revoker=static function()use($in){if($in['failure']==='exception')throw new RuntimeException('Synthetic revocation failure');return false;};echo SchoolSafeRecoveryCompleteStaffReset($in['id'],$in['password'],$in['deadline'],$in['new'],$revoker)?'1':'0';`;
  const result=docker(['exec','-i',web,'php','-r',code],{input:JSON.stringify({...current,new:passwords[0],failure})});
  assert.equal(result,'0');assert.equal(snapshot(),before,'Password and deadline rollback on '+failure);
 }
 const clientCode=String.raw`$input=json_decode(fgets(STDIN),true);$c=curl_init();curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HEADER=>true,CURLOPT_COOKIEFILE=>'',CURLOPT_TIMEOUT=>20,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_USERAGENT=>$input['marker']]);curl_setopt($c,CURLOPT_URL,'http://127.0.0.1/index.php');curl_exec($c);echo "READY\n";fflush(STDOUT);fgets(STDIN);curl_setopt_array($c,[CURLOPT_URL=>'http://127.0.0.1/PasswordReset.php',CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query(['h'=>$input['h'],'PASSWORD'=>$input['password']])]);$raw=curl_exec($c);$code=curl_getinfo($c,CURLINFO_HTTP_CODE);$header=substr((string)$raw,0,curl_getinfo($c,CURLINFO_HEADER_SIZE));preg_match('/^Location:\s*([^\r\n]+)/mi',$header,$m);echo json_encode(['status'=>$code,'location'=>$m[1]??''])."\n";if(curl_errno($c))exit(2);`;
 for(const password of passwords){const c=worker(clientCode,{h,password,marker:user});clients.push(c);await c.ready;}
 const lockCode=String.raw`$id=(int)trim(fgets(STDIN));$db=pg_connect('host='.getenv('SCHOOLSAFE_DB_HOST').' dbname=schoolcef_fixture user=schoolcef_fixture password='.getenv('POSTGRES_PASSWORD'));pg_query($db,'BEGIN');pg_query($db,"SELECT staff_id FROM staff WHERE staff_id=$id AND syear=2026 FOR UPDATE");echo "READY\n";fflush(STDOUT);fgets(STDIN);pg_query($db,'COMMIT');`;
 // This worker takes a numeric ID line rather than the JSON client payload.
 locker=worker(lockCode);locker.p.stdin.write(String(staffId)+'\n');await locker.ready;
 for(const c of clients)c.release();
 let blocked=0;for(let i=0;i<35;i++){
  blocked=Number(sql("SELECT count(*) FROM pg_stat_activity WHERE datname='schoolcef_fixture' AND wait_event_type='Lock' AND query ILIKE '%staff%'"));
  if(blocked>=2)break;await new Promise(resolve=>setTimeout(resolve,80));
 }
 assert.equal(blocked,2,'Both independent HTTP submissions must be blocked at the database barrier');
 locker.release();await locker.done;await Promise.all(clients.map(c=>c.done));
 const httpResults=results.map(r=>JSON.parse(r.result));assert.equal(httpResults.length,2);
 const successes=httpResults.filter(r=>r.status===302&&r.location==='index.php').length;
 const epoch=Number(sql(`SELECT COALESCE(MAX(session_epoch),0) FROM schoolsafe_account_state WHERE person_key=${quote(user)} AND school_id=0`));
 const proof={revocationFailureRollbacks:2,barrierWaiters:blocked,nativeSuccesses:successes,httpResults,sessionEpoch:epoch,scope:'Fictitious PostgreSQL, two independent native HTTP sessions; no external delivery.'};
 fs.writeFileSync(path.join(out,'reset-concurrency-result.json'),JSON.stringify(proof,null,2));console.log(JSON.stringify(proof));
 assert.equal(successes,1,'Only one concurrent native password reset may succeed');assert.equal(epoch,1,'Only one successful reset revokes sessions');
 const final=sql(`SELECT count(DISTINCT password) FROM staff WHERE username=${quote(user)}`);assert.equal(final,'1','Native all-school-years password update preserved');
 const matching=docker(['exec','-i',web,'php','-r',String.raw`chdir('/var/www/html');require 'Warehouse.php';$in=json_decode(stream_get_contents(STDIN),true);$hash=DBGetOne("SELECT password FROM staff WHERE staff_id=".(int)$in['id']);$count=0;foreach($in['passwords']as$p)if(match_password($hash,$p))$count++;echo $count;`],{input:JSON.stringify({id:staffId,passwords})});assert.equal(matching,'1','Exactly one submitted password is the final credential');
 console.log('PASS rollback on false/exception; one concurrent winner; one epoch; all school years share the winning password');
}finally{
 if(locker){if(!locker.p.stdin.destroyed)locker.release();await locker.done.catch(()=>{});}
 for(const c of clients){if(!c.p.stdin.destroyed)c.release();await c.done.catch(()=>{});}
 if(staffId)sql(`BEGIN;DELETE FROM schoolsafe_account_audit WHERE person_key=${quote(user)};DELETE FROM schoolsafe_account_state WHERE person_key=${quote(user)};DELETE FROM access_log WHERE user_agent=${quote(user)} OR username=${quote(user)};DELETE FROM staff WHERE username=${quote(user)} AND syear IN (2025,2026);COMMIT;`);
 console.log('Synthetic concurrency fixtures cleaned');
}})().catch(e=>{console.error(e.stack);process.exitCode=1;});
