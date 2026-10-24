#!/usr/bin/env python3
"""Native school top-ten matrix release preserving all three mounts."""
import argparse,hashlib,importlib.util,json,os,pathlib,re,subprocess,time
os.umask(0o077)
ROOT=pathlib.Path('/opt/schoolsafe-releases/school-palmares-matrix-20261024')
def module(name,path):
 s=importlib.util.spec_from_file_location(name,path);obj=importlib.util.module_from_spec(s);s.loader.exec_module(obj);return obj
portrait=module('toolbar_portrait_release',ROOT/'portrait-release.py');logo=portrait.logo;access=portrait.access;tools=portrait.tools;preserve=portrait.preserve;run=preserve.run;inspect=preserve.inspect
BASE='sha256:f2d23fdc61df4ed28516ec00e4fc70bdbd1efdbd9e2cd16379c497cce6d3488e'
BASE_COMMIT='1deda155d7877435fe8bc383a05e49f1ccf476db'
SOURCE='acbb834dc538695e8c35a11eac443a5d5d2fd0fe'
IMAGE='sha256:8f2366bc38e684ce20c07a480e180c68fc7ae0a60fd031c34074b6f9c7b6f918'
DIGEST='8a6a27fc446bfc3a5313acb0ec311917ec3ef565be52d669b578f2afe77463cc'
FILES={'ProgramFunctions/SchoolSafePhotoDashboard.fnc.php','ProgramFunctions/SchoolSafeSchoolLife.fnc.php','assets/css/schoolsafe-photo-dashboard.css','assets/js/schoolsafe-photo-dashboard.js'}
PREFIX='app/modules/codex-ui-v1/extensions-test-runtime/'
WORK_BRANCH='feat/school-dashboard-finish-20261024';BRANCH='deployed/school-palmares-matrix-20261024';BASE_BRANCH='deployed/school-birthdays-20261024'
def load(n):return json.loads((ROOT/n).read_text())
def write(n,d):(ROOT/n).write_text(json.dumps(d,indent=2))
def git(c,p):return run(['git','-C','/opt/app-src','show',c+':'+p])
def media_hashes():
 school=pathlib.Path('/opt/schoolsafe-media/school-logos/school_logo_3.jpg');candidates=list(pathlib.Path('/opt/schoolsafe-media/user-photos').glob('2026/6.*jpg'))
 assert school.is_file() and len(candidates)==1 and candidates[0].is_file()
 return {'logo':hashlib.sha256(school.read_bytes()).hexdigest(),'owner_photo':hashlib.sha256(candidates[0].read_bytes()).hexdigest()}
def qualification(release):
 assert re.fullmatch('[a-f0-9]{40}',release['commit']) and release['image']==IMAGE and release['source_commit']==SOURCE and release['source_digest']==DIGEST
 assert set(release['files'])==FILES and set(release['base_files'])==FILES
 manifest=load('source-manifest.json');proof=load('image-prepare-proof.json')
 assert manifest['base_image']==BASE and manifest['source_commit']==SOURCE and manifest['source_digest']==DIGEST and not manifest['new_files']
 assert manifest['payload_hashes']==release['files'] and manifest['base_hashes']==release['base_files']
 assert proof['status']=='READY' and proof['image']==IMAGE and set(proof['changed_files'])==FILES and proof['unchanged_files']==2801 and proof['renderer_contracts']==178 and proof['recovery_contracts']==37
 assert proof['internal_network'] and proof['read_only_web'] and not proof['published_ports'] and proof['production_containers_unchanged']
 protected={}
 for n in ['base-native-manifest.json','base-discovery-manifest.json','base-state-manifest.json','base-scanner-manifest.json','base-logo-manifest.json','base-portrait-manifest.json','base-toolbar-manifest.json','base-birthday-manifest.json']:protected.update(load(n)['payload_hashes'])
 assert len(protected)==89
 expected=dict(protected,**release['files'])
 for f,d in protected.items():
  if f in FILES:continue
  assert hashlib.sha256(git(BASE_COMMIT,PREFIX+f)).hexdigest()==d and hashlib.sha256(git(release['commit'],PREFIX+f)).hexdigest()==d
 for f,d in release['files'].items():
  assert hashlib.sha256(git(SOURCE,PREFIX+f)).hexdigest()==d==hashlib.sha256(git(release['commit'],PREFIX+f)).hexdigest()
  assert hashlib.sha256(git(BASE_COMMIT,PREFIX+f)).hexdigest()==release['base_files'][f]
 code='$o=[];foreach(json_decode($argv[1],true)as$f)$o[$f]=hash_file("sha256","/var/www/html/".$f);echo json_encode($o);'
 assert json.loads(run(['docker','run','--rm','--pull=never','--network','none','--read-only','--entrypoint','php',IMAGE,'-r',code,json.dumps(sorted(expected))]))==expected
 labels=inspect(IMAGE)['Config']['Labels'];assert labels['org.opencontainers.image.revision']==SOURCE and labels['schoolsafe.source.digest']==DIGEST
 http=load('matrix-http.private.json');r=json.loads(http['stdout']);assert http['exit']==0 and http['stderr']=='' and r['status']=='PASS' and r['checks']==62 and len(r['results'])==62 and r['error'] is None
 matrix=load('matrix-contracts.json');assert matrix['status']=='PASS' and matrix['checks']==10
 test=(ROOT/'matrix-contracts.php').read_bytes();assert test.replace(b'\r\n',b'\n')==git(release['commit'],'scripts/school-palmares-matrix-contracts.php').replace(b'\r\n',b'\n')
 assert json.loads(run(['docker','run','--rm','--pull=never','--network','none','--read-only','-e','SCHOOLSAFE_RUNTIME_ROOT=/var/www/html/ProgramFunctions','-v',str(ROOT/'matrix-contracts.php')+':/tmp/matrix-contracts.php:ro','--entrypoint','php',IMAGE,'/tmp/matrix-contracts.php']))==matrix
 browser=load('matrix-browser.json');assert browser['status']=='PASS' and len(browser['results'])==6
 verified=load('image-verify-proof.json');clean=load('image-cleanup-proof.json');assert verified['status']=='PASS' and verified['image']==IMAGE and verified['fixture_snapshot_unchanged'] and verified['production_containers_unchanged'] and verified['no_test_accounts_or_schemas']
 assert clean['status']=='REMOVED' and clean['only_owned_ephemeral_resources'] and clean['production_containers_unchanged']
 for local,p in [('deploy.py','ops/releases/school-palmares-matrix-20261024/deploy.py'),('portrait-release.py','ops/releases/school-admin-portrait-20261024/deploy.py'),('logo-release.py','ops/releases/school-logo-20261024/deploy.py'),('native-access.py','ops/releases/school-native-access-20261024/deploy.py'),('recovery.py','ops/releases/prodeli-recovery-sms-20261022/deploy.py'),('preservation.py','ops/releases/prodeli-native-access-20261021/deploy.py')]:assert (ROOT/local).read_bytes().replace(b'\r\n',b'\n')==git(release['commit'],p).replace(b'\r\n',b'\n')
def prepare(release):
 assert not (ROOT/'backup-proof.json').exists()
 run(['git','-C','/opt/app-src','fetch','--no-tags','origin','refs/heads/'+WORK_BRANCH]);assert run(['git','-C','/opt/app-src','rev-parse','FETCH_HEAD']).decode().strip()==release['commit']
 qualification(release)
 assert run(['git','-C','/opt/app-src','rev-parse','HEAD']).decode().strip()==BASE_COMMIT
 assert run(['git','-C','/opt/app-src','branch','--show-current']).decode().strip()==BASE_BRANCH
 assert not run(['git','-C','/opt/app-src','status','--porcelain']).strip()
 assert set(run(['git','-C','/opt/app-src','diff','--name-only',BASE_COMMIT,release['commit'],'--',PREFIX]).decode().splitlines())=={PREFIX+f for f in FILES}
 pre=inspect(tools.NAME);assert pre['Image']==BASE and pre['State']['Health']['Status']=='healthy' and len(pre['Mounts'])==3 and len(pre['HostConfig']['Binds'])==3
 assert access.source_hashes(tools.NAME)==release['base_files']
 for n in [tools.OLD,tools.STAGED]:assert subprocess.run(['docker','inspect',n],capture_output=True).returncode!=0
 preserve.check_layer(tools.NAME);preserve.probe();preserve.probe(True)
 write('container-before.private.json',pre);write('other-services-before.json',preserve.other_state());write('media-before.json',media_hashes())
 (ROOT/'config-before.sha256').write_text(tools.configuration_hash(tools.NAME));cli=tools.db_cli();access.schema(cli)
 cfg=load('db-cli.private.json');dump=run(['docker','exec','schoolsafe-rosario-db','pg_dump','-U',cfg['user'],'-d',cfg['database'],'-Fc'],timeout=180)
 assert len(dump)>1000 and b'TABLE DATA' in run(['docker','exec','-i','schoolsafe-rosario-db','pg_restore','--list'],dump)
 f=ROOT/'database-before.private.dump';f.write_bytes(dump);assert f.stat().st_mode&0o777==0o600
 for n,source in [('logo',pathlib.Path('/opt/schoolsafe-media-backups')),('photo',pathlib.Path('/opt/schoolsafe-photo-backups'))]:assert any(source.glob(('school-logos-' if n=='logo' else 'user-photos-')+'*.tar'))
 result={'status':'PASS','commit':release['commit'],'image':IMAGE,'prepared_at':time.time(),'backup_sha256':hashlib.sha256(dump).hexdigest(),'backup_bytes':len(dump),'backup_mode':'0o600','media_preserved':True,'migrations':[],'restored_on_production':False}
 write('backup-proof.json',result);print(json.dumps(result))
def main():
 p=argparse.ArgumentParser(description=__doc__);p.add_argument('action',choices=['prepare','deploy','status']);a=p.parse_args().action
 release=load('release.json');access.ROOT=tools.ROOT=preserve.ROOT=ROOT
 access.BASE=tools.BASE=BASE;access.IMAGE=tools.IMAGE=IMAGE;access.DIGEST=tools.DIGEST=DIGEST
 access.BASE_COMMIT=tools.BASE_COMMIT=BASE_COMMIT;access.FILES=tools.FILES=FILES
 tools.BASE_BRANCH=BASE_BRANCH;tools.BRANCH=BRANCH;tools.OLD='schoolsafe-rosario-pre-matrix-20261024';tools.STAGED='schoolsafe-rosario-candidate-matrix-20261024'
 preserve.check_layer=portrait.check_layer_with_mounts;tools.source_hashes=access.source_hashes;tools.qualification=qualification;tools.unchanged_runtime=logo.original_unchanged
 if a=='prepare':prepare(release)
 elif a=='deploy':
  assert load('media-before.json')==media_hashes();tools.deploy(release);assert load('media-before.json')==media_hashes()
 else:
  result=tools.final_status(release);result['logo_and_portrait_preserved']=load('media-before.json')==media_hashes();assert result['logo_and_portrait_preserved'];print(json.dumps(result))
if __name__=='__main__':main()
