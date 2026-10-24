#!/usr/bin/env python3
"""Four-file native profile-owner navigation patch. No grant, migration or worker activation."""
import argparse,hashlib,importlib.util,json,os,re,subprocess,time
from pathlib import Path
os.umask(0o077)
ROOT=Path('/opt/schoolsafe-releases/school-scan-profile-owner-20261024')
BASE='sha256:d4359b7b3b174f70ea02aef6202c98e41381814960dedcf2df576994dac10b9d'
BASE_COMMIT='ec59446f4eeb5738256a72ea506cbfee652443f1'
SOURCE='127d6cdfcebf6cd284ea5a0a663f20664a9baabf'
IMAGE='sha256:7bedfc1e37289136d9a54ac87f82603784053a47bd45c90c1b1d3c159dc7c628'
DIGEST='ebebf34ea4ecea50b51457c3d083b349d477413ee755a4b8bb1f9d036ea5e14d'
PREFIX='app/modules/codex-ui-v1/extensions-test-runtime/'
FILES={'ProgramFunctions/SchoolSafeScanDelegation.fnc.php','scanner-relay.php','family-management.php','schoolsafe-card-bridge/family-management.js'}
def module(name,path):
 s=importlib.util.spec_from_file_location(name,path);m=importlib.util.module_from_spec(s);s.loader.exec_module(m);return m
bundled=Path(__file__).parent/'recovery.py'
tools=module('profile_owner_preservation',bundled if bundled.exists() else Path(__file__).resolve().parents[1]/'prodeli-recovery-sms-20261022/deploy.py')
preserve=tools.preserve;tools.ROOT=preserve.ROOT=ROOT
run=preserve.run;inspect=preserve.inspect

def load(name):return json.loads((ROOT/name).read_text())
def write(name,value):(ROOT/name).write_text(json.dumps(value,indent=2))
def source_hashes(name):
 code='$o=[];foreach(json_decode($argv[1],true)as$p)$o[$p]=is_file("/var/www/html/".$p)?hash_file("sha256","/var/www/html/".$p):false;echo json_encode($o);'
 return json.loads(run(['docker','exec',name,'php','-r',code,json.dumps(sorted(FILES))]))

def qualification(release):
 assert re.fullmatch('[a-f0-9]{40}',release['commit'])
 assert release['image']==IMAGE and release['source_commit']==SOURCE and release['source_digest']==DIGEST
 assert set(release['files'])==FILES and set(release['base_files'])==FILES
 manifest=load('source-manifest.json');proof=load('image-prepare-proof.json')
 assert manifest['source_commit']==SOURCE and manifest['source_digest']==DIGEST and len(manifest['payload_hashes'])==66
 assert proof['status']=='READY' and proof['image']==IMAGE and proof['source_digest']==DIGEST
 assert proof['renderer_contracts']==372 and proof['recovery_contracts']==37 and proof['unchanged_files']==2734
 assert proof['internal_network'] and proof['read_only_web'] and not proof['published_ports']
 assert set(proof['changed_files'])==set(manifest['payload_hashes'])
 # All cumulative Scan sources outside the four-file delta remain identical.
 for f,digest in manifest['payload_hashes'].items():
  assert hashlib.sha256(run(['git','-C','/opt/app-src','show',SOURCE+':'+PREFIX+f])).hexdigest()==digest
  if f not in FILES:assert hashlib.sha256(run(['git','-C','/opt/app-src','show',BASE_COMMIT+':'+PREFIX+f])).hexdigest()==digest
 for f,digest in release['files'].items():
  assert manifest['payload_hashes'][f]==digest
  assert hashlib.sha256(run(['git','-C','/opt/app-src','show',release['commit']+':'+PREFIX+f])).hexdigest()==digest
 for local,path in [('deploy.py','ops/releases/school-scan-profile-owner-20261024/deploy.py'),('recovery.py','ops/releases/prodeli-recovery-sms-20261022/deploy.py'),('preservation.py','ops/releases/prodeli-native-access-20261021/deploy.py')]:
  assert (ROOT/local).read_bytes()==run(['git','-C','/opt/app-src','show',release['commit']+':'+path])
 integration=load('exact-integration-result.json')
 assert integration['status']=='PASS' and integration['image']==IMAGE and integration['source_commit']==SOURCE
 assert {s['suite'] for s in integration['suites']}=={'native','recovery','concurrency'} and all(s['exit']==0 for s in integration['suites'])
 assert load('browser-cleanup-proof.json')['owned_browser_absent'] is True
 envelope=load('card-authenticated-http-delegation-r1.private.json')
 assert envelope['exit']==0 and envelope['stderr']==''
 http=json.loads(envelope['stdout']);assert http['status']=='PASS' and http['checks']==58 and len(http['results'])==58 and http['error'] is None
 verification=load('image-verify-proof.json');cleanup=load('image-cleanup-proof.json')
 assert verification['status']=='PASS' and verification['image']==IMAGE and verification['fixture_snapshot_unchanged'] and verification['production_containers_unchanged']
 assert cleanup['status']=='REMOVED' and cleanup['only_owned_ephemeral_resources'] and cleanup['production_containers_unchanged']
 image=inspect(IMAGE)
 assert image['Config']['Labels']['org.opencontainers.image.revision']==SOURCE and image['Config']['Labels']['schoolsafe.source.digest']==DIGEST
 assert not any(e.startswith('SCHOOLSAFE_CARD_HMAC_SECRET=') for e in image['Config'].get('Env',[]))

def prepare(release):
 assert not (ROOT/'backup-proof.json').exists()
 run(['git','-C','/opt/app-src','fetch','--no-tags','origin','refs/heads/feat/presence-scan-qr-20261023'])
 assert run(['git','-C','/opt/app-src','rev-parse','FETCH_HEAD']).decode().strip()==release['commit']
 qualification(release)
 assert run(['git','-C','/opt/app-src','rev-parse','HEAD']).decode().strip()==BASE_COMMIT
 assert run(['git','-C','/opt/app-src','branch','--show-current']).decode().strip()==tools.BASE_BRANCH
 assert not run(['git','-C','/opt/app-src','status','--porcelain']).strip()
 delta=run(['git','-C','/opt/app-src','diff','--name-only',BASE_COMMIT,release['commit'],'--',PREFIX]).decode().splitlines()
 assert set(delta)=={PREFIX+f for f in FILES}
 assert not run(['git','-C','/opt/app-src','diff','--name-only',BASE_COMMIT,release['commit'],'--','ops/deployment/config.inc.production.php']).strip()
 pre=inspect(tools.NAME);assert pre['Image']==BASE and pre['State']['Health']['Status']=='healthy'
 assert source_hashes(tools.NAME)==release['base_files']
 assert len(pre['Mounts'])==1 and pre['Mounts'][0]['Destination']=='/var/www/html/config.inc.php' and not pre['Mounts'][0]['RW']
 keys=[e.split('=',1)[1] for e in pre['Config']['Env'] if e.startswith('SCHOOLSAFE_CARD_HMAC_SECRET=')]
 assert len(keys)==1 and re.fullmatch('[a-f0-9]{64}',keys[0]) and keys[0]!='0'*64
 # Existing preservation engine copies the entire environment unchanged, including
 # the durable signing key. Never print, rotate, generate or read its escrow file.
 for name in [tools.OLD,tools.STAGED]:assert subprocess.run(['docker','inspect',name],capture_output=True).returncode!=0
 preserve.check_layer(tools.NAME);preserve.probe();preserve.probe(True)
 write('container-before.private.json',pre);write('other-services-before.json',preserve.other_state())
 (ROOT/'config-before.sha256').write_text(tools.configuration_hash(tools.NAME))
 cli=tools.db_cli()
 metadata=run(cli,b"BEGIN READ ONLY; SELECT count(*) FROM information_schema.columns WHERE table_schema='public' AND ((table_name='user_profiles' AND column_name='school_id') OR (table_name='profile_exceptions' AND column_name IN ('profile_id','modname','can_use','can_edit'))); COMMIT;").decode().strip()
 assert metadata=='5','Native school-profile schema required; no migration is implicit'
 write('schema-proof.json',{'status':'PASS','read_only':True,'migrations':[],'end_user_permissions_modified':False})
 cfg=load('db-cli.private.json');dump=run(['docker','exec','schoolsafe-rosario-db','pg_dump','-U',cfg['user'],'-d',cfg['database'],'-Fc'],timeout=180)
 assert len(dump)>1000 and b'TABLE DATA' in run(['docker','exec','-i','schoolsafe-rosario-db','pg_restore','--list'],dump)
 target=ROOT/'database-before.private.dump';target.write_bytes(dump);assert target.stat().st_mode&0o777==0o600
 result={'status':'PASS','commit':release['commit'],'image':IMAGE,'prepared_at':time.time(),'backup_bytes':len(dump),'backup_sha256':hashlib.sha256(dump).hexdigest(),'backup_mode':'0o600','archive_list_valid':True,'migrations':[],'restored_on_production':False}
 write('backup-proof.json',result);print(json.dumps(result))

def main():
 p=argparse.ArgumentParser(description=__doc__);p.add_argument('action',choices=['prepare','deploy','status']);a=p.parse_args();release=load('release.json')
 tools.BASE=BASE;tools.IMAGE=IMAGE;tools.DIGEST=DIGEST;tools.BASE_COMMIT=BASE_COMMIT;tools.BASE_BRANCH='deployed/school-presence-scan-20261024';tools.BRANCH='deployed/school-scan-profile-owner-20261024'
 tools.OLD='schoolsafe-rosario-pre-school-scan-profile-owner-20261024';tools.STAGED='schoolsafe-rosario-candidate-school-scan-profile-owner-20261024';tools.FILES=FILES;tools.qualification=qualification;tools.source_hashes=source_hashes
 if a.action=='prepare':prepare(release)
 elif a.action=='deploy':tools.deploy(release)
 else:print(json.dumps(tools.final_status(release)))
if __name__=='__main__':main()
