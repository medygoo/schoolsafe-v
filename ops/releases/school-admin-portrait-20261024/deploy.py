#!/usr/bin/env python3
"""Separate portrait release: preserve logo bind and add durable native UserPhotos."""
import copy,hashlib,importlib.util,json,os,pathlib,re,subprocess,time
os.umask(0o077)
ROOT=pathlib.Path('/opt/schoolsafe-releases/school-admin-portrait-20261024')
def load(name):return json.loads((ROOT/name).read_text())
def write(name,value):(ROOT/name).write_text(json.dumps(value,indent=2))
def module(name,path):
 spec=importlib.util.spec_from_file_location(name,path);obj=importlib.util.module_from_spec(spec);spec.loader.exec_module(obj);return obj
logo=module('portrait_logo_release',ROOT/'logo-release.py');access=logo.access;tools=logo.tools;preserve=logo.preserve
run=preserve.run;inspect=preserve.inspect
MEDIA=pathlib.Path('/opt/schoolsafe-media/user-photos');BIND=str(MEDIA)+':/var/www/html/assets/UserPhotos:rw'
BASE='sha256:e24ee9e177c3ae48ac6765efd36b1db40fef7ab2ecba653c41b50a8e8fdecd6c'
BASE_COMMIT='c7c064ad62adc3e2ef9980081fc1a02d07e72285'
SOURCE='9b33a0a2ee79d5b8c16520d6c3ac50c12cdaf0eb'
IMAGE='sha256:ed97d7d18745e715bf8075ac066967e11444d79d657b9d08e3bbb6f632c549a6'
DIGEST='1ee472bfdfcf2bdb8b5a88c86c5e96b07bd31c3eb94973237609d9674d9f8fa8'
PREFIX='app/modules/codex-ui-v1/extensions-test-runtime/'
FILE='plugins/SchoolSafe_Keycloak/Wizard.php';FILES={FILE}
WORK_BRANCH='feat/school-admin-portrait-20261024';BRANCH='deployed/school-admin-portrait-20261024';BASE_BRANCH='deployed/school-logo-20261024'
LOGO_BIND='/opt/schoolsafe-media/school-logos:/var/www/html/assets/school-logos:rw'
def git(commit,path):return run(['git','-C','/opt/app-src','show',commit+':'+path])
def check_layer_with_mounts(name):
 """Keep the old writable-layer gate, admitting only verified bind mount points."""
 current=inspect(name)
 mounts={m['Destination']:m for m in current['Mounts']}
 logo=mounts.get('/var/www/html/assets/school-logos')
 assert logo and logo['Type']=='bind' and logo['Source']=='/opt/schoolsafe-media/school-logos' and logo['RW']
 photos=mounts.get('/var/www/html/assets/UserPhotos')
 if photos:assert photos['Type']=='bind' and photos['Source']==str(MEDIA) and photos['RW']
 allowed={'/run','/run/apache2','/run/apache2/apache2.pid','/tmp','/tmp/.config','/tmp/.config/chromium','/var','/var/www','/var/www/html','/var/www/html/config.inc.php','/var/www/html/assets','/var/www/html/assets/school-logos'}
 if photos:allowed.add('/var/www/html/assets/UserPhotos')
 for line in run(['docker','diff',name]).decode().splitlines():
  path=line[2:]
  assert path in allowed or path.startswith(('/tmp/sess_','/tmp/.config/chromium/')),'Unpreserved writable data: stop and inspect privately'
def expected_binds(pre):
 binds=pre['HostConfig']['Binds'];assert len(binds)==2 and LOGO_BIND in binds and BIND not in binds
 return binds+[BIND]
def media_state(empty=True):
 assert MEDIA.is_dir() and not MEDIA.is_symlink()
 st=MEDIA.stat();assert st.st_uid==0 and st.st_gid==33 and st.st_mode&0o7777==0o2770
 entries=sorted(x.name for x in MEDIA.iterdir());assert 'index.html' in entries and (MEDIA/'index.html').read_bytes()==b''
 if empty:assert entries==['index.html'],'UserPhotos existed unexpectedly; stop and preserve'
 return {'status':'READY','path':str(MEDIA),'owner_uid':0,'group_gid':33,'mode':'0o2770','entries':len(entries)}
def mounted_runtime(pre,current):
 virtual=copy.deepcopy(pre);binds=pre['HostConfig']['Binds']
 virtual['HostConfig']['Binds']=binds if BIND in binds and len(binds)==3 else expected_binds(pre)
 logo.original_unchanged(virtual,current)
 matches=[m for m in current['Mounts'] if m['Destination']=='/var/www/html/assets/UserPhotos']
 assert len(matches)==1 and matches[0]['Source']==str(MEDIA) and matches[0]['RW'] and matches[0]['Type']=='bind'
def qualification(release):
 assert re.fullmatch('[a-f0-9]{40}',release['commit']) and release['image']==IMAGE and release['source_commit']==SOURCE and release['source_digest']==DIGEST
 assert set(release['files'])==FILES and set(release['base_files'])==FILES
 manifest=load('source-manifest.json');proof=load('image-prepare-proof.json')
 assert manifest['base_image']==BASE and manifest['source_commit']==SOURCE and manifest['source_digest']==DIGEST and not manifest['new_files']
 assert manifest['payload_hashes']==release['files'] and manifest['base_hashes']==release['base_files']
 assert proof['status']=='READY' and proof['image']==IMAGE and proof['changed_files']==[FILE] and proof['unchanged_files']==2804 and proof['renderer_contracts']==178 and proof['recovery_contracts']==37
 assert proof['internal_network'] and proof['read_only_web'] and not proof['published_ports'] and proof['production_containers_unchanged']
 assert hashlib.sha256(git(SOURCE,PREFIX+FILE)).hexdigest()==release['files'][FILE]==hashlib.sha256(git(release['commit'],PREFIX+FILE)).hexdigest()
 assert hashlib.sha256(git(BASE_COMMIT,PREFIX+FILE)).hexdigest()==release['base_files'][FILE]
 protected={}
 for name in ['base-native-manifest.json','base-discovery-manifest.json','base-state-manifest.json','base-scanner-manifest.json','base-logo-manifest.json']:
  protected.update(load(name)['payload_hashes'])
 assert len(protected)==88 and protected[FILE]==release['base_files'][FILE]
 expected=dict(protected,**release['files'])
 for f,digest in protected.items():
  if f==FILE:continue
  assert hashlib.sha256(git(BASE_COMMIT,PREFIX+f)).hexdigest()==digest and hashlib.sha256(git(release['commit'],PREFIX+f)).hexdigest()==digest
 code='$o=[];foreach(json_decode($argv[1],true)as$f)$o[$f]=hash_file("sha256","/var/www/html/".$f);echo json_encode($o);'
 actual=json.loads(run(['docker','run','--rm','--pull=never','--network','none','--read-only','--entrypoint','php',IMAGE,'-r',code,json.dumps(sorted(expected))]));assert actual==expected
 label=inspect(IMAGE)['Config']['Labels'];assert label['org.opencontainers.image.revision']==SOURCE and label['schoolsafe.source.digest']==DIGEST
 http=load('portrait-http.private.json');out=json.loads(http['stdout']);assert http['exit']==0 and http['stderr']=='' and out['status']=='PASS' and out['checks']==61 and len(out['results'])==61 and out['error'] is None
 portrait=load('portrait-native-qa.json');assert portrait['status']=='PASS' and portrait['ok'] and portrait['errors']==0 and portrait['image_mime']=='image/jpeg'
 verify=load('image-verify-proof.json');cleanup=load('image-cleanup-proof.json');assert verify['status']=='PASS' and verify['image']==IMAGE and verify['fixture_snapshot_unchanged'] and verify['production_containers_unchanged'] and verify['no_test_accounts_or_schemas']
 assert cleanup['status']=='REMOVED' and cleanup['only_owned_ephemeral_resources'] and cleanup['production_containers_unchanged']
 for local,path in [('deploy.py','ops/releases/school-admin-portrait-20261024/deploy.py'),('backup-user-photos.sh','ops/releases/school-admin-portrait-20261024/backup-user-photos.sh'),('logo-release.py','ops/releases/school-logo-20261024/deploy.py'),('native-access.py','ops/releases/school-native-access-20261024/deploy.py'),('recovery.py','ops/releases/prodeli-recovery-sms-20261022/deploy.py'),('preservation.py','ops/releases/prodeli-native-access-20261021/deploy.py')]:assert (ROOT/local).read_bytes()==git(release['commit'],path)
def prepare(release):
 assert not (ROOT/'backup-proof.json').exists()
 run(['git','-C','/opt/app-src','fetch','--no-tags','origin','refs/heads/'+WORK_BRANCH]);assert run(['git','-C','/opt/app-src','rev-parse','FETCH_HEAD']).decode().strip()==release['commit']
 qualification(release)
 assert run(['git','-C','/opt/app-src','rev-parse','HEAD']).decode().strip()==BASE_COMMIT
 assert run(['git','-C','/opt/app-src','branch','--show-current']).decode().strip()==BASE_BRANCH
 assert not run(['git','-C','/opt/app-src','status','--porcelain']).strip()
 delta=run(['git','-C','/opt/app-src','diff','--name-only',BASE_COMMIT,release['commit'],'--',PREFIX]).decode().splitlines();assert delta==[PREFIX+FILE]
 pre=inspect(tools.NAME);assert pre['Image']==BASE and pre['State']['Health']['Status']=='healthy'
 assert len(pre['Mounts'])==2 and pre['HostConfig']['Binds']==[pre['HostConfig']['Binds'][0],LOGO_BIND]
 assert access.source_hashes(tools.NAME)==release['base_files']
 for n in [tools.OLD,tools.STAGED]:assert subprocess.run(['docker','inspect',n],capture_output=True).returncode!=0
 preserve.check_layer(tools.NAME);preserve.probe();preserve.probe(True)
 logo_file=pathlib.Path('/opt/schoolsafe-media/school-logos/school_logo_3.jpg');assert logo_file.is_file()
 logo_sha=hashlib.sha256(logo_file.read_bytes()).hexdigest()
 write('container-before.private.json',pre);write('other-services-before.json',preserve.other_state())
 (ROOT/'config-before.sha256').write_text(tools.configuration_hash(tools.NAME));cli=tools.db_cli();access.schema(cli)
 config=load('db-cli.private.json');dump=run(['docker','exec','schoolsafe-rosario-db','pg_dump','-U',config['user'],'-d',config['database'],'-Fc'],timeout=180)
 assert len(dump)>1000 and b'TABLE DATA' in run(['docker','exec','-i','schoolsafe-rosario-db','pg_restore','--list'],dump)
 target=ROOT/'database-before.private.dump';target.write_bytes(dump);assert target.stat().st_mode&0o777==0o600
 assert not MEDIA.exists();MEDIA.mkdir(mode=0o2770);os.chown(MEDIA,0,33);os.chmod(MEDIA,0o2770);(MEDIA/'index.html').write_bytes(b'');os.chmod(MEDIA/'index.html',0o644)
 media=media_state();write('media-proof.json',media)
 cron=pathlib.Path('/etc/cron.d/schoolsafe-user-photos');assert not cron.exists()
 cron.write_text('37 2 * * * root /bin/sh /opt/schoolsafe-releases/school-admin-portrait-20261024/backup-user-photos.sh\n');os.chown(cron,0,0);os.chmod(cron,0o644)
 run(['/bin/sh',str(ROOT/'backup-user-photos.sh')]);archives=sorted(pathlib.Path('/opt/schoolsafe-photo-backups').glob('user-photos-*.tar'))
 assert len(archives)==1 and archives[0].stat().st_mode&0o777==0o600
 assert run(['tar','-tf',str(archives[0])]).decode().splitlines()==['user-photos/','user-photos/index.html']
 write('photo-backup-proof.json',{'status':'PASS','archive':str(archives[0]),'sha256':hashlib.sha256(archives[0].read_bytes()).hexdigest(),'daily_cron':str(cron)})
 assert hashlib.sha256(logo_file.read_bytes()).hexdigest()==logo_sha
 write('logo-sha-before.json',{'sha256':logo_sha})
 result={'status':'PASS','commit':release['commit'],'image':IMAGE,'prepared_at':time.time(),'backup_sha256':hashlib.sha256(dump).hexdigest(),'backup_bytes':len(dump),'backup_mode':'0o600','migrations':[],'restored_on_production':False,'photo_media_new':True,'existing_logo_preserved':True}
 write('backup-proof.json',result);print(json.dumps(result))
def main():
 import argparse
 p=argparse.ArgumentParser(description=__doc__);p.add_argument('action',choices=['prepare','deploy','status']);a=p.parse_args().action
 release=load('release.json')
 logo.ROOT=access.ROOT=tools.ROOT=preserve.ROOT=ROOT
 preserve.check_layer=check_layer_with_mounts
 logo.MEDIA=MEDIA;logo.BIND=BIND;logo.BASE=access.BASE=tools.BASE=BASE
 logo.BASE_COMMIT=access.BASE_COMMIT=tools.BASE_COMMIT=BASE_COMMIT
 logo.SOURCE=SOURCE;logo.IMAGE=access.IMAGE=tools.IMAGE=IMAGE
 logo.DIGEST=access.DIGEST=tools.DIGEST=DIGEST
 logo.FILES=access.FILES=tools.FILES=FILES
 logo.WORK_BRANCH=WORK_BRANCH;tools.BASE_BRANCH=BASE_BRANCH;tools.BRANCH=BRANCH
 tools.OLD='schoolsafe-rosario-pre-portrait-20261024';tools.STAGED='schoolsafe-rosario-candidate-portrait-20261024'
 logo.expected_binds=expected_binds;logo.media_state=media_state;logo.mounted_runtime=mounted_runtime
 logo.qualification=access.qualification=tools.qualification=qualification
 tools.source_hashes=access.source_hashes;tools.create_staged=logo.create_mounted;tools.unchanged_runtime=mounted_runtime
 if a=='prepare':prepare(release)
 elif a=='deploy':
  assert load('media-proof.json')==media_state()
  archive=load('photo-backup-proof.json');assert hashlib.sha256(pathlib.Path(archive['archive']).read_bytes()).hexdigest()==archive['sha256']
  tools.deploy(release)
  logo_file=pathlib.Path('/opt/schoolsafe-media/school-logos/school_logo_3.jpg')
  assert hashlib.sha256(logo_file.read_bytes()).hexdigest()==load('logo-sha-before.json')['sha256']
 else:
  status=tools.final_status(release);status['photo_media']=media_state(False)
  status['logo_preserved']=hashlib.sha256(pathlib.Path('/opt/schoolsafe-media/school-logos/school_logo_3.jpg').read_bytes()).hexdigest()==load('logo-sha-before.json')['sha256']
  assert status['logo_preserved'];print(json.dumps(status))
if __name__=='__main__':main()
