#!/usr/bin/env python3
"""Ship only the mobile search-above-logo correction on the qualified immutable image."""
import argparse, hashlib, importlib.util, json, pathlib, re, subprocess
ROOT=pathlib.Path('/opt/schoolsafe-releases/school-logo-search-below-20261025')
OLD_ROOT=pathlib.Path('/opt/schoolsafe-releases/school-logo-admin-photo-20261024')
spec=importlib.util.spec_from_file_location('previous_logo_release',OLD_ROOT/'deploy.py')
old=importlib.util.module_from_spec(spec);spec.loader.exec_module(old)
tools=old.tools;access=old.access;preserve=old.preserve;run=old.run;inspect=old.inspect
BASE='sha256:01802082ffba8cc9600862accfe39b68fd8df8795b2705fd2ddd6b2bee527305'
BASE_COMMIT='2894d6407ad83001c05bd5d48b4f0e60c860fecb'
SOURCE='941eade9a1fe3efaac87414d55b49c7064cc8a8c'
IMAGE='sha256:7ceefc00ceaf903b7d71f1fcf3d5104f4891dda845e2184b034f460c53b24694'
DIGEST='68eaaa36c51ea9c350e570561118b2cdffeebe14b9373469cff8dba6dc61eda1'
FILES={'ProgramFunctions/SchoolSafePhotoDashboard.fnc.php','assets/css/schoolsafe-photo-dashboard.css'}
PREFIX='app/modules/codex-ui-v1/extensions-test-runtime/'
BRANCH='deployed/school-logo-search-below-20261025'

def load(name):return json.loads((ROOT/name).read_text())
def git(commit,path):return run(['git','-C','/opt/app-src','show',commit+':'+path])
def qualification(release):
 assert re.fullmatch('[0-9a-f]{40}',release['commit']) and release['image']==IMAGE and release['source_commit']==SOURCE and release['source_digest']==DIGEST
 manifest=load('source-manifest.json');proof=load('image-prepare-proof.json');verified=load('image-verify-proof.json');clean=load('image-cleanup-proof.json')
 assert manifest['base_image']==BASE and manifest['base_commit']==BASE_COMMIT and manifest['source_commit']==SOURCE and manifest['source_digest']==DIGEST and not manifest['new_files']
 assert set(manifest['payload_hashes'])==FILES and release['files']==manifest['payload_hashes'] and release['base_files']==manifest['base_hashes']
 assert proof['status']=='READY' and proof['image']==IMAGE and set(proof['changed_files'])==FILES and proof['unchanged_files']==2805 and proof['renderer_contracts']==178 and proof['recovery_contracts']==37
 assert proof['internal_network'] and proof['read_only_web'] and not proof['published_ports'] and proof['production_containers_unchanged']
 assert verified['status']=='PASS' and verified['fixture_snapshot_unchanged'] and verified['production_containers_unchanged'] and verified['no_test_accounts_or_schemas']
 assert clean['status']=='REMOVED' and clean['only_owned_ephemeral_resources'] and clean['production_containers_unchanged']
 checks=load('http-proof.private.json');result=json.loads(checks['stdout']);assert checks['exit']==0 and checks['stderr']=='' and result['status']=='PASS' and result['checks']==62
 browser=load('browser-proof.json');assert browser['status']=='PASS' and len(browser['results'])==8
 assert all(r['logoHeight']==172 and r['searchWidth']>=320 for r in browser['results'] if r['width']<=390)
 assert set(release['files'])==FILES and set(release['base_files'])==FILES
 for f,d in release['files'].items():assert hashlib.sha256(git(SOURCE,PREFIX+f)).hexdigest()==d==hashlib.sha256(git(release['commit'],PREFIX+f)).hexdigest()
 for f,d in release['base_files'].items():assert hashlib.sha256(git(BASE_COMMIT,PREFIX+f)).hexdigest()==d
 assert hashlib.sha256(git(release['commit'],'ops/releases/school-logo-search-below-20261025/deploy.py')).digest()==hashlib.sha256((ROOT/'deploy.py').read_bytes()).digest()
 code='$o=[];foreach(json_decode($argv[1],true)as$f)$o[$f]=hash_file("sha256","/var/www/html/".$f);echo json_encode($o);'
 actual=json.loads(run(['docker','run','--rm','--pull=never','--network','none','--read-only','--entrypoint','php',IMAGE,'-r',code,json.dumps(sorted(FILES))]));assert actual==release['files']
 labels=inspect(IMAGE)['Config']['Labels'];assert labels['org.opencontainers.image.revision']==SOURCE and labels['schoolsafe.source.digest']==DIGEST

def main():
 p=argparse.ArgumentParser();p.add_argument('action',choices=['prepare','deploy','status']);action=p.parse_args().action
 release=load('release.json')
 old.ROOT=access.ROOT=tools.ROOT=preserve.ROOT=ROOT
 old.BASE=access.BASE=tools.BASE=BASE;old.BASE_COMMIT=access.BASE_COMMIT=tools.BASE_COMMIT=BASE_COMMIT
 old.SOURCE=SOURCE;old.IMAGE=access.IMAGE=tools.IMAGE=IMAGE;old.DIGEST=access.DIGEST=tools.DIGEST=DIGEST
 old.FILES=access.FILES=tools.FILES=FILES;old.NEW=set();old.WORK_BRANCH='feat/school-dashboard-finish-20261024';old.BRANCH=tools.BRANCH=BRANCH;old.BASE_BRANCH=tools.BASE_BRANCH='deployed/school-logo-admin-photo-20261024'
 old.qualification=tools.qualification=qualification;tools.source_hashes=access.source_hashes
 tools.unchanged_runtime=old.logo.original_unchanged;preserve.check_layer=old.portrait.check_layer_with_mounts
 tools.OLD='schoolsafe-rosario-pre-logo-search-below-20261025';tools.STAGED='schoolsafe-rosario-candidate-logo-search-below-20261025'
 if action=='prepare':old.prepare(release)
 elif action=='deploy':
  assert load('media-before.json')==old.media_hashes();tools.deploy(release);assert load('media-before.json')==old.media_hashes()
 else:
  result=tools.final_status(release);result['logo_and_portrait_preserved']=load('media-before.json')==old.media_hashes();assert result['logo_and_portrait_preserved'];print(json.dumps(result))
if __name__=='__main__':main()
