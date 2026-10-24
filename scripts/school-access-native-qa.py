#!/usr/bin/env python3
"""Disposable local native access gate. No production hosts, ports or providers."""
import argparse,base64,json,os,pathlib,secrets,subprocess,time
p=argparse.ArgumentParser();p.add_argument('--evidence',required=True);a=p.parse_args()
repo=pathlib.Path(__file__).resolve().parents[1];out=pathlib.Path(a.evidence).resolve()
assert not out.is_relative_to(repo);out.mkdir(parents=True,exist_ok=False)
nonce=secrets.token_hex(5);db='access-proof-db-'+nonce;net='access-proof-net-'+nonce;label='schoolcef-native-access-'+nonce
password=secrets.token_hex(24);php='sha256:6c1a8715597e99f75cf4ed19d05d2341f7b6c9b2cbe6453ac40ccf7407c1ad19';pg='postgres:17.11-bookworm'
env=dict(os.environ,MSYS_NO_PATHCONV='1');owned=[];result={'status':'INCOMPLETE'}
def run(cmd,timeout=40):
 r=subprocess.run(cmd,env=env,capture_output=True,timeout=timeout)
 if r.returncode:raise RuntimeError(r.stderr.decode(errors='replace').replace(password,'[fixture]')[-1500:])
 return r.stdout.decode()
try:
 run(['docker','image','inspect',php]);run(['docker','image','inspect',pg])
 run(['docker','network','create','--internal','--label','schoolcef.qualification='+label,net]);owned.append(('network',net))
 run(['docker','run','-d','--name',db,'--label','schoolcef.qualification='+label,'--network',net,'--tmpfs','/var/lib/postgresql/data','-e','POSTGRES_DB=schoolcef_fixture','-e','POSTGRES_USER=schoolcef_fixture','-e','POSTGRES_PASSWORD='+password,pg]);owned.append(('container',db))
 deadline=time.monotonic()+30
 while subprocess.run(['docker','exec',db,'pg_isready','-h','127.0.0.1','-U','schoolcef_fixture'],env=env,capture_output=True).returncode:
  if time.monotonic()>deadline:raise RuntimeError('Fixture readiness timeout')
  time.sleep(.1)
 r=subprocess.run(['docker','run','--rm','--read-only','--network',net,'-v',str(repo)+':/repo:ro','-e','SCHOOLSAFE_ACCESS_FIXTURE=isolated-native-access','-e','POSTGRES_DB=schoolcef_fixture','-e','SCHOOLSAFE_DB_HOST='+db,'-e','POSTGRES_PASSWORD='+password,php,'php','/repo/scripts/school-access-native-pg.php'],env=env,capture_output=True,timeout=90)
 (out/'pg.log').write_bytes(r.stdout+r.stderr)
 if r.returncode:raise RuntimeError('Native PG gate failed; inspect private pg.log')
 for line in r.stdout.decode().splitlines():
  if line.startswith('RENDER:'):(out/'school-access-rendered.html').write_bytes(base64.b64decode(line[7:],validate=True))
 result=dict(json.loads(r.stdout.decode().strip().splitlines()[-1]),status='PASS',evidence='Disposable local PostgreSQL; native DB, profiles and permission resolver; account state/auth substituted; not HTTP or production')
finally:
 for kind,name in reversed(owned):
  info=json.loads(run(['docker',kind,'inspect',name]))[0]
  labels=info.get('Config',{}).get('Labels',{}) if kind=='container' else info.get('Labels',{})
  assert labels.get('schoolcef.qualification')==label
  run(['docker',kind,'rm',*(['-f'] if kind=='container' else []),name])
 result['owned_resources_removed']=True
 (out/'result.json').write_text(json.dumps(result,indent=2))
print(json.dumps(result))
