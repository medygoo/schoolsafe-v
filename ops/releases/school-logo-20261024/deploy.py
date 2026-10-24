#!/usr/bin/env python3
"""Install isolated persistent school-logo mount and the exact twelve-file logo image."""
import argparse,copy,hashlib,importlib.util,json,os,re,subprocess,time
from pathlib import Path
os.umask(0o077)
ROOT=Path('/opt/schoolsafe-releases/school-logo-20261024')
MEDIA=Path('/opt/schoolsafe-media/school-logos')
BIND=str(MEDIA)+':/var/www/html/assets/school-logos:rw'
BASE='sha256:14209bb46a9ca9d31f00c9f64ad1dab4301ef8894249f9c39e6373c9373d0183'
BASE_COMMIT='68e422d47b2c0179b9b6318eb71afa70ce69b5dc'
SOURCE='614826bb9e832b0e56f6677b7ba379832ddcbf9e'
TEST_SOURCE='98af6d3f10c2aa5c1838b80a148dc02ae1764e4e'
IMAGE='sha256:e24ee9e177c3ae48ac6765efd36b1db40fef7ab2ecba653c41b50a8e8fdecd6c'
DIGEST='98df5d2db024185bb8c7a8447de8ac0d6093eab386440f819a70374ddc574570'
PREFIX='app/modules/codex-ui-v1/extensions-test-runtime/'
NEW={'ProgramFunctions/SchoolSafeSchoolLogo.fnc.php','assets/js/schoolsafe-school-logo.js'}
FILES={'ProgramFunctions/SchoolSafeSchoolLogo.fnc.php','ProgramFunctions/SchoolSafePhotoDashboard.fnc.php',
'assets/css/schoolsafe-photo-dashboard.css','assets/js/schoolsafe-school-logo.js',
'modules/Grades/includes/ReportCards.fnc.php','modules/Grades/includes/Transcripts.fnc.php',
'modules/School_Setup/Configuration.php','modules/School_Setup/Schools.php',
'modules/School_Setup/includes/SchoolReceiptIdentity.fnc.php','modules/Students/PrintStudentInfo.php',
'plugins/SchoolSafe_Keycloak/Wizard.php','schoolsafe-card-bridge/RosarioCardRead.fnc.php'}
BASE_BRANCH='deployed/school-scanner-theme-20261024';BRANCH='deployed/school-logo-20261024'
WORK_BRANCH='feat/school-logo-20261024'
def module(name,path):
 spec=importlib.util.spec_from_file_location(name,path);obj=importlib.util.module_from_spec(spec);spec.loader.exec_module(obj);return obj
bundled=Path(__file__).parent/'native-access.py'
access=module('logo_native_access',bundled if bundled.exists() else Path(__file__).resolve().parents[1]/'school-native-access-20261024/deploy.py')
tools=access.tools;preserve=tools.preserve;run=preserve.run;inspect=preserve.inspect
original_create=tools.create_staged;original_unchanged=tools.unchanged_runtime
def load(name):return json.loads((ROOT/name).read_text())
def write(name,data):(ROOT/name).write_text(json.dumps(data,indent=2))
def git(commit,path):return run(['git','-C','/opt/app-src','show',commit+':'+path])
def expected_binds(pre):
 binds=pre['HostConfig']['Binds'];assert binds and len(binds)==1 and BIND not in binds
 return binds+[BIND]
def mounted_runtime(pre,current):
 virtual=copy.deepcopy(pre);binds=pre['HostConfig']['Binds']
 virtual['HostConfig']['Binds']=binds if BIND in binds and len(binds)==2 else expected_binds(pre)
 original_unchanged(virtual,current)
 matches=[m for m in current['Mounts'] if m['Destination']=='/var/www/html/assets/school-logos']
 assert len(matches)==1 and matches[0]['Source']==str(MEDIA) and matches[0]['RW'] and matches[0]['Type']=='bind'
def create_mounted(pre,release):
 assert media_state()['status']=='READY'
 virtual=copy.deepcopy(pre);virtual['HostConfig']['Binds']=expected_binds(pre)
 return original_create(virtual,release)
def media_state(empty=True):
 assert MEDIA.parent.is_dir() and MEDIA.is_dir() and not MEDIA.is_symlink()
 st=MEDIA.stat();assert st.st_uid==0 and st.st_gid==33 and st.st_mode&0o7777==0o2770
 if empty:assert not list(MEDIA.iterdir()),'Existing media found: stop, do not overwrite'
 return {'status':'READY','path':str(MEDIA),'mode':'0o2770','owner_uid':0,'group_gid':33,'files':len(list(MEDIA.iterdir()))}
def qualification(release):
 assert re.fullmatch('[a-f0-9]{40}',release['commit'])
 assert release['image']==IMAGE and release['source_commit']==SOURCE and release['source_digest']==DIGEST
 assert set(release['files'])==FILES and set(release['base_files'])==FILES and all(release['base_files'][f] is False for f in NEW)
 manifest=load('source-manifest.json');proof=load('image-prepare-proof.json')
 assert manifest['base_image']==BASE and manifest['source_commit']==SOURCE and manifest['source_digest']==DIGEST
 assert manifest['payload_hashes']==release['files'] and set(manifest['base_hashes'])==FILES-NEW and set(manifest['new_files'])==NEW
 assert proof['status']=='READY' and proof['image']==IMAGE and proof['unchanged_files']==2793 and proof['renderer_contracts']==178 and proof['recovery_contracts']==37
 assert set(proof['changed_files'])==FILES and proof['internal_network'] and proof['read_only_web'] and not proof['published_ports'] and proof['production_containers_unchanged']
 for f,digest in release['files'].items():
  assert hashlib.sha256(git(SOURCE,PREFIX+f)).hexdigest()==digest and hashlib.sha256(git(release['commit'],PREFIX+f)).hexdigest()==digest
  if f not in NEW:assert hashlib.sha256(git(BASE_COMMIT,PREFIX+f)).hexdigest()==release['base_files'][f]
 http=load('logo-http.private.json');assert http['exit']==0 and http['stderr']=='' and http['test_sha256']==hashlib.sha256(git(TEST_SOURCE,'scripts/school-access-native-http.php')).hexdigest()
 result=json.loads(http['stdout']);assert result['status']=='PASS' and result['checks']==61 and len(result['results'])==61 and result['error'] is None
 browser=load('logo-browser.json');assert browser['status']=='PASS' and browser['checks']==12
 mount=load('logo-mount-qa-proof.json');assert mount['status']=='PASS' and mount['read_write_as_www_data'] and mount['persisted_across_disposable_containers'] and mount['synthetic_file_removed'] and mount['production_untouched']
 verification=load('image-verify-proof.json');cleanup=load('image-cleanup-proof.json')
 assert verification['status']=='PASS' and verification['image']==IMAGE and verification['fixture_snapshot_unchanged'] and verification['production_containers_unchanged'] and verification['no_test_accounts_or_schemas']
 assert cleanup['status']=='REMOVED' and cleanup['only_owned_ephemeral_resources'] and cleanup['production_containers_unchanged']
 base=load('base-native-manifest.json');discovery=load('base-discovery-manifest.json');state=load('base-state-manifest.json');scan=load('base-scanner-manifest.json')
 protected=dict(base['payload_hashes'],**discovery['payload_hashes'],**state['payload_hashes'],**scan['payload_hashes']);assert len(protected)==77
 expected=dict(protected,**release['files']);assert len(expected)>=79
 for f,digest in protected.items():
  if f in FILES:continue
  assert hashlib.sha256(git(BASE_COMMIT,PREFIX+f)).hexdigest()==digest and hashlib.sha256(git(release['commit'],PREFIX+f)).hexdigest()==digest
 code='$o=[];foreach(json_decode($argv[1],true)as$f)$o[$f]=hash_file("sha256","/var/www/html/".$f);echo json_encode($o);'
 actual=json.loads(run(['docker','run','--rm','--pull=never','--network','none','--read-only','--entrypoint','php',IMAGE,'-r',code,json.dumps(sorted(expected))]));assert actual==expected
 labels=inspect(IMAGE)['Config'];assert labels['Labels']['org.opencontainers.image.revision']==SOURCE and labels['Labels']['schoolsafe.source.digest']==DIGEST
 assert not any(x.startswith('SCHOOLSAFE_CARD_HMAC_SECRET=') for x in labels.get('Env',[]))
 for local,path in [('deploy.py','ops/releases/school-logo-20261024/deploy.py'),('backup-media.sh','ops/releases/school-logo-20261024/backup-media.sh'),('native-access.py','ops/releases/school-native-access-20261024/deploy.py'),('recovery.py','ops/releases/prodeli-recovery-sms-20261022/deploy.py'),('preservation.py','ops/releases/prodeli-native-access-20261021/deploy.py')]:assert (ROOT/local).read_bytes()==git(release['commit'],path)
def prepare(release):
 assert not (ROOT/'backup-proof.json').exists()
 run(['git','-C','/opt/app-src','fetch','--no-tags','origin','refs/heads/'+WORK_BRANCH]);assert run(['git','-C','/opt/app-src','rev-parse','FETCH_HEAD']).decode().strip()==release['commit']
 qualification(release)
 assert run(['git','-C','/opt/app-src','rev-parse','HEAD']).decode().strip()==BASE_COMMIT
 assert run(['git','-C','/opt/app-src','branch','--show-current']).decode().strip()==BASE_BRANCH
 assert not run(['git','-C','/opt/app-src','status','--porcelain']).strip()
 delta=run(['git','-C','/opt/app-src','diff','--name-only',BASE_COMMIT,release['commit'],'--',PREFIX]).decode().splitlines();assert set(delta)=={PREFIX+f for f in FILES}
 assert not run(['git','-C','/opt/app-src','diff','--name-only',BASE_COMMIT,release['commit'],'--','ops/deployment/config.inc.production.php']).strip()
 pre=inspect(tools.NAME);assert pre['Image']==BASE and pre['State']['Health']['Status']=='healthy' and len(pre['Mounts'])==1
 assert access.source_hashes(tools.NAME)==release['base_files']
 for name in [tools.OLD,tools.STAGED]:assert subprocess.run(['docker','inspect',name],capture_output=True).returncode!=0
 preserve.check_layer(tools.NAME);preserve.probe();preserve.probe(True)
 write('container-before.private.json',pre);write('other-services-before.json',preserve.other_state())
 (ROOT/'config-before.sha256').write_text(tools.configuration_hash(tools.NAME))
 cli=tools.db_cli();access.schema(cli)
 cfg=load('db-cli.private.json');dump=run(['docker','exec','schoolsafe-rosario-db','pg_dump','-U',cfg['user'],'-d',cfg['database'],'-Fc'],timeout=180)
 assert len(dump)>1000 and b'TABLE DATA' in run(['docker','exec','-i','schoolsafe-rosario-db','pg_restore','--list'],dump)
 target=ROOT/'database-before.private.dump';target.write_bytes(dump);assert target.stat().st_mode&0o777==0o600
 # The media root is created only after the read-only source/schema gates. No real logo exists at preflight.
 assert not MEDIA.exists();MEDIA.parent.mkdir(mode=0o700,parents=True,exist_ok=True);MEDIA.mkdir(mode=0o2770);os.chown(MEDIA,0,33);os.chmod(MEDIA,0o2770)
 media=media_state();write('media-proof.json',media)
 cron=Path('/etc/cron.d/schoolsafe-school-logo-media')
 assert not cron.exists(),'Backup job exists: inspect it before proceeding'
 cron.write_text('23 2 * * * root /bin/sh /opt/schoolsafe-releases/school-logo-20261024/backup-media.sh\n');os.chown(cron,0,0);os.chmod(cron,0o644)
 run(['/bin/sh',str(ROOT/'backup-media.sh')])
 archives=sorted(Path('/opt/schoolsafe-media-backups').glob('school-logos-*.tar'))
 assert len(archives)==1 and archives[0].stat().st_mode&0o777==0o600
 assert run(['tar','-tf',str(archives[0])]).decode().splitlines()==['school-logos/']
 write('logo-backup-proof.json',{'status':'PASS','archive':str(archives[0]),'sha256':hashlib.sha256(archives[0].read_bytes()).hexdigest(),'daily_cron':str(cron),'private_archive':True})
 result={'status':'PASS','commit':release['commit'],'image':IMAGE,'prepared_at':time.time(),'backup_bytes':len(dump),'backup_sha256':hashlib.sha256(dump).hexdigest(),'backup_mode':'0o600','archive_list_valid':True,'migrations':[],'restored_on_production':False,'media_new_empty':True}
 write('backup-proof.json',result);print(json.dumps(result))
def main():
 parser=argparse.ArgumentParser(description=__doc__);parser.add_argument('action',choices=['prepare','deploy','status']);action=parser.parse_args().action
 release=load('release.json');access.ROOT=tools.ROOT=preserve.ROOT=ROOT
 access.BASE=tools.BASE=BASE;access.IMAGE=tools.IMAGE=IMAGE;access.DIGEST=tools.DIGEST=DIGEST
 access.BASE_COMMIT=tools.BASE_COMMIT=BASE_COMMIT;access.FILES=tools.FILES=FILES
 tools.BASE_BRANCH=BASE_BRANCH;tools.BRANCH=BRANCH;tools.OLD='schoolsafe-rosario-pre-logo-20261024';tools.STAGED='schoolsafe-rosario-candidate-logo-20261024'
 access.qualification=tools.qualification=qualification;tools.source_hashes=access.source_hashes
 tools.create_staged=create_mounted;tools.unchanged_runtime=mounted_runtime
 if action=='prepare':prepare(release)
 elif action=='deploy':
  assert load('media-proof.json')==media_state()
  archive=load('logo-backup-proof.json');assert archive['status']=='PASS' and hashlib.sha256(Path(archive['archive']).read_bytes()).hexdigest()==archive['sha256']
  assert Path(archive['daily_cron']).read_text()=='23 2 * * * root /bin/sh /opt/schoolsafe-releases/school-logo-20261024/backup-media.sh\n'
  tools.deploy(release)
 else:
  result=tools.final_status(release);result['logo_media']=media_state(False);print(json.dumps(result))
if __name__=='__main__':main()
