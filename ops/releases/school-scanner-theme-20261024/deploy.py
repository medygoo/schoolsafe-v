#!/usr/bin/env python3
"""Only themed Scan reader UI, on the independently verified state-safe production base."""
import argparse,hashlib,importlib.util,json,os,re
from pathlib import Path
os.umask(0o077)
ROOT=Path('/opt/schoolsafe-releases/school-scanner-theme-20261024')
BASE='sha256:1705fc9453c0788534273c5ba6a96bd799f15574ade8e02c7ff55351a8d72a3c'
BASE_COMMIT='335643ef61cc67ea56d43ba51181e72cd450d1f1'
SOURCE='abf4145404f1dc45696c9519f7065ea2cf545661'
TEST_SOURCE='1a3adc40a0348824cc5f6decda51c3d6dd69f016'
IMAGE='sha256:14209bb46a9ca9d31f00c9f64ad1dab4301ef8894249f9c39e6373c9373d0183'
DIGEST='8517438552874007cc2b408a3b3638ea7ed6a4ecb46b65fa0ae452351e006426'
PREFIX='app/modules/codex-ui-v1/extensions-test-runtime/'
NEW='schoolsafe-card-bridge/scanner-theme.js'
FILES={'scanner-native.php','schoolsafe-card-bridge/scanner-native.css','schoolsafe-card-bridge/scanner-native.js',NEW}
BASE_BRANCH='deployed/prodeli-state-safety-20261024'
BRANCH='deployed/school-scanner-theme-20261024'
def module(name,path):
 spec=importlib.util.spec_from_file_location(name,path);obj=importlib.util.module_from_spec(spec);spec.loader.exec_module(obj);return obj
bundled=Path(__file__).parent/'native-access.py'
access=module('scan_native_access',bundled if bundled.exists() else Path(__file__).resolve().parents[1]/'school-native-access-20261024/deploy.py')
tools=access.tools;preserve=tools.preserve;run=preserve.run;inspect=preserve.inspect
def load(name):return json.loads((ROOT/name).read_text())
def git(commit,path):return run(['git','-C','/opt/app-src','show',commit+':'+path])
def qualification(release):
 assert re.fullmatch('[a-f0-9]{40}',release['commit'])
 assert release['image']==IMAGE and release['source_commit']==SOURCE and release['source_digest']==DIGEST
 assert set(release['files'])==FILES and set(release['base_files'])==FILES and release['base_files'][NEW] is False
 manifest=load('source-manifest.json');proof=load('image-prepare-proof.json')
 assert manifest['source_commit']==SOURCE and manifest['source_digest']==DIGEST and manifest['base_image']==BASE
 assert set(manifest['payload_hashes'])==FILES and set(manifest['base_hashes'])==FILES-{NEW}
 assert manifest['new_files']==[NEW] and manifest['payload_hashes']==release['files']
 assert {**manifest['base_hashes'],NEW:False}==release['base_files']
 assert proof['status']=='READY' and proof['image']==IMAGE and proof['source_digest']==DIGEST
 assert proof['renderer_contracts']==178 and proof['recovery_contracts']==37 and proof['unchanged_files']==2799
 assert set(proof['changed_files'])==FILES and proof['internal_network'] and proof['read_only_web'] and not proof['published_ports'] and proof['production_containers_unchanged']
 for f,digest in release['files'].items():
  assert hashlib.sha256(git(SOURCE,PREFIX+f)).hexdigest()==digest
  assert hashlib.sha256(git(release['commit'],PREFIX+f)).hexdigest()==digest
  if f!=NEW:assert hashlib.sha256(git(BASE_COMMIT,PREFIX+f)).hexdigest()==release['base_files'][f]
 assert not run(['git','-C','/opt/app-src','ls-tree','--name-only',BASE_COMMIT,'--',PREFIX+NEW]).strip()
 http=load('scanner-http.private.json');assert http['exit']==0 and http['stderr']==''
 result=json.loads(http['stdout']);assert result['status']=='PASS' and result['checks']==58 and len(result['results'])==58 and result['error'] is None
 assert http['test_sha256']==hashlib.sha256(git(TEST_SOURCE,'scripts/school-access-native-http.php')).hexdigest()
 browser=load('scanner-browser.json');assert browser['status']=='PASS' and browser['checks']==14 and len(browser['scenarios'])==14
 camera=load('camera-browser.json');assert len(camera['results'])==4 and all(x['status']=='PASS' for x in camera['results'])
 verification=load('image-verify-proof.json');cleanup=load('image-cleanup-proof.json')
 assert verification['status']=='PASS' and verification['image']==IMAGE and verification['fixture_snapshot_unchanged'] and verification['production_containers_unchanged'] and verification['no_test_accounts_or_schemas']
 assert cleanup['status']=='REMOVED' and cleanup['only_owned_ephemeral_resources'] and cleanup['production_containers_unchanged']
 base=load('base-native-manifest.json');discovery=load('base-discovery-manifest.json');state=load('base-state-manifest.json')
 assert len(base['payload_hashes'])==71 and len(discovery['payload_hashes'])==2 and len(state['payload_hashes'])==2
 protected=dict(base['payload_hashes'],**discovery['payload_hashes'],**state['payload_hashes'])
 assert len(protected)==75 and NEW not in protected and 'schoolsafe-card-bridge/scanner-native.css' not in protected
 expected=dict(protected,**release['files']);assert len(expected)==77
 for f,digest in protected.items():
  if f in FILES:continue
  assert hashlib.sha256(git(BASE_COMMIT,PREFIX+f)).hexdigest()==digest and hashlib.sha256(git(release['commit'],PREFIX+f)).hexdigest()==digest
 code='$o=[];foreach(json_decode($argv[1],true)as$f)$o[$f]=hash_file("sha256","/var/www/html/".$f);echo json_encode($o);'
 actual=json.loads(run(['docker','run','--rm','--pull=never','--network','none','--read-only','--entrypoint','php',IMAGE,'-r',code,json.dumps(sorted(expected))]))
 assert actual==expected
 labels=inspect(IMAGE)['Config'];assert labels['Labels']['org.opencontainers.image.revision']==SOURCE and labels['Labels']['schoolsafe.source.digest']==DIGEST
 assert not any(e.startswith('SCHOOLSAFE_CARD_HMAC_SECRET=') for e in labels.get('Env',[]))
 for local,path in [('deploy.py','ops/releases/school-scanner-theme-20261024/deploy.py'),('native-access.py','ops/releases/school-native-access-20261024/deploy.py'),('recovery.py','ops/releases/prodeli-recovery-sms-20261022/deploy.py'),('preservation.py','ops/releases/prodeli-native-access-20261021/deploy.py')]:
  assert (ROOT/local).read_bytes()==git(release['commit'],path)
def main():
 parser=argparse.ArgumentParser(description=__doc__);parser.add_argument('action',choices=['prepare','deploy','status']);action=parser.parse_args().action
 release=load('release.json')
 access.ROOT=tools.ROOT=preserve.ROOT=ROOT
 access.BASE=tools.BASE=BASE;access.IMAGE=tools.IMAGE=IMAGE;access.DIGEST=tools.DIGEST=DIGEST
 access.BASE_COMMIT=tools.BASE_COMMIT=BASE_COMMIT;access.FILES=tools.FILES=FILES
 tools.BASE_BRANCH=BASE_BRANCH;tools.BRANCH=BRANCH
 tools.OLD='schoolsafe-rosario-pre-scanner-theme-20261024';tools.STAGED='schoolsafe-rosario-candidate-scanner-theme-20261024'
 access.qualification=tools.qualification=qualification;tools.source_hashes=access.source_hashes
 if action=='prepare':access.prepare(release)
 elif action=='deploy':tools.deploy(release)
 else:print(json.dumps(tools.final_status(release)))
if __name__=='__main__':main()
