#!/usr/bin/env python3
"""Local disposable PostgreSQL concurrency gate; no production URLs, ports or accounts."""
import argparse,json,os,pathlib,secrets,subprocess,time

def main():
 parser=argparse.ArgumentParser(description=__doc__);parser.add_argument('--evidence',required=True);args=parser.parse_args()
 repo=pathlib.Path(__file__).resolve().parents[1];out=pathlib.Path(args.evidence).resolve()
 assert not out.is_relative_to(repo),'Evidence must stay private, outside Git'
 out.mkdir(parents=True,exist_ok=False)
 nonce=secrets.token_hex(5);label='schoolcef-scan-proof-'+nonce
 db='scan-proof-db-'+nonce;net='scan-proof-net-'+nonce;password=secrets.token_hex(24)
 php='6c1a8715597e99f75cf4ed19d05d2341f7b6c9b2cbe6453ac40ccf7407c1ad19'
 pg='postgres:17.11-bookworm';owned=[];results=[];fail=None;started=False
 env=dict(os.environ,MSYS_NO_PATHCONV='1')
 def run(cmd,**kw):
  value=subprocess.run(cmd,env=env,capture_output=True,timeout=kw.pop('timeout',40),**kw)
  if value.returncode:raise RuntimeError(value.stderr.decode(errors='replace').replace(password,'[fixture-secret]')[-2500:])
  return value.stdout.decode().strip()
 def sql(query):return run(['docker','exec','-i',db,'psql','-X','-v','ON_ERROR_STOP=1','-U','schoolcef_fixture','-d','schoolcef_fixture','-qAt'],input=query.encode())
 def php_cmd(action,*extra,barrier=False):
  name='scan-proof-worker-'+nonce+'-'+secrets.token_hex(3);owned.append(name)
  return ['docker','run','--rm','--name',name,'--label','schoolcef.qualification='+label,'--network',net,'--read-only',
   '-v',str(repo)+':/repo:ro','-e','SCHOOLSAFE_SCAN_FIXTURE=schoolcef-isolated-scan-20261023',
   '-e','POSTGRES_DB=schoolcef_fixture','-e','POSTGRES_PASSWORD='+password,'-e','SCHOOLSAFE_DB_HOST='+db,
   '-e','SCHOOLSAFE_QA_PROOF_BARRIER='+('1' if barrier else '0'),php,'php','/repo/scripts/school-pickup-proof-pg.php',action,*extra]
 def wait_sql(query,expected):
  deadline=time.monotonic()+12
  while time.monotonic()<deadline:
   if sql(query)==expected:return
   time.sleep(.04)
  raise RuntimeError('Bounded fixture barrier failed: '+query)
 token='a'*64
 def seed():
  sql("DELETE FROM schoolsafe_pickup_proofs; DELETE FROM entry_exit_student_records; DELETE FROM schoolsafe_pickup_authorizations; UPDATE qa_barrier SET hold=false,arrived=false; UPDATE schoolsafe_pickup_guardians SET is_active=true,photo_accepted=true,is_authorized_pickup=true;")
  sql("INSERT INTO schoolsafe_pickup_proofs(token,school_id,student_id,guardian_id,is_principal,location_id,prepared_by,expires_at) VALUES('"+token+"',1,11,21,true,7,9,'2099-01-01');")
 def worker(*extra):return json.loads(run(php_cmd('worker',token,*extra)))
 def compete(action,left,right):
  sql('UPDATE qa_barrier SET hold=true,arrived=false')
  first=subprocess.Popen(php_cmd(action,left,barrier=True),env=env,stdout=subprocess.PIPE,stderr=subprocess.PIPE)
  wait_sql('SELECT arrived FROM qa_barrier','t')
  second=subprocess.Popen(php_cmd(action,right),env=env,stdout=subprocess.PIPE,stderr=subprocess.PIPE)
  deadline=time.monotonic()+12
  while second.poll() is None and sql("SELECT (count(*)>0) FROM pg_stat_activity WHERE datname='schoolcef_fixture' AND wait_event_type='Lock'")!='t':
   if time.monotonic()>deadline:raise RuntimeError('No competing validation observed')
   time.sleep(.04)
  sql('UPDATE qa_barrier SET hold=false')
  responses=[]
  for process in [first,second]:
   stdout,stderr=process.communicate(timeout=25)
   if process.returncode:raise RuntimeError(stderr.decode(errors='replace').replace(password,'[fixture-secret]'))
   responses.append(json.loads(stdout))
  return responses
 def check(name,ok):
  assert ok,name
  results.append({'name':name,'status':'PASS'})
 try:
  # Existing images only: no registry pulls or installed dependencies.
  run(['docker','image','inspect',php]);run(['docker','image','inspect',pg])
  run(['docker','network','create','--internal','--label','schoolcef.qualification='+label,net]);started=True
  run(['docker','run','-d','--name',db,'--label','schoolcef.qualification='+label,'--network',net,
   '--tmpfs','/var/lib/postgresql/data','-e','POSTGRES_DB=schoolcef_fixture','-e','POSTGRES_USER=schoolcef_fixture','-e','POSTGRES_PASSWORD='+password,pg])
  deadline=time.monotonic()+25
  while subprocess.run(['docker','exec',db,'pg_isready','-h','127.0.0.1','-U','schoolcef_fixture','-d','schoolcef_fixture'],env=env,capture_output=True).returncode:
   if time.monotonic()>deadline:raise RuntimeError('Fixture PostgreSQL readiness timeout')
   time.sleep(.15)
  check('internal network and no published ports',run(['docker','network','inspect',net,'--format','{{.Internal}}'])=='true' and json.loads(run(['docker','inspect',db]))[0]['HostConfig']['PortBindings']=={})
  check('empty fixture accepts native migrations',json.loads(run(php_cmd('setup')))['status']=='SETUP')
  seed();sql('UPDATE qa_barrier SET hold=true,arrived=false')
  first=subprocess.Popen(php_cmd('worker',token,barrier=True),env=env,stdout=subprocess.PIPE,stderr=subprocess.PIPE)
  wait_sql('SELECT arrived FROM qa_barrier','t')
  second=subprocess.Popen(php_cmd('worker',token),env=env,stdout=subprocess.PIPE,stderr=subprocess.PIPE)
  wait_sql("SELECT (count(*)>0) FROM pg_stat_activity WHERE datname='schoolcef_fixture' AND wait_event_type='Lock'",'t')
  sql('UPDATE qa_barrier SET hold=false')
  completed=[]
  for process in [first,second]:
   stdout,stderr=process.communicate(timeout=25)
   if process.returncode:raise RuntimeError(stderr.decode(errors='replace').replace(password,'[fixture-secret]'))
   completed.append(json.loads(stdout))
  check('two real concurrent confirmations return one result and one replay',all(x['result']=='CONFIRMED' for x in completed) and sorted(x['idempotent'] for x in completed)==[False,True] and completed[0]['record_id']==completed[1]['record_id'])
  check('exactly one PostgreSQL handover record',sql("SELECT count(*) FROM entry_exit_student_records WHERE type='out'")=='1')
  seed();r=worker('18');check('other scanner operator denied without exit',r['result']=='DENIED' and r['reason']=='OUT_OF_SCOPE' and sql('SELECT count(*) FROM entry_exit_student_records')=='0')
  seed();r=worker('9','2');check('other school denied',r['result']=='DENIED' and r['reason']=='OUT_OF_SCOPE')
  seed();r=worker('9','1','12');check('other child denied',r['result']=='DENIED' and r['reason']=='OUT_OF_SCOPE')
  seed();sql("UPDATE schoolsafe_pickup_proofs SET expires_at='2026-10-23 16:29:59'");r=worker();check('expired proof denied',r['result']=='DENIED' and r['reason']=='PROOF_EXPIRED')
  seed();sql('UPDATE schoolsafe_pickup_guardians SET photo_accepted=false');r=worker();check('rejected photo denied at final recheck',r['result']=='DENIED' and r['reason']=='NOT_DELEGATED')
  seed();sql('UPDATE schoolsafe_pickup_guardians SET is_active=false');r=worker();check('inactive guardian denied at final recheck',r['result']=='DENIED' and r['reason']=='NOT_DELEGATED')
  seed();sql('UPDATE schoolsafe_pickup_guardians SET is_authorized_pickup=false');r=worker();check('withdrawn family pickup authority denied',r['result']=='DENIED' and r['reason']=='NOT_DELEGATED')
  check('all refusal scenarios left no exit',sql('SELECT count(*) FROM entry_exit_student_records')=='0')
  seed()
  sql("INSERT INTO schoolsafe_pickup_guardians(id,school_id,student_id,guardian_type,full_name,photo_accepted,photo_path) SELECT id,1,11,'externe','Synthetic external fixture',true,'/synthetic-only/portrait.jpg' FROM generate_series(31,34) id;")
  sql("INSERT INTO schoolsafe_pickup_authorizations(id,guardian_id,student_id,school_id,status,slot_no,photo_verified,requested_by) SELECT id,id,11,1,CASE WHEN id<33 THEN 'active' ELSE 'pending' END,CASE WHEN id<33 THEN id-30 ELSE NULL END,true,9 FROM generate_series(31,34) id;")
  activation=compete('activate','33','34')
  activation_count=sql("SELECT count(*) FROM schoolsafe_pickup_authorizations WHERE status='active'")
  slots=sql("SELECT count(DISTINCT slot_no) FROM schoolsafe_pickup_authorizations WHERE status='active'")
  seed();sql("INSERT INTO schoolsafe_pickup_guardians(id,school_id,student_id,guardian_type,full_name) VALUES(22,1,11,'tuteur','Synthetic family fixture');")
  registration=compete('family','','')
  family_count=sql("SELECT count(*) FROM schoolsafe_pickup_guardians WHERE is_active AND guardian_type IN ('pere','mere','tuteur')")
  (out/'limits-observed.json').write_text(json.dumps({'external_responses':activation,'external_active':activation_count,'external_distinct_slots':slots,'family_responses':registration,'family_active':family_count},indent=2))
  check('concurrent validations preserve three external slots',activation_count=='3' and slots=='3' and sum(r.get('status')=='active' for r in activation)==1 and sum(r.get('error')=='LIMIT_REACHED' for r in activation)==1)
  check('concurrent registrations preserve three active familial people',family_count=='3' and sum('guardian_id' in r for r in registration)==1 and sum(r.get('error')=='LIMIT_REACHED' for r in registration)==1)
  seed();sql("INSERT INTO schoolsafe_pickup_authorizations(id,guardian_id,student_id,school_id,status,photo_verified,requested_by) VALUES(51,31,11,1,'pending',true,9)")
  r=json.loads(run(php_cmd('activate','51','2025')));check('activation refuses absent enrollment year',r.get('error')=='NOT_FOUND')
  r=json.loads(run(php_cmd('activate','51','2026','2')));check('activation refuses another school enrollment',r.get('error')=='NOT_FOUND')
  sql('UPDATE schoolsafe_pickup_guardians SET photo_accepted=false WHERE id=31')
  r=json.loads(run(php_cmd('activate','51')));check('activation refuses rejected photo in real PostgreSQL',r.get('error')=='PHOTO_NOT_VALIDATED')
  check('failed activations reserve no slot',sql("SELECT count(*) FROM schoolsafe_pickup_authorizations WHERE status='active' OR slot_no IS NOT NULL")=='0')
 except Exception as error:fail=str(error)
 finally:
  for name in owned+[db]:
   found=subprocess.run(['docker','inspect','--format','{{index .Config.Labels "schoolcef.qualification"}}',name],env=env,capture_output=True)
   if found.returncode==0:
    assert found.stdout.decode().strip()==label,'Refuse touching an unowned container'
    run(['docker','rm','-f',name])
  if started:
   assert json.loads(run(['docker','network','inspect',net]))[0]['Labels']['schoolcef.qualification']==label
   run(['docker','network','rm',net])
 result={'status':'FAIL' if fail else 'PASS','scope':'Local disposable PostgreSQL with real native PHP confirmation, synthetic identities; not HTTP/camera/production','results':results,'cleanup':'owned ephemeral resources removed','error':fail}
 (out/'result.json').write_text(json.dumps(result,indent=2));print(json.dumps(result))
 if fail:raise SystemExit(1)
if __name__=='__main__':main()
