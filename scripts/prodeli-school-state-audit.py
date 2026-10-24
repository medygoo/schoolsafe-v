#!/usr/bin/env python3
"""Reproduction-only audit: current product must NOT be called secure when findings reproduce.
Uses disposable local PostgreSQL + native PHP DB functions. Never connects to production.
"""
import argparse, hashlib, json, os, pathlib, secrets, subprocess, time
p=argparse.ArgumentParser();p.add_argument('--evidence',required=True);p.add_argument('--expect-fixed',action='store_true');a=p.parse_args()
repo=pathlib.Path(__file__).resolve().parents[1];out=pathlib.Path(a.evidence).resolve()
assert not out.is_relative_to(repo)
out.mkdir(parents=True,exist_ok=False)
nonce=secrets.token_hex(5);db='prodeli-stability-db-'+nonce;net='prodeli-stability-net-'+nonce
label='prodeli-stability-'+nonce;password=secrets.token_hex(24)
php='sha256:6c1a8715597e99f75cf4ed19d05d2341f7b6c9b2cbe6453ac40ccf7407c1ad19';pg='postgres:17.11-bookworm'
env=dict(os.environ,MSYS_NO_PATHCONV='1');owned=[]
result={'status':'INCOMPLETE','production_touched':False,'authenticated_http_tested':False}
def call(args,timeout=30):return subprocess.run(args,env=env,capture_output=True,timeout=timeout)
def run(args,timeout=30):
 r=call(args,timeout)
 if r.returncode:raise RuntimeError(r.stderr.decode(errors='replace').replace(password,'[fixture]')[-1200:])
 return r.stdout.decode()
def worker(mode):
 r=call(['docker','run','--rm','--label','schoolcef.qualification='+label,'--read-only','--cap-drop','ALL','--security-opt','no-new-privileges','--network',net,'-v',str(repo)+':/repo:ro',
 '-e','SCHOOLSAFE_STABILITY_FIXTURE=isolated-school-state','-e','POSTGRES_DB=schoolcef_stability_fixture','-e','SCHOOLSAFE_DB_HOST='+db,'-e','POSTGRES_PASSWORD='+password,
 php,'php','/repo/scripts/prodeli-school-state-probe.php',mode])
 (out/(mode+'.private.log')).write_bytes(r.stdout+r.stderr)
 if r.returncode:raise RuntimeError('Probe failed unexpectedly; inspect private '+mode+' log')
 return r.stdout.decode()
def snapshot():
 sql="SELECT json_build_object('state',(SELECT state FROM schoolsafe_school_state WHERE school_id=999002),'audit_count',(SELECT count(*) FROM schoolsafe_school_audit WHERE school_id=999002));"
 return json.loads(run(['docker','exec',db,'psql','-U','schoolcef_fixture','-d','schoolcef_stability_fixture','-X','-A','-t','-c',sql]))
try:
 for image in [php,pg]:run(['docker','image','inspect',image])
 run(['docker','network','create','--internal','--label','schoolcef.qualification='+label,net]);owned.append(('network',net))
 run(['docker','run','-d','--name',db,'--label','schoolcef.qualification='+label,'--network',net,'--tmpfs','/var/lib/postgresql/data','-e','POSTGRES_DB=schoolcef_stability_fixture','-e','POSTGRES_USER=schoolcef_fixture','-e','POSTGRES_PASSWORD='+password,pg]);owned.append(('container',db))
 deadline=time.monotonic()+30
 while call(['docker','exec',db,'pg_isready','-h','127.0.0.1','-U','schoolcef_fixture']).returncode:
  if time.monotonic()>deadline:raise RuntimeError('Fixture readiness timeout')
  time.sleep(.1)
 assert json.loads(run(['docker','network','inspect',net]))[0]['Internal']
 assert not json.loads(run(['docker','inspect',db]))[0]['HostConfig']['PortBindings']
 assert json.loads(worker('setup'))['setup']=='READY'
 clean=json.loads(worker('clean'));assert clean['returned'] is True and clean['effective']=='suspended'
 before=snapshot();assert before=={'state':'active','audit_count':0}
 fault_output=worker('audit-failure');after=snapshot()
 if not a.expect_fixed:assert 'SYNTHETIC_AUDIT_FAILURE' in (out/'audit-failure.private.log').read_text()
 if a.expect_fixed:
  assert after==before and json.loads(fault_output)['returned'] is False
 else:
  assert after=={'state':'suspended','audit_count':0}
  assert '"returned"' not in fault_output
 (out/'audit-failure-observed.json').write_text(json.dumps({'before':before,'after':after},indent=2))
 invalid=json.loads(worker('invalid-date'))
 assert invalid['invalid_date_passes_console_regex']
 assert invalid['returned'] is (not a.expect_fixed)
 assert invalid['effective']==('active' if a.expect_fixed else 'suspended')
 stored=json.loads(run(['docker','exec',db,'psql','-U','schoolcef_fixture','-d','schoolcef_stability_fixture','-X','-A','-t','-c',"SELECT json_build_object('state',state,'until',until) FROM schoolsafe_school_state WHERE school_id=999003;"]))
 assert stored==({'state':'active','until':None} if a.expect_fixed else {'state':'suspended','until':'1970-01-01T00:00:00'})
 guards={}
 for name in ['prodeli-school-boundary-contracts.php','prodeli-account-presentation-contracts.php','school-scan-native-access-contracts.php']:
  r=call(['docker','run','--rm','--read-only','--cap-drop','ALL','--security-opt','no-new-privileges','--network','none','-v',str(repo)+':/repo:ro','-e','SCHOOLSAFE_CONTRACT_PHP_ROOT=/repo/app/modules/codex-ui-v1/extensions-test-runtime',php,'php','/repo/scripts/'+name])
  (out/(name+'.log')).write_bytes(r.stdout+r.stderr)
  assert r.returncode==0
  proof=json.loads(r.stdout.decode().strip().splitlines()[-1]);assert proof.get('failed',proof.get('fail'))==0
  guards[name]=proof.get('passed',proof.get('pass'))
 assert sum(guards.values())==162
 paths=['ProgramFunctions/SchoolSafeSchoolState.fnc.php','database.inc.php','functions/DBGet.fnc.php','admin-console.php']
 hashes={}
 for path in paths:
  full='app/modules/codex-ui-v1/extensions-test-runtime/'+path
  committed=run(['git','-C',str(repo),'show','HEAD:'+full]).replace('\r\n','\n')
  current=(repo/full).read_text(encoding='utf-8').replace('\r\n','\n')
  if path!='ProgramFunctions/SchoolSafeSchoolState.fnc.php' or not a.expect_fixed:assert current==committed
  hashes[path]=hashlib.sha256(current.encode()).hexdigest()
 result.update(status='FIX_VERIFIED' if a.expect_fixed else 'FINDINGS_CONFIRMED',product_gate='LOCAL_ONLY_PENDING_HTTP_IMAGE' if a.expect_fixed else 'BLOCKED_PENDING_CORRECTION',findings=[
 {'id':'PD-STAB-01','desired_invariant':'failed audit must leave state unchanged','invariant_passed':a.expect_fixed,'before':before,'after':after,'native_request_terminated':not a.expect_fixed},
 {'id':'PD-STAB-02','desired_invariant':'invalid calendar date refused without write','invariant_passed':a.expect_fixed,'input':'2026-99-99','helper_reported_success':not a.expect_fixed,'stored':stored,'effective':invalid['effective']}],
 positive_control=clean,regression_guards=guards,normalized_sources_match_git=True,normalized_source_hashes=hashes,
 evidence_level='Real disposable local PostgreSQL; native DB and school state helpers; identity/hooks substituted; NOT HTTP or production')
finally:
 for kind,name in reversed(owned):
  info=json.loads(run(['docker',kind,'inspect',name]))[0]
  labels=info.get('Config',{}).get('Labels',{}) if kind=='container' else info.get('Labels',{})
  assert labels.get('schoolcef.qualification')==label
  run(['docker',kind,'rm',*(['-f'] if kind=='container' else []),name])
 assert not run(['docker','ps','-aq','--filter','label=schoolcef.qualification='+label]).strip()
 assert not run(['docker','network','ls','-q','--filter','label=schoolcef.qualification='+label]).strip()
 result['owned_resources_removed']=True
 (out/'result.json').write_text(json.dumps(result,indent=2))
print(json.dumps(result,indent=2))
