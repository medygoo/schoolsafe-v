#!/usr/bin/env python3
"""Three isolated RED reproductions on disposable local PostgreSQL; no production access."""
import argparse,json,os,pathlib,secrets,subprocess,time
p=argparse.ArgumentParser();p.add_argument('--evidence',required=True);p.add_argument('--expect-fixed',action='store_true');a=p.parse_args()
repo=pathlib.Path(__file__).resolve().parents[1];out=pathlib.Path(a.evidence).resolve();assert not out.is_relative_to(repo);out.mkdir(parents=True,exist_ok=False)
env=dict(os.environ,MSYS_NO_PATHCONV='1');password=secrets.token_hex(24);nonce=secrets.token_hex(5);label='prodeli-account-state-'+nonce
net='prodeli-stability-net-'+nonce;pg='postgres:17.11-bookworm';php='sha256:6c1a8715597e99f75cf4ed19d05d2341f7b6c9b2cbe6453ac40ccf7407c1ad19'
def run(cmd,timeout=30):
 r=subprocess.run(cmd,capture_output=True,env=env,timeout=timeout)
 if r.returncode:raise RuntimeError(r.stderr.decode(errors='replace').replace(password,'[fixture]')[-600:])
 return r.stdout.decode()
for image in (pg,php):run(['docker','image','inspect',image])
owned=[];observed={}
try:
 run(['docker','network','create','--internal','--label','schoolcef.qualification='+label,net]);owned.append(('network',net))
 for index,mode in enumerate(('set','revoke','invalid','clean')):
  db='prodeli-stability-db-'+secrets.token_hex(5)
  run(['docker','run','-d','--name',db,'--network',net,'--tmpfs','/var/lib/postgresql/data','--label','schoolcef.qualification='+label,'-e','POSTGRES_DB=schoolcef_stability_fixture','-e','POSTGRES_USER=schoolcef_fixture','-e','POSTGRES_PASSWORD='+password,pg]);owned.append(('container',db))
  deadline=time.monotonic()+30
  while subprocess.run(['docker','exec',db,'pg_isready','-h','127.0.0.1','-U','schoolcef_fixture'],env=env,capture_output=True).returncode:
   if time.monotonic()>deadline:raise RuntimeError('fixture readiness timeout')
   time.sleep(.1)
  assert json.loads(run(['docker','inspect',db]))[0]['HostConfig']['PortBindings']=={}
  r=subprocess.run(['docker','run','--rm','--read-only','--cap-drop','ALL','--security-opt','no-new-privileges','--network',net,'-v',str(repo)+':/repo:ro','-e','SCHOOLSAFE_STABILITY_FIXTURE=isolated-account-state','-e','POSTGRES_DB=schoolcef_stability_fixture','-e','SCHOOLSAFE_DB_HOST='+db,'-e','POSTGRES_PASSWORD='+password,php,'php','/repo/scripts/prodeli-account-state-probe.php',mode],capture_output=True,env=env,timeout=40)
  (out/(mode+'.private.log')).write_bytes(r.stdout+r.stderr)
  sql="SELECT json_build_object('state',state,'epoch',session_epoch,'audit',(SELECT count(*) FROM schoolsafe_account_audit WHERE person_key='fixture.person'),'until',until) FROM schoolsafe_account_state WHERE person_key='fixture.person' AND school_id=3"
  after=json.loads(run(['docker','exec',db,'psql','-U','schoolcef_fixture','-d','schoolcef_stability_fixture','-X','-A','-t','-c',sql]))
  observed[mode]={'native_request_returncode':r.returncode,'native_returned_json':r.stdout.decode().strip() if r.stdout.decode().strip().startswith('{') else None,'after':after}
  run(['docker','rm','-f',db]);owned.pop()
 if a.expect_fixed:
  for mode in ('set','revoke','invalid'):
   assert observed[mode]['after']=={'state':'active','epoch':5,'audit':0,'until':None},mode
   assert json.loads(observed[mode]['native_returned_json'])=={'returned':False},mode
 else:
  assert observed['set']['after']['state']=='suspended' and observed['set']['after']['audit']==0
  assert observed['revoke']['after']['epoch']==6 and observed['revoke']['after']['audit']==0
  assert observed['invalid']['after']['until']=='1970-01-01T00:00:00' and observed['invalid']['after']['audit']==0
 assert observed['clean']['after']=={'state':'active','epoch':6,'audit':1,'until':None}
 assert json.loads(observed['clean']['native_returned_json'])=={'returned':True}
 proof={'status':'FIX_VERIFIED' if a.expect_fixed else 'FINDINGS_CONFIRMED','evidence_level':'Native PHP DB and PostgreSQL 17.11 isolated local tmpfs; synthetic actor; not HTTP or production','observed':observed,'production_touched':False}
finally:
 for kind,name in reversed(owned):
  info=json.loads(run(['docker',kind,'inspect',name]))[0]
  labels=info.get('Config',{}).get('Labels',{}) if kind=='container' else info.get('Labels',{})
  assert labels.get('schoolcef.qualification')==label
  run(['docker',kind,'rm',*(['-f'] if kind=='container' else []),name])
 assert not run(['docker','ps','-aq','--filter','label=schoolcef.qualification='+label]).strip()
 assert not run(['docker','network','ls','-q','--filter','label=schoolcef.qualification='+label]).strip()
 proof['owned_resources_removed']=True
 (out/'result.json').write_text(json.dumps(proof,indent=2))
print(json.dumps(proof,indent=2))
