#!/usr/bin/env python3
"""Qualified three-file composed dashboard release; no scan changes or migration."""
import argparse
import hashlib
import importlib.util
import json
import os
from pathlib import Path
import re
import subprocess
import sys
import time
sys.dont_write_bytecode = True
os.umask(0o077)
ROOT = Path('/opt/schoolsafe-releases/school-dashboard-composition-20261023')
BASE_COMMIT = 'f1dcb3bddec98830446483ad4becb2c88a41c6de'
BASE_BRANCH = 'deployed/school-dashboard-stability-20261022'
BASE = 'sha256:b271dcc5c4e069623b278d327af6f71013b5fcc88c3bc90cee53c7f3bffe12e7'
PREFIX = 'app/modules/codex-ui-v1/extensions-test-runtime/'
FILES = {'ProgramFunctions/SchoolSafePhotoDashboard.fnc.php','assets/css/schoolsafe-photo-dashboard.css',
 'assets/js/schoolsafe-photo-dashboard.js'}

def module(name,path):
 spec=importlib.util.spec_from_file_location(name,path);value=importlib.util.module_from_spec(spec);spec.loader.exec_module(value);return value
previous=Path(__file__).resolve().parent/'recovery.py'
if not previous.exists(): previous=Path(__file__).resolve().parents[1]/'prodeli-recovery-sms-20261022/deploy.py'
tools=module('school_navigation_preservation',previous)
preserve=tools.preserve
tools.ROOT=preserve.ROOT=ROOT
run=preserve.run
inspect=preserve.inspect

def load(name): return json.loads((ROOT/name).read_text())
def write(name,value): (ROOT/name).write_text(json.dumps(value,indent=2))
def source_hashes(name):
 code='$o=[];foreach(json_decode($argv[1],true)as$p)$o[$p]=hash_file("sha256","/var/www/html/".$p);echo json_encode($o);'
 return json.loads(run(['docker','exec',name,'php','-r',code,json.dumps(sorted(FILES))]))

def qualification(release):
 assert re.fullmatch('[a-f0-9]{40}',release['commit']) and re.fullmatch('[a-f0-9]{40}',release['source_commit'])
 manifest=load('source-manifest.json');proof=load('image-prepare-proof.json')
 assert manifest['source_commit']==release['source_commit'] and manifest['base_image']==BASE
 assert manifest['payload_hashes']==release['files'] and set(release['files'])==FILES
 assert proof['status']=='READY' and proof['image']==release['image']
 assert proof['source_digest']==manifest['source_digest']==release['source_digest']
 assert set(proof['changed_files'])==FILES and proof['unchanged_files']==2761
 assert proof['renderer_contracts']==186 and proof['recovery_contracts']==37
 assert proof['read_only_web'] and proof['internal_network'] and not proof['published_ports']
 result=load('exact-integration-result.json')
 assert result['status']=='PASS' and result['image']==release['image'] and result['source_commit']==release['source_commit']
 assert {s['suite'] for s in result['suites']}=={'native','recovery','concurrency'} and all(s['exit']==0 for s in result['suites'])
 dom=load('dom-navigation-result.json');assert len(dom['results'])==126 and all(r['status']=='PASS' for r in dom['results']) and dom['errors']==[]
 native=load('native-result.json');assert native['status']=='PASS' and native['cleanup'] and len(native['results'])==25
 recovery=load('browser-result.json');assert recovery['cleanup'] and len(recovery['results'])==10
 race=load('reset-concurrency-result.json');assert race['revocationFailureRollbacks']==2 and race['barrierWaiters']==2 and race['nativeSuccesses']==race['sessionEpoch']==1
 for key in ['wizard','direct']:
  nav=load('school-navigation-'+key+'.json');assert nav['status']=='PASS' and len(nav['results'])==3
  assert {r['width'] for r in nav['results']}=={1280,390,768}
  assert all(len(r['sections'])==6 and r['navigation']=='PASS' for r in nav['results'])
  assert len({r['menuDestinations'] for r in nav['results']})==1
  assert len(nav['destinations'])>=8 and all(r['status']==200 for r in nav['destinations'])
 verify=load('image-verify-proof.json');cleanup=load('image-cleanup-proof.json')
 assert verify['status']=='PASS' and verify['image']==release['image'] and verify['fixture_snapshot_unchanged'] and verify['production_containers_unchanged']
 assert cleanup['status']=='REMOVED' and cleanup['only_owned_ephemeral_resources'] and cleanup['production_containers_unchanged']
 for f,digest in release['files'].items():
  assert hashlib.sha256(run(['git','-C','/opt/app-src','show',release['commit']+':'+PREFIX+f])).hexdigest()==digest
 for local,path in [('deploy.py','ops/releases/school-dashboard-composition-20261023/deploy.py'),('recovery.py','ops/releases/prodeli-recovery-sms-20261022/deploy.py'),('preservation.py','ops/releases/prodeli-native-access-20261021/deploy.py')]:
  assert (ROOT/local).read_bytes()==run(['git','-C','/opt/app-src','show',release['commit']+':'+path])
 for path in ['assets/themes','schoolsafe-attendance/ArrivalRead.php','scanner-native.php','scanner-relay.php','family-management.php','schoolsafe-card-bridge/scanner-native.js','schoolsafe-card-bridge/family-management.js','modules/SchoolSafe','PasswordReset.php','compte-recuperation.php','plateforme-prodeli-sms.php','ProgramFunctions/SchoolSafeRecovery.fnc.php','ProgramFunctions/SchoolSafeSms.fnc.php','ProgramFunctions/FirstLogin.fnc.php','ProgramFunctions/SchoolSafeSchoolScope.fnc.php','ProgramFunctions/SchoolSafeNativeOnboarding.fnc.php','functions/Password.php','index.php','modules/Grades','modules/School_Setup/Schools.php','modules/School_Setup/CopySchool.php','ops/deployment/config.inc.production.php']:
  assert run(['git','-C','/opt/app-src','diff',BASE_COMMIT,release['commit'],'--',PREFIX+path if not path.startswith('ops/') else path])==b''
 image=inspect(release['image'])
 assert image['Config']['Labels']['org.opencontainers.image.revision']==release['source_commit']
 assert image['Config']['Labels']['schoolsafe.source.digest']==release['source_digest']

def prepare(release):
 assert not (ROOT/'backup-proof.json').exists(),'Already prepared'
 run(['git','-C','/opt/app-src','fetch','--no-tags','origin','refs/heads/fix/school-dashboard-stability-20261022'])
 assert run(['git','-C','/opt/app-src','rev-parse','FETCH_HEAD']).decode().strip()==release['commit']
 qualification(release)
 assert run(['git','-C','/opt/app-src','rev-parse','HEAD']).decode().strip()==BASE_COMMIT
 assert run(['git','-C','/opt/app-src','branch','--show-current']).decode().strip()==BASE_BRANCH
 assert not run(['git','-C','/opt/app-src','status','--porcelain']).strip()
 changed=run(['git','-C','/opt/app-src','diff','--name-only',BASE_COMMIT,release['commit'],'--',PREFIX]).decode().splitlines()
 assert set(changed)=={PREFIX+f for f in FILES}
 pre=inspect('schoolsafe-rosario')
 assert pre['Image']==BASE and pre['State']['Running'] and pre['State']['Health']['Status']=='healthy'
 assert source_hashes('schoolsafe-rosario')==release['base_files']
 assert len(pre['Mounts'])==1 and pre['Mounts'][0]['Destination']=='/var/www/html/config.inc.php' and not pre['Mounts'][0]['RW']
 for name in [tools.OLD,tools.STAGED]:assert subprocess.run(['docker','inspect',name],capture_output=True).returncode!=0
 preserve.check_layer('schoolsafe-rosario');preserve.probe();preserve.probe(True)
 write('container-before.private.json',pre);write('other-services-before.json',preserve.other_state())
 others={}
 for cid in run(['docker','ps','-aq']).decode().split():
  item=inspect(cid);name=item['Name'].lstrip('/')
  if name!='schoolsafe-rosario':others[name]={'id':item['Id'],'image':item['Image'],'started':item['State']['StartedAt'],'running':item['State']['Running']}
 write('all-other-containers-before.private.json',others)
 (ROOT/'config-before.sha256').write_text(tools.configuration_hash('schoolsafe-rosario'))
 cli=tools.db_cli()
 assert run(cli,b"BEGIN READ ONLY; SELECT count(*) FROM information_schema.tables WHERE table_schema='public' AND table_name IN ('schools','staff','schoolsafe_native_onboarding','schoolsafe_recovery_otp'); COMMIT;").decode().strip()=='4'
 write('schema-proof.json',{'status':'PASS','read_only':True,'migrations':[]})
 config=load('db-cli.private.json');dump=run(['docker','exec','schoolsafe-rosario-db','pg_dump','-U',config['user'],'-d',config['database'],'-Fc'],timeout=180)
 assert len(dump)>1000
 backup=ROOT/'database-before.private.dump';backup.write_bytes(dump)
 listing=run(['docker','exec','-i','schoolsafe-rosario-db','pg_restore','--list'],dump);assert b'TABLE DATA' in listing
 (ROOT/'database-before-list.private.txt').write_bytes(listing)
 for path in FILES:
  target=ROOT/'source-before'/path;target.parent.mkdir(parents=True,exist_ok=True)
  data=run(['docker','exec','schoolsafe-rosario','php','-r','echo file_get_contents($argv[1]);','/var/www/html/'+path]);assert hashlib.sha256(data).hexdigest()==release['base_files'][path];target.write_bytes(data)
 result={'status':'PASS','commit':release['commit'],'image':release['image'],'backup_bytes':len(dump),'backup_sha256':hashlib.sha256(dump).hexdigest(),'archive_list_valid':True,'backup_mode':oct(backup.stat().st_mode&0o777),'source_backups':len(FILES),'migrations':[],'prepared_at':time.time()}
 assert result['backup_mode']=='0o600';write('backup-proof.json',result);print(json.dumps(result))

def create_staged(pre,release):
 payload=dict(pre['Config']);payload['Image']=release['image'];payload['Labels']=dict(payload.get('Labels')or{})
 payload['Labels'].update({'schoolsafe.patch.commit':release['commit'],'schoolsafe.patch.scope':'school-dashboard-composition','schoolsafe.patch.source-digest':release['source_digest']})
 payload['HostConfig']=dict(pre['HostConfig'])
 if payload['HostConfig'].get('OomKillDisable') is None:payload['HostConfig']['OomKillDisable']=False
 network=pre['HostConfig']['NetworkMode'];endpoint=pre['NetworkSettings']['Networks'][network]
 payload['NetworkingConfig']={'EndpointsConfig':{network:{'Aliases':endpoint.get('Aliases')or[]}}}
 connection=preserve.UnixHTTP('localhost');connection.request('POST','/v1.47/containers/create?name='+tools.STAGED,body=json.dumps(payload),headers={'Content-Type':'application/json'})
 response=connection.getresponse();body=response.read();connection.close()
 if response.status!=201:(ROOT/'create-error.private.json').write_bytes(body);raise RuntimeError('Candidate creation failed before production stop')
 staged=inspect(tools.STAGED);assert not staged['State']['Running'] and staged['Image']==release['image'];tools.unchanged_runtime(pre,staged);return staged['Id']

def main():
 parser=argparse.ArgumentParser(description=__doc__);parser.add_argument('action',choices=['prepare','deploy','status']);args=parser.parse_args();release=load('release.json')
 tools.BASE=BASE;tools.BASE_COMMIT=BASE_COMMIT;tools.BASE_BRANCH=BASE_BRANCH;tools.BRANCH='deployed/school-dashboard-composition-20261023'
 tools.OLD='schoolsafe-rosario-pre-school-dashboard-composition-20261023';tools.STAGED='schoolsafe-rosario-candidate-school-dashboard-composition-20261023'
 tools.IMAGE=release['image'];tools.DIGEST=release['source_digest'];tools.FILES=FILES;tools.source_hashes=source_hashes;tools.qualification=qualification;tools.create_staged=create_staged
 if args.action=='prepare':prepare(release)
 elif args.action=='deploy':tools.deploy(release)
 else:print(json.dumps(tools.final_status(release)))
if __name__=='__main__':main()
